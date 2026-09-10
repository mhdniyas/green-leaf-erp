<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashFlow\CashFlowTreeService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CashFlowTreeReconciliationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_expected_closing_minus_located_closing_equals_difference_formula(): void
    {
        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $bank = CompanyAccount::create([
            'name' => 'Main Company Bank',
            'bank_name' => 'HDFC',
            'account_type' => 'bank',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);

        $shop = Shop::factory()->create(['name' => 'Metro Shop']);
        ShopDailyLedgerSnapshot::create([
            'shop_id' => $shop->id,
            'business_date' => '2026-09-01',
            'opening_shop_position' => 10000.00,
            'closing_shop_position' => 10000.00,
        ]);

        $purchaser = User::factory()->create(['name' => 'Purchaser Niyas']);
        $purchaser->assignRole('purchaser');

        $vendor = Supplier::factory()->create(['name' => 'Fresh Farms']);

        // 1. External money in: Shop Cash Sales 40,000
        $entryType = LedgerEntryType::first();
        ShopLedgerTransaction::create([
            'shop_id' => $shop->id,
            'business_date' => '2026-09-04',
            'entry_type_id' => $entryType->id,
            'amount' => 40000.00,
            'direction' => 'credit',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'status' => 'active',
        ]);

        // 2. Company funds purchaser from Bank: 50,000
        CompanyAccountStatementEntry::create([
            'company_account_id' => $bank->id,
            'transaction_date' => '2026-09-05',
            'direction' => 'out',
            'amount' => 50000.00,
            'narration' => 'Advance to Purchaser Niyas',
            'source' => 'purchaser_funding',
        ]);
        PurchaserCredit::create([
            'purchaser_id' => $purchaser->id,
            'business_date' => '2026-09-05',
            'type' => 'in',
            'amount' => 50000.00,
            'company_account_id' => $bank->id,
            'description' => 'Procurement advance',
        ]);

        // 3. Purchaser spends 35,000 on cash purchases (Vendor Fresh Farms)
        $cashInv = PurchaseInvoice::factory()->create([
            'supplier_id' => $vendor->id,
            'amount' => 35000.00,
            'discount_amount' => 0.00,
            'status' => InvoiceStatus::Approved,
        ]);
        PurchaserCredit::create([
            'purchaser_id' => $purchaser->id,
            'business_date' => '2026-09-06',
            'type' => 'out',
            'amount' => 35000.00,
            'purchase_invoice_id' => $cashInv->id,
            'description' => 'Cash purchase Fresh Farms',
        ]);

        // 4. Purchaser returns 10,000 to Bank
        CompanyAccountStatementEntry::create([
            'company_account_id' => $bank->id,
            'transaction_date' => '2026-09-10',
            'direction' => 'in',
            'amount' => 10000.00,
            'narration' => 'Return from Purchaser Niyas',
            'source' => 'purchaser_funding',
        ]);
        PurchaserCredit::create([
            'purchaser_id' => $purchaser->id,
            'business_date' => '2026-09-10',
            'type' => 'out',
            'amount' => 10000.00,
            'company_account_id' => $bank->id,
            'description' => 'Refund excess advance',
        ]);

        $service = app(CashFlowTreeService::class);
        $result = $service->build('2026-09');

        $summary = $result['summary'];

        // Formula assertion: Expected Closing - Located Money = Unexplained Difference
        $expectedDiff = round($summary->expectedClosing - $summary->locatedMoney, 2);
        $this->assertEquals($expectedDiff, $summary->unexplainedDifference);

        // Verification of controllers response via HTTP
        $response = $this->actingAs($admin)->get(route('admin.cashbook.cash-flow-tree.index', ['month' => '2026-09']));
        $response->assertOk();
        $response->assertSee('Monthly Cash Flow Tree');
        $response->assertSee('Main Company Bank');
        $response->assertSee('Metro Shop');
        $response->assertSee('Purchaser Niyas');

        // Verification of drill-down endpoint
        $drillResponse = $this->actingAs($admin)->getJson(route('admin.cashbook.cash-flow-tree.drilldown', [
            'month' => '2026-09',
            'node_id' => "purchaser_{$purchaser->id}",
        ]));
        $drillResponse->assertOk();
        $drillResponse->assertJsonPath('status', 'success');
        $drillResponse->assertJsonPath('node_id', "purchaser_{$purchaser->id}");
        $this->assertNotEmpty($drillResponse->json('movements'));
    }
}
