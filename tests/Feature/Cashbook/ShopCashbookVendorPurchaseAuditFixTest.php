<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
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

class ShopCashbookVendorPurchaseAuditFixTest extends TestCase
{
    use RefreshDatabase;

    private User $shopUser;

    private Shop $shop;

    private Supplier $vendor;

    private Product $product;

    private ShopLedgerEntrySetting $cashVpSetting;

    private ShopLedgerEntrySetting $creditVpSetting;

    private ShopLedgerHeaderGroup $cashHeaderGroup;

    private ShopLedgerHeaderGroup $creditHeaderGroup;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-16 12:00:00');

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->shop = Shop::query()->create([
            'name' => 'Koramangala Fresh',
            'code' => 'KOR_01',
            'warehouse_tag' => 'KOR',
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
            'name' => 'Green Valley Mandi',
            'mobile_number' => '9845099999',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
        ]);
        $this->shop->suppliers()->attach($this->vendor->id, ['is_active' => true]);

        $this->product = Product::factory()->create([
            'name' => 'Cauliflower',
            'sku' => 'CAU-001',
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

        $this->cashHeaderGroup = ShopLedgerHeaderGroup::query()->firstOrCreate(
            ['shop_id' => $this->shop->id, 'name' => 'CASH PURCHASE'],
            ['type' => 'expense', 'display_order' => 1, 'enabled' => true]
        );
        $this->cashVpSetting->update(['header_group_id' => $this->cashHeaderGroup->id]);

        $this->creditHeaderGroup = ShopLedgerHeaderGroup::query()->firstOrCreate(
            ['shop_id' => $this->shop->id, 'name' => 'OTHER EXPENSES'],
            ['type' => 'expense', 'display_order' => 2, 'enabled' => true]
        );
        $this->creditVpSetting->update(['header_group_id' => $this->creditHeaderGroup->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cash_purchase_appears_under_configured_cash_header_and_matches_summary(): void
    {
        $businessDate = '2026-09-16';

        $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $this->vendor->id,
            'payment_method' => 'Cash',
            'business_date' => $businessDate,
            'bill_number' => 'BILL-CSH-4000',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 200,
                    'rate' => 20.0,
                    'unit' => 'kg',
                ],
            ],
        ]);

        $cashbookResponse = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.show', ['date' => $businessDate]));
        $cashbookResponse->assertOk();

        $summaries = $cashbookResponse->viewData('vendorPurchaseSummaries');
        $cashSummary = $summaries[$this->cashVpSetting->id] ?? null;

        $this->assertNotNull($cashSummary);
        $this->assertEquals(4000.0, $cashSummary['cash_amount']);
        $this->assertEquals(4000.0, $cashSummary['total_amount']);
        $this->assertSame(1, $cashSummary['count']);

        // Vendor Purchases page matching check
        $vpPageResponse = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.vendor-purchases', [
            'date' => $businessDate,
            'category_id' => $this->cashVpSetting->id,
            'payment_method' => 'Cash',
        ]));
        $vpPageResponse->assertOk();

        $summary = $vpPageResponse->viewData('summary');
        $this->assertEquals(4000.0, $summary['cash_amount']);
        $this->assertEquals(4000.0, $summary['total_amount']);
        $this->assertSame(1, $summary['total_invoices']);
    }

    public function test_credit_purchase_appears_under_configured_credit_header_and_does_not_affect_cash_balance(): void
    {
        $businessDate = '2026-09-16';

        $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $this->vendor->id,
            'payment_method' => 'Credit',
            'business_date' => $businessDate,
            'bill_number' => 'BILL-CRD-8000',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 400,
                    'rate' => 20.0,
                    'unit' => 'kg',
                ],
            ],
        ]);

        $cashbookResponse = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.show', ['date' => $businessDate]));
        $cashbookResponse->assertOk();

        $summaries = $cashbookResponse->viewData('vendorPurchaseSummaries');
        $creditSummary = $summaries[$this->creditVpSetting->id] ?? null;

        $this->assertNotNull($creditSummary);
        $this->assertEquals(8000.0, $creditSummary['credit_amount']);
        $this->assertEquals(8000.0, $creditSummary['total_amount']);
        $this->assertSame(1, $creditSummary['count']);

        // Credit purchase has zero cash impact in snapshot
        $snapshot = $cashbookResponse->viewData('snapshot');
        $this->assertEquals(0.0, (float) ($snapshot->vendor_purchase_cash_outflow ?? 0.0));
    }

    public function test_moving_category_header_changes_placement(): void
    {
        $newHeaderGroup = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'VENDOR LIABILITIES',
            'type' => 'expense',
            'display_order' => 10,
            'enabled' => true,
        ]);

        $this->creditVpSetting->update(['header_group_id' => $newHeaderGroup->id]);

        $this->assertSame($newHeaderGroup->id, $this->creditVpSetting->fresh()->header_group_id);
    }

    public function test_date_filters_exact_today_yesterday_7days_30days_this_month(): void
    {
        // Exact Date 16 Sep
        $respExact = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.vendor-purchases', [
            'date' => '2026-09-16',
            'period' => 'exact',
        ]));
        $respExact->assertOk();
        $this->assertSame('2026-09-16', $respExact->viewData('filters')['start_date']);
        $this->assertSame('2026-09-16', $respExact->viewData('filters')['end_date']);
        $this->assertSame('2026-09-16', $respExact->viewData('selectedDate')->toDateString());

        // 7 Days relative to 16 Sep (10 Sep -> 16 Sep, 7 days inclusive)
        $resp7 = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.vendor-purchases', [
            'date' => '2026-09-16',
            'period' => '7days',
        ]));
        $resp7->assertOk();
        $this->assertSame('2026-09-10', $resp7->viewData('filters')['start_date']);
        $this->assertSame('2026-09-16', $resp7->viewData('filters')['end_date']);

        // 30 Days relative to 16 Sep
        $resp30 = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.vendor-purchases', [
            'date' => '2026-09-16',
            'period' => '30days',
        ]));
        $resp30->assertOk();
        $this->assertSame('2026-08-18', $resp30->viewData('filters')['start_date']);
        $this->assertSame('2026-09-16', $resp30->viewData('filters')['end_date']);

        // This Month relative to 16 Sep (01 Sep 2026 -> 30 Sep 2026)
        $respMonth = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.vendor-purchases', [
            'date' => '2026-09-16',
            'period' => 'month',
        ]));
        $respMonth->assertOk();
        $this->assertSame('2026-09-01', $respMonth->viewData('filters')['start_date']);
        $this->assertSame('2026-09-30', $respMonth->viewData('filters')['end_date']);
    }

    public function test_filter_preservation_across_actions(): void
    {
        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.vendor-purchases', [
            'date' => '2026-09-16',
            'category_id' => $this->cashVpSetting->id,
            'supplier_id' => $this->vendor->id,
            'payment_method' => 'Cash',
            'search' => 'BILL-100',
        ]));

        $response->assertOk();
        $response->assertSee('value="2026-09-16"', false);
        $response->assertSee('value="'.$this->cashVpSetting->id.'"', false);
        $response->assertSee('value="'.$this->vendor->id.'"', false);
        $response->assertSee('BILL-100', false);
    }

    public function test_families_jindal_city_vendor_purchase_summaries_match_popup_and_main_cashbook(): void
    {
        $jindalShop = Shop::query()->create([
            'name' => 'Families Jindal City',
            'code' => 'AV_JINDAL_CITY',
            'warehouse_tag' => 'JIN',
            'status' => 'active',
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_purchasing_enabled' => true,
        ]);

        $jindalUser = User::factory()->create(['shop_id' => $jindalShop->id]);
        $jindalUser->assignRole('shop');
        $jindalUser->ownedShopAssignments()->create(['shop_id' => $jindalShop->id]);

        $jindalVendor = Supplier::query()->create([
            'name' => 'Jindal Local Vendor',
            'mobile_number' => '9845077777',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
        ]);
        $jindalShop->suppliers()->attach($jindalVendor->id, ['is_active' => true]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $cashSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $jindalShop->id)
            ->where('is_vendor_purchase', true)
            ->where('vendor_purchase_payment_type', 'cash')
            ->firstOrFail();

        $creditSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $jindalShop->id)
            ->where('is_vendor_purchase', true)
            ->where('vendor_purchase_payment_type', 'credit')
            ->firstOrFail();

        $businessDate = '2026-09-19';

        // Cash purchase ₹211
        $this->actingAs($jindalUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $jindalVendor->id,
            'payment_method' => 'Cash',
            'business_date' => $businessDate,
            'bill_number' => 'BILL-JIN-211',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'rate' => 21.10,
                    'unit' => 'kg',
                ],
            ],
        ]);

        // Credit purchase ₹1432.99
        $this->actingAs($jindalUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $jindalVendor->id,
            'payment_method' => 'Credit',
            'business_date' => $businessDate,
            'bill_number' => 'BILL-JIN-1432',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 100,
                    'rate' => 14.3299,
                    'unit' => 'kg',
                ],
            ],
        ]);

        // Cashbook main page
        $cashbookResp = $this->actingAs($jindalUser)->get(route('shop-owner.cashbook.show', ['date' => $businessDate]));
        $cashbookResp->assertOk();

        $summaries = $cashbookResp->viewData('vendorPurchaseSummaries');
        $this->assertNotNull($summaries);

        $cashSum = $summaries[$cashSetting->id] ?? null;
        $this->assertNotNull($cashSum);
        $this->assertEquals(211.0, $cashSum['cash_amount']);
        $this->assertSame(1, $cashSum['count']);

        $creditSum = $summaries[$creditSetting->id] ?? null;
        $this->assertNotNull($creditSum);
        $this->assertEquals(1432.99, $creditSum['credit_amount']);
        $this->assertSame(1, $creditSum['count']);

        // Vendor Purchases page
        $vpPageResp = $this->actingAs($jindalUser)->get(route('shop-owner.cashbook.vendor-purchases', ['date' => $businessDate]));
        $vpPageResp->assertOk();

        $kpiSummary = $vpPageResp->viewData('summary');
        $this->assertEquals(211.0, $kpiSummary['cash_amount']);
        $this->assertEquals(1432.99, $kpiSummary['credit_amount']);
    }
}
