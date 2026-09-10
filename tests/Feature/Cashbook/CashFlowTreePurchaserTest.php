<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashFlow\CashFlowTreeService;
use App\Services\Cashbook\CashFlow\Sources\PurchaserCashFlowSource;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CashFlowTreePurchaserTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $purchaser;

    private CompanyAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->purchaser = User::factory()->create(['name' => 'Purchaser Niyas']);
        $this->purchaser->assignRole('purchaser');

        $this->bank = CompanyAccount::create([
            'name' => 'Company HDFC',
            'bank_name' => 'HDFC BANK',
            'account_type' => 'bank',
            'opening_balance' => 500000.00,
            'current_balance' => 500000.00,
            'enabled' => true,
        ]);
    }

    public function test_purchaser_funding_and_purchases_and_returns_invariant(): void
    {
        // 1. Prior balance before September 1: 12,500
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'business_date' => '2026-08-25',
            'type' => 'in',
            'amount' => 12500.00,
            'payment_source' => 'cash',
            'description' => 'Opening advance in August',
        ]);

        // 2. Company funding in September: 80,000
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'business_date' => '2026-09-02',
            'type' => 'in',
            'amount' => 80000.00,
            'company_account_id' => $this->bank->id,
            'description' => 'September procurement float',
        ]);

        // 3. Cash purchases in September:
        // Vendor ABC: 40,000
        // Vendor XYZ: 30,000
        $vendorABC = Supplier::factory()->create(['name' => 'Vendor ABC']);
        $vendorXYZ = Supplier::factory()->create(['name' => 'Vendor XYZ']);

        $inv1 = PurchaseInvoice::factory()->create([
            'supplier_id' => $vendorABC->id,
            'amount' => 40000.00,
            'discount_amount' => 0.00,
        ]);
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'business_date' => '2026-09-05',
            'type' => 'out',
            'amount' => 40000.00,
            'purchase_invoice_id' => $inv1->id,
            'description' => 'Paid Vendor ABC',
        ]);

        $inv2 = PurchaseInvoice::factory()->create([
            'supplier_id' => $vendorXYZ->id,
            'amount' => 30000.00,
            'discount_amount' => 0.00,
        ]);
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'business_date' => '2026-09-10',
            'type' => 'out',
            'amount' => 30000.00,
            'purchase_invoice_id' => $inv2->id,
            'description' => 'Paid Vendor XYZ',
        ]);

        // 4. Returned to company: 10,000
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'business_date' => '2026-09-20',
            'type' => 'out',
            'amount' => 10000.00,
            'company_account_id' => $this->bank->id,
            'description' => 'Return excess cash advance to HDFC',
        ]);

        $source = app(PurchaserCashFlowSource::class);
        $openings = $source->openingBalances('2026-09-01', ['purchaser_id' => $this->purchaser->id]);
        $this->assertEquals(12500.0, $openings[$this->purchaser->id]['opening_advance']);

        $service = app(CashFlowTreeService::class);
        $result = $service->build('2026-09', ['purchaser_id' => $this->purchaser->id]);

        $purchasersBranch = collect($result['tree']->children)->firstWhere('id', 'branch_purchasers');
        $this->assertNotNull($purchasersBranch);

        $purchaserNode = collect($purchasersBranch->children)->firstWhere('id', "purchaser_{$this->purchaser->id}");
        $this->assertNotNull($purchaserNode);

        // Invariant: Opening (12,500) + In (80,000) - Out (70,000 purchases + 10,000 return) = Closing (12,500)
        $this->assertEquals(12500.0, $purchaserNode->openingBalance);
        $this->assertEquals(80000.0, $purchaserNode->totalIn);
        $this->assertEquals(80000.0, $purchaserNode->totalOut);
        $this->assertEquals(12500.0, $purchaserNode->closingBalance);

        // Verify Vendor Split under Cash Purchases
        $purchasesGroup = collect($purchaserNode->children)->firstWhere('entityType', 'cash_purchases_group');
        $this->assertNotNull($purchasesGroup);
        $this->assertEquals(70000.0, $purchasesGroup->totalOut);

        $vendorABCSplit = collect($purchasesGroup->children)->firstWhere('title', 'Vendor ABC');
        $this->assertNotNull($vendorABCSplit);
        $this->assertEquals(40000.0, $vendorABCSplit->totalOut);

        $vendorXYZSplit = collect($purchasesGroup->children)->firstWhere('title', 'Vendor XYZ');
        $this->assertNotNull($vendorXYZSplit);
        $this->assertEquals(30000.0, $vendorXYZSplit->totalOut);
    }

    public function test_missing_supplier_becomes_unlinked_vendor(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Temporary Vendor']);
        $inv = PurchaseInvoice::factory()->create([
            'supplier_id' => $supplier->id,
            'amount' => 15000.00,
            'discount_amount' => 0.00,
        ]);
        $supplier->delete();

        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'business_date' => '2026-09-08',
            'type' => 'out',
            'amount' => 15000.00,
            'purchase_invoice_id' => $inv->id,
            'description' => 'Cash purchase unlinked',
        ]);

        $service = app(CashFlowTreeService::class);
        $result = $service->build('2026-09', ['purchaser_id' => $this->purchaser->id]);

        $purchasersBranch = collect($result['tree']->children)->firstWhere('id', 'branch_purchasers');
        $purchaserNode = collect($purchasersBranch->children)->firstWhere('id', "purchaser_{$this->purchaser->id}");
        $purchasesGroup = collect($purchaserNode->children)->firstWhere('entityType', 'cash_purchases_group');

        $unlinked = collect($purchasesGroup->children)->firstWhere('title', 'Unlinked Vendor');
        $this->assertNotNull($unlinked);
        $this->assertEquals(15000.0, $unlinked->totalOut);
    }
}
