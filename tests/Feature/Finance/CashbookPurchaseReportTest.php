<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseProductFilter;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\PurchaserCredit;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Finance\PurchaserFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookPurchaseReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-25');

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

    public function test_dashboard_defaults_to_today_and_uses_item_level_purchase_value(): void
    {
        $todayInvoice = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
            ['category' => 'Onion', 'product' => 'Onion', 'line_total' => 300],
        ]);
        $this->invoiceWithItems('2026-08-24', 'Cash', [
            ['category' => 'Potato', 'product' => 'Potato', 'line_total' => 999],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase'));

        $response->assertOk()
            ->assertSee('Purchase')
            ->assertSee('₹400.00')
            ->assertSee($todayInvoice->invoice_number)
            ->assertDontSee('₹999.00');
    }

    public function test_category_filter_counts_only_matching_invoice_item_and_cash_credit_equals_total(): void
    {
        $tomatoInvoice = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
            ['category' => 'Onion', 'product' => 'Onion', 'line_total' => 300],
        ]);
        $this->invoiceWithItems('2026-08-25', 'Credit', [
            ['category' => 'Tomato', 'product' => 'Cherry Tomato', 'line_total' => 200],
        ]);
        $tomatoCategory = Category::query()->where('name', 'Tomato')->firstOrFail();

        $filtered = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchasers', [
            'period' => 'today',
            'category_ids' => [$tomatoCategory->id],
        ]));
        $all = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchasers', ['period' => 'today']));

        $filtered->assertOk()->assertSee('₹300.00')->assertSee($tomatoInvoice->invoice_number)->assertDontSee('₹400.00');
        $all->assertOk()->assertSee('₹600.00')->assertSee('₹400.00')->assertSee('₹200.00');
    }

    public function test_invoice_references_link_to_existing_purchase_invoice_detail_route(): void
    {
        $invoice = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchasers', ['period' => 'today']));

        $response->assertOk()->assertSee(route('purchasing.invoices.show', $invoice->public_uuid), false);
    }

    public function test_empty_today_dashboard_explains_the_state_and_report_defaults_to_this_month(): void
    {
        $invoice = $this->invoiceWithItems('2026-08-24', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
        ]);

        $dashboard = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase'));
        $report = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchasers'));

        $dashboard->assertOk()
            ->assertSee('No purchases recorded for this period')
            ->assertSee('Procurement overview')
            ->assertDontSee('@endsection')
            ->assertDontSee('ndsection Procurement overview')
            ->assertSee(route('admin.cashbook.finance.purchase', ['period' => 'month']), false);
        $report->assertOk()->assertSee($invoice->invoice_number)->assertSee('₹100.00');
    }

    public function test_today_period_ignores_stale_dates_and_custom_date_uses_the_submitted_date(): void
    {
        $invoice = $this->invoiceWithItems('2026-08-24', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
        ]);
        $rangeInvoice = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Onion', 'product' => 'Onion', 'line_total' => 200],
        ]);

        $today = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-24',
        ]));
        $custom = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'custom',
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-25',
        ]));
        $range = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'range',
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-25',
        ]));

        $today->assertOk()->assertDontSee($invoice->invoice_number);
        $custom->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee($rangeInvoice->invoice_number)
            ->assertSee('₹300.00');
        $range->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee($rangeInvoice->invoice_number)
            ->assertSee('₹300.00');
    }

    public function test_month_report_filters_use_browser_query_strings_without_unrequested_predicates(): void
    {
        $cashInvoice = $this->invoiceWithItems('2026-08-24', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
        ]);
        $creditInvoice = $this->invoiceWithItems('2026-08-25', 'Credit', [
            ['category' => 'Onion', 'product' => 'Onion', 'line_total' => 200],
        ]);

        $base = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchasers', ['period' => 'month']));
        $vendor = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchasers', [
            'period' => 'month',
            'vendor_id' => $cashInvoice->supplier_id,
        ]));
        $purchaser = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchasers', [
            'period' => 'month',
            'purchaser_id' => $cashInvoice->purchaserCart->user_id,
        ]));
        $paymentAll = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchasers', [
            'period' => 'month',
            'payment' => 'all',
        ]));
        $produceAll = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchasers', [
            'period' => 'month',
            'produce_type' => 'all',
        ]));
        $blankGrade = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchasers', [
            'period' => 'month',
            'grade' => '',
        ]));

        $base->assertOk()->assertSee('₹300.00')->assertSee($cashInvoice->invoice_number)->assertSee($creditInvoice->invoice_number);
        $vendor->assertOk()->assertSee('₹100.00')->assertSee($cashInvoice->invoice_number)->assertDontSee($creditInvoice->invoice_number);
        $purchaser->assertOk()->assertSee('₹100.00')->assertSee($cashInvoice->invoice_number)->assertDontSee($creditInvoice->invoice_number);
        $paymentAll->assertOk()->assertSee('₹300.00')->assertSee($cashInvoice->invoice_number)->assertSee($creditInvoice->invoice_number);
        $produceAll->assertOk()->assertSee('₹300.00')->assertSee($cashInvoice->invoice_number)->assertSee($creditInvoice->invoice_number);
        $blankGrade->assertOk()->assertSee('₹300.00')->assertSee($cashInvoice->invoice_number)->assertSee($creditInvoice->invoice_number);
    }

    public function test_purchase_ui_removes_advanced_dashboard_filters_and_batch_ui(): void
    {
        $invoice = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
        ]);
        GoodsReceived::query()->whereKey($invoice->goods_received_id)->update(['received_at' => '2026-08-25']);
        $cart = PurchaserCart::query()->findOrFail($invoice->purchaser_cart_id);
        $goodsReceivedItem = GoodsReceivedItem::query()->create([
            'goods_received_id' => $invoice->goods_received_id,
            'product_id' => PurchaserCartItem::query()->where('purchaser_cart_id', $cart->id)->value('product_id'),
            'grade' => 'A',
            'received_qty' => 1,
            'variance' => 0,
        ]);
        $batch = StockBatch::factory()->create([
            'goods_received_id' => $invoice->goods_received_id,
            'goods_received_item_id' => $goodsReceivedItem->id,
            'product_id' => $goodsReceivedItem->product_id,
            'warehouse_id' => Product::query()->findOrFail($goodsReceivedItem->product_id)->default_warehouse_id,
            'received_at' => '2026-08-25',
        ]);

        $dashboard = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase'));
        $report = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchasers'));

        $dashboard->assertOk()
            ->assertSee('Today')
            ->assertSee('Yesterday')
            ->assertSee('This Week')
            ->assertSee('This Month')
            ->assertSee('Custom')
            ->assertSee('All Products')
            ->assertSee('Manage Filters')
            ->assertDontSee('All purchasers')
            ->assertDontSee('All vendors')
            ->assertDontSee('All grades')
            ->assertDontSee('Categories<select', false)
            ->assertDontSee('Search')
            ->assertDontSee('Batch Summary')
            ->assertDontSee($batch->reference);
        $report->assertOk()
            ->assertSee($cart->user->name)
            ->assertSee($invoice->supplier->name)
            ->assertSee('Tomato')
            ->assertDontSee('Batch Summary')
            ->assertDontSee($batch->reference);
    }

    public function test_dashboard_summary_totals_sections_and_invoice_links_are_correct(): void
    {
        $cashInvoice = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
        ]);
        $creditInvoice = $this->invoiceWithItems('2026-08-25', 'Credit', [
            ['category' => 'Onion', 'product' => 'Onion', 'line_total' => 200],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase'));

        $response->assertOk()
            ->assertSee('What are we purchasing right now?')
            ->assertSee('₹300.00')
            ->assertSee('₹100.00')
            ->assertSee('₹200.00')
            ->assertSee('Credit Outstanding')
            ->assertSee('Purchasers Overview')
            ->assertSee('Vendors Overview')
            ->assertSee('Category Overview')
            ->assertSee('Recent Purchases')
            ->assertSee(route('purchasing.invoices.show', $cashInvoice->public_uuid), false)
            ->assertSee(route('purchasing.invoices.show', $creditInvoice->public_uuid), false);
    }

    public function test_dashboard_product_filter_filters_every_section_and_old_produce_type_falls_back(): void
    {
        $vegetableInvoice = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Vegetable', 'product' => 'Tomato', 'line_total' => 100, 'warehouse_code' => 'VEG-WH'],
        ]);
        $fruitInvoice = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Fruit', 'product' => 'Apple', 'line_total' => 200, 'warehouse_code' => 'FRT-WH'],
        ]);

        $tomatoProduct = Product::query()->where('name', 'Tomato')->firstOrFail();
        $filter = PurchaseProductFilter::query()->create([
            'name' => 'Vegetable Filter',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync([$tomatoProduct->id]);

        // Using saved product_filter
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'product_filter' => $filter->uuid,
        ]));

        $response->assertOk()
            ->assertSee('₹100.00')
            ->assertSee($vegetableInvoice->invoice_number)
            ->assertDontSee($fruitInvoice->invoice_number)
            ->assertDontSee('₹200.00');
        $this->assertCount(1, $response['dashboard']['recentPurchases']);
        $this->assertCount(1, $response['dashboard']['vendors']);
        $this->assertCount(1, $response['dashboard']['purchasers']);
        $this->assertCount(1, $response['dashboard']['categories']);

        // Backward compatibility: old produce_type falls back to All Products without crashing or redirecting
        $legacy = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'produce_type' => 'vegetables',
        ]));
        $legacy->assertOk()
            ->assertSee('₹300.00')
            ->assertSee($vegetableInvoice->invoice_number)
            ->assertSee($fruitInvoice->invoice_number);
    }

    public function test_dashboard_overviews_are_bounded_and_vendor_category_tags_are_compact(): void
    {
        $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 50],
            ['category' => 'Onion', 'product' => 'Onion', 'line_total' => 50],
            ['category' => 'Potato', 'product' => 'Potato', 'line_total' => 50],
            ['category' => 'Carrot', 'product' => 'Carrot', 'line_total' => 50],
        ]);
        for ($index = 1; $index <= 12; $index++) {
            $this->invoiceWithItems('2026-08-25', 'Cash', [
                ['category' => 'Category '.$index, 'product' => 'Product '.$index, 'line_total' => 10 + $index],
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase'));

        $response->assertOk()
            ->assertSee('Tomato')
            ->assertSee('Onion')
            ->assertSee('Potato')
            ->assertSee('+1');
        $this->assertCount(5, $response['dashboard']['purchasers']);
        $this->assertCount(5, $response['dashboard']['vendors']);
        $this->assertCount(8, $response['dashboard']['categories']);
        $this->assertCount(10, $response['dashboard']['recentPurchases']);
    }

    public function test_dashboard_query_count_stays_bounded(): void
    {
        for ($index = 1; $index <= 12; $index++) {
            $this->invoiceWithItems('2026-08-25', $index % 2 === 0 ? 'Credit' : 'Cash', [
                ['category' => 'Category '.$index, 'product' => 'Product '.$index, 'line_total' => 10 + $index],
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase'))->assertOk();

        $this->assertLessThan(30, count(DB::getQueryLog()));
    }

    public function test_purchase_subsection_routes_load_real_paginated_pages_without_batch_ui(): void
    {
        foreach ([
            route('admin.cashbook.finance.purchase.purchasers') => 'Purchasers',
            route('admin.cashbook.finance.purchase.vendors') => 'Vendors',
            route('admin.cashbook.finance.purchase.categories') => 'Categories',
            route('admin.cashbook.finance.purchase.invoices') => 'Invoices',
        ] as $url => $label) {
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertSee($label)
                ->assertSee('More Filters')
                ->assertDontSee('Batch');
        }
    }

    public function test_overview_drilldowns_preserve_context_and_detail_links_are_valid(): void
    {
        $invoice = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
        ]);
        $context = ['period' => 'month'];
        $purchaser = $invoice->purchaserCart->user;
        $category = Category::query()->where('name', 'Tomato')->firstOrFail();

        $overview = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', $context));

        $overview->assertOk()
            ->assertSee(route('admin.cashbook.finance.purchase.purchasers', $context))
            ->assertSee(route('admin.cashbook.finance.purchase.vendors', $context))
            ->assertSee(route('admin.cashbook.finance.purchase.categories', $context))
            ->assertSee(route('admin.cashbook.finance.purchase.invoices', $context))
            ->assertSee(route('admin.cashbook.finance.purchase.purchasers.show', ['purchaser' => $purchaser->public_uuid] + $context))
            ->assertSee(route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $invoice->supplier] + $context))
            ->assertSee(route('admin.cashbook.finance.purchase.categories.show', ['category' => $category] + $context))
            ->assertSee(route('purchasing.invoices.show', $invoice->public_uuid), false);
    }

    public function test_subsection_totals_reconcile_and_filters_use_the_same_item_population(): void
    {
        $cash = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
            ['category' => 'Onion', 'product' => 'Onion', 'line_total' => 200],
        ]);
        $credit = $this->invoiceWithItems('2026-08-25', 'Credit', [
            ['category' => 'Apple', 'product' => 'Apple', 'line_total' => 300, 'warehouse_code' => 'FRT-WH'],
        ]);

        foreach (['purchase', 'purchase.purchasers', 'purchase.vendors', 'purchase.categories', 'purchase.invoices'] as $suffix) {
            $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.'.$suffix, ['period' => 'today']));
            $response->assertOk()->assertSee('₹600.00');
        }

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.invoices', ['period' => 'today', 'vendor_id' => $cash->supplier_id]))
            ->assertOk()->assertSee($cash->invoice_number)->assertDontSee($credit->invoice_number)->assertSee('₹300.00');
        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.invoices', ['period' => 'today', 'search' => 'Apple']))
            ->assertOk()->assertSee($credit->invoice_number)->assertDontSee($cash->invoice_number);
    }

    public function test_purchaser_vendor_and_category_details_use_scoped_purchase_data(): void
    {
        $invoice = $this->invoiceWithItems('2026-08-25', 'Credit', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
            ['category' => 'Onion', 'product' => 'Onion', 'line_total' => 200],
        ]);
        $purchaser = $invoice->purchaserCart->user;
        $category = Category::query()->where('name', 'Tomato')->firstOrFail();
        PurchaserCredit::query()->create(['purchaser_id' => $purchaser->id, 'type' => 'in', 'amount' => 500, 'description' => 'Test funding', 'business_date' => '2026-08-25']);

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', ['purchaser' => $purchaser->public_uuid, 'period' => 'today']))
            ->assertOk()->assertSee($purchaser->name)->assertSee('₹300.00')->assertSee('₹500.00')->assertSee('Funding')->assertDontSee('Batch');
        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $invoice->supplier, 'period' => 'today']))
            ->assertOk()->assertSee($invoice->supplier->name)->assertSee('₹300.00')->assertSee('Payments')->assertDontSee('Batch');
        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.categories.show', ['category' => $category, 'period' => 'today']))
            ->assertOk()->assertSee('Tomato')->assertSee('₹100.00')->assertDontSee('₹300.00')->assertSee($invoice->invoice_number)->assertDontSee('Batch');

        $purchasers = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers', ['period' => 'today']));
        $purchasers->assertOk()->assertSee('₹300.00')->assertSee('₹500.00')->assertDontSee('₹800.00');
    }

    public function test_purchaser_workspace_overview_reuses_cumulative_finance_totals_without_mixing_funding_into_purchase(): void
    {
        $invoice = $this->invoiceWithItems('2026-08-25', 'Credit', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 300],
        ]);
        $purchaser = $invoice->purchaserCart->user;
        Role::firstOrCreate(['name' => 'purchaser']);
        $purchaser->assignRole('purchaser');
        PurchaserCredit::query()->create(['purchaser_id' => $purchaser->id, 'type' => 'in', 'amount' => 1000, 'description' => 'Opening funding', 'business_date' => '2026-07-01']);
        PurchaserCredit::query()->create(['purchaser_id' => $purchaser->id, 'type' => 'out', 'amount' => 250, 'description' => 'Advance used', 'business_date' => '2026-07-02']);

        $workspace = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $purchaser->public_uuid,
            'period' => 'today',
        ]));
        $workspace->assertOk()
            ->assertSeeInOrder(['Overview', 'Purchases', 'Vendors', 'Categories', 'Finance'])
            ->assertSee('Purchase Summary')
            ->assertSee('Total Purchase')
            ->assertSee('₹300.00')
            ->assertSee('Company Funding')
            ->assertSee('₹1,000.00')
            ->assertSee('Cash Used')
            ->assertSee('₹250.00')
            ->assertSee('Current Advance / Balance')
            ->assertSee('₹750.00')
            ->assertSee('View Finance')
            ->assertDontSee('₹1,300.00')
            ->assertDontSee('Batch');

        $this->assertSame(300.0, (float) $workspace->viewData('detail')['summary']->total_purchase);
        $this->assertSame(app(PurchaserFinanceService::class)->summaryFor((int) $purchaser->id), $workspace->viewData('financeSummary'));
    }

    public function test_purchaser_workspace_purchase_vendor_and_category_tabs_preserve_period_context(): void
    {
        $invoice = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
            ['category' => 'Onion', 'product' => 'Onion', 'line_total' => 200],
        ]);
        $purchaser = $invoice->purchaserCart->user;
        $context = ['period' => 'custom', 'start_date' => '2026-08-25', 'end_date' => '2026-08-25'];
        $base = ['purchaser' => $purchaser->public_uuid] + $context;

        $purchases = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', $base + ['tab' => 'purchases']));
        $vendors = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', $base + ['tab' => 'vendors']));
        $categories = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', $base + ['tab' => 'categories']));

        $purchases->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee($invoice->supplier->name)
            ->assertSee('cash')
            ->assertSee('Tomato')
            ->assertSee('Onion')
            ->assertSee('₹300.00')
            ->assertSee(route('purchasing.invoices.show', $invoice->public_uuid), false);
        $vendors->assertOk()->assertSee($invoice->supplier->name)->assertSee('Tomato')->assertSee('Onion')->assertSee('₹300.00');
        $categories->assertOk()->assertSee('Tomato')->assertSee('Onion')->assertSee('₹100.00')->assertSee('₹200.00');

        foreach (array_keys(['overview' => true, 'purchases' => true, 'vendors' => true, 'categories' => true, 'finance' => true]) as $tab) {
            $purchases->assertSee(route('admin.cashbook.finance.purchase.purchasers.show', $base + ['tab' => $tab]));
        }
    }

    public function test_purchaser_summary_cards_link_to_filtered_purchase_details(): void
    {
        $cashInvoice = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 100],
        ]);
        $purchaser = $cashInvoice->purchaserCart->user;
        $creditInvoice = $this->invoiceWithItems('2026-08-25', 'Credit', [
            ['category' => 'Onion', 'product' => 'Onion', 'line_total' => 200],
        ], $purchaser);
        $base = ['purchaser' => $purchaser->public_uuid, 'period' => 'today'];

        $overview = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', $base));
        $cashUrl = route('admin.cashbook.finance.purchase.purchasers.show', $base + ['tab' => 'purchases', 'payment' => 'cash']);
        $creditUrl = route('admin.cashbook.finance.purchase.purchasers.show', $base + ['tab' => 'purchases', 'payment' => 'credit']);

        $overview->assertOk()
            ->assertSee($cashUrl)
            ->assertSee($creditUrl)
            ->assertSee('arrow-up-right');

        $cash = $this->actingAs($this->admin)->get($cashUrl);
        $credit = $this->actingAs($this->admin)->get($creditUrl);

        $cash->assertOk()->assertSee($cashInvoice->invoice_number)->assertDontSee($creditInvoice->invoice_number);
        $credit->assertOk()->assertSee($creditInvoice->invoice_number)->assertDontSee($cashInvoice->invoice_number);

        $vendors = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors', ['period' => 'today']));
        $vendors->assertOk()
            ->assertSee(route('admin.cashbook.finance.purchase.vendors', ['period' => 'today', 'payment' => 'cash']).'#purchase-results')
            ->assertSee(route('admin.cashbook.finance.purchase.vendors', ['period' => 'today', 'payment' => 'credit']).'#purchase-results')
            ->assertSee(route('admin.cashbook.finance.vendor-credit'), false)
            ->assertSee('Vendor Credit Payments');
    }

    public function test_purchaser_workspace_finance_tab_uses_existing_movements_actions_and_reconciliation_status(): void
    {
        $invoice = $this->invoiceWithItems('2026-08-25', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 300],
        ]);
        $purchaser = $invoice->purchaserCart->user;
        $creditInvoice = $this->invoiceWithItems('2026-08-25', 'Credit', [
            ['category' => 'Onion', 'product' => 'Onion', 'line_total' => 450],
        ], $purchaser, $invoice->supplier);
        $account = CompanyAccount::query()->create(['name' => 'Operations Bank', 'account_type' => 'bank', 'enabled' => true]);
        PurchaserCredit::query()->create(['purchaser_id' => $purchaser->id, 'type' => 'in', 'amount' => 1000, 'description' => 'Opening funding', 'business_date' => '2026-07-01']);
        $funding = PurchaserCredit::query()->create([
            'purchaser_id' => $purchaser->id,
            'type' => 'in',
            'amount' => 700,
            'description' => 'Daily funding',
            'payment_source' => 'Bank',
            'company_account_id' => $account->id,
            'reference' => 'FUND-REF-700',
            'business_date' => '2026-08-25',
        ]);
        PurchaserCredit::query()->create(['purchaser_id' => $purchaser->id, 'type' => 'out', 'amount' => 200, 'description' => 'Cash bill utilization', 'purchase_invoice_id' => $invoice->id, 'business_date' => '2026-08-25']);
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $account->id,
            'transaction_date' => '2026-08-25',
            'direction' => 'out',
            'amount' => 700,
            'source' => 'purchaser_funding',
            'source_type' => PurchaserCredit::class,
            'source_id' => $funding->id,
            'status' => 'reconciled',
            'matched_amount' => 700,
            'is_finalized' => true,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $purchaser->public_uuid,
            'period' => 'today',
            'tab' => 'finance',
        ]));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk()
            ->assertSee('Current balance is cumulative')
            ->assertSee('₹1,500.00')
            ->assertSee('Reconciled Funding')
            ->assertSee('₹700.00')
            ->assertSee('Pending Reconciliation')
            ->assertSee('₹1,000.00')
            ->assertSee('Cash and Credit History')
            ->assertSee($invoice->invoice_number)
            ->assertSee($creditInvoice->invoice_number)
            ->assertSee('name="finance_payment"', false)
            ->assertSee('name="period"', false)
            ->assertSee('Vendor Credit Payments')
            ->assertSee(route('admin.cashbook.finance.vendor-credit.show', $invoice->supplier), false)
            ->assertDontSee('href="'.route('admin.cashbook.finance.purchasers').'"', false)
            ->assertDontSee('href="'.route('admin.cashbook.finance.purchasers.details', $purchaser->public_uuid).'"', false)
            ->assertSee(route('admin.cashbook.finance.reconciliation'));
        $this->assertLessThan(35, $queryCount, "Purchaser workspace finance tab executed {$queryCount} queries.");

        $fundingResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $purchaser->public_uuid,
            'period' => 'today',
            'tab' => 'funding',
        ]));

        $fundingResponse->assertOk()
            ->assertSee('Purchaser Funding')
            ->assertSee('Operations Bank')
            ->assertSee('FUND-REF-700')
            ->assertSee('matched')
            ->assertSee('advance utilized')
            ->assertSee(route('admin.cashbook.finance.purchasers.funding.store', $purchaser->public_uuid), false);

        $creditHistory = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers.show', [
            'purchaser' => $purchaser->public_uuid,
            'period' => 'today',
            'tab' => 'finance',
            'finance_payment' => 'credit',
        ]));
        $creditHistory->assertOk();
        $this->assertSame(['Credit'], $creditHistory->viewData('finance')['history']->pluck('payment_type')->unique()->values()->all());
    }

    public function test_cashbook_navigation_exposes_only_the_purchase_purchasers_finance_path(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers', ['period' => 'month']));

        $response->assertOk()
            ->assertSee(route('admin.cashbook.finance.purchase'), false)
            ->assertSee(route('admin.cashbook.finance.purchase.purchasers'), false)
            ->assertDontSee('>Purchaser Finance</span>', false)
            ->assertDontSee('href="'.route('admin.cashbook.finance.purchasers').'"', false);
    }

    public function test_invoice_filters_reuse_period_presets_and_show_dates_only_for_custom_periods(): void
    {
        $preset = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.invoices', ['period' => 'month']));

        $preset->assertOk()
            ->assertSeeInOrder(['Today', 'Yesterday', 'This Week', 'This Month', 'Custom'])
            ->assertSee('Search invoice, vendor, purchaser, product...')
            ->assertSee('More Filters')
            ->assertSee('name="purchaser_id"', false)
            ->assertSee('name="vendor_id"', false)
            ->assertSee('name="payment"', false)
            ->assertSee('name="category_id"', false)
            ->assertSee('name="grade"', false)
            ->assertSee('overflow-x-auto', false)
            ->assertDontSee('<select name="period"', false)
            ->assertDontSee('>Apply<', false)
            ->assertDontSee('name="start_date"', false)
            ->assertDontSee('name="end_date"', false);

        $custom = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.invoices', [
            'period' => 'custom',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-25',
        ]));

        $custom->assertOk()->assertSee('>Go<', false);
        $this->assertSame(1, substr_count($custom->getContent(), 'name="start_date"'));
        $this->assertSame(1, substr_count($custom->getContent(), 'name="end_date"'));
    }

    public function test_invoice_period_product_filter_and_search_filters_keep_existing_query_semantics(): void
    {
        $yesterday = $this->invoiceWithItems('2026-08-24', 'Cash', [
            ['category' => 'Apple', 'product' => 'Apple', 'line_total' => 100, 'warehouse_code' => 'FRT-WH'],
        ]);
        $today = $this->invoiceWithItems('2026-08-25', 'Credit', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 200],
        ]);

        $appleProduct = Product::query()->where('name', 'Apple')->firstOrFail();
        $filter = PurchaseProductFilter::query()->create([
            'name' => 'Fruits Filter',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync([$appleProduct->id]);

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.invoices', [
            'period' => 'today',
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-24',
        ]))->assertOk()->assertSee($today->invoice_number)->assertDontSee($yesterday->invoice_number)->assertSee('₹200.00');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.invoices', ['period' => 'month']))
            ->assertOk()->assertSee($today->invoice_number)->assertSee($yesterday->invoice_number)->assertSee('₹300.00');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.invoices', ['period' => 'month', 'product_filter' => $filter->uuid]))
            ->assertOk()->assertSee($yesterday->invoice_number)->assertDontSee($today->invoice_number)->assertSee('₹100.00');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.invoices', ['period' => 'month', 'search' => 'Tomato']))
            ->assertOk()->assertSee($today->invoice_number)->assertDontSee($yesterday->invoice_number)->assertSee('₹200.00');
    }

    public function test_invoice_advanced_filters_render_removable_chips_and_empty_state_guidance(): void
    {
        $invoice = $this->invoiceWithItems('2026-08-25', 'Credit', [
            ['category' => 'Tomato', 'product' => 'Tomato', 'line_total' => 200],
        ]);
        $purchaser = $invoice->purchaserCart->user;
        $category = Category::query()->where('name', 'Tomato')->firstOrFail();
        $tomatoProduct = Product::query()->where('name', 'Tomato')->firstOrFail();
        $filter = PurchaseProductFilter::query()->create([
            'name' => 'Vegetable Filter',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync([$tomatoProduct->id]);

        $query = [
            'period' => 'today',
            'product_filter' => $filter->uuid,
            'search' => 'Tomato',
            'purchaser_id' => $purchaser->id,
            'vendor_id' => $invoice->supplier_id,
            'payment' => 'credit',
            'category_id' => $category->id,
            'grade' => 'A',
        ];

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.invoices', $query));

        $response->assertOk()
            ->assertSee('Filter: Vegetable Filter')
            ->assertSee('Purchaser: '.$purchaser->name)
            ->assertSee('Vendor: '.$invoice->supplier->name)
            ->assertSee('Payment: Credit')
            ->assertSee('Category: Tomato')
            ->assertSee('Grade: A')
            ->assertSee(route('admin.cashbook.finance.purchase.invoices', Arr::except($query, 'vendor_id')));

        $empty = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.invoices', [
            'period' => 'today',
            'search' => 'no-such-purchase-invoice',
        ]));

        $empty->assertOk()
            ->assertSee('No purchase invoices match the selected filters.')
            ->assertSee('Clear Filters')
            ->assertSee(route('admin.cashbook.finance.purchase.invoices'))
            ->assertDontSee('No matching purchase data.');
    }

    public function test_invoice_subsection_paginates_and_keeps_query_count_bounded(): void
    {
        for ($index = 1; $index <= 2; $index++) {
            $this->invoiceWithItems('2026-08-25', 'Cash', [
                ['category' => 'Category '.$index, 'product' => 'Product '.$index, 'line_total' => 10],
            ]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.invoices', ['period' => 'today']));

        $response->assertOk();
        $this->assertSame(20, $response['sectionData']['rows']->perPage());
        $this->assertSame(2, $response['sectionData']['rows']->total());
        $this->assertLessThan(35, count(DB::getQueryLog()));
    }

    /** @param array<int, array{category:string,product:string,line_total:float,warehouse_code?:string}> $items */
    private function invoiceWithItems(string $businessDate, string $paymentMethod, array $items, ?User $purchaser = null, ?Supplier $supplier = null): PurchaseInvoice
    {
        $purchaser ??= User::factory()->create();
        $supplier ??= Supplier::factory()->create();
        $cart = PurchaserCart::query()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $businessDate,
            'status' => 'submitted',
            'cart_number' => 'VC-'.str()->upper(str()->random(12)),
            'payment_method' => $paymentMethod,
        ]);
        $goodsReceived = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id]);
        $total = collect($items)->sum('line_total');
        $invoice = PurchaseInvoice::query()->create([
            'goods_received_id' => $goodsReceived->id,
            'supplier_id' => $supplier->id,
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'PUR-'.str()->upper(str()->random(10)),
            'amount' => $total,
            'discount_amount' => 0,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentMethod === 'Credit' ? 'credit_pending_approval' : 'paid',
            'payment_paid_by' => $paymentMethod === 'Credit' ? 'vendor_credit' : 'purchaser',
            'paid_amount' => $paymentMethod === 'Credit' ? 0 : $total,
        ]);
        foreach ($items as $item) {
            $warehouseCode = $item['warehouse_code'] ?? 'VEG-WH';
            $warehouse = Warehouse::query()->firstOrCreate(
                ['code' => $warehouseCode],
                ['name' => $warehouseCode === 'FRT-WH' ? 'Fruit Warehouse' : 'Vegetable Warehouse', 'is_active' => true],
            );
            $category = Category::query()->firstOrCreate(['name' => $item['category']], ['is_active' => true]);
            $product = Product::factory()->create([
                'name' => $item['product'],
                'category_id' => $category->id,
                'default_warehouse_id' => $warehouse->id,
            ]);
            PurchaserCartItem::query()->create([
                'purchaser_cart_id' => $cart->id,
                'product_id' => $product->id,
                'grade' => 'A',
                'quantity' => 1,
                'unit_price' => $item['line_total'],
                'line_total' => $item['line_total'],
            ]);
        }

        return $invoice;
    }
}
