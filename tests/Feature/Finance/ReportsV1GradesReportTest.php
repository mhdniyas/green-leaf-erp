<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Actions\Inventory\ProcessPartialSegregationAction;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsV1GradesReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow('2026-10-23');

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MWH',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'code' => 'VEG',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createProduct(string $name = 'Tomato', string $unit = 'kg'): Product
    {
        return Product::create([
            'name' => $name,
            'sku' => strtoupper(substr($name, 0, 3)).'-'.rand(100, 999),
            'unit' => $unit,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);
    }

    private function createRootBatch(
        Product $product,
        string $grade,
        float $quantity,
        float $costPerKg,
        string $receivedAt = '2026-10-23',
        ?Warehouse $warehouse = null
    ): StockBatch {
        $wh = $warehouse ?? $this->warehouse;

        return StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $wh->id,
            'created_by' => $this->admin->id,
            'reference' => 'BAT-'.rand(1000, 9999),
            'purchase_grade' => $grade,
            'received_at' => $receivedAt,
            'total_kg' => $quantity,
            'cost_per_kg' => $costPerKg,
            'status' => $grade === 'U' ? BatchStatus::Pending : BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);
    }

    public function test_sidebar_includes_grades_report_under_reports_v1(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase'));
        $response->assertOk();
        $response->assertSee(route('admin.cashbook.reports-v1.grades'));
        $response->assertSee('Grades Report');
    }

    public function test_grades_report_requires_authorization(): void
    {
        $unauthorized = User::factory()->create();

        $response = $this->actingAs($unauthorized)->get(route('admin.cashbook.reports-v1.grades'));
        $this->assertTrue(in_array($response->status(), [302, 403], true));
    }

    public function test_direct_grade_a_purchase_reporting(): void
    {
        $product = $this->createProduct('Carrot');
        $batch = $this->createRootBatch($product, 'A', 150.0, 25.50);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 150.0,
            'cost_per_unit' => 25.50,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        $response->assertSee('Carrot');
        $response->assertSee('150.0');
        $response->assertSee('25.50');
    }

    public function test_mixed_purchase_followed_by_segregation(): void
    {
        $product = $this->createProduct('Capsicum');
        $mixedBatch = $this->createRootBatch($product, 'U', 100.0, 30.00);

        // Segregate 40 kg to Grade A and 30 kg to Grade B
        $action = app(ProcessPartialSegregationAction::class);
        $action->execute($mixedBatch, ProductGrade::GradeA, 40.0, $this->admin->id);
        $action->execute($mixedBatch, ProductGrade::GradeB, 30.0, $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();

        $content = $response->getContent();
        // Check that original purchased is 100 kg under Mixed
        $this->assertStringContainsString('Capsicum', $content);
        $this->assertStringContainsString('40.0', $content); // Segregated to A
        $this->assertStringContainsString('30.0', $content); // Segregated to B
    }

    public function test_mixed_purchase_loaded_directly_as_grade_b(): void
    {
        $product = $this->createProduct('Potato');
        $mixedBatch = $this->createRootBatch($product, 'U', 80.0, 20.00);

        $shop = Shop::factory()->create(['name' => 'Metro Supermarket', 'status' => 'active']);
        $order = ShopOrder::factory()->create(['shop_id' => $shop->id, 'business_date' => '2026-10-23']);
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'B',
            'unit' => 'kg',
            'requested_qty' => 25.0,
            'approved_qty' => 25.0,
            'loaded_qty' => 25.0,
            'line_total' => 625.00,
        ]);

        // Consume 25 kg from Mixed batch for Grade B order
        StockMovement::create([
            'batch_id' => $mixedBatch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'shop_order_item_id' => $orderItem->id,
            'created_by' => $this->admin->id,
            'grade' => 'U',
            'type' => StockMovementType::Out->value,
            'quantity' => 25.0,
            'cost_per_unit' => 20.00,
            'notes' => 'Loaded 25 kg Mixed for Grade B order',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        // Mixed row physical loadout must show 25 kg
        // Commercial loadout for Grade B must reflect 25 kg (from Mixed)
        $response->assertSee('25.0');
        $response->assertSee('from Mixed');
    }

    public function test_grade_to_grade_reclassification(): void
    {
        $product = $this->createProduct('Apple');
        $batchA = $this->createRootBatch($product, 'A', 50.0, 80.00);

        StockMovement::create([
            'batch_id' => $batchA->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 50.0,
            'cost_per_unit' => 80.00,
        ]);

        // Regrade 15 kg from Grade A to Grade B
        $action = app(ProcessPartialSegregationAction::class);
        $action->execute($batchA, ProductGrade::GradeB, 15.0, $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        $response->assertSee('Apple');
        $response->assertSee('15.0');
    }

    public function test_damage_write_off_reporting(): void
    {
        $product = $this->createProduct('Onion');
        $batch = $this->createRootBatch($product, 'A', 100.0, 18.00);

        // Record damage of 6.5 kg
        WastageEntry::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'recorded_by' => $this->admin->id,
            'grade' => 'A',
            'quantity' => 6.5,
            'cost_per_kg' => 18.00,
            'reason' => WastageReason::Rotten,
            'wastage_date' => '2026-10-23',
            'notes' => 'Rotten stock on delivery',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        $response->assertSee('6.5');
        // Loss: 6.5 * 18 = ₹117
        $response->assertSee('117');
    }

    public function test_negative_stock_dispatch_marked_pending_cost_reconciliation(): void
    {
        $product = $this->createProduct('Garlic');

        // Negative stock batch with 0 cost
        $negBatch = StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'reference' => 'NEG-20261023-'.$product->id.'-A',
            'purchase_grade' => 'A',
            'received_at' => '2026-10-23',
            'total_kg' => 0,
            'cost_per_kg' => 0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);

        StockMovement::create([
            'batch_id' => $negBatch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 12.0,
            'cost_per_unit' => 0.00,
            'notes' => 'Fast morning loadout shortfall; negative stock allowed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        $response->assertSee('Pending Cost Reconciliation');
    }

    public function test_realized_invoice_selling_prices(): void
    {
        $product = $this->createProduct('Ginger');
        $batch = $this->createRootBatch($product, 'A', 50.0, 60.00);

        $shop = Shop::factory()->create(['name' => 'Green Grocer', 'status' => 'active']);
        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-23',
            'status' => 'finalized',
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => 'Ginger',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 30.0,
            'unit_price' => 90.00,
            'final_line_total' => 2700.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        $response->assertSee('90.00');
        $response->assertSee('2,700');
    }

    public function test_batch_audit_detail_endpoint(): void
    {
        $product = $this->createProduct('Cabbage');
        $batch = $this->createRootBatch($product, 'A', 40.0, 15.00);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades.batch-detail', [
            'batch' => $batch->reference,
        ]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.batch.reference', $batch->reference);
        $response->assertJsonPath('data.batch.product_name', 'Cabbage');
        $this->assertEquals(15.0, (float) $response->json('data.batch.cost_per_kg'));
    }

    public function test_multiple_batches_weighted_average_cost(): void
    {
        $product = $this->createProduct('Banana');
        $this->createRootBatch($product, 'A', 100.0, 20.00);
        $this->createRootBatch($product, 'A', 100.0, 40.00);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        // Weighted cost = (100*20 + 100*40) / 200 = 30.00
        $response->assertSee('30.00');
        $response->assertSee('200.0');
    }

    public function test_loadout_reversal_restores_available_inventory(): void
    {
        $product = $this->createProduct('Mango');
        $batch = $this->createRootBatch($product, 'A', 50.0, 50.00);

        // Initial IN
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 50.0,
            'cost_per_unit' => 50.00,
        ]);

        // Out movement of 20 kg
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 20.0,
            'cost_per_unit' => 50.00,
        ]);

        // Sale reversal of 20 kg
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::SaleReversal->value,
            'quantity' => 20.0,
            'cost_per_unit' => 50.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        // Net available should be 50 - 20 + 20 = 50.0 kg
        $response->assertSee('50.0');
    }

    public function test_warehouse_and_grade_filters(): void
    {
        $wh2 = Warehouse::create(['name' => 'Secondary WH', 'code' => 'SWH', 'is_active' => true]);
        $product = $this->createProduct('Orange');

        // Batch in WH 1 Grade A
        $this->createRootBatch($product, 'A', 30.0, 45.00, '2026-10-23', $this->warehouse);
        // Batch in WH 2 Grade B
        $this->createRootBatch($product, 'B', 60.0, 35.00, '2026-10-23', $wh2);

        // Filter for WH2 & Grade B
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'product' => $product->id,
            'warehouse_id' => $wh2->id,
            'grade' => 'B',
            'period' => 'this_month',
        ]));

        $response->assertOk();
        $response->assertSee('60.0');
        $response->assertSee('35.00');
        // Should not see Grade A batch of 30 kg from WH1
        $response->assertDontSee('45.00');
    }
}
