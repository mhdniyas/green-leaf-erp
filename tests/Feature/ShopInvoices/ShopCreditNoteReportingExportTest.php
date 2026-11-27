<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteLineType;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\ShopInvoices\ShopCreditApplicationService;
use App\Services\ShopInvoices\ShopCreditNoteReportService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use Carbon\Carbon;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class ShopCreditNoteReportingExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountsUser;

    private User $unauthorizedUser;

    private Shop $shopA;

    private Shop $shopB;

    private Warehouse $warehouse;

    private Product $productOnion;

    private ShopCreditNoteService $creditNoteService;

    private ShopCreditApplicationService $applicationService;

    private InvoiceCashbookProjectionService $projectionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        config(['admin.user_access.main_admin_email' => 'admin@example.com']);

        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->admin->assignRole('admin');

        $this->accountsUser = User::factory()->create(['name' => 'Accounts Officer']);
        $this->accountsUser->assignRole('accounts');

        $this->unauthorizedUser = User::factory()->create(['name' => 'Purchaser']);
        $this->unauthorizedUser->assignRole('purchaser');

        $this->warehouse = Warehouse::factory()->create([
            'code' => 'WH-MAIN',
            'name' => 'Main Warehouse',
            'is_active' => true,
        ]);

        $this->shopA = Shop::factory()->create([
            'code' => 'SHP-A',
            'name' => 'Kochi Central',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->shopB = Shop::factory()->create([
            'code' => 'SHP-B',
            'name' => 'Calicut Mart',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->creditNoteService = app(ShopCreditNoteService::class);
        $this->applicationService = app(ShopCreditApplicationService::class);
        $this->projectionService = app(InvoiceCashbookProjectionService::class);

        $this->productOnion = Product::factory()->create([
            'name' => 'Big Onion',
            'sku' => 'PROD-ONN-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
    }

    private function createInvoice(Shop $shop, string $number, string $date, float $total): ShopInvoice
    {
        static $seq = 1;
        $num = $seq++;

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'shop_daily_order_key' => "shop:{$shop->id}:{$date}:{$num}",
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $number,
            'business_date' => $date,
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'final_total' => $total,
            'paid_amount' => 0.00,
            'balance_amount' => $total,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now(),
        ]);

        $this->projectionService->syncInvoice($invoice);

        return $invoice;
    }

    private function createInvoiceItem(ShopInvoice $invoice, Product $product, float $qty, float $price): ShopInvoiceItem
    {
        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $invoice->shop_order_id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => $qty,
            'approved_qty' => $qty,
            'loaded_qty' => $qty,
            'delivered_qty' => $qty,
            'unit' => 'kg',
            'unit_cost' => 40.00,
        ]);

        return ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_grade' => 'A',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => $qty,
            'price_quantity' => $qty,
            'delivered_qty' => $qty,
            'delivered_price_quantity' => $qty,
            'unit_price' => $price,
            'line_subtotal' => round($qty * $price, 2),
            'final_line_total' => round($qty * $price, 2),
        ]);
    }

    public function test_unauthorized_users_cannot_access_report_or_exports(): void
    {
        $this->get(route('admin.cashbook.reports.shops.credit-notes.report'))->assertRedirect();

        $this->actingAs($this->unauthorizedUser);
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report'))->assertForbidden();
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.pdf'))->assertForbidden();
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report.export.summary.pdf'))->assertForbidden();
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.excel'))->assertForbidden();
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report.export.summary.excel'))->assertForbidden();
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.csv'))->assertForbidden();
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report.export.summary.csv'))->assertForbidden();
    }

    public function test_report_reconciles_across_all_formats_and_handles_edge_cases(): void
    {
        $day1 = today()->subDays(2)->toDateString();
        $day2 = today()->subDay()->toDateString(); // Zero-credit date
        $day3 = today()->toDateString();
        $olderInvoiceDate = today()->subDays(15)->toDateString();

        // Older invoice for Shop A
        $olderInvoice = $this->createInvoice($this->shopA, 'SINV-OLD-001', $olderInvoiceDate, 8573.70);
        $olderItem = $this->createInvoiceItem($olderInvoice, $this->productOnion, 100, 85.74);

        // Current invoice for Shop B
        $shopBInvoice = $this->createInvoice($this->shopB, 'SINV-CUR-002', $day3, 5000.00);

        // 1. Price correction credit note on Day 1 against OLDER invoice (₹200.00)
        $cn1 = ShopCreditNote::factory()->create([
            'credit_note_number' => 'SCN-TEST-001',
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $olderInvoice->id,
            'credit_note_date' => $day1,
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'status' => ShopCreditNoteStatus::Approved,
            'total_credit_amount' => 200.00,
            'subtotal' => 200.00,
            'notes' => '=CMD|calc!A0 Price correction note', // Formula injection test
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
        ShopCreditNoteItem::query()->create([
            'shop_credit_note_id' => $cn1->id,
            'line_type' => ShopCreditNoteLineType::ProductAdjustment,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'shop_invoice_item_id' => $olderItem->id,
            'source_shop_invoice_id' => $olderInvoice->id,
            'product_id' => $this->productOnion->id,
            'product_name' => $this->productOnion->name,
            'unit' => 'kg',
            'original_quantity' => 100,
            'original_unit_price' => 85.74,
            'original_line_total' => 8574.00,
            'adjusted_quantity' => 100,
            'adjusted_unit_price' => 83.74,
            'credit_rate' => 2.00,
            'line_credit_amount' => 200.00,
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
        ]);

        // 2. Second credit note against SAME older invoice on Day 3 (₹300.00, direct monetary)
        $cn2 = ShopCreditNote::factory()->create([
            'credit_note_number' => 'SCN-TEST-002',
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $olderInvoice->id,
            'credit_note_date' => $day3,
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::CommercialDiscount,
            'status' => ShopCreditNoteStatus::Approved,
            'total_credit_amount' => 300.00,
            'subtotal' => 300.00,
            'notes' => 'Goodwill commercial discount',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // 3. Shop B credit note on Day 3 (₹450.00)
        $cn3 = ShopCreditNote::factory()->create([
            'credit_note_number' => 'SCN-TEST-003',
            'shop_id' => $this->shopB->id,
            'shop_invoice_id' => $shopBInvoice->id,
            'credit_note_date' => $day3,
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::Goodwill,
            'status' => ShopCreditNoteStatus::Approved,
            'total_credit_amount' => 450.00,
            'subtotal' => 450.00,
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // 4. Draft, Voided, and Debit note — MUST BE EXCLUDED from credit note totals!
        ShopCreditNote::factory()->create([
            'credit_note_number' => 'SCN-DRAFT-999',
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $olderInvoice->id,
            'credit_note_date' => $day3,
            'direction' => ShopCreditNoteDirection::Credit,
            'status' => ShopCreditNoteStatus::Draft,
            'total_credit_amount' => 999.00,
        ]);
        ShopCreditNote::factory()->create([
            'credit_note_number' => 'SCN-VOID-888',
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $olderInvoice->id,
            'credit_note_date' => $day3,
            'direction' => ShopCreditNoteDirection::Credit,
            'status' => ShopCreditNoteStatus::Voided,
            'total_credit_amount' => 888.00,
        ]);
        // Debit note on older invoice (₹100.00) -> excluded from CN total, included in Bill Reconciliation!
        ShopCreditNote::factory()->create([
            'credit_note_number' => 'SDN-DEBIT-100',
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $olderInvoice->id,
            'credit_note_date' => $day3,
            'direction' => ShopCreditNoteDirection::Debit,
            'status' => ShopCreditNoteStatus::Approved,
            'total_credit_amount' => 100.00,
        ]);

        // Expected authoritative totals: 200 + 300 + 450 = ₹950.00, 3 CNs, 2 Shops, 2 Invoices
        $this->actingAs($this->admin);

        $params = [
            'timeframe' => 'custom',
            'from_date' => $day1,
            'to_date' => $day3,
        ];

        // A. On-screen Detailed & Summary pages
        $detailedResp = $this->get(route('admin.cashbook.reports.shops.credit-notes.report', $params));
        $detailedResp->assertOk();
        $detailedResp->assertSee('950.00');
        $detailedResp->assertSee('SCN-TEST-001');
        $detailedResp->assertSee('SCN-TEST-002');
        $detailedResp->assertSee('SCN-TEST-003');
        $detailedResp->assertSee('PROD-ONN-01');
        $detailedResp->assertDontSee('SCN-DRAFT-999');
        $detailedResp->assertDontSee('SCN-VOID-888');
        // Older invoice reconciliation: 8573.70 - 500.00 + 100.00 = 8173.70
        $detailedResp->assertSee('8,173.70');

        $summaryResp = $this->get(route('admin.cashbook.reports.shops.credit-notes.report', array_merge($params, ['report_type' => 'summary'])));
        $summaryResp->assertOk();
        $summaryResp->assertSee('950.00');
        // Zero-credit date included in Daily Summary
        $summaryResp->assertSee(Carbon::parse($day2)->format('d M Y'));

        // B. Single-shop filter
        $shopAResp = $this->get(route('admin.cashbook.reports.shops.credit-notes.report', array_merge($params, ['shop_id' => $this->shopA->id])));
        $shopAResp->assertOk();
        $shopAResp->assertSee('500.00');
        $shopAResp->assertDontSee('SCN-TEST-003');

        // C. Detailed & Summary PDFs
        $detailedPdf = $this->get(route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.pdf', $params));
        $detailedPdf->assertOk();
        $this->assertStringStartsWith('%PDF-', $detailedPdf->getContent());

        $summaryPdf = $this->get(route('admin.cashbook.reports.shops.credit-notes.report.export.summary.pdf', $params));
        $summaryPdf->assertOk();
        $this->assertStringStartsWith('%PDF-', $summaryPdf->getContent());

        // D. Detailed Excel (7 sheets) & formula injection check
        $detailedExcel = $this->get(route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.excel', $params));
        $detailedExcel->assertOk();
        $xlsxPath = $detailedExcel->getFile()->getPathname();
        $spreadsheet = IOFactory::load($xlsxPath);
        $this->assertSame([
            'Overview',
            'Credit Note Register',
            'Invoice Reconciliation',
            'Product Adjustments',
            'Daily Summary',
            'Shop-wise Summary',
            'Daily Shop-wise',
        ], $spreadsheet->getSheetNames());

        $registerSheet = $spreadsheet->getSheetByName('Credit Note Register');
        $this->assertSame("'=CMD|calc!A0 Price correction note", $registerSheet->getCell('G2')->getValue());
        $this->assertSame('=SUM(J2:J4)', $registerSheet->getCell('J5')->getValue());

        // E. Summary Excel (4 sheets)
        $summaryExcel = $this->get(route('admin.cashbook.reports.shops.credit-notes.report.export.summary.excel', $params));
        $summaryExcel->assertOk();
        $summarySpreadsheet = IOFactory::load($summaryExcel->getFile()->getPathname());
        $this->assertSame([
            'Overview',
            'Daily Summary',
            'Shop-wise Summary',
            'Daily Shop-wise Summary',
        ], $summarySpreadsheet->getSheetNames());

        // F. CSV Exports (UTF-8 BOM, plain decimals, formula injection sanitization, ZIP bundle)
        $registerCsv = $this->get(route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.csv', $params));
        $registerCsv->assertOk();
        $csvContent = $registerCsv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvContent);
        $this->assertStringContainsString("'=CMD|calc!A0 Price correction note", $csvContent);
        $this->assertStringContainsString('200.00', $csvContent);
        $this->assertStringContainsString('300.00', $csvContent);
        $this->assertStringContainsString('450.00', $csvContent);

        $summaryCsvBundle = $this->get(route('admin.cashbook.reports.shops.credit-notes.report.export.summary.csv', array_merge($params, ['dataset' => 'bundle'])));
        $summaryCsvBundle->assertOk();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($summaryCsvBundle->getFile()->getPathname()) === true);
        $this->assertSame(3, $zip->numFiles);
        $zip->close();

        // G. Reconcile against authoritative AdminShopReportsController query
        $authoritativeTotal = round((float) ShopCreditNote::query()
            ->whereDate('credit_note_date', '>=', $day1)
            ->whereDate('credit_note_date', '<=', $day3)
            ->where('status', ShopCreditNoteStatus::Approved->value)
            ->where('direction', ShopCreditNoteDirection::Credit->value)
            ->sum('total_credit_amount'), 2);

        $service = app(ShopCreditNoteReportService::class);
        $filters = $service->resolveFilters($params, $this->accountsUser);
        $summaryData = $service->buildSummaryReport($filters);
        $detailedData = $service->buildDetailedReport($filters);

        $this->assertSame($authoritativeTotal, $summaryData['overview']['total_credit_amount']);
        $this->assertSame($authoritativeTotal, $detailedData['overview']['total_credit_amount']);
        $this->assertSame($authoritativeTotal, round((float) collect($detailedData['register_rows'])->sum('credit_amount'), 2));
        $this->assertSame($authoritativeTotal, round((float) collect($summaryData['daily_summary'])->sum('credit_amount'), 2));
        $this->assertSame($authoritativeTotal, round((float) collect($summaryData['shop_summary'])->sum('credit_amount'), 2));
        $this->assertSame($authoritativeTotal, round((float) collect($summaryData['daily_shop_rows'])->sum('credit_amount'), 2));
    }

    public function test_benchmark_summary_and_detailed_query_counts_and_performance(): void
    {
        $day = today()->toDateString();
        $inv = $this->createInvoice($this->shopA, 'SINV-BENCH-01', $day, 10000.00);
        $item = $this->createInvoiceItem($inv, $this->productOnion, 100, 100.00);

        for ($i = 1; $i <= 25; $i++) {
            $cn = ShopCreditNote::factory()->create([
                'credit_note_number' => "SCN-BENCH-{$i}",
                'shop_id' => $this->shopA->id,
                'shop_invoice_id' => $inv->id,
                'credit_note_date' => $day,
                'direction' => ShopCreditNoteDirection::Credit,
                'status' => ShopCreditNoteStatus::Approved,
                'total_credit_amount' => 10.00,
                'created_by' => $this->admin->id,
                'approved_by' => $this->admin->id,
                'approved_at' => now(),
            ]);
            ShopCreditNoteItem::query()->create([
                'shop_credit_note_id' => $cn->id,
                'line_type' => ShopCreditNoteLineType::ProductAdjustment,
                'shop_invoice_item_id' => $item->id,
                'source_shop_invoice_id' => $inv->id,
                'product_id' => $this->productOnion->id,
                'product_name' => $this->productOnion->name,
                'adjusted_quantity' => 1,
                'original_unit_price' => 100.00,
                'adjusted_unit_price' => 90.00,
                'credit_rate' => 10.00,
                'line_credit_amount' => 10.00,
                'direction' => ShopCreditNoteDirection::Credit,
                'inventory_action' => ShopCreditNoteInventoryAction::None,
            ]);
        }

        $service = app(ShopCreditNoteReportService::class);
        $filters = $service->resolveFilters(['timeframe' => 'today'], $this->admin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $summary = $service->buildSummaryReport($filters);
        $summaryQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $detailed = $service->buildDetailedReport($filters);
        $detailedQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(250.00, $summary['overview']['total_credit_amount']);
        $this->assertSame(250.00, $detailed['overview']['total_credit_amount']);
        // No N+1 queries: summary <= 5 queries, detailed <= 18 queries regardless of 25+ credit notes
        $this->assertLessThanOrEqual(5, $summaryQueries);
        $this->assertLessThanOrEqual(18, $detailedQueries);
    }

    public function test_shop_level_authorization_scopes_shop_and_custom_roles_strictly(): void
    {
        $day = today()->toDateString();
        $invA = $this->createInvoice($this->shopA, 'SINV-AUTH-A', $day, 1000.00);
        $invB = $this->createInvoice($this->shopB, 'SINV-AUTH-B', $day, 1000.00);

        ShopCreditNote::factory()->create([
            'credit_note_number' => 'SCN-AUTH-A',
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $invA->id,
            'credit_note_date' => $day,
            'direction' => ShopCreditNoteDirection::Credit,
            'status' => ShopCreditNoteStatus::Approved,
            'total_credit_amount' => 111.00,
        ]);
        ShopCreditNote::factory()->create([
            'credit_note_number' => 'SCN-AUTH-B',
            'shop_id' => $this->shopB->id,
            'shop_invoice_id' => $invB->id,
            'credit_note_date' => $day,
            'direction' => ShopCreditNoteDirection::Credit,
            'status' => ShopCreditNoteStatus::Approved,
            'total_credit_amount' => 222.00,
        ]);

        // 1. Shop-scoped custom/manager role assigned ONLY to Shop A
        $managerRole = Role::firstOrCreate(['name' => 'shop_manager_custom', 'guard_name' => 'web']);
        $managerRole->givePermissionTo(['cashbook.access', 'cashbook.reports.view']);

        $scopedManager = User::factory()->create(['shop_id' => $this->shopA->id]);
        $scopedManager->assignRole($managerRole);

        $this->actingAs($scopedManager);
        $resp = $this->get(route('admin.cashbook.reports.shops.credit-notes.report'));
        $resp->assertOk();
        $resp->assertSee('SCN-AUTH-A');
        $resp->assertDontSee('SCN-AUTH-B');

        // Attempting to access Shop B via URL parameter is rejected across page & all 6 exports
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report', ['shop_id' => $this->shopB->id]))->assertForbidden();
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.pdf', ['shop_id' => $this->shopB->id]))->assertForbidden();
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report.export.summary.pdf', ['shop_id' => $this->shopB->id]))->assertForbidden();
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.excel', ['shop_id' => $this->shopB->id]))->assertForbidden();
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report.export.summary.excel', ['shop_id' => $this->shopB->id]))->assertForbidden();
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.csv', ['shop_id' => $this->shopB->id]))->assertForbidden();
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report.export.summary.csv', ['shop_id' => $this->shopB->id]))->assertForbidden();

        // 2. Custom/manager user with report permission but NO assigned shops gets 0 access (not unrestricted!)
        $unassignedManager = User::factory()->create(['shop_id' => null]);
        $unassignedManager->assignRole($managerRole);

        $this->actingAs($unassignedManager);
        $emptyResp = $this->get(route('admin.cashbook.reports.shops.credit-notes.report'));
        $emptyResp->assertOk();
        $emptyResp->assertDontSee('SCN-AUTH-A');
        $emptyResp->assertDontSee('SCN-AUTH-B');
        $this->getJson(route('admin.cashbook.reports.shops.credit-notes.report', ['shop_id' => $this->shopA->id]))->assertForbidden();
    }

    public function test_shop_level_applications_partial_and_full_reversals_and_empty_periods(): void
    {
        $day = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();
        $inv1 = $this->createInvoice($this->shopA, 'SINV-APP-1', $day, 2000.00);
        $inv2 = $this->createInvoice($this->shopA, 'SINV-APP-2', $yesterday, 2000.00);
        $item1 = $this->createInvoiceItem($inv1, $this->productOnion, 40, 50.00);

        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => $day,
            'notes' => 'Multi-invoice shop-level credit note',
            'items' => [[
                'source_shop_invoice_id' => $inv1->id,
                'shop_invoice_item_id' => $item1->id,
                'product_id' => $this->productOnion->id,
                'product_grade' => 'A',
                'unit' => 'kg',
                'credit_quantity' => 10.0,
                'credit_rate' => 50.0,
                'line_credit_amount' => 500.00,
                'inventory_action' => ShopCreditNoteInventoryAction::None,
                'reason_type' => ShopCreditNoteReason::CommercialDiscount,
            ]],
        ], $this->admin);
        $cn = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        // Apply ₹200 to inv1 and ₹300 to inv2
        $app1 = $this->applicationService->applyCredit($cn, $inv1, 200.00, $this->admin);
        $app2 = $this->applicationService->applyCredit($cn, $inv2, 300.00, $this->admin);

        $service = app(ShopCreditNoteReportService::class);
        $filters = $service->resolveFilters(['timeframe' => 'today'], $this->admin);
        $detailed = $service->buildDetailedReport($filters);

        $this->assertSame(1, $detailed['overview']['total_credit_notes']);
        $this->assertSame(500.00, $detailed['overview']['total_credit_amount']);
        $this->assertSame(2, $detailed['overview']['total_affected_invoices']);

        // Reverse app2 (₹300 on inv2) -> inv2 must no longer be counted as affected!
        $this->applicationService->reverseCreditApplication($app2, $this->admin, 'Test reversal');
        $detailedAfterPartial = $service->buildDetailedReport($filters);
        $this->assertSame(1, $detailedAfterPartial['overview']['total_affected_invoices']);
        $this->assertSame(500.00, $detailedAfterPartial['overview']['total_credit_amount']);

        // Reverse app1 too -> 0 affected invoices, inv1/inv2 Effective Net Bill = 2000.00
        $this->applicationService->reverseCreditApplication($app1, $this->admin, 'Full reversal');
        $detailedAfterFull = $service->buildDetailedReport($filters);
        $this->assertSame(0, $detailedAfterFull['overview']['total_affected_invoices']);
        $this->assertSame(2000.00, $inv1->fresh()->finalBill()->finalNetPayable);
        $this->assertSame(2000.00, $inv2->fresh()->finalBill()->finalNetPayable);

        // Empty reporting period + invalid date range check
        $this->actingAs($this->admin);
        $emptyPeriod = $this->get(route('admin.cashbook.reports.shops.credit-notes.report', [
            'timeframe' => 'custom',
            'from_date' => '2020-01-01',
            'to_date' => '2020-01-02',
        ]));
        $emptyPeriod->assertOk();
        $emptyPeriod->assertSee('No Approved Credit Notes Found');

        $invalidRange = $this->get(route('admin.cashbook.reports.shops.credit-notes.report', [
            'timeframe' => 'custom',
            'from_date' => '2026-11-10',
            'to_date' => '2026-11-01',
        ]));
        $invalidRange->assertSessionHasErrors('to_date');
    }
}
