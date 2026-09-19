<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Carbon\Carbon;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookVendorPurchaseSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $shopUser;

    private Shop $shop;

    private Supplier $vendor;

    private Product $product;

    private ShopLedgerEntrySetting $cashVpSetting;

    private ShopLedgerEntrySetting $creditVpSetting;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-15 12:00:00');

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->shop = Shop::query()->create([
            'name' => 'Indiranagar Fresh',
            'code' => 'IND_01',
            'warehouse_tag' => 'IND',
            'status' => 'active',
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_purchasing_enabled' => true,
        ]);

        $this->shopUser = User::factory()->create(['shop_id' => $this->shop->id]);
        $this->shopUser->assignRole('shop');
        $this->shopUser->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);

        $this->vendor = Supplier::query()->create([
            'name' => 'Kisan Mandi',
            'mobile_number' => '9845011111',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
        ]);
        $this->shop->suppliers()->attach($this->vendor->id, ['is_active' => true]);

        $this->product = Product::factory()->create([
            'name' => 'Tomato Local',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->cashVpSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('is_vendor_purchase', true)
            ->where('vendor_purchase_payment_type', 'cash')
            ->firstOrFail();

        $this->creditVpSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('is_vendor_purchase', true)
            ->where('vendor_purchase_payment_type', 'credit')
            ->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_vendor_purchase_summaries_are_split_by_payment_type(): void
    {
        $businessDate = '2026-09-15';

        // Create 1 Cash Purchase invoice
        $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $this->vendor->id,
            'payment_method' => 'Cash',
            'business_date' => $businessDate,
            'bill_number' => 'BILL-CSH-101',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'rate' => 20.0,
                    'unit' => 'kg',
                ],
            ],
        ]);

        // Create 1 Credit Purchase invoice
        $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $this->vendor->id,
            'payment_method' => 'Credit',
            'business_date' => $businessDate,
            'bill_number' => 'BILL-CRD-102',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 25,
                    'rate' => 20.0,
                    'unit' => 'kg',
                ],
            ],
        ]);

        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.show', ['date' => $businessDate]));
        $response->assertOk();

        $summaries = $response->viewData('vendorPurchaseSummaries');
        $this->assertNotNull($summaries);

        // Cash setting summary should have 1 count, 200 cash, 0 credit
        $cashSummary = $summaries[$this->cashVpSetting->id] ?? null;
        $this->assertNotNull($cashSummary);
        $this->assertSame(1, $cashSummary['count']);
        $this->assertEquals(200.0, (float) $cashSummary['cash_amount']);
        $this->assertEquals(0.0, (float) $cashSummary['credit_amount']);
        $this->assertEquals(200.0, (float) $cashSummary['total_amount']);

        // Credit setting summary should have 1 count, 0 cash, 500 credit
        $creditSummary = $summaries[$this->creditVpSetting->id] ?? null;
        $this->assertNotNull($creditSummary);
        $this->assertSame(1, $creditSummary['count']);
        $this->assertEquals(0.0, (float) $creditSummary['cash_amount']);
        $this->assertEquals(500.0, (float) $creditSummary['credit_amount']);
        $this->assertEquals(500.0, (float) $creditSummary['total_amount']);
    }

    public function test_vendor_purchases_date_form_preserves_category_id_filter(): void
    {
        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.vendor-purchases', [
            'category_id' => $this->cashVpSetting->id,
            'date' => '2026-09-15',
        ]));

        $response->assertOk();
        $response->assertSee('name="category_id"', false);
        $response->assertSee('value="'.$this->cashVpSetting->id.'"', false);
    }
}
