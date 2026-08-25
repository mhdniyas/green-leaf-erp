<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
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
            ->assertSeeInOrder(['Period', 'Produce', 'Search', 'Apply', 'More Filters'])
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
            ->assertSeeInOrder(['Today', 'Yesterday', 'This Week', 'This Month', 'Custom', 'Produce', 'Status', 'Search', 'Apply', 'Total Credit Purchases'])
            ->assertSee('Vendor name or phone...')
            ->assertSee('All Produce')
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

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.credit-purchases', [
            'period' => 'today',
            'produce_type' => 'vegetables',
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

    private function product(string $name, string $warehouseCode): Product
    {
        $warehouse = Warehouse::query()->firstOrCreate(['code' => $warehouseCode], ['name' => $warehouseCode, 'is_active' => true]);
        $category = Category::query()->firstOrCreate(['name' => $name.' Category'], ['is_active' => true]);

        return Product::factory()->create(['name' => $name, 'category_id' => $category->id, 'default_warehouse_id' => $warehouse->id, 'unit' => 'kg']);
    }

    private function approval(Product $product, string $date, float $purchase, float $a, float $b, float $c): DailyPriceApproval
    {
        return DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $date,
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
