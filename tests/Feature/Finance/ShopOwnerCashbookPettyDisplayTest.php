<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopOwnerCashbookPettyDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $shopUser;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private LedgerEntryType $salesType;

    private LedgerEntryType $purchaseType;

    private LedgerEntryType $rentType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->shop = Shop::factory()->create([
            'name' => 'Bazaro',
            'code' => 'AV_BAZARO',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->shopUser = User::factory()->create([
            'shop_id' => $this->shop->id,
        ]);
        $this->shopUser->assignRole('shop');
        $this->shopUser->givePermissionTo('sales.order.create');

        $this->profile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'av-bazaro-bazaro',
            'code' => $this->shop->code,
            'name' => $this->shop->name,
            'enabled' => true,
        ]);

        $this->salesType = LedgerEntryType::query()->create([
            'code' => 'cash_sales',
            'name' => 'Cash Sales',
            'category' => 'income',
            'active' => true,
            'display_order' => 0,
        ]);

        $this->purchaseType = LedgerEntryType::query()->create([
            'code' => 'cash_purchase',
            'name' => 'Cash Purchase',
            'category' => 'expense',
            'active' => true,
            'display_order' => 1,
        ]);

        $this->rentType = LedgerEntryType::query()->create([
            'code' => 'rent_expense',
            'name' => 'Rent',
            'category' => 'expense',
            'active' => true,
            'display_order' => 2,
        ]);

        ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->salesType->id,
            'enabled' => true,
            'effective_from' => '2026-01-01',
            'display_order' => 0,
        ]);

        ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->purchaseType->id,
            'enabled' => true,
            'effective_from' => '2026-01-01',
            'display_order' => 1,
        ]);

        ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->rentType->id,
            'enabled' => true,
            'effective_from' => '2026-01-01',
            'display_order' => 2,
        ]);
    }

    public function test_cashbook_tab_renders_four_summary_cards_and_funded_from_petty_label(): void
    {
        $businessDate = '2026-08-01';

        // Cash Sales: ₹82,676 (funding_source: none)
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_id' => $this->salesType->id,
            'direction' => 'income',
            'funding_source' => 'none',
            'amount' => 82676.00,
            'status' => 'approved',
            'entered_by' => $this->shopUser->id,
        ]);

        // Cash Purchase: ₹1,550 (funding_source: petty)
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_id' => $this->purchaseType->id,
            'direction' => 'expense',
            'funding_source' => 'petty',
            'amount' => 1550.00,
            'petty_delta' => -1550.00,
            'status' => 'approved',
            'entered_by' => $this->shopUser->id,
        ]);

        // Rent: ₹9,921 (funding_source: sales)
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_id' => $this->rentType->id,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'amount' => 9921.00,
            'status' => 'approved',
            'entered_by' => $this->shopUser->id,
        ]);

        ShopDailyLedgerSnapshot::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'total_sales' => 82676.00,
            'total_income' => 82676.00,
            'total_expense' => 11471.00,
            'net_pl' => 71205.00,
            'opening_petty' => 0.00,
            'petty_in' => 0.00,
            'petty_out' => 1550.00,
            'closing_petty' => -1550.00,
            'opening_shop_position' => 0.00,
            'settlement_increase' => 82676.00,
            'settlement_decrease' => 9921.00,
            'closing_shop_position' => 72755.00,
            'opening_company_pending' => 0.00,
            'company_pending_in' => 0.00,
            'company_pending_out' => 0.00,
            'closing_company_pending' => 0.00,
            'status' => 'open',
        ]);

        // 1. Check HTML view rendering
        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.show', [
            'date' => $businessDate,
            'tab' => 'cashbook',
        ]));

        $response->assertOk();
        $response->assertSee('Petty Balance');
        $response->assertSee('Petty cash remaining');
        $response->assertSee('Funded from: Petty');
        $response->assertSee('Total Sales');
        $response->assertSee('Total Expense');
        $response->assertSee('Closing Balance');

        // 2. Check API endpoint returning serialized transactions and snapshot
        $apiResponse = $this->actingAs($this->shopUser)->getJson(route('shop-owner.cashbook.api.shop-data', [
            'business_date' => $businessDate,
            'timeframe' => 'daily',
        ]));

        $apiResponse->assertOk();
        $apiResponse->assertJsonPath('success', true);
        $this->assertEquals(-1550.00, (float) $apiResponse->json('snapshot.closing_petty'));

        $txs = collect($apiResponse->json('transactions'));
        $pettyTx = $txs->firstWhere('funding_source', 'petty');
        $this->assertNotNull($pettyTx);
        $this->assertEquals(1550.0, (float) $pettyTx['amount']);

        $salesTx = $txs->firstWhere('funding_source', 'none');
        $this->assertNotNull($salesTx);
        $this->assertEquals(82676.0, (float) $salesTx['amount']);
    }
}
