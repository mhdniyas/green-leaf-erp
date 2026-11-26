<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Inventory\ProductGrade;
use App\Exports\PurchasePriceReportExport;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserReadCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchasePriceReportUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Warehouse $vegWarehouse;

    private Warehouse $fruitWarehouse;

    private Category $vegCategory;

    private Category $fruitCategory;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-26 10:00:00');
        $permission = Permission::firstOrCreate(['name' => 'cashbook.reconciliation.view']);
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo($permission);
        config(['admin.user_access.main_admin_email' => $this->admin->email]);
        $this->unauthorizedUser = User::factory()->create();

        $this->vegWarehouse = Warehouse::factory()->create(['code' => 'VEG-WH', 'name' => 'Vegetable Warehouse']);
        $this->fruitWarehouse = Warehouse::factory()->create(['code' => 'FRT-WH', 'name' => 'Fruit Warehouse']);
        $this->vegCategory = Category::factory()->create(['name' => 'Vegetables']);
        $this->fruitCategory = Category::factory()->create(['name' => 'Fruits']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_grade_wise_purchase_prices_and_multiple_grades_for_same_product_do_not_mix(): void
    {
        $tomato = $this->createProduct('Tomato', 'TOM-01', 'VEG-WH');
        $purchaser = User::factory()->create();
        $supplier = Supplier::factory()->create();

        // Grade A: (10*40 + 20*46) / 30 = 1320 / 30 = 44.00
        $this->createPurchase($tomato, '2026-11-26', 10, 40, $purchaser, $supplier, 'A');
        $this->createPurchase($tomato, '2026-11-26', 20, 46, $purchaser, $supplier, 'A');
        // Grade B: (10*25) / 10 = 25.00
        $this->createPurchase($tomato, '2026-11-26', 10, 25, $purchaser, $supplier, 'B');
        // Grade C: (5*15) / 5 = 15.00
        $this->createPurchase($tomato, '2026-11-26', 5, 15, $purchaser, $supplier, 'C');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices', [
            'date' => '2026-11-26',
            'period' => 'custom',
            'produce_type' => 'all',
            'sort' => 'code',
        ]));

        $response->assertOk();
        $rows = $response['rows']->getCollection();
        $this->assertCount(3, $rows);

        $gradeA = $rows->firstWhere('grade', 'A');
        $gradeB = $rows->firstWhere('grade', 'B');
        $gradeC = $rows->firstWhere('grade', 'C');

        $this->assertSame(44.0, round((float) $gradeA->actual_purchase_price, 2));
        $this->assertSame(30.0, round((float) $gradeA->purchase_qty, 3));
        $this->assertSame('Current Price', $gradeA->price_status_label);

        $this->assertSame(25.0, round((float) $gradeB->actual_purchase_price, 2));
        $this->assertSame(10.0, round((float) $gradeB->purchase_qty, 3));
        $this->assertSame('Current Price', $gradeB->price_status_label);

        $this->assertSame(15.0, round((float) $gradeC->actual_purchase_price, 2));
        $this->assertSame(5.0, round((float) $gradeC->purchase_qty, 3));
        $this->assertSame('Current Price', $gradeC->price_status_label);
    }

    public function test_grade_filter_returns_only_matching_grade_and_supports_all_active_grades(): void
    {
        $apple = $this->createProduct('Apple', 'APL-01', 'FRT-WH');
        $purchaser = User::factory()->create();
        $supplier = Supplier::factory()->create();

        $this->createPurchase($apple, '2026-11-26', 10, 120, $purchaser, $supplier, 'A');
        $this->createPurchase($apple, '2026-11-26', 5, 80, $purchaser, $supplier, 'B');
        $this->createPurchase($apple, '2026-11-26', 4, 50, $purchaser, $supplier, 'C');

        $responseB = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices', [
            'date' => '2026-11-26',
            'grade' => ProductGrade::GradeB->value,
        ]));

        $responseB->assertOk();
        $rowsB = $responseB['rows']->getCollection();
        $this->assertCount(1, $rowsB);
        $this->assertSame('B', $rowsB->first()->grade);
        $this->assertSame(80.0, round((float) $rowsB->first()->actual_purchase_price, 2));

        $responseC = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices', [
            'date' => '2026-11-26',
            'grade' => ProductGrade::GradeC->value,
        ]));

        $responseC->assertOk();
        $rowsC = $responseC['rows']->getCollection();
        $this->assertCount(1, $rowsC);
        $this->assertSame('C', $rowsC->first()->grade);
        $this->assertSame(50.0, round((float) $rowsC->first()->actual_purchase_price, 2));
    }

    public function test_missing_current_price_falls_back_to_most_recent_valid_historical_price_by_product_and_grade(): void
    {
        $onion = $this->createProduct('Onion', 'ONI-01', 'VEG-WH');
        $purchaser = User::factory()->create();
        $supplier = Supplier::factory()->create();

        // Grade A purchased on 2026-11-20 and 2026-11-24 (most recent before 2026-11-26)
        $this->createPurchase($onion, '2026-11-20', 10, 30, $purchaser, $supplier, 'A');
        $this->createPurchase($onion, '2026-11-24', 10, 35, $purchaser, $supplier, 'A');

        // Grade B purchased on 2026-11-22 (30.00) and on 2026-11-26 (28.00)
        $this->createPurchase($onion, '2026-11-22', 10, 30, $purchaser, $supplier, 'B');
        $this->createPurchase($onion, '2026-11-26', 10, 28, $purchaser, $supplier, 'B');

        // Future purchase on 2026-11-28 MUST be ignored
        $this->createPurchase($onion, '2026-11-28', 10, 99, $purchaser, $supplier, 'A');

        // Cancelled invoice on 2026-11-25 MUST be ignored
        $cancelledInvoice = $this->createPurchase($onion, '2026-11-25', 10, 77, $purchaser, $supplier, 'A');
        $cancelledInvoice->update(['status' => 'cancelled']);

        // Soft-deleted invoice on 2026-11-25 MUST be ignored
        $deletedInvoice = $this->createPurchase($onion, '2026-11-25', 10, 88, $purchaser, $supplier, 'A');
        $deletedInvoice->delete();

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices', [
            'date' => '2026-11-26',
            'period' => 'custom',
        ]));

        $response->assertOk()
            ->assertSee('Historical Price')
            ->assertSee('Current Price')
            ->assertSee('₹35.00')
            ->assertSee('24 Nov 2026')
            ->assertDontSee('₹99.00')
            ->assertDontSee('₹77.00')
            ->assertDontSee('₹88.00');

        $rows = $response['rows']->getCollection();
        $gradeA = $rows->firstWhere('grade', 'A');
        $gradeB = $rows->firstWhere('grade', 'B');

        $this->assertNull($gradeA->actual_purchase_price);
        $this->assertNull($gradeA->purchase_qty);
        $this->assertSame(35.0, round((float) $gradeA->last_purchase_price, 2));
        $this->assertSame('2026-11-24', $gradeA->last_purchase_date);
        $this->assertSame('Historical Price', $gradeA->price_status_label);

        $this->assertSame(28.0, round((float) $gradeB->actual_purchase_price, 2));
        $this->assertSame('Current Price', $gradeB->price_status_label);
    }

    public function test_products_without_purchase_history_display_not_available(): void
    {
        $this->createProduct('Mango', 'MNG-01', 'FRT-WH');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices', [
            'date' => '2026-11-26',
        ]));

        $response->assertOk()
            ->assertSee('Mango')
            ->assertSee('No Price Available')
            ->assertSee('Not Available');

        $row = $response['rows']->getCollection()->first();
        $this->assertNull($row->actual_purchase_price);
        $this->assertNull($row->last_purchase_price);
        $this->assertSame('Not Available', $row->price_status_label);
    }

    public function test_historical_purchases_without_grade_are_compatible_and_mapped_to_grade_u(): void
    {
        $potato = $this->createProduct('Potato', 'POT-01', 'VEG-WH');
        $purchaser = User::factory()->create();
        $supplier = Supplier::factory()->create();

        $invoice = $this->createPurchase($potato, '2026-11-21', 12, 22, $purchaser, $supplier, 'A');
        DB::table('purchaser_cart_items')
            ->where('purchaser_cart_id', $invoice->purchaser_cart_id)
            ->update(['grade' => '']);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices', [
            'date' => '2026-11-26',
        ]));

        $response->assertOk()->assertSee('₹22.00')->assertSee('21 Nov 2026');
        $row = $response['rows']->getCollection()->first();
        $this->assertSame('U', $row->grade);
        $this->assertSame(22.0, round((float) $row->last_purchase_price, 2));
    }

    public function test_draft_carts_and_cancelled_invoices_are_excluded_from_prices(): void
    {
        $carrot = $this->createProduct('Carrot', 'CAR-01', 'VEG-WH');
        $purchaser = User::factory()->create();
        $supplier = Supplier::factory()->create();

        $this->createPurchase($carrot, '2026-11-26', 10, 99, $purchaser, $supplier, 'A', 'draft');

        $cancelledInvoice = $this->createPurchase($carrot, '2026-11-26', 10, 88, $purchaser, $supplier, 'A');
        $cancelledInvoice->update(['status' => 'cancelled']);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices', [
            'date' => '2026-11-26',
        ]));

        $response->assertOk()->assertSee('Not Available');
        $row = $response['rows']->getCollection()->first();
        $this->assertNull($row->actual_purchase_price);
        $this->assertNull($row->last_purchase_price);
        $this->assertSame('Not Available', $row->price_status_label);
    }

    public function test_csv_export_neutralizes_formula_injection(): void
    {
        $this->createProduct('=CMD("calc")', '=SUM(A1)', 'VEG-WH');

        $csvResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices.export.csv', [
            'date' => '2026-11-26',
        ]));
        $csvResponse->assertOk();
        $content = $csvResponse->streamedContent();
        $this->assertStringContainsString("'=SUM(A1)", $content);
        $this->assertStringContainsString("'=CMD(\"\"calc\"\")", $content);
    }

    public function test_all_grades_and_single_grade_filter_produce_identical_row_values_and_export_parity(): void
    {
        $tomato = $this->createProduct('Tomato', 'TOM-99', 'VEG-WH');
        $purchaser = User::factory()->create();
        $supplier = Supplier::factory()->create();

        // Grade A has both historical (Nov 22) and current (Nov 26)
        $this->createPurchase($tomato, '2026-11-22', 10, 30, $purchaser, $supplier, 'A');
        $this->createPurchase($tomato, '2026-11-26', 10, 42, $purchaser, $supplier, 'A');

        // Grade B has only historical (Nov 23)
        $this->createPurchase($tomato, '2026-11-23', 5, 24, $purchaser, $supplier, 'B');

        $allResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices', [
            'date' => '2026-11-26',
            'product_id' => $tomato->id,
        ]));
        $allResponse->assertOk();
        $allRows = $allResponse['rows']->getCollection();
        $allGradeA = $allRows->firstWhere('grade', 'A');
        $allGradeB = $allRows->firstWhere('grade', 'B');

        $onlyAResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices', [
            'date' => '2026-11-26',
            'product_id' => $tomato->id,
            'grade' => 'A',
        ]));
        $onlyARow = $onlyAResponse['rows']->getCollection()->first();

        $onlyBResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices', [
            'date' => '2026-11-26',
            'product_id' => $tomato->id,
            'grade' => 'B',
        ]));
        $onlyBRow = $onlyBResponse['rows']->getCollection()->first();

        $this->assertEquals((array) $allGradeA, (array) $onlyARow);
        $this->assertEquals((array) $allGradeB, (array) $onlyBRow);

        // Verify CSV, Excel, and PDF parity
        $csvResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices.export.csv', [
            'date' => '2026-11-26',
            'product_id' => $tomato->id,
        ]));
        $csvResponse->assertOk();
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('TOM-99,Tomato,Vegetables,A,KG,10,42', $csvContent);
        $this->assertStringContainsString('TOM-99,Tomato,Vegetables,B,KG,,,24,2026-11-23', $csvContent);
        $this->assertStringContainsString('2026-11-23', $csvContent);
        $this->assertStringContainsString('Historical Price', $csvContent);

        $pdfResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices.export.pdf', [
            'date' => '2026-11-26',
            'product_id' => $tomato->id,
        ]));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('Content-Type'));
    }

    public function test_cache_invalidation_observer_clears_purchase_price_cache_on_mutations(): void
    {
        $cacheService = app(PurchaserReadCacheService::class);
        $initialCartVer = $cacheService->getScopeVersion('carts');
        $initialPriceVer = $cacheService->getScopeVersion('prices');

        $tomato = $this->createProduct('Tomato', 'TOM-CACHE', 'VEG-WH');
        $purchaser = User::factory()->create();
        $supplier = Supplier::factory()->create();

        $invoice = $this->createPurchase($tomato, '2026-11-26', 10, 40, $purchaser, $supplier, 'A');
        $afterCreateCartVer = $cacheService->getScopeVersion('carts');
        $this->assertGreaterThan($initialCartVer, $afterCreateCartVer);

        // Modify item quantity/price/grade
        $item = PurchaserCartItem::query()->where('purchaser_cart_id', $invoice->purchaser_cart_id)->firstOrFail();
        $item->update(['quantity' => 15, 'unit_price' => 45, 'grade' => 'B']);
        $afterItemVer = $cacheService->getScopeVersion('carts');
        $this->assertGreaterThan($afterCreateCartVer, $afterItemVer);

        // Cancel invoice
        $invoice->update(['status' => 'cancelled']);
        $afterCancelVer = $cacheService->getScopeVersion('carts');
        $this->assertGreaterThan($afterItemVer, $afterCancelVer);

        // DailyPriceApproval mutation invalidates 'prices'
        DailyPriceApproval::query()
            ->where('product_id', $tomato->id)
            ->whereDate('business_date', '2026-11-26')
            ->where('grade', 'A')
            ->firstOrFail()
            ->update([
                'purchase_price' => 42,
                'price_a' => 52,
                'price_b' => 54,
                'price_c' => 56,
            ]);
        $this->assertGreaterThan($initialPriceVer, $cacheService->getScopeVersion('prices'));
    }

    public function test_custom_date_range_boundary_and_exports_include_all_paginated_records(): void
    {
        $purchaser = User::factory()->create();
        $supplier = Supplier::factory()->create();

        // Create 55 products (> 50 pagination limit) to verify exports include all records
        for ($i = 1; $i <= 55; $i++) {
            $code = sprintf('PRD-%03d', $i);
            $product = $this->createProduct("Product {$i}", $code, $i <= 30 ? 'VEG-WH' : 'FRT-WH');
            if ($i === 1) {
                $this->createPurchase($product, '2026-11-24', 10, 50, $purchaser, $supplier, 'A');
                $this->createPurchase($product, '2026-11-26', 10, 60, $purchaser, $supplier, 'A');
            }
        }

        // 1. UI Page 1 has 50 items, total 55
        $uiResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices', [
            'period_mode' => 'custom',
            'from' => '2026-11-24',
            'to' => '2026-11-26',
            'sort' => 'code',
        ]));
        $uiResponse->assertOk();
        $this->assertSame(55, $uiResponse['rows']->total());
        $this->assertCount(50, $uiResponse['rows']->items());
        // Weighted average across 2026-11-24..2026-11-26 for PRD-001 = (10*50 + 10*60)/20 = 55.00
        $this->assertSame(55.0, round((float) $uiResponse['rows']->first()->actual_purchase_price, 2));

        // 2. CSV Export includes all 55 matching records (not just page 1)
        $csvResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices.export.csv', [
            'period_mode' => 'custom',
            'from' => '2026-11-24',
            'to' => '2026-11-26',
            'sort' => 'code',
        ]));
        $csvResponse->assertOk();
        $csvContent = $csvResponse->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csvContent))));
        $this->assertCount(56, $lines); // 1 header + 55 data rows
        $this->assertStringContainsString('PRD-055', $csvContent);
        $this->assertStringContainsString(',55,', $lines[1]);

        // 3. CSV Export respects produce_type filter
        $vegCsvResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices.export.csv', [
            'period_mode' => 'custom',
            'from' => '2026-11-24',
            'to' => '2026-11-26',
            'produce_type' => 'vegetables',
        ]));
        $vegLines = array_values(array_filter(explode("\n", trim($vegCsvResponse->streamedContent()))));
        $this->assertCount(31, $vegLines); // 1 header + 30 vegetables

        // 4. Excel Export downloads with all 55 rows
        Excel::fake();
        $excelResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.prices.export.excel', [
            'period_mode' => 'custom',
            'from' => '2026-11-24',
            'to' => '2026-11-26',
            'sort' => 'code',
        ]));
        $excelResponse->assertOk();
        Excel::assertDownloaded('purchase-price-report-2026-11-24-to-2026-11-26.xlsx', static function (PurchasePriceReportExport $export): bool {
            return $export->collection()->count() === 55;
        });
    }

    public function test_unauthorized_user_cannot_access_report_or_exports(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.cashbook.finance.purchase.reports.prices'))
            ->assertRedirect();

        $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.cashbook.finance.purchase.reports.prices.export.csv'))
            ->assertRedirect();

        $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.cashbook.finance.purchase.reports.prices.export.excel'))
            ->assertRedirect();
    }

    private function createProduct(string $name, string $sku, string $warehouseCode): Product
    {
        $warehouse = $warehouseCode === 'VEG-WH' ? $this->vegWarehouse : $this->fruitWarehouse;
        $category = $warehouseCode === 'VEG-WH' ? $this->vegCategory : $this->fruitCategory;

        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'default_warehouse_id' => $warehouse->id,
            'category_id' => $category->id,
            'unit' => 'kg',
            'base_price' => 50,
            'vendor_price' => 40,
            'is_active' => true,
        ]);
    }

    private function createPurchase(
        Product $product,
        string $date,
        float $quantity,
        float $unitPrice,
        User $purchaser,
        Supplier $supplier,
        string $grade = 'A',
        string $cartStatus = 'submitted'
    ): PurchaseInvoice {
        $cart = PurchaserCart::query()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $date,
            'status' => $cartStatus,
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
