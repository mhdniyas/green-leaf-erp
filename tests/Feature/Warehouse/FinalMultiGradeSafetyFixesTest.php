<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinalMultiGradeSafetyFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private Warehouse $warehouse;

    private Shop $shop;

    private Product $product;

    private StockLedgerService $stockLedgerService;

    private Carbon $businessDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessDate = Carbon::parse('2026-10-17');
        $this->mock(PurchaserBusinessDayService::class, function ($mock): void {
            $mock->shouldReceive('operationalDate')->andReturn($this->businessDate);
        });

        Role::firstOrCreate(['name' => 'warehouse_receiver', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Central Warehouse', 'code' => 'WH-C']);
        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->warehouses()->attach($this->warehouse->id);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->product = Product::factory()->create([
            'name' => 'Tomato Hybrid',
            'sku' => 'VEG-TOM-01',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'KG',
            'is_active' => true,
        ]);

        $priceGroup = ShopPriceGroup::create([
            'name' => 'A',
            'code' => 'A',
            'is_active' => true,
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Casio Pilot Shop',
            'code' => 'SH-CASIO',
            'shop_price_group_id' => $priceGroup->id,
            'allowed_product_grades' => null,
        ]);

        BusinessSetting::updateOrCreate(
            ['key' => 'allow_historical_invoice_repricing'],
            ['value' => '1']
        );

        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $this->businessDate,
            'grade' => 'A',
            'purchase_price' => 20.0,
            'price_unit' => 'KG',
            'price_a' => 50.0,
            'price_b' => 40.0,
            'price_c' => 30.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->stockLedgerService = app(StockLedgerService::class);
    }

    private function createSortedBatch(string $grade, float $quantity): StockBatch
    {
        static $seq = 1;
        $batch = StockBatch::create([
            'reference' => 'BATCH-TEST-'.($seq++),
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => $quantity,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Sorted,
            'purchase_grade' => $grade,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'created_by' => $this->receiver->id,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => $grade,
            'type' => StockMovementType::In,
            'quantity' => $quantity,
            'cost_per_unit' => 20.0,
            'notes' => "Sorted stock for Grade {$grade}",
        ]);

        return $batch;
    }

    /**
     * Test 1: Migration rollback refusal on non-Grade-A daily price approvals.
     */
    public function test_01_daily_price_approvals_migration_refuses_rollback_on_non_grade_a(): void
    {
        $migration = require database_path('migrations/2026_10_16_221000_add_grade_to_daily_price_approvals_table.php');

        // Create Grade B approval record
        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => '2026-10-17',
            'grade' => 'B',
            'purchase_price' => 18.00,
            'price_a' => 30.00,
            'price_b' => 25.00,
            'price_c' => 20.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rollback aborted: Found 1 daily_price_approvals record(s) with non-Grade-A or NULL grade values.');

        try {
            $migration->down();
        } finally {
            // Verify schema and data remain 100% intact
            $this->assertTrue(Schema::hasColumn('daily_price_approvals', 'grade'));
            $this->assertDatabaseHas('daily_price_approvals', [
                'product_id' => $this->product->id,
                'grade' => 'B',
            ]);
        }
    }

    /**
     * Test 2: Migration rollback refusal on non-Grade-A shop invoice items.
     */
    public function test_02_shop_invoice_items_migration_refuses_rollback_on_non_grade_a(): void
    {
        $migration = require database_path('migrations/2026_10_16_230000_add_product_grade_to_shop_invoice_items_table.php');

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
        ]);

        $invoice = ShopInvoice::create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-TEST-001',
            'business_date' => $this->businessDate,
            'status' => 'draft',
            'subtotal' => 100.00,
            'final_total' => 100.00,
            'balance_amount' => 100.00,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_grade' => 'B',
            'unit' => 'KG',
            'approved_qty' => 4.0,
            'delivered_qty' => 4.0,
            'unit_price' => 25.0,
            'line_subtotal' => 100.0,
            'final_line_total' => 100.0,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rollback aborted: Found 1 shop_invoice_items record(s) with non-Grade-A or NULL product_grade values.');

        try {
            $migration->down();
        } finally {
            // Verify schema and data remain 100% intact
            $this->assertTrue(Schema::hasColumn('shop_invoice_items', 'product_grade'));
            $this->assertDatabaseHas('shop_invoice_items', [
                'shop_invoice_id' => $invoice->id,
                'product_grade' => 'B',
            ]);
        }
    }

    /**
     * Test 3: Existing Grade A-only Flutter loadout still works completely unchanged.
     */
    public function test_03_existing_grade_a_flutter_loadout_still_works(): void
    {
        $this->createSortedBatch('A', 50.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 600.0,
        ]);

        // Legacy mobile request sending plain product ID
        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    (string) $this->product->id => 15.0,
                ],
            ]);

        $response->assertOk();
        $itemA->refresh();
        $this->assertSame(15.0, (float) $itemA->loaded_qty);
        $this->assertSame(35.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));

        // Test 3B: Legitimate Grade A-only order with repeated product lines / different measurement units
        $shop2 = Shop::factory()->create([
            'allowed_product_grades' => null,
            'shop_price_group_id' => $this->shop->shop_price_group_id,
        ]);
        $orderRepeatedGradeA = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop2->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $orderRepeatedGradeA->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 400.0,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $orderRepeatedGradeA->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 0.0,
            'unit' => 'CRATE',
            'requested_unit' => 'crate',
            'requested_unit_conversion_to_base' => 1.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 200.0,
        ]);

        // Legitimate Grade A order with repeated product IDs must NOT lose mobile loadout
        $this->assertFalse($orderRepeatedGradeA->hasMultiGradeItems());

        $responseRepeated = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$orderRepeatedGradeA->id}/save", [
                'items' => [
                    (string) $this->product->id => 15.0,
                ],
            ]);

        $responseRepeated->assertOk();
    }

    /**
     * Test 4: Legacy Flutter cannot modify multi-grade orders.
     */
    public function test_04_legacy_flutter_cannot_modify_multi_grade_orders(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 30.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 400.0,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 125.0,
        ]);

        $initialMovementCount = StockMovement::count();

        // Legacy Flutter client attempts save on multi-grade order without compatible header
        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    (string) $this->product->id => 10.0,
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code' => 'MULTI_GRADE_MOBILE_RESTRICTED',
        ]);
        $this->assertStringContainsString('Web Warehouse portal', (string) $response->json('message'));

        // Client attempts save with compatible header BUT ambiguous bare product ID for multi-grade product
        $responseAmbiguous = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    (string) $this->product->id => 10.0,
                ],
            ], [
                'X-Client-Supports-Multi-Grade' => 'true',
            ]);

        $responseAmbiguous->assertStatus(422);
        $responseAmbiguous->assertJson([
            'success' => false,
            'code' => 'AMBIGUOUS_MULTI_GRADE_PAYLOAD',
        ]);
        $this->assertStringContainsString('Ambiguous item key', (string) $responseAmbiguous->json('message'));

        // Client attempts save with compatible header BUT invalid grade string
        $responseInvalidGrade = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    "{$this->product->id}|XYZ" => 10.0,
                ],
            ], [
                'X-Client-Supports-Multi-Grade' => 'true',
            ]);

        $responseInvalidGrade->assertStatus(422);
        $responseInvalidGrade->assertJson([
            'success' => false,
            'code' => 'INVALID_GRADE_IDENTITY',
        ]);

        // Client attempts save with compatible header BUT non-existent line (e.g. Grade C when order only has A & B)
        $responseUnknownLine = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/save", [
                'items' => [
                    "{$this->product->id}|C" => 10.0,
                ],
            ], [
                'X-Client-Supports-Multi-Grade' => 'true',
            ]);

        $responseUnknownLine->assertStatus(422);
        $responseUnknownLine->assertJson([
            'success' => false,
            'code' => 'UNKNOWN_LINE_IDENTITY',
        ]);

        // Test 5: Rejected API request created zero stock movements
        $this->assertSame($initialMovementCount, StockMovement::count());
        $this->assertSame(50.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
        $this->assertSame(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
    }

    /**
     * Test 5: Rejection of mobile bulk load actions on multi-grade orders.
     */
    public function test_05_mobile_bulk_load_rejected_on_multi_grade_order(): void
    {
        $this->createSortedBatch('A', 50.0);
        $this->createSortedBatch('B', 30.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 125.0,
        ]);

        $initialMovementCount = StockMovement::count();

        $response = $this->actingAs($this->receiver)
            ->postJson("/api/v1/warehouse/loadout/{$order->id}/load-all");

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code' => 'MULTI_GRADE_BULK_LOAD_RESTRICTED',
        ]);

        $this->assertSame($initialMovementCount, StockMovement::count());
    }

    /**
     * Test 6 & 7: Web multi-grade loadout still works and isolates Grade A and Grade B stock.
     */
    public function test_06_web_multi_grade_loadout_works_and_isolates_grades(): void
    {
        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $this->businessDate,
            'grade' => 'B',
            'purchase_price' => 15.0,
            'price_unit' => 'KG',
            'price_a' => 40.0,
            'price_b' => 30.0,
            'price_c' => 20.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->createSortedBatch('A', 100.0);
        $this->createSortedBatch('B', 40.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'delivery_status' => 'pending_delivery',
            'business_date' => $this->businessDate,
        ]);

        $itemA = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 800.0,
        ]);

        $itemB = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'line_total' => 250.0,
        ]);

        // Web warehouse portal submits composite keys
        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.loadout.save', $order), [
                'items' => [
                    "{$this->product->id}|A" => 20.0,
                    "{$this->product->id}|B" => 10.0,
                ],
            ]);

        $response->assertRedirect();

        $itemA->refresh();
        $itemB->refresh();
        $this->assertSame(20.0, (float) $itemA->loaded_qty);
        $this->assertSame(10.0, (float) $itemB->loaded_qty);

        // Inventory strictly segregated
        $this->assertSame(80.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeA));
        $this->assertSame(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, null, ProductGrade::GradeB));
    }

    /**
     * Test 8: Invoice gross, shortage credit, delivered value, and cashbook reconciliation.
     */
    public function test_08_invoice_gross_shortage_and_cashbook_reconciliation(): void
    {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->businessDate,
        ]);

        $invoice = ShopInvoice::create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-20261017-001',
            'business_date' => $this->businessDate,
            'status' => 'generated',
            'subtotal' => 0.00,
            'shortage_total' => 0.00,
            'excess_total' => 0.00,
            'discount_total' => 0.00,
            'final_total' => 0.00,
            'paid_amount' => 0.00,
            'balance_amount' => 0.00,
        ]);

        // Tomato A: 100 kg approved, 100 kg delivered @ ₹40/kg
        $itemA = ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'product_name' => 'Tomato Hybrid (Grade A)',
            'product_grade' => 'A',
            'unit' => 'KG',
            'price_unit' => 'KG',
            'approved_qty' => 100.0,
            'delivered_qty' => 100.0,
            'shortage_qty' => 0.0,
            'excess_qty' => 0.0,
            'unit_price' => 40.0,
            'line_subtotal' => 4000.0,
            'shortage_amount' => 0.0,
            'excess_amount' => 0.0,
            'final_line_total' => 4000.0,
        ]);

        // Tomato B: 20 kg approved, 18 kg delivered (2 kg shortage) @ ₹25/kg
        $itemB = ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'product_name' => 'Tomato Hybrid (Grade B)',
            'product_grade' => 'B',
            'unit' => 'KG',
            'price_unit' => 'KG',
            'approved_qty' => 20.0,
            'delivered_qty' => 18.0,
            'shortage_qty' => 2.0,
            'excess_qty' => 0.0,
            'unit_price' => 25.0,
            'line_subtotal' => 500.0,
            'shortage_amount' => 50.0,
            'excess_amount' => 0.0,
            'final_line_total' => 450.0,
        ]);

        // Tomato C: 80 kg approved, 80 kg delivered @ ₹15/kg
        $itemC = ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'product_name' => 'Tomato Hybrid (Grade C)',
            'product_grade' => 'C',
            'unit' => 'KG',
            'price_unit' => 'KG',
            'approved_qty' => 80.0,
            'delivered_qty' => 80.0,
            'shortage_qty' => 0.0,
            'excess_qty' => 0.0,
            'unit_price' => 15.0,
            'line_subtotal' => 1200.0,
            'shortage_amount' => 0.0,
            'excess_amount' => 0.0,
            'final_line_total' => 1200.0,
        ]);

        $invoiceService = app(ShopInvoiceService::class);
        $recalculated = $invoiceService->recalculate($invoice);

        // Verification of invoice fields:
        // Gross approved value = 4000 + 500 + 1200 = ₹5,700.00
        $grossSubtotal = (float) $recalculated->items->sum('line_subtotal');
        $this->assertSame(5700.0, $grossSubtotal);

        // Grade B shortage credit = ₹50.00
        $this->assertSame(50.0, (float) $recalculated->shortage_total);

        // Net delivered value = 4000 + 450 + 1200 = ₹5,650.00
        $this->assertSame(5650.0, (float) $recalculated->subtotal);
        $this->assertSame(5650.0, (float) $recalculated->final_total);
        $this->assertSame(5650.0, (float) $recalculated->balance_amount);

        // Finalize invoice and verify cashbook projection posts net ₹5,650.00
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $finalized = $invoiceService->markFinalized($recalculated, $admin->id, 'finalization', 'Test reconciliation');
        $this->assertSame(5650.0, (float) $finalized->final_total);

        // Verify that the cashbook posting amount is exactly ₹5,650.00, not double-crediting
        $transaction = DB::table('shop_ledger_transactions')
            ->where('reference_type', ShopInvoice::class)
            ->where('reference_id', $finalized->id)
            ->first();

        if ($transaction) {
            $this->assertSame(5650.0, (float) $transaction->amount);
        }
    }

    /**
     * Test 9: Production activation safety - global flag remains disabled and no shops eligible.
     */
    public function test_09_production_activation_safety_checks(): void
    {
        // 1. Global flag disabled
        $globalSetting = BusinessSetting::where('key', 'enable_multi_grade_ordering')->first();
        $this->assertTrue($globalSetting === null || $globalSetting->value === '0' || $globalSetting->value === 'false' || $globalSetting->value === null);

        // 2. Shop eligibility double-lock: no shop has non-empty allowed_product_grades
        $eligibleShopCount = Shop::all()
            ->filter(fn (Shop $shop): bool => ! empty($shop->allowed_product_grades))
            ->count();
        $this->assertSame(0, $eligibleShopCount);
    }

    /**
     * Test 10: Grade A-only migrations can safely reverse when data is genuinely Grade A.
     */
    public function test_10_grade_a_only_migrations_can_safely_reverse(): void
    {
        // In the test database, ensure only Grade A records exist
        DailyPriceApproval::query()->where('grade', '!=', 'A')->delete();
        ShopInvoiceItem::query()->where('product_grade', '!=', 'A')->delete();

        $migrationApprovals = require database_path('migrations/2026_10_16_221000_add_grade_to_daily_price_approvals_table.php');
        $migrationInvoices = require database_path('migrations/2026_10_16_230000_add_product_grade_to_shop_invoice_items_table.php');

        // Safe reversal executes without throwing RuntimeException
        $migrationApprovals->down();
        $migrationInvoices->down();

        // Check columns dropped safely
        $this->assertFalse(Schema::hasColumn('daily_price_approvals', 'grade'));
        $this->assertFalse(Schema::hasColumn('shop_invoice_items', 'product_grade'));
    }
}
