<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseProductFilter;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\ShopPriceGroup;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookPurchaseReportsWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-25 12:00:00');
        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reports_open_directly_with_all_report_titles_and_no_batch_ui(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.reports'))
            ->assertRedirect(route('admin.cashbook.finance.purchase.reports.credit-purchases'));

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.credit-purchases'));

        $response->assertOk()
            ->assertSee('Credit Purchase Report')
            ->assertSee('Purchaser Report')
            ->assertSee('Price Report')
            ->assertSee('Changed Items')
            ->assertSee('Purchaser Price')
            ->assertDontSee('Batch');
    }

    public function test_dashboard_keeps_all_operational_subsections_under_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase'));

        $response->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Reports')
            ->assertSee('Overview')
            ->assertSee('Purchasers')
            ->assertSee('Vendors')
            ->assertSee('Categories')
            ->assertSee('Invoices');
    }

    public function test_purchaser_report_uses_compact_filters_with_advanced_options(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchasers'));

        $response->assertOk()
            ->assertSeeInOrder(['Period', 'Product Filter', 'Search', 'Apply', 'More Filters'])
            ->assertSee('Purchaser, invoice, vendor, product...')
            ->assertSee('All Purchasers')
            ->assertSee('All Vendors')
            ->assertSee('All Payments')
            ->assertSee('All Categories')
            ->assertDontSee('name="category_ids[]"', false);
    }

    public function test_credit_purchase_report_uses_the_compact_filter_layout(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.credit-purchases'));

        $response->assertOk()
            ->assertSeeInOrder(['Today', 'Yesterday', 'This Week', 'This Month', 'Custom', 'Product Filter', 'Status', 'Search', 'Apply', 'Total Credit Purchases'])
            ->assertSee('Vendor name or phone...')
            ->assertSee('All Products')
            ->assertSee('All Vendors')
            ->assertSee('Unpaid')
            ->assertSee('Partially Paid')
            ->assertSee('Fully Settled')
            ->assertSee('aria-label="Reset filters"', false);
    }

    public function test_credit_purchase_report_filters_credit_invoices_by_produce(): void
    {
        $vegetable = $this->product('Tomato', 'VEG-WH');
        $fruit = $this->product('Apple', 'FRT-WH');
        $vegetableVendor = Supplier::factory()->create(['name' => 'Vegetable Vendor']);
        $fruitVendor = Supplier::factory()->create(['name' => 'Fruit Vendor']);
        $purchaser = User::factory()->create();

        foreach ([
            [$vegetable, $vegetableVendor],
            [$fruit, $fruitVendor],
        ] as [$product, $vendor]) {
            $this->purchase($product, '2026-08-25', 1, 100, $purchaser, $vendor)->update([
                'payment_method' => 'Credit',
                'payment_status' => 'credit_pending_approval',
                'payment_paid_by' => 'vendor_credit',
            ]);
        }

        $filter = PurchaseProductFilter::query()->create([
            'name' => 'Vegetable Filter',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync([$vegetable->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.credit-purchases', [
            'period' => 'today',
            'product_filter' => $filter->uuid,
        ]));

        $response->assertOk()->assertSee('Vegetable Vendor')->assertDontSee('Fruit Vendor');
        $this->assertSame(100.0, $response['kpi']['total_invoiced']);
    }

    public function test_legacy_report_routes_redirect_with_filters(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.report', ['period' => 'month']))
            ->assertRedirect(route('admin.cashbook.finance.purchase.reports.purchasers', ['period' => 'month']));

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.vendor-credit', ['status' => 'unpaid']))
            ->assertRedirect(route('admin.cashbook.finance.purchase.reports.credit-purchases', ['status' => 'unpaid']));
    }

    public function test_price_report_labels_sources_and_calculates_group_matrix(): void
    {
        ShopPriceGroup::factory()->create(['name' => 'A', 'is_active' => true]);
        ShopPriceGroup::factory()->create(['name' => 'B', 'is_active' => false]);
        ShopPriceGroup::factory()->create(['name' => 'C', 'is_active' => false]);
        $product = $this->product('Tomato', 'VEG-WH');
        $supplier = Supplier::factory()->create(['name' => 'Market Vendor']);
        $purchaser = User::factory()->create(['name' => 'Buyer One']);
        $this->purchase($product, '2026-08-25', 2, 30, $purchaser, $supplier);
        $this->purchase($product, '2026-08-25', 1, 36, $purchaser, $supplier);
        $this->approval($product, '2026-08-25', 32, 38, 40, 42);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices'));

        $response->assertOk()
            ->assertSee('Business Date')
            ->assertSee('name="date" value="2026-08-25"', false)
            ->assertDontSee('name="period"', false)
            ->assertDontSee('name="start_date"', false)
            ->assertDontSee('name="end_date"', false)
            ->assertSee('Actual Purchase Price')
            ->assertSee('Approved Purchase Price')
            ->assertSee('Group A')
            ->assertDontSee('Group B')
            ->assertDontSee('Group C')
            ->assertSee('₹32.00')
            ->assertSee('₹38.00')
            ->assertSee('+₹6.00')
            ->assertSee('18.75%')
            ->assertSee(route('admin.cashbook.finance.purchase.reports.prices.product', $product), false);
        $this->assertSame(32.0, round((float) $response['rows']->first()->actual_purchase_price, 2));
        $this->assertLessThan(25, count(DB::getQueryLog()));

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.reports.prices.product', ['product' => $product, 'period' => 'today']))
            ->assertOk()
            ->assertSee('Group A')
            ->assertDontSee('Group B')
            ->assertDontSee('Group C')
            ->assertSee('Market Vendor')
            ->assertSee('Buyer One');
    }

    public function test_price_report_and_product_details_use_only_the_selected_day(): void
    {
        $product = $this->product('Tomato', 'VEG-WH');
        $yesterdayVendor = Supplier::factory()->create(['name' => 'Yesterday Vendor']);
        $todayVendor = Supplier::factory()->create(['name' => 'Today Vendor']);
        $purchaser = User::factory()->create();
        $this->purchase($product, '2026-08-24', 1, 20, $purchaser, $yesterdayVendor);
        $this->purchase($product, '2026-08-25', 1, 30, $purchaser, $todayVendor);
        $this->approval($product, '2026-08-24', 21, 31, 33, 35);
        $this->approval($product, '2026-08-25', 32, 42, 44, 46);

        $report = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices', [
            'date' => '2026-08-24',
        ]));

        $report->assertOk()->assertSee('value="2026-08-24"', false)->assertSee('₹20.00')->assertSee('₹21.00');
        $this->assertSame(20.0, round((float) $report['rows']->first()->actual_purchase_price, 2));

        $detail = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices.product', [
            'product' => $product,
            'date' => '2026-08-24',
        ]));

        $detail->assertOk()
            ->assertSee('24 Aug 2026')
            ->assertSee('Yesterday Vendor')
            ->assertDontSee('Today Vendor');
    }

    public function test_changed_items_and_whatsapp_exclude_unchanged_products(): void
    {
        $tomato = $this->product('Tomato', 'VEG-WH');
        $onion = $this->product('Onion', 'VEG-WH');
        $this->approval($tomato, '2026-08-24', 30, 38, 40, 42);
        $this->approval($tomato, '2026-08-25', 32, 40, 42, 44);
        $this->approval($onion, '2026-08-24', 20, 30, 32, 34);
        $this->approval($onion, '2026-08-25', 21, 30, 32, 34);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.changed-items'));
        $response->assertOk()->assertSee('Tomato')->assertSee('+₹2.00');
        $this->assertSame(['Tomato'], $response['rows']->pluck('product_name')->all());

        $share = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.changed-items.whatsapp'));
        $location = urldecode((string) $share->headers->get('Location'));
        $this->assertStringContainsString('GREEN LEAF PRICE UPDATE', $location);
        $this->assertStringContainsString('Tomato', $location);
        $this->assertStringNotContainsString('Onion', $location);
    }

    public function test_purchaser_price_defaults_and_custom_vendor_filter_work(): void
    {
        $tomato = $this->product('Tomato', 'VEG-WH');
        $onion = $this->product('Onion', 'FRT-WH');
        $includedVendor = Supplier::factory()->create();
        $excludedVendor = Supplier::factory()->create();
        $purchaser = User::factory()->create();
        $excludedPurchaser = User::factory()->create();
        $this->purchase($tomato, '2026-08-25', 1, 34, $purchaser, $includedVendor);
        $this->purchase($onion, '2026-08-25', 1, 26, $excludedPurchaser, $excludedVendor, 'B');
        $this->approval($tomato, '2026-08-24', 30, 38, 40, 42);
        $this->approval($tomato, '2026-08-25', 34, 42, 44, 46);
        $this->approval($onion, '2026-08-24', 28, 36, 38, 40);
        $this->approval($onion, '2026-08-25', 26, 34, 36, 38);

        $default = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices'));
        $default->assertOk()->assertSee('2026-08-24')->assertSee('2026-08-25')->assertSee('+₹4.00')->assertSee('₹-2.00');

        $filtered = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
            'vendor_id' => $includedVendor->id,
        ]));
        $filtered->assertOk()->assertSee('Tomato');
        $this->assertSame(['Tomato'], $filtered['rows']->pluck('product_name')->all());

        foreach ([
            ['produce_type' => 'vegetables'],
            ['category_id' => $tomato->category_id],
            ['product_id' => $tomato->id],
            ['purchaser_id' => $purchaser->id],
            ['grade' => 'A'],
        ] as $filter) {
            $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices', $filter));
            $this->assertSame(['Tomato'], $response['rows']->pluck('product_name')->all());
        }
    }

    public function test_purchaser_prices_defaults_to_all_rows_and_changed_only_filters(): void
    {
        $tomato = $this->product('Tomato', 'VEG-WH', 'SKU-02');
        $onion = $this->product('Onion', 'FRT-WH', 'SKU-01');
        $carrot = $this->product('Carrot', 'VEG-WH', 'SKU-03');

        // Tomato: changed 30 -> 34
        $this->approval($tomato, '2026-08-24', 30, 38, 40, 42);
        $this->approval($tomato, '2026-08-25', 34, 42, 44, 46);

        // Onion: changed 28 -> 26
        $this->approval($onion, '2026-08-24', 28, 36, 38, 40);
        $this->approval($onion, '2026-08-25', 26, 34, 36, 38);

        // Carrot: unchanged 40 -> 40
        $this->approval($carrot, '2026-08-24', 40, 48, 50, 52);
        $this->approval($carrot, '2026-08-25', 40, 48, 50, 52);

        // 1. Default (All) shows all 3 rows
        $responseAll = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
        ]));
        $responseAll->assertOk();
        $this->assertCount(3, $responseAll['rows']);

        // 2. Changed Only shows only 2 rows
        $responseChanged = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
            'view' => 'changed',
        ]));
        $responseChanged->assertOk();
        $this->assertCount(2, $responseChanged['rows']);
        $productNames = collect($responseChanged['rows']->items())->pluck('product_name')->all();
        $this->assertContains('Tomato', $productNames);
        $this->assertContains('Onion', $productNames);
        $this->assertNotContains('Carrot', $productNames);
    }

    public function test_purchaser_prices_product_code_and_name_search_works(): void
    {
        $tomato = $this->product('Special Tomato', 'VEG-WH', 'SKU-TOM-100');
        $onion = $this->product('Red Onion', 'FRT-WH', 'SKU-ONI-200');

        $this->approval($tomato, '2026-08-24', 30, 38, 40, 42);
        $this->approval($tomato, '2026-08-25', 34, 42, 44, 46);
        $this->approval($onion, '2026-08-24', 28, 36, 38, 40);
        $this->approval($onion, '2026-08-25', 26, 34, 36, 38);

        // Search by SKU
        $responseSku = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
            'search' => 'TOM-100',
        ]));
        $responseSku->assertOk();
        $this->assertCount(1, $responseSku['rows']);
        $this->assertSame('Special Tomato', $responseSku['rows']->first()->product_name);

        // Search by Name
        $responseName = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
            'search' => 'Red Onion',
        ]));
        $responseName->assertOk();
        $this->assertCount(1, $responseName['rows']);
        $this->assertSame('Red Onion', $responseName['rows']->first()->product_name);
    }

    public function test_purchaser_prices_default_sort_is_product_code_asc_with_nulls_last(): void
    {
        $prodB = $this->product('Zebra Product with Code B', 'VEG-WH', 'B-100');
        $prodA = $this->product('Apple Product with Code A', 'VEG-WH', 'A-200');
        $prodNoSku = $this->product('Alpha Product without Code', 'VEG-WH', '');

        $this->approval($prodB, '2026-08-24', 30, 38, 40, 42);
        $this->approval($prodB, '2026-08-25', 30, 38, 40, 42);
        $this->approval($prodA, '2026-08-24', 20, 28, 30, 32);
        $this->approval($prodA, '2026-08-25', 20, 28, 30, 32);
        $this->approval($prodNoSku, '2026-08-24', 10, 18, 20, 22);
        $this->approval($prodNoSku, '2026-08-25', 10, 18, 20, 22);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
        ]));
        $response->assertOk();

        $names = collect($response['rows']->items())->pluck('product_name')->all();
        // A-200 (Apple) first, B-100 (Zebra) second, empty sku (Alpha) last
        $this->assertSame([
            'Apple Product with Code A',
            'Zebra Product with Code B',
            'Alpha Product without Code',
        ], $names);
    }

    public function test_sentinel_price_9999_excluded_from_comparison_and_share(): void
    {
        $valid = $this->product('Valid Item', 'VEG-WH', 'V-01');
        $sentinel = $this->product('Unavailable Item', 'VEG-WH', 'U-01');

        $this->approval($valid, '2026-08-24', 30, 38, 40, 42);
        $this->approval($valid, '2026-08-25', 35, 42, 44, 46);

        // Sentinel 9999
        $this->approval($sentinel, '2026-08-24', 9999, 9999, 9999, 9999);
        $this->approval($sentinel, '2026-08-25', 20, 25, 28, 30);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
        ]));
        $response->assertOk();
        $names = collect($response['rows']->items())->pluck('product_name')->all();
        $this->assertContains('Valid Item', $names);
        $this->assertNotContains('Unavailable Item', $names);

        $shareResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices.whatsapp', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
        ]));
        $shareResponse->assertRedirect();
        $decodedText = rawurldecode(explode('text=', $shareResponse->headers->get('Location'), 2)[1]);
        $this->assertStringContainsString('Valid Item', $decodedText);
        $this->assertStringNotContainsString('Unavailable Item', $decodedText);
    }

    public function test_purchaser_prices_whatsapp_share_uses_monospace_format_with_single_and_two_line_rows(): void
    {
        $shortProduct = $this->product('Amla', 'VEG-WH', 'AML-01');
        $longProduct = $this->product('Banana Nendran Colony Special', 'FRT-WH', 'BNC-01');
        $expensiveProduct = $this->product('Anar S S', 'FRT-WH', 'ANAR-01');

        $this->approval($shortProduct, '2026-08-24', 110, 120, 122, 125);
        $this->approval($shortProduct, '2026-08-25', 90, 100, 102, 105);

        $this->approval($longProduct, '2026-08-24', 80, 90, 92, 95);
        $this->approval($longProduct, '2026-08-25', 74, 85, 87, 90);

        $this->approval($expensiveProduct, '2026-08-24', 2150, 2400, 2450, 2500);
        $this->approval($expensiveProduct, '2026-08-25', 1850, 2100, 2150, 2200);

        $shareResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices.whatsapp', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
        ]));

        $shareResponse->assertRedirect();
        $targetUrl = $shareResponse->headers->get('Location');
        $this->assertStringStartsWith('https://api.whatsapp.com/send?text=', $targetUrl);

        $decodedText = rawurldecode(explode('text=', $targetUrl, 2)[1]);

        $this->assertStringContainsString('*GREEN LEAF*', $decodedText);
        $this->assertStringContainsString('*PURCHASER PRICE CHANGES*', $decodedText);
        $this->assertStringContainsString('24 Aug 2026 → 25 Aug 2026', $decodedText);
        $this->assertStringContainsString('Total Changes: 3', $decodedText);
        $this->assertStringContainsString('```', $decodedText);
        $this->assertStringContainsString("PRODUCT      Y'DAY →   TODAY", $decodedText);

        // Short product: single line format with arrow
        $this->assertStringContainsString('Amla        110.00 →   90.00', $decodedText);

        // Large price: two line format, no commas in thousands, with arrow
        $this->assertStringContainsString("Anar S S\n           2150.00 → 1850.00", $decodedText);

        // Long product: two line format with arrow
        $this->assertStringContainsString("Banana Nendran Colony Special\n             80.00 →   74.00", $decodedText);
    }

    public function test_purchaser_prices_whatsapp_share_respects_filters_and_search(): void
    {
        $tomato = $this->product('Tomato', 'VEG-WH', 'SKU-TOM');
        $onion = $this->product('Onion', 'FRT-WH', 'SKU-ONI');
        $purchaser = User::factory()->create();
        $vendor = Supplier::factory()->create();

        $this->purchase($tomato, '2026-08-25', 1, 34, $purchaser, $vendor);
        $this->approval($tomato, '2026-08-24', 30, 38, 40, 42);
        $this->approval($tomato, '2026-08-25', 34, 42, 44, 46);

        $this->approval($onion, '2026-08-24', 28, 36, 38, 40);
        $this->approval($onion, '2026-08-25', 26, 34, 36, 38);

        $shareResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices.whatsapp', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
            'search' => 'TOM',
        ]));

        $shareResponse->assertRedirect();
        $decodedText = rawurldecode(explode('text=', $shareResponse->headers->get('Location'), 2)[1]);

        $this->assertStringContainsString('Tomato', $decodedText);
        $this->assertStringNotContainsString('Onion', $decodedText);
    }

    public function test_purchaser_prices_empty_states(): void
    {
        $responseNoSearch = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
            'search' => 'NONEXISTENT',
        ]));
        $responseNoSearch->assertOk()->assertSee('No products match your search.');

        $carrot = $this->product('Carrot', 'VEG-WH');
        $this->approval($carrot, '2026-08-24', 40, 48, 50, 52);
        $this->approval($carrot, '2026-08-25', 40, 48, 50, 52);

        $responseNoChanged = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
            'view' => 'changed',
        ]));
        $responseNoChanged->assertOk()->assertSee('No purchaser price changes found.');
    }

    private function product(string $name, string $warehouseCode, ?string $sku = null): Product
    {
        $warehouse = Warehouse::query()->firstOrCreate(['code' => $warehouseCode], ['name' => $warehouseCode, 'is_active' => true]);
        $category = Category::query()->firstOrCreate(['name' => $name.' Category'], ['is_active' => true]);

        $attributes = [
            'name' => $name,
            'category_id' => $category->id,
            'default_warehouse_id' => $warehouse->id,
            'unit' => 'kg',
        ];

        if ($sku !== null) {
            $attributes['sku'] = $sku;
        }

        return Product::factory()->create($attributes);
    }

    private function approval(Product $product, string $date, float $purchase, float $a, float $b, float $c): DailyPriceApproval
    {
        $dateStr = Carbon::parse($date)->toDateString();
        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', $dateStr)
            ->first();

        if ($approval instanceof DailyPriceApproval) {
            $approval->update([
                'purchase_price' => $purchase,
                'price_unit' => 'kg',
                'price_a' => $a,
                'price_b' => $b,
                'price_c' => $c,
                'status' => 'approved',
                'approved_by' => $this->admin->id,
                'approved_at' => $date.' 08:00:00',
            ]);

            return $approval;
        }

        return DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $dateStr,
            'purchase_price' => $purchase,
            'price_unit' => 'kg',
            'price_a' => $a,
            'price_b' => $b,
            'price_c' => $c,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => $date.' 08:00:00',
        ]);
    }

    private function purchase(Product $product, string $date, float $quantity, float $unitPrice, User $purchaser, Supplier $supplier, string $grade = 'A'): PurchaseInvoice
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $date,
            'status' => 'submitted',
            'cart_number' => 'VC-'.str()->upper(str()->random(12)),
            'payment_method' => 'Cash',
        ]);
        $goodsReceived = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id]);
        $invoice = PurchaseInvoice::query()->create([
            'goods_received_id' => $goodsReceived->id,
            'supplier_id' => $supplier->id,
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'PUR-'.str()->upper(str()->random(10)),
            'amount' => $quantity * $unitPrice,
            'discount_amount' => 0,
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
            'payment_paid_by' => 'purchaser',
            'paid_amount' => $quantity * $unitPrice,
        ]);
        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'grade' => $grade,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
        ]);

        return $invoice;
    }
}
