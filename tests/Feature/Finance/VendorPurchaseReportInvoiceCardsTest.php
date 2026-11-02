<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Exports\VendorPurchaseReportExport;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseProductFilter;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Models\VendorSettlementAllocation;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaseReportingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class VendorPurchaseReportInvoiceCardsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 12:00:00');

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'purchaser', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);
        config(['greenleaf.main_admin_email' => $this->admin->email]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_single_vendor_produces_one_invoice_style_card_with_exact_financial_fields(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'AMAN ONIONS']);
        $purchaser = User::factory()->create(['name' => 'Purchaser Raman']);
        $purchaser->assignRole('purchaser');

        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Onion', 'product' => 'Onion Small', 'line_total' => 125000.00],
        ], $purchaser, $vendor);

        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Onion', 'product' => 'Onion Big', 'line_total' => 271551.00],
        ], $purchaser, $vendor);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
        ]));

        $response->assertOk()
            ->assertSee('GREEN LEAF')
            ->assertSee('VENDOR PURCHASE REPORT')
            ->assertSee('AMAN ONIONS')
            ->assertSee('PURCHASE TOTAL')
            ->assertSee('Rs. 396,551.00')
            ->assertSee('Cash Paid')
            ->assertSee('Discount')
            ->assertDontSee('CREDIT BALANCE TO PAY')
            ->assertSee('2 Purchase Bills')
            ->assertSee('View Vendor Details')
            ->assertSee(route('admin.cashbook.finance.purchase.vendors.single.export.pdf', [
                'supplier' => $vendor->public_uuid,
                'period' => 'today',
            ]))
            ->assertSee(route('admin.cashbook.finance.purchase.vendors.single.export.excel', [
                'supplier' => $vendor->public_uuid,
                'period' => 'today',
            ]))
            ->assertSee(route('admin.cashbook.finance.purchase.vendors.show', [
                'supplier' => $vendor->public_uuid,
                'period' => 'today',
            ]));
    }

    public function test_multiple_vendors_display_sequentially_as_full_width_cards(): void
    {
        $vendor1 = Supplier::factory()->create(['name' => 'ALPHA PRODUCE']);
        $vendor2 = Supplier::factory()->create(['name' => 'BETA FARMS']);
        $vendor3 = Supplier::factory()->create(['name' => 'GAMMA TRADERS']);

        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato Local', 'line_total' => 5000.00],
        ], null, $vendor1);

        $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Potato', 'product' => 'Potato Jyoti', 'line_total' => 12000.00],
        ], null, $vendor2);

        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Onion', 'product' => 'Onion Red', 'line_total' => 8000.00],
        ], null, $vendor3);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
        ]));

        $response->assertOk()
            ->assertSee('ALPHA PRODUCE')
            ->assertSee('BETA FARMS')
            ->assertSee('GAMMA TRADERS')
            ->assertSee('Rs. 5,000.00')
            ->assertSee('Rs. 12,000.00')
            ->assertSee('Rs. 8,000.00');
    }

    public function test_cash_only_credit_only_and_mixed_purchases_calculate_and_label_correctly(): void
    {
        $cashVendor = Supplier::factory()->create(['name' => 'CASH ONLY SUPPLIER']);
        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato Local', 'line_total' => 15000.00],
        ], null, $cashVendor);

        $creditVendor = Supplier::factory()->create(['name' => 'CREDIT UNPAID SUPPLIER']);
        $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Potato', 'product' => 'Potato Jyoti', 'line_total' => 25000.00],
        ], null, $creditVendor);

        $settledVendor = Supplier::factory()->create(['name' => 'PARTIALLY SETTLED SUPPLIER']);
        $settledInvoice = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Onion', 'product' => 'Onion Red', 'line_total' => 30000.00],
        ], null, $settledVendor);
        $this->createSettlementAllocation($settledInvoice, 18000.00, '2026-10-15');

        $fullyPaidVendor = Supplier::factory()->create(['name' => 'FULLY PAID CREDIT SUPPLIER']);
        $fullyPaidInvoice = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Apple', 'product' => 'Shimla Apple', 'line_total' => 10000.00],
        ], null, $fullyPaidVendor);
        $this->createSettlementAllocation($fullyPaidInvoice, 10000.00, '2026-10-15');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
        ]));

        $response->assertOk()
            ->assertSee('CASH ONLY SUPPLIER')
            ->assertSee('Cash Paid')
            ->assertSee('Rs. 15,000.00')
            ->assertSee('CREDIT UNPAID SUPPLIER')
            ->assertSee('Rs. 25,000.00')
            ->assertSee('PARTIALLY SETTLED SUPPLIER')
            ->assertSee('Rs. 30,000.00')
            ->assertSee('Rs. 18,000.00')
            ->assertSee('Rs. 12,000.00')
            ->assertSee('FULLY PAID CREDIT SUPPLIER')
            ->assertSee('Rs. 10,000.00');

        $service = app(PurchaseReportingService::class);
        $filters = ['start_date' => '2026-10-15', 'end_date' => '2026-10-15'];
        $vendorData = $service->vendorReportData($filters);
        $vendorsById = $vendorData['vendors']->keyBy('supplier_id');

        $cashRow = $vendorsById->get($cashVendor->id);
        $this->assertEquals(15000.00, $cashRow->total_purchase);
        $this->assertEquals(15000.00, $cashRow->paid_amount);
        $this->assertEquals(0.00, $cashRow->outstanding);

        $creditRow = $vendorsById->get($creditVendor->id);
        $this->assertEquals(25000.00, $creditRow->total_purchase);
        $this->assertEquals(0.00, $creditRow->paid_amount);
        $this->assertEquals(25000.00, $creditRow->outstanding);

        $settledRow = $vendorsById->get($settledVendor->id);
        $this->assertEquals(30000.00, $settledRow->total_purchase);
        $this->assertEquals(18000.00, $settledRow->paid_amount);
        $this->assertEquals(12000.00, $settledRow->outstanding);

        $fullyPaidRow = $vendorsById->get($fullyPaidVendor->id);
        $this->assertEquals(10000.00, $fullyPaidRow->total_purchase);
        $this->assertEquals(10000.00, $fullyPaidRow->paid_amount);
        $this->assertEquals(0.00, $fullyPaidRow->outstanding);
    }

    public function test_discounts_and_settlement_discounts_reconcile_without_double_counting(): void
    {
        $discountVendor = Supplier::factory()->create(['name' => 'DISCOUNTED SUPPLIER']);

        // Gross 20,000 with 1,000 direct discount => Net 19,000
        $invoice = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Tomato', 'product' => 'Tomato Hybrid', 'line_total' => 20000.00],
        ], null, $discountVendor, 1000.00);

        // 9,000 cash settlement + 500 settlement discount => Total settled 9,500
        $this->createSettlementAllocation($invoice, 9000.00, '2026-10-15', 'approved', 500.00);

        $service = app(PurchaseReportingService::class);
        $filters = ['start_date' => '2026-10-15', 'end_date' => '2026-10-15'];
        $vendorData = $service->vendorReportData($filters);
        $row = $vendorData['vendors']->firstWhere('supplier_id', $discountVendor->id);

        $this->assertNotNull($row);
        $this->assertEquals(19000.00, $row->total_purchase);
        $this->assertEquals(1500.00, $row->discount);
        $this->assertEquals(9500.00, $row->paid_amount);
        $this->assertEquals(9500.00, $row->outstanding); // 19000 - 9500
    }

    public function test_dynamic_filters_purchaser_date_and_product_filters_work_consistently(): void
    {
        $purchaserA = User::factory()->create(['name' => 'Purchaser Alice']);
        $purchaserB = User::factory()->create(['name' => 'Purchaser Bob']);
        $vendorA = Supplier::factory()->create(['name' => 'SUPPLIER A']);
        $vendorB = Supplier::factory()->create(['name' => 'SUPPLIER B']);

        // Purchase under Alice on Oct 15
        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Cherry Tomato', 'line_total' => 4000.00],
        ], $purchaserA, $vendorA);

        // Purchase under Bob on Oct 15
        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Potato', 'product' => 'Red Potato', 'line_total' => 6000.00],
        ], $purchaserB, $vendorB);

        // Purchase under Alice on Oct 10 (different date)
        $this->invoiceWithItems('2026-10-10', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Cherry Tomato', 'line_total' => 9000.00],
        ], $purchaserA, $vendorA);

        // 1. Filter by Purchaser Alice today
        $responseAlice = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
            'purchaser_id' => $purchaserA->id,
        ]));
        $responseAlice->assertOk()
            ->assertSee('SUPPLIER A')
            ->assertSee('Rs. 4,000.00')
            ->assertDontSee('SUPPLIER B')
            ->assertDontSee('Rs. 9,000.00');

        // 2. Filter by Date range Oct 10 to Oct 12
        $responseRange = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'custom',
            'from' => '2026-10-10',
            'to' => '2026-10-12',
        ]));
        $responseRange->assertOk()
            ->assertSee('SUPPLIER A')
            ->assertSee('Rs. 9,000.00')
            ->assertDontSee('Rs. 4,000.00');

        // 3. Filter by Product Filter
        $product = Product::query()->where('name', 'Red Potato')->firstOrFail();
        $filter = PurchaseProductFilter::query()->create([
            'uuid' => (string) str()->uuid(),
            'name' => 'Potatoes Only',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->attach($product->id);

        $responseProduct = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
            'product_filter' => $filter->uuid,
        ]));
        $responseProduct->assertOk()
            ->assertSee('SUPPLIER B')
            ->assertSee('Rs. 6,000.00')
            ->assertDontSee('SUPPLIER A');
    }

    public function test_credit_and_cash_filter_on_top_toggles_vendor_visibility(): void
    {
        $cashVendor = Supplier::factory()->create(['name' => 'CASH ONLY VENDOR']);
        $creditVendor = Supplier::factory()->create(['name' => 'CREDIT ONLY VENDOR']);

        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Vegetable', 'product' => 'Cucumber', 'line_total' => 5000.00],
        ], null, $cashVendor);

        $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Fruit', 'product' => 'Apple', 'line_total' => 8000.00],
        ], null, $creditVendor);

        // All vendors
        $responseAll = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
            'payment' => 'all',
        ]));
        $responseAll->assertOk()
            ->assertSee('CASH ONLY VENDOR')
            ->assertSee('CREDIT ONLY VENDOR')
            ->assertSee('Cash Only')
            ->assertSee('Credit Only');

        // Cash only filter
        $responseCash = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
            'payment' => 'cash',
        ]));
        $responseCash->assertOk()
            ->assertSee('CASH ONLY VENDOR')
            ->assertSee('Rs. 5,000.00')
            ->assertDontSee('CREDIT ONLY VENDOR');

        // Credit only filter
        $responseCredit = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
            'payment' => 'credit',
        ]));
        $responseCredit->assertOk()
            ->assertSee('CREDIT ONLY VENDOR')
            ->assertSee('Rs. 8,000.00')
            ->assertDontSee('CASH ONLY VENDOR');
    }

    public function test_vendor_search_input_and_load_more_pagination_are_rendered(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $vendor = Supplier::factory()->create(['name' => "PAGINATED VENDOR {$i}"]);
            $this->invoiceWithItems('2026-10-15', 'Cash', [
                ['category' => 'Vegetable', 'product' => "Veg Item {$i}", 'line_total' => 1000.00 * $i],
            ], null, $vendor);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
        ]));

        $response->assertOk()
            ->assertSee('placeholder="Filter vendor or product..."', false)
            ->assertSee('Load More Vendors')
            ->assertSee('PAGINATED VENDOR 1')
            ->assertSee('PAGINATED VENDOR 5')
            ->assertSee('PAGINATED VENDOR 7')
            ->assertSee('display: none;', false);
    }

    public function test_vendor_detail_links_preserve_active_filters(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'FILTER PRESERVED VENDOR']);
        $purchaser = User::factory()->create(['name' => 'Purchaser Filter']);
        $purchaser->assignRole('purchaser');

        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Tomato Local', 'line_total' => 2000.00],
        ], $purchaser, $vendor);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
            'purchaser_id' => $purchaser->id,
        ]));

        $response->assertOk();
        $expectedUrl = route('admin.cashbook.finance.purchase.vendors.show', [
            'supplier' => $vendor->public_uuid,
            'period' => 'today',
            'purchaser_id' => $purchaser->id,
        ]);

        $response->assertSee($expectedUrl);
    }

    public function test_pdf_export_contains_all_filtered_vendors_and_invoice_details(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'PDF EXPORT VENDOR']);
        $purchaser = User::factory()->create(['name' => 'PDF Purchaser']);
        $purchaser->assignRole('purchaser');

        $invoice = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Onion', 'product' => 'Onion Export', 'line_total' => 50000.00],
        ], $purchaser, $vendor);
        $this->createSettlementAllocation($invoice, 30000.00, '2026-10-15');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.export.pdf', [
            'period' => 'today',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('vendor-purchase-report-', (string) $response->headers->get('Content-Disposition'));

        // Test single vendor PDF export
        $singlePdfResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.single.export.pdf', [
            'supplier' => $vendor->public_uuid,
            'period' => 'today',
        ]));
        $singlePdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $singlePdfResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('pdf-export-vendor', (string) $singlePdfResponse->headers->get('Content-Disposition'));
    }

    public function test_single_vendor_excel_export_downloads(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'SINGLE EXCEL VENDOR']);
        $purchaser = User::factory()->create(['name' => 'Excel Single Purchaser']);
        $purchaser->assignRole('purchaser');

        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Cherry Tomato', 'line_total' => 7000.00],
        ], $purchaser, $vendor);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.single.export.excel', [
            'supplier' => $vendor->public_uuid,
            'period' => 'today',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('vendor-single-excel-vendor-purchase-report-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_excel_export_contains_vendor_summary_and_invoice_details_sheets(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'EXCEL EXPORT VENDOR']);
        $purchaser = User::factory()->create(['name' => 'Excel Purchaser']);
        $purchaser->assignRole('purchaser');

        $invoice = $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Tomato', 'product' => 'Green Tomato', 'line_total' => 12000.00],
        ], $purchaser, $vendor);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.export.excel', [
            'period' => 'today',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('vendor-purchase-report-', (string) $response->headers->get('Content-Disposition'));

        // Test export data directly
        $service = app(PurchaseReportingService::class);
        $data = $service->vendorReportData(['start_date' => '2026-10-15', 'end_date' => '2026-10-15']);
        $export = new VendorPurchaseReportExport($data);
        $sheets = $export->sheets();

        $this->assertCount(2, $sheets);
        $this->assertEquals('Vendor Summary', $sheets[0]->title());
        $this->assertEquals('Invoice Details', $sheets[1]->title());

        $summaryArray = $sheets[0]->array();
        $this->assertEquals('Green Leaf ERP — Vendor Purchase Report', $summaryArray[0][0]);
        $vendorRow = collect($summaryArray)->firstWhere(0, 'EXCEL EXPORT VENDOR');
        $this->assertNotNull($vendorRow);
        $this->assertEquals(1, $vendorRow[1]); // Bill count numeric
        $this->assertEquals(12000.00, $vendorRow[2]); // Total purchase numeric
        $this->assertEquals(12000.00, $vendorRow[3]); // Paid numeric

        $invoiceArray = $sheets[1]->array();
        $invRow = collect($invoiceArray)->firstWhere(1, $invoice->invoice_number);
        $this->assertNotNull($invRow);
        $this->assertEquals(12000.00, $invRow[5]); // Purchase total numeric
        $this->assertEquals(12000.00, $invRow[6]); // Paid numeric
        $this->assertEquals('Paid', $invRow[9]);
    }

    public function test_cash_and_credit_filter_reloads_report_and_updates_totals_and_visible_cards(): void
    {
        $cashVendor = Supplier::factory()->create(['name' => 'CASH FILTER VENDOR']);
        $creditVendor = Supplier::factory()->create(['name' => 'CREDIT FILTER VENDOR']);

        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Cucumber', 'line_total' => 20000.00],
        ], null, $cashVendor);

        $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Capsicum', 'line_total' => 35000.00],
        ], null, $creditVendor);

        // 1. All (default) shows both
        $allResp = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
            'payment' => 'all',
        ]));
        $allResp->assertOk()
            ->assertSee('CASH FILTER VENDOR')
            ->assertSee('CREDIT FILTER VENDOR');

        // 2. Cash shows only cash vendor
        $cashResp = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
            'payment' => 'cash',
        ]));
        $cashResp->assertOk()
            ->assertSee('CASH FILTER VENDOR')
            ->assertDontSee('CREDIT FILTER VENDOR');

        // 3. Credit shows only credit vendor
        $creditResp = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
            'payment' => 'credit',
        ]));
        $creditResp->assertOk()
            ->assertDontSee('CASH FILTER VENDOR')
            ->assertSee('CREDIT FILTER VENDOR');
    }

    public function test_serial_numbers_display_sequentially_on_report_cards_and_pdf(): void
    {
        $v1 = Supplier::factory()->create(['name' => 'VENDOR FIRST ALPHA']);
        $v2 = Supplier::factory()->create(['name' => 'VENDOR SECOND BETA']);

        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Carrot', 'line_total' => 5000.00],
        ], null, $v1);

        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Beetroot', 'line_total' => 7000.00],
        ], null, $v2);

        $webResp = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
        ]));
        $webResp->assertOk()
            ->assertSee('SL 01')
            ->assertSee('SL 02')
            ->assertDontSee('No. 01')
            ->assertDontSee('No. 02');

        $pdfResp = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.export.pdf', [
            'period' => 'today',
        ]));
        $pdfResp->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $service = app(PurchaseReportingService::class);
        $data = $service->vendorReportData(['period' => 'today', 'start_date' => '2026-10-15', 'end_date' => '2026-10-15', 'payment' => 'all']);
        $pdfHtml = view('admin.cashbook.finance.purchase.reports.vendors_overview_pdf', array_merge($data, [
            'generatedAt' => now(),
            'purchaserName' => null,
            'productFilterName' => null,
            'singleVendor' => null,
        ]))->render();
        $this->assertStringContainsString('01.', $pdfHtml);
        $this->assertStringContainsString('02.', $pdfHtml);
    }

    public function test_pdf_export_conditional_credit_due_and_compact_sections(): void
    {
        $cashVendor = Supplier::factory()->create(['name' => 'ZERO DUE VENDOR']);
        $creditVendor = Supplier::factory()->create(['name' => 'OUTSTANDING VENDOR']);

        // Cash purchase of 10,000 (0 outstanding)
        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Cucumber', 'line_total' => 10000.00],
        ], null, $cashVendor);

        // Credit purchase of 15,000 (15,000 outstanding)
        $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Capsicum', 'line_total' => 15000.00],
        ], null, $creditVendor);

        $service = app(PurchaseReportingService::class);
        $data = $service->vendorReportData(['period' => 'today', 'start_date' => '2026-10-15', 'end_date' => '2026-10-15', 'payment' => 'all']);

        $pdfHtml = view('admin.cashbook.finance.purchase.reports.vendors_overview_pdf', array_merge($data, [
            'generatedAt' => now(),
            'purchaserName' => 'All Purchasers',
            'productFilterName' => 'All Products',
            'singleVendor' => null,
        ]))->render();

        // 1. Both vendors present
        $this->assertStringContainsString('ZERO DUE VENDOR', $pdfHtml);
        $this->assertStringContainsString('OUTSTANDING VENDOR', $pdfHtml);

        // 2. Serial numbers present
        $this->assertStringContainsString('01.', $pdfHtml);
        $this->assertStringContainsString('02.', $pdfHtml);

        // 3. Outstanding vendor shows Credit Due
        $this->assertStringContainsString('credit-due-header', $pdfHtml);
        $this->assertStringContainsString('credit-due-cell', $pdfHtml);
        $this->assertStringContainsString('Rs. 15,000.00', $pdfHtml);

        // 4. Repeated headings removed from vendor sections
        $this->assertStringNotContainsString('status-pill-paid', $pdfHtml);
        $this->assertStringNotContainsString('status-pill-unpaid', $pdfHtml);
        $this->assertStringNotContainsString('bill-brand-badge', $pdfHtml);
    }

    public function test_single_bill_payment_details_endpoint_returns_accurate_financials_and_history(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'POPUP DETAILS VENDOR']);
        $purchaser = User::factory()->create(['name' => 'Purchaser Salim']);
        $purchaser->assignRole('purchaser');

        $invoice = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Ginger', 'line_total' => 10000.00],
        ], $purchaser, $supplier, 500.00); // 10000 gross, 500 discount = 9500 net

        $response = $this->actingAs($this->admin)->getJson(route('admin.cashbook.finance.vendor-credit.invoice-details', [
            'invoice' => $invoice->public_uuid,
        ]));

        $response->assertOk()
            ->assertJson([
                'id' => $invoice->id,
                'public_uuid' => $invoice->public_uuid,
                'invoice_number' => $invoice->invoice_number,
                'supplier_name' => 'POPUP DETAILS VENDOR',
                'amount' => 10000.00,
                'discount_amount' => 500.00,
                'net_payable' => 9500.00,
                'paid_amount' => 0.00,
                'outstanding' => 9500.00,
            ]);
    }

    public function test_single_bill_payment_update_settles_selected_bill_without_affecting_other_bills(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'SETTLEMENT TEST VENDOR']);
        $purchaser = User::factory()->create(['name' => 'Purchaser Kiran']);
        $purchaser->assignRole('purchaser');

        $invoice1 = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Onion', 'line_total' => 10000.00],
        ], $purchaser, $supplier);

        $invoice2 = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Garlic', 'line_total' => 8000.00],
        ], $purchaser, $supplier);

        // Record a partial payment of 4000 on Bill 1 only
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.finance.vendor-credit.pay', [
            'invoice' => $invoice1->public_uuid,
        ]), [
            'payment_amount' => 4000.00,
            'payment_method' => 'Bank',
            'payment_date' => '2026-10-15',
            'payment_note' => 'Partial payment via Bank NEFT',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'invoice' => [
                    'id' => $invoice1->id,
                    'paid_amount' => 4000.00,
                    'outstanding' => 6000.00,
                ],
            ]);

        // Verify Bill 1 is updated in database
        $this->assertEquals(4000.00, (float) $invoice1->fresh()->paid_amount);

        // Verify Bill 2 is completely UNTOUCHED
        $this->assertEquals(0.00, (float) $invoice2->fresh()->paid_amount);

        // Verify payment record was created
        $this->assertDatabaseHas('purchase_invoice_payments', [
            'purchase_invoice_id' => $invoice1->id,
            'supplier_id' => $supplier->id,
            'amount' => 4000.00,
            'payment_method' => 'Bank',
        ]);
    }

    public function test_single_bill_payment_update_rejects_excess_amounts(): void
    {
        $supplier = Supplier::factory()->create();
        $invoice = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Pumpkin', 'line_total' => 5000.00],
        ], null, $supplier);

        // Attempt to pay more than the outstanding balance (5000)
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.finance.vendor-credit.pay', [
            'invoice' => $invoice->public_uuid,
        ]), [
            'payment_amount' => 9999.00,
            'payment_method' => 'Cash',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_amount']);

        $this->assertEquals(0.00, (float) $invoice->fresh()->paid_amount);
    }

    /**
     * @param  array<int, array{category:string,product:string,line_total:float,warehouse_code?:string}>  $items
     */
    private function invoiceWithItems(
        string $businessDate,
        string $paymentMethod,
        array $items,
        ?User $purchaser = null,
        ?Supplier $supplier = null,
        float $discount = 0.0
    ): PurchaseInvoice {
        $purchaser ??= User::factory()->create();
        $supplier ??= Supplier::factory()->create();

        $cart = PurchaserCart::query()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $businessDate,
            'status' => 'submitted',
            'cart_number' => 'VC-'.str()->upper(str()->random(12)),
            'bill_number' => 'BILL-'.str()->upper(str()->random(8)),
            'payment_method' => $paymentMethod,
            'discount_amount' => $discount,
        ]);

        $goodsReceived = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id]);
        $total = (float) collect($items)->sum('line_total');
        $net = max(0.0, $total - $discount);
        $isCredit = strcasecmp($paymentMethod, 'credit') === 0;

        $invoice = PurchaseInvoice::query()->create([
            'goods_received_id' => $goodsReceived->id,
            'supplier_id' => $supplier->id,
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'PUR-'.str()->upper(str()->random(10)),
            'amount' => $total,
            'discount_amount' => $discount,
            'payment_method' => $paymentMethod,
            'payment_status' => $isCredit ? 'credit_pending_approval' : 'paid',
            'payment_paid_by' => $isCredit ? 'vendor_credit' : 'purchaser',
            'paid_amount' => $isCredit ? 0 : $net,
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

    public function test_credit_balance_row_appears_only_when_outstanding_balance_is_greater_than_zero(): void
    {
        $cashVendor = Supplier::factory()->create(['name' => 'CASH ZERO BALANCE VENDOR']);
        $creditVendor = Supplier::factory()->create(['name' => 'CREDIT DUE VENDOR']);

        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Cucumber', 'line_total' => 10000.00],
        ], null, $cashVendor);

        $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Capsicum', 'line_total' => 15000.00],
        ], null, $creditVendor);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'today',
        ]));

        $response->assertOk();
        // Credit Balance to Pay is displayed for CREDIT DUE VENDOR
        $response->assertSee('CREDIT BALANCE TO PAY');
        $response->assertSee('Rs. 15,000.00');

        // Cash card does not have credit balance to pay
        $cashCardHtml = view('admin.cashbook.finance.purchase._vendor-invoice-card', [
            'vendor' => app(PurchaseReportingService::class)->vendorReportData(['period' => 'today', 'start_date' => '2026-10-15', 'end_date' => '2026-10-15'])['vendors']->firstWhere('supplier_id', $cashVendor->id),
            'serialNumber' => 1,
            'purchaseContext' => ['period' => 'today'],
            'formattedRange' => '15 Oct 2026',
        ])->render();

        $this->assertStringNotContainsString('CREDIT BALANCE TO PAY', $cashCardHtml);

        $creditCardHtml = view('admin.cashbook.finance.purchase._vendor-invoice-card', [
            'vendor' => app(PurchaseReportingService::class)->vendorReportData(['period' => 'today', 'start_date' => '2026-10-15', 'end_date' => '2026-10-15'])['vendors']->firstWhere('supplier_id', $creditVendor->id),
            'serialNumber' => 2,
            'purchaseContext' => ['period' => 'today'],
            'formattedRange' => '15 Oct 2026',
        ])->render();

        $this->assertStringContainsString('CREDIT BALANCE TO PAY', $creditCardHtml);
    }

    public function test_payment_status_badge_accurately_reflects_settlement_status(): void
    {
        $unpaidVendor = Supplier::factory()->create(['name' => 'ZERO PAID VENDOR']);
        $partialVendor = Supplier::factory()->create(['name' => 'HALF PAID VENDOR']);
        $settledVendor = Supplier::factory()->create(['name' => 'FULL PAID VENDOR']);

        // 1. Unpaid credit invoice (0 paid, full due)
        $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Onion', 'line_total' => 20000.00],
        ], null, $unpaidVendor);

        // 2. Partial credit invoice (8000 paid out of 20000)
        $invPartial = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Potato', 'line_total' => 20000.00],
        ], null, $partialVendor);
        $this->createSettlementAllocation($invPartial, 8000.00, '2026-10-15');

        // 3. Fully paid credit invoice
        $invSettled = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Carrot', 'line_total' => 15000.00],
        ], null, $settledVendor);
        $this->createSettlementAllocation($invSettled, 15000.00, '2026-10-15');

        $data = app(PurchaseReportingService::class)->vendorReportData(['period' => 'today', 'start_date' => '2026-10-15', 'end_date' => '2026-10-15']);
        $vendorsById = $data['vendors']->keyBy('supplier_id');

        // Render cards
        $unpaidCard = view('admin.cashbook.finance.purchase._vendor-invoice-card', [
            'vendor' => $vendorsById->get($unpaidVendor->id),
            'serialNumber' => 1,
            'purchaseContext' => ['period' => 'today'],
            'formattedRange' => '15 Oct 2026',
        ])->render();
        $this->assertStringContainsString('UNPAID', $unpaidCard);
        $this->assertStringNotContainsString('<span>PAID</span>', $unpaidCard);

        $partialCard = view('admin.cashbook.finance.purchase._vendor-invoice-card', [
            'vendor' => $vendorsById->get($partialVendor->id),
            'serialNumber' => 2,
            'purchaseContext' => ['period' => 'today'],
            'formattedRange' => '15 Oct 2026',
        ])->render();
        $this->assertStringContainsString('PARTIAL', $partialCard);

        $settledCard = view('admin.cashbook.finance.purchase._vendor-invoice-card', [
            'vendor' => $vendorsById->get($settledVendor->id),
            'serialNumber' => 3,
            'purchaseContext' => ['period' => 'today'],
            'formattedRange' => '15 Oct 2026',
        ])->render();
        $this->assertStringContainsString('PAID', $settledCard);
    }

    public function test_global_pdf_export_hides_detailed_reconciliation_by_default_and_shows_with_include_details(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'RECONCILIATION TEST VENDOR']);
        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Fruits', 'product' => 'Apple', 'line_total' => 5000.00],
        ], null, $vendor);

        // 1. By default: include_details is false -> no Detailed Reconciliation
        $responseDefault = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.export.pdf', [
            'period' => 'today',
        ]));
        $responseDefault->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $data = app(PurchaseReportingService::class)->vendorReportData(['period' => 'today', 'start_date' => '2026-10-15', 'end_date' => '2026-10-15']);
        $pdfHtmlDefault = view('admin.cashbook.finance.purchase.reports.vendors_overview_pdf', array_merge($data, [
            'generatedAt' => now(),
            'purchaserName' => 'All Purchasers',
            'productFilterName' => 'All Products',
            'singleVendor' => null,
            'includeDetails' => false,
        ]))->render();

        $this->assertStringNotContainsString('Underlying Purchase Bills &amp; Invoices &mdash; Detailed Reconciliation', $pdfHtmlDefault);

        // 2. With include_details=1 -> Detailed Reconciliation is included
        $responseDetailed = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.export.pdf', [
            'period' => 'today',
            'include_details' => 1,
        ]));
        $responseDetailed->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $pdfHtmlDetailed = view('admin.cashbook.finance.purchase.reports.vendors_overview_pdf', array_merge($data, [
            'generatedAt' => now(),
            'purchaserName' => 'All Purchasers',
            'productFilterName' => 'All Products',
            'singleVendor' => null,
            'includeDetails' => true,
        ]))->render();

        $this->assertStringContainsString('Underlying Purchase Bills &amp; Invoices &mdash; Detailed Reconciliation', $pdfHtmlDetailed);
    }

    public function test_single_vendor_pdf_export_always_includes_detailed_reconciliation(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'SINGLE VENDOR RECON VENDOR']);
        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Tomato', 'line_total' => 3000.00],
        ], null, $vendor);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.single.export.pdf', [
            'supplier' => $vendor->public_uuid,
            'period' => 'today',
        ]));
        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $data = app(PurchaseReportingService::class)->vendorReportData(['period' => 'today', 'start_date' => '2026-10-15', 'end_date' => '2026-10-15', 'vendor_id' => $vendor->id]);
        $pdfHtml = view('admin.cashbook.finance.purchase.reports.vendors_overview_pdf', array_merge($data, [
            'generatedAt' => now(),
            'purchaserName' => 'All Purchasers',
            'productFilterName' => 'All Products',
            'singleVendor' => $vendor,
            'includeDetails' => true,
        ]))->render();

        $this->assertStringContainsString('Underlying Purchase Bills &amp; Invoices &mdash; Detailed Reconciliation', $pdfHtml);
    }

    public function test_lazy_load_endpoint_returns_only_unsettled_bills_with_bounded_batch_of_five(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'LAZY LOAD BATCH VENDOR']);
        $purchaser = User::factory()->create(['name' => 'Purchaser Ali']);
        $purchaser->assignRole('purchaser');

        // Create 2 fully settled bills
        $settled1 = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Potato', 'line_total' => 5000.00],
        ], $purchaser, $vendor);
        $this->createSettlementAllocation($settled1, 5000.00, '2026-10-15');

        $settled2 = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Onion', 'line_total' => 6000.00],
        ], $purchaser, $vendor);
        $this->createSettlementAllocation($settled2, 6000.00, '2026-10-15');

        // Create 6 outstanding bills (amounts 1000 to 6000)
        for ($i = 1; $i <= 6; $i++) {
            $this->invoiceWithItems('2026-10-15', 'Credit', [
                ['category' => 'Vegetables', 'product' => 'Carrot '.$i, 'line_total' => 1000.00 * $i],
            ], $purchaser, $vendor);
        }

        // Page 1: Request first batch of 5
        $page1Resp = $this->actingAs($this->admin)->getJson(route('admin.cashbook.finance.purchase.vendors.outstanding-bills', [
            'supplier' => $vendor->public_uuid,
            'page' => 1,
            'period' => 'today',
        ]));

        $page1Resp->assertOk()
            ->assertJson([
                'success' => true,
                'page' => 1,
                'per_page' => 5,
                'total_count' => 6,
                'loaded_count' => 5,
                'remaining_count' => 1,
                'has_more' => true,
            ]);

        $billsPage1 = $page1Resp->json('bills');
        $this->assertCount(5, $billsPage1);

        // Verify settled bills are NOT included in the outstanding list
        $returnedInvoiceIds = collect($billsPage1)->pluck('id')->all();
        $this->assertNotContains($settled1->id, $returnedInvoiceIds);
        $this->assertNotContains($settled2->id, $returnedInvoiceIds);

        // Page 2: Request remaining batch
        $page2Resp = $this->actingAs($this->admin)->getJson(route('admin.cashbook.finance.purchase.vendors.outstanding-bills', [
            'supplier' => $vendor->public_uuid,
            'page' => 2,
            'period' => 'today',
        ]));

        $page2Resp->assertOk()
            ->assertJson([
                'success' => true,
                'page' => 2,
                'per_page' => 5,
                'total_count' => 6,
                'loaded_count' => 6,
                'remaining_count' => 0,
                'has_more' => false,
            ]);

        $this->assertCount(1, $page2Resp->json('bills'));
    }

    public function test_discount_can_be_updated_in_single_bill_payment_popup(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'DISCOUNT UPDATE VENDOR']);
        $purchaser = User::factory()->create(['name' => 'Purchaser Zaid']);
        $purchaser->assignRole('purchaser');

        $invoice = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Garlic', 'line_total' => 10000.00],
        ], $purchaser, $supplier); // 10000 gross, 0 discount, 0 paid

        // Update discount to 500 and pay 4000
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.finance.vendor-credit.pay', [
            'invoice' => $invoice->public_uuid,
        ]), [
            'discount_amount' => 500.00,
            'payment_amount' => 4000.00,
            'payment_method' => 'Bank',
            'payment_date' => '2026-10-15',
            'payment_note' => 'Adjusted discount and partial payment',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'invoice' => [
                    'id' => $invoice->id,
                    'discount_amount' => 500.00,
                    'paid_amount' => 4000.00,
                    'outstanding' => 5500.00,
                ],
            ]);

        $invoice->refresh();
        $this->assertEquals(500.00, (float) $invoice->discount_amount);
        $this->assertEquals(4000.00, (float) $invoice->paid_amount);
    }

    public function test_discount_cannot_reduce_net_payable_below_already_settled_amount(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'EXCESS DISCOUNT VENDOR']);
        $purchaser = User::factory()->create(['name' => 'Purchaser Tariq']);
        $purchaser->assignRole('purchaser');

        $invoice = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Ginger', 'line_total' => 10000.00],
        ], $purchaser, $supplier);

        // Pre-settle 6,000 on this bill
        $this->createSettlementAllocation($invoice, 6000.00, '2026-10-15');

        // Attempting to set discount to 5,000 would make Net Payable 5,000, which is less than 6,000 already settled
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.finance.vendor-credit.pay', [
            'invoice' => $invoice->public_uuid,
        ]), [
            'discount_amount' => 5000.00,
            'payment_amount' => 0.00,
            'payment_method' => 'Bank',
            'payment_date' => '2026-10-15',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discount_amount']);
    }

    public function test_overpayment_exceeding_outstanding_balance_is_rejected(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'OVERPAYMENT REJECT VENDOR']);
        $purchaser = User::factory()->create(['name' => 'Purchaser Fahad']);
        $purchaser->assignRole('purchaser');

        $invoice = $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Cucumber', 'line_total' => 8000.00],
        ], $purchaser, $supplier); // 8000 balance

        // Attempt to post payment of 10,000 (exceeds balance by 2,000)
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.finance.vendor-credit.pay', [
            'invoice' => $invoice->public_uuid,
        ]), [
            'payment_amount' => 10000.00,
            'payment_method' => 'Bank',
            'payment_date' => '2026-10-15',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_amount']);
    }

    private function createSettlementAllocation(
        PurchaseInvoice $invoice,
        float $allocatedAmount,
        string $paymentDate = '2026-10-15',
        string $status = 'approved',
        float $discountAllocated = 0.0
    ): VendorSettlementAllocation {
        $companyAccount = CompanyAccount::query()->create([
            'name' => 'Main Operating Account',
            'account_number' => '9876543210',
            'bank_name' => 'HDFC',
            'account_type' => 'bank',
            'enabled' => true,
        ]);

        $settlement = VendorSettlement::query()->create([
            'supplier_id' => $invoice->supplier_id,
            'actual_payment_amount' => $allocatedAmount,
            'settlement_discount_amount' => $discountAllocated,
            'vendor_advance_used_amount' => 0,
            'new_vendor_advance_amount' => 0,
            'company_account_id' => $companyAccount->id,
            'payment_method' => 'Bank',
            'payment_date' => $paymentDate,
            'reference' => 'SETTLE-REF-'.fake()->numerify('####'),
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);

        return VendorSettlementAllocation::query()->create([
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $invoice->id,
            'cash_allocated' => $allocatedAmount,
            'advance_allocated' => 0,
            'discount_allocated' => $discountAllocated,
            'total_settled' => $allocatedAmount + $discountAllocated,
            'is_reversed' => false,
        ]);
    }
}
