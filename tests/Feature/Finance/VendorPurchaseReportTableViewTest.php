<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Exports\VendorPurchaseReportExport;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaseReportingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class VendorPurchaseReportTableViewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-02 12:00:00');

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

    public function test_vendor_purchase_report_loads_with_view_switcher_and_table_view_as_default_with_pagination_20(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'BARATH']);
        $purchaser = User::factory()->create(['id' => 6, 'name' => 'Test Purchaser']);
        $purchaser->assignRole('purchaser');

        $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Cucumber', 'line_total' => 72.00],
        ], $purchaser, $vendor);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'purchaser_id' => 6,
            'period' => 'prev_month',
            'period_mode' => 'month',
            'month' => '2026-10',
        ]));

        $response->assertOk();

        // 1. View switcher exists with both Table View and Card View
        $response->assertSee('Table View');
        $response->assertSee('Card View');
        $response->assertSee('aria-label="View Switcher"', false);

        // 2. Default viewMode is table
        $response->assertSee('viewMode: (function()', false);
        $response->assertSee("localStorage.getItem('gl_vendor_overview_view') || 'table'", false);
        $response->assertSee("x-show=\"viewMode === 'table'\"", false);
        $response->assertSee("x-show=\"viewMode === 'card'\"", false);

        // 3. Pagination limit of 20
        $response->assertSee('limit: 20', false);
        $response->assertSee('step: 20', false);

        // 4. Card View is preserved with x-cloak
        $response->assertSee('x-ref="vendorList"', false);
    }

    public function test_table_view_has_sl_no_as_first_column_with_required_headings(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'TEST VENDOR']);
        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Tomato', 'line_total' => 100.00],
        ], null, $vendor);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'prev_month',
            'month' => '2026-10',
        ]));

        $response->assertOk();

        // Verify SL No as first column followed by required headings
        $content = $response->getContent();
        $this->assertStringContainsString('<th scope="col" class="py-3 px-4 text-center w-12">SL No</th>', $content);
        $this->assertStringContainsString('<th scope="col" class="py-3 px-4 text-left">Name</th>', $content);
        $this->assertStringContainsString('<th scope="col" class="py-3 px-4 text-right">Amount</th>', $content);
        $this->assertStringContainsString('<th scope="col" class="py-3 px-4 text-right">Paid</th>', $content);
        $this->assertStringContainsString('<th scope="col" class="py-3 px-4 text-right">Balance</th>', $content);
        $this->assertStringContainsString('<th scope="col" class="py-3 px-4 text-center">Action</th>', $content);
        $this->assertStringNotContainsString('<th scope="col" class="py-3 px-4 text-center">Export</th>', $content);
    }

    public function test_table_view_displays_vendor_barath_with_sl_no_exact_amounts_and_links(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'BARATH']);
        $purchaser = User::factory()->create(['id' => 6, 'name' => 'Purchaser 6']);
        $purchaser->assignRole('purchaser');

        // Unpaid credit purchase: ₹72.00 total, ₹0.00 paid, ₹72.00 balance
        $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Cucumber', 'line_total' => 72.00],
        ], $purchaser, $vendor);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'purchaser_id' => 6,
            'period' => 'prev_month',
            'period_mode' => 'month',
            'month' => '2026-10',
        ]));

        $response->assertOk();

        $content = $response->getContent();

        // Check BARATH row with SL No
        $this->assertStringContainsString('class="table-sl-no"', $content);
        $this->assertStringContainsString('BARATH', $content);
        $this->assertStringContainsString('₹72.00', $content);
        $this->assertStringContainsString('₹0.00', $content);

        // Check View action button (eye icon only in action column)
        $expectedVendorUrl = route('admin.cashbook.finance.purchase.vendors.show', [
            'supplier' => $vendor->public_uuid,
            'period' => 'prev_month',
            'month' => '2026-10',
            'period_mode' => 'month',
            'purchaser_id' => 6,
        ]);
        $this->assertStringContainsString(e($expectedVendorUrl), $content);
        $this->assertStringContainsString('title="View Vendor Details for BARATH"', $content);
        $this->assertStringContainsString('aria-label="View Vendor Details for BARATH"', $content);
        $this->assertStringContainsString('data-lucide="eye"', $content);

        // Check single vendor PDF and Excel export links
        $expectedPdfUrl = route('admin.cashbook.finance.purchase.vendors.single.export.pdf', [
            'supplier' => $vendor->public_uuid,
            'period' => 'prev_month',
            'month' => '2026-10',
            'period_mode' => 'month',
            'purchaser_id' => 6,
        ]);
        $expectedExcelUrl = route('admin.cashbook.finance.purchase.vendors.single.export.excel', [
            'supplier' => $vendor->public_uuid,
            'period' => 'prev_month',
            'month' => '2026-10',
            'period_mode' => 'month',
            'purchaser_id' => 6,
        ]);
        $this->assertStringContainsString(e($expectedPdfUrl), $content);
        $this->assertStringContainsString(e($expectedExcelUrl), $content);

        // Check export icons only (no text in export buttons)
        $this->assertStringContainsString('title="Export BARATH Bill Report to PDF"', $content);
        $this->assertStringContainsString('title="Export BARATH Report to Excel (.xlsx)"', $content);

        // Check clickable balance amount and Update Payment icon appear in Balance column because credit balance is pending (₹72.00 > 0)
        $expectedVendorCreditUrl = route('admin.cashbook.finance.vendor-credit.show', $vendor->public_uuid);
        $this->assertStringContainsString($expectedVendorCreditUrl, $content);
        $this->assertStringContainsString('title="Click to update payment for BARATH (Balance: ₹72.00)"', $content);
        $this->assertStringContainsString('title="Update Payment for BARATH"', $content);
    }

    public function test_table_balance_column_shows_clickable_amount_and_update_payment_icon_when_balance_pending_and_omits_for_zero_balance(): void
    {
        $creditVendor = Supplier::factory()->create(['name' => 'PENDING CREDIT VENDOR']);
        $cashVendor = Supplier::factory()->create(['name' => 'ZERO BALANCE VENDOR']);

        // 1. Credit vendor with outstanding balance
        $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Cucumber', 'line_total' => 250.00],
        ], null, $creditVendor);

        // 2. Cash vendor with 0 outstanding balance
        $this->invoiceWithItems('2026-10-16', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Tomato', 'line_total' => 400.00],
        ], null, $cashVendor);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'prev_month',
            'month' => '2026-10',
        ]));

        $response->assertOk();
        $content = $response->getContent();

        // Credit vendor row: Action column has "View" (eye icon) and exports
        $this->assertStringContainsString('title="View Vendor Details for PENDING CREDIT VENDOR"', $content);
        $this->assertStringContainsString('title="Export PENDING CREDIT VENDOR Bill Report to PDF"', $content);
        $this->assertStringContainsString('title="Export PENDING CREDIT VENDOR Report to Excel (.xlsx)"', $content);

        // Credit vendor row: Balance column has clickable amount badge and update payment icon
        $expectedCreditSettlementUrl = route('admin.cashbook.finance.vendor-credit.show', $creditVendor->public_uuid);
        $this->assertStringContainsString($expectedCreditSettlementUrl, $content);
        $this->assertStringContainsString('title="Click to update payment for PENDING CREDIT VENDOR (Balance: ₹250.00)"', $content);
        $this->assertStringContainsString('title="Update Payment for PENDING CREDIT VENDOR"', $content);
        $this->assertStringContainsString('openVendorPayment(\''.$creditVendor->public_uuid.'\'', $content);

        // Cash vendor row: Action column has "View" (eye icon) and exports
        $this->assertStringContainsString('title="View Vendor Details for ZERO BALANCE VENDOR"', $content);
        $this->assertStringContainsString('title="Export ZERO BALANCE VENDOR Bill Report to PDF"', $content);
        $this->assertStringContainsString('title="Export ZERO BALANCE VENDOR Report to Excel (.xlsx)"', $content);

        // Cash vendor row: Balance column does NOT have clickable link or update payment icon
        $unexpectedCashSettlementUrl = route('admin.cashbook.finance.vendor-credit.show', $cashVendor->public_uuid);
        $this->assertStringNotContainsString($unexpectedCashSettlementUrl, $content);
        $this->assertStringNotContainsString('title="Click to update payment for ZERO BALANCE VENDOR', $content);
        $this->assertStringNotContainsString('title="Update Payment for ZERO BALANCE VENDOR"', $content);
    }

    public function test_recent_purchases_bills_table_has_sl_no_as_first_column(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'BILLS VENDOR']);
        $purchaser = User::factory()->create(['name' => 'Bills Purchaser']);
        $purchaser->assignRole('purchaser');

        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Potato', 'line_total' => 250.00],
        ], $purchaser, $vendor);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'prev_month',
            'month' => '2026-10',
        ]));

        $response->assertOk();

        $content = $response->getContent();

        // Check that Recent Purchases table has SL No as first column header
        $this->assertStringContainsString('<span>Recent Purchases &bull; Bills &amp; Transactions</span>', $content);
        $this->assertStringContainsString('<th class="p-3.5 text-center w-12 font-black uppercase text-[10px] tracking-wider text-slate-600 select-none">SL No</th>', $content);
        $this->assertStringContainsString('<td colspan="9"', $content);
    }

    public function test_excel_export_matches_new_default_table_columns_and_values(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'EXCEL DEFAULT VENDOR']);
        $this->invoiceWithItems('2026-10-15', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Carrot', 'line_total' => 8500.00],
        ], null, $vendor);

        $service = app(PurchaseReportingService::class);
        $data = $service->vendorReportData(['period' => 'prev_month', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']);
        $export = new VendorPurchaseReportExport($data);
        $sheets = $export->sheets();

        $summarySheet = $sheets[0];
        $rows = $summarySheet->array();

        // Row index 5 is the header row
        $headers = $rows[5];
        $this->assertSame(['SL No', 'Name', 'Amount', 'Paid', 'Balance'], $headers);

        // Find the vendor row
        $vendorRow = collect($rows)->firstWhere(1, 'EXCEL DEFAULT VENDOR');
        $this->assertNotNull($vendorRow);
        $this->assertIsInt($vendorRow[0]); // SL No
        $this->assertEquals('EXCEL DEFAULT VENDOR', $vendorRow[1]); // Name
        $this->assertEquals(8500.00, $vendorRow[2]); // Amount
        $this->assertEquals(0.00, $vendorRow[3]); // Paid
        $this->assertEquals(8500.00, $vendorRow[4]); // Balance
    }

    public function test_table_view_and_card_view_share_identical_financial_calculations(): void
    {
        $cashVendor = Supplier::factory()->create(['name' => 'CASH VENDOR']);
        $creditVendor = Supplier::factory()->create(['name' => 'CREDIT VENDOR']);

        // Cash purchase 500.00 -> Amount: 500, Paid: 500, Balance: 0
        $this->invoiceWithItems('2026-10-10', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Potato', 'line_total' => 500.00],
        ], null, $cashVendor);

        // Credit purchase 1200.00 -> Amount: 1200, Paid: 0, Balance: 1200
        $this->invoiceWithItems('2026-10-12', 'Credit', [
            ['category' => 'Vegetables', 'product' => 'Carrot', 'line_total' => 1200.00],
        ], null, $creditVendor);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period' => 'prev_month',
            'month' => '2026-10',
        ]));

        $response->assertOk();
        $content = $response->getContent();

        // Both views present in the page
        // Table view amounts
        $this->assertStringContainsString('₹500.00', $content);
        $this->assertStringContainsString('₹1,200.00', $content);

        // Card view amounts
        $this->assertStringContainsString('Rs. 500.00', $content);
        $this->assertStringContainsString('Rs. 1,200.00', $content);
    }

    public function test_single_vendor_pdf_export_and_excel_export_routes_respond_successfully(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'EXPORT TARGET VENDOR']);
        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Spinach', 'line_total' => 300.00],
        ], null, $vendor);

        $pdfResp = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.single.export.pdf', [
            'supplier' => $vendor->public_uuid,
            'period' => 'prev_month',
            'month' => '2026-10',
        ]));
        $pdfResp->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResp->headers->get('Content-Type'));

        $excelResp = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.single.export.excel', [
            'supplier' => $vendor->public_uuid,
            'period' => 'prev_month',
            'month' => '2026-10',
        ]));
        $excelResp->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $excelResp->headers->get('Content-Type'));
    }

    public function test_all_vendor_exports_remain_functional(): void
    {
        $vendor = Supplier::factory()->create(['name' => 'ALL VENDOR TARGET']);
        $this->invoiceWithItems('2026-10-15', 'Cash', [
            ['category' => 'Vegetables', 'product' => 'Beans', 'line_total' => 450.00],
        ], null, $vendor);

        $pdfResp = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.export.pdf', [
            'period' => 'prev_month',
            'month' => '2026-10',
        ]));
        $pdfResp->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResp->headers->get('Content-Type'));

        $excelResp = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.vendors.export.excel', [
            'period' => 'prev_month',
            'month' => '2026-10',
        ]));
        $excelResp->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $excelResp->headers->get('Content-Type'));
    }

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
}
