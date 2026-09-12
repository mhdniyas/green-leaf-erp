<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use App\Services\Purchasing\AutoAdvanceClearPlanningService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitDifferencesSameUnitFixTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Shop $shop;

    private AdvanceReceiveReconciliationService $reconciliationService;

    private AutoAdvanceClearPlanningService $planningService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Main Vegetable Hub',
            'code' => 'MAIN-HUB',
            'is_active' => true,
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Warehouse Outlet',
            'code' => 'WH-OUT',
        ]);

        $this->reconciliationService = app(AdvanceReceiveReconciliationService::class);
        $this->planningService = app(AutoAdvanceClearPlanningService::class);
    }

    private function createAdvance(Product $product, float $qty, string $unit): array
    {
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => now()->toDateString().' 08:00:00',
        ]);

        $item = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $unit,
        ]);

        $batch = StockBatch::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $item->id,
            'warehouse_receive_pending' => false,
            'total_kg' => $qty,
        ]);

        return [$advGrn, $item, $batch];
    }

    public function test_bill_kg_and_advance_kg_with_product_base_piece_is_not_unit_issue(): void
    {
        // Product base unit = piece, with NO kg conversion configured
        $product = Product::factory()->create([
            'name' => 'Cauliflower Test',
            'sku' => 'CAULI-01',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'piece',
            'label' => 'piece',
            'conversion_to_base' => 1.0,
            'is_base' => true,
        ]);

        // Open Advance in kg
        $this->createAdvance($product, 100.0, 'kg');

        // Pending PO Bill in kg
        $po = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'destination_shop_id' => $this->shop->id,
            'order_date' => now()->toDateString(),
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 50.0,
            'purchase_unit' => 'kg',
        ]);

        $diffs = $this->reconciliationService->paginateUnitDifferences(['warehouse_id' => $this->warehouse->id], 100);

        $this->assertCount(0, $diffs->items());
    }

    public function test_bill_kg_and_advance_kg_with_product_base_box_is_not_unit_issue(): void
    {
        // Product base unit = box, with NO kg conversion configured
        $product = Product::factory()->create([
            'name' => 'Sprouds Test',
            'sku' => 'SPROUD-01',
            'unit' => 'box',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'box',
            'label' => 'box',
            'conversion_to_base' => 1.0,
            'is_base' => true,
        ]);

        // Open Advance in kg
        $this->createAdvance($product, 60.0, 'kg');

        // Pending PO Bill in kg
        $po = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'destination_shop_id' => $this->shop->id,
            'order_date' => now()->toDateString(),
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 10.0,
            'purchase_unit' => 'kg',
        ]);

        $diffs = $this->reconciliationService->paginateUnitDifferences(['warehouse_id' => $this->warehouse->id], 100);

        $this->assertCount(0, $diffs->items());
    }

    public function test_bill_box_and_advance_box_is_not_unit_issue(): void
    {
        $product = Product::factory()->create([
            'name' => 'Apple Misri Test',
            'sku' => 'APP-MIS',
            'unit' => 'box',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'box',
            'label' => 'box',
            'conversion_to_base' => 1.0,
            'is_base' => true,
        ]);

        $this->createAdvance($product, 10.0, 'box');

        $po = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'destination_shop_id' => $this->shop->id,
            'order_date' => now()->toDateString(),
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 5.0,
            'purchase_unit' => 'box',
        ]);

        $diffs = $this->reconciliationService->paginateUnitDifferences(['warehouse_id' => $this->warehouse->id], 100);

        $this->assertCount(0, $diffs->items());
    }

    public function test_bill_kg_and_advance_box_without_conversion_remains_unit_issue(): void
    {
        $product = Product::factory()->create([
            'name' => 'Anar Real Mismatch',
            'sku' => 'ANAR-REAL',
            'unit' => 'box',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'box',
            'label' => 'box',
            'conversion_to_base' => 1.0,
            'is_base' => true,
        ]);

        // Advance is in box
        $this->createAdvance($product, 20.0, 'box');

        // Bill is in kg with no conversion
        $po = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'destination_shop_id' => $this->shop->id,
            'order_date' => now()->toDateString(),
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 5.0,
            'purchase_unit' => 'kg',
        ]);

        $diffs = $this->reconciliationService->paginateUnitDifferences(['warehouse_id' => $this->warehouse->id], 100);

        $this->assertCount(1, $diffs->items());
        $row = $diffs->items()[0];
        $this->assertSame('Anar Real Mismatch', $row['product_name']);
        $this->assertSame('kg', $row['bill_unit']);
        $this->assertStringContainsString('Advance in box', $row['reason']);
    }

    public function test_mixed_pool_kg_and_box_allows_matching_compatible_kg_while_retaining_box_unresolved_if_short(): void
    {
        $product = Product::factory()->create([
            'name' => 'Mixed Pool Gala',
            'sku' => 'GALA-MIX',
            'unit' => 'box',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'box',
            'label' => 'box',
            'conversion_to_base' => 1.0,
            'is_base' => true,
        ]);

        // Advance 1: 10 kg
        $this->createAdvance($product, 10.0, 'kg');

        // Advance 2: 40 box (incompatible with kg)
        $this->createAdvance($product, 40.0, 'box');

        // Bill A: 5 kg (covered by 10 kg advance) -> NOT a unit issue
        $poA = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'destination_shop_id' => $this->shop->id,
            'order_date' => now()->toDateString(),
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $poA->id,
            'product_id' => $product->id,
            'quantity' => 5.0,
            'purchase_unit' => 'kg',
        ]);

        $diffsA = $this->reconciliationService->paginateUnitDifferences(['warehouse_id' => $this->warehouse->id], 100);
        $this->assertCount(0, $diffsA->items());

        // Bill B: 25 kg (exceeds 10 kg advance, requiring the incompatible box advance) -> IS a unit issue
        $poB = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'destination_shop_id' => $this->shop->id,
            'order_date' => now()->toDateString(),
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $poB->id,
            'product_id' => $product->id,
            'quantity' => 25.0,
            'purchase_unit' => 'kg',
        ]);

        $diffsB = $this->reconciliationService->paginateUnitDifferences(['warehouse_id' => $this->warehouse->id], 100);
        $this->assertCount(1, $diffsB->items());
        $this->assertSame(25.0, (float) $diffsB->items()[0]['bill_qty']);
    }

    public function test_same_unit_manual_match_creates_no_extra_stock_movement(): void
    {
        $product = Product::factory()->create([
            'name' => 'Direct Match Veg',
            'sku' => 'DIR-VEG',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'piece',
            'label' => 'piece',
            'conversion_to_base' => 1.0,
            'is_base' => true,
        ]);

        [$advGrn, $advItem] = $this->createAdvance($product, 50.0, 'kg');

        $po = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'destination_shop_id' => $this->shop->id,
            'order_date' => now()->toDateString(),
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 20.0,
            'purchase_unit' => 'kg',
        ]);

        $initialMovementsCount = StockMovement::query()->count();

        // Perform manual match 1:1 in kg via resolveUnitDifference
        $result = $this->reconciliationService->resolveUnitDifference([
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poItem->id,
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'matched_qty' => 20.0,
            'conversion_factor' => 1.0,
            'notes' => '1:1 manual match test',
        ], (int) $this->admin->id);

        $this->assertSame('success', $result['status'] ?? null);

        // Matching existing advance against bill creates 0 extra physical stock movements
        $finalMovementsCount = StockMovement::query()->count();
        $this->assertSame($initialMovementsCount, $finalMovementsCount);
    }
}
