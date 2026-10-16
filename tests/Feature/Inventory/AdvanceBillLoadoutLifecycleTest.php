<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\DailyAdvanceMatchExecutionService;
use App\Services\Purchasing\DailyAdvanceMatchPlanningService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdvanceBillLoadoutLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Warehouse $warehouse2;

    private Product $product;

    private Supplier $supplier;

    private Shop $shop;

    private StockMovementRepository $stockRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('warehouse_receiver');
        $this->user->givePermissionTo([
            'purchasing.grn.create',
            'purchasing.grn.approve',
            'warehouse.receive.view',
            'warehouse.receive.confirm',
            'warehouse.loadout.all',
        ]);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Vegetable Warehouse', 'code' => 'WH1']);
        $this->warehouse2 = Warehouse::factory()->create(['name' => 'Fruit Warehouse', 'code' => 'WH2']);
        $this->user->warehouses()->attach([$this->warehouse->id, $this->warehouse2->id]);
        $this->actingAs($this->user);

        $this->supplier = Supplier::factory()->create();
        $priceGroup = ShopPriceGroup::factory()->create(['name' => 'A', 'is_active' => true]);
        $this->shop = Shop::factory()->create(['shop_price_group_id' => $priceGroup->id]);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'name' => 'Test Tomato',
            'sku' => 'TOM-001',
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $this->product->id,
            'business_date' => '2026-10-15',
            'purchase_price' => 5,
            'price_unit' => 'kg',
            'price_a' => 10,
            'price_b' => 10,
            'price_c' => 10,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->user->id,
        ]);

        $this->stockRepo = app(StockMovementRepository::class);
        Carbon::setTestNow('2026-10-15 08:00:00');
    }

    public function test_complete_critical_lifecycle(): void
    {
        $date = '2026-10-15';

        // 1. Advance receipt 100 kg -> Stock = 100 kg
        [$advGrn, $advItem, $advBatch] = $this->createAdvanceReceipt(100.0, $date);
        $this->assertSame(100.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));

        // 2. Loadout 30 kg -> Stock = 70 kg
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
        ]);
        $soItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'loaded_qty' => 30.0,
            'unit' => 'kg',
            'sorting_status' => 'loaded',
        ]);
        app(StockLedgerService::class)->consumeStockForProductAllowingNegative(
            $this->product->id,
            30.0,
            $this->user->id,
            StockMovementType::Out,
            "Loadout dispatch to delivery — Order: {$order->order_number}",
            $soItem->id,
            $this->warehouse->id,
            ProductGrade::GradeA,
        );
        $this->assertSame(70.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));

        // 3. Create pending bill 100 kg -> Stock remains 70 kg
        [$billGrn, $billItem, $billBatch] = $this->createPendingBill(100.0, $date);
        $this->assertSame(70.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));

        // 4. Match full advance (100 kg) via Daily Auto Match -> Stock remains 70 kg
        $plan = app(DailyAdvanceMatchPlanningService::class)->buildDailyPlan($this->warehouse->id, $date);
        $this->assertNotEmpty($plan['ready_bills']);

        app(DailyAdvanceMatchExecutionService::class)->execute(
            $this->warehouse->id,
            $date,
            $plan['plan_hash'],
            (string) Str::uuid(),
            $this->user->id,
        );
        $this->assertSame(70.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));

        // 5. Run matching again (idempotent) -> Stock remains 70 kg
        $plan2 = app(DailyAdvanceMatchPlanningService::class)->buildDailyPlan($this->warehouse->id, $date);
        $this->assertEmpty($plan2['ready_bills']);
        $this->assertSame(70.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));

        // 6. Additional new physical receipt 20 kg -> Stock = 90 kg
        $this->createConfirmedReceipt(20.0, $date);
        $this->assertSame(90.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));

        // 7. Loadout another 40 kg -> Stock = 50 kg
        $shop2 = Shop::factory()->create(['shop_price_group_id' => $this->shop->shop_price_group_id]);
        $order2 = ShopOrder::factory()->create([
            'shop_id' => $shop2->id,
            'business_date' => $date,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
        ]);
        $soItem2 = ShopOrderItem::create([
            'shop_order_id' => $order2->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 40.0,
            'approved_qty' => 40.0,
            'loaded_qty' => 40.0,
            'unit' => 'kg',
            'sorting_status' => 'loaded',
        ]);
        app(StockLedgerService::class)->consumeStockForProductAllowingNegative(
            $this->product->id,
            40.0,
            $this->user->id,
            StockMovementType::Out,
            "Loadout dispatch to delivery — Order: {$order2->order_number}",
            $soItem2->id,
            $this->warehouse->id,
            ProductGrade::GradeA,
        );
        $this->assertSame(50.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));
    }

    public function test_bill_received_before_daily_auto_match_removes_duplicate_stock(): void
    {
        $date = '2026-10-15';

        // Advance received 100 kg
        [$advGrn, $advItem, $advBatch] = $this->createAdvanceReceipt(100.0, $date);
        $this->assertSame(100.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));

        // Bill created and confirmed in warehouse (100 kg)
        [$billGrn, $billItem, $billBatch] = $this->createConfirmedBill(100.0, $date);
        // Before matching, both are confirmed in warehouse
        $this->assertSame(200.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));

        // Execute match via API
        $submissionId = (string) Str::uuid();
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'client_submission_id' => $submissionId,
            'advance_matches' => [[
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'goods_received_item_id' => $billItem->id,
                'purchase_order_item_id' => $billItem->purchase_order_item_id,
                'product_id' => $this->product->id,
                'matched_qty' => 100.0,
                'unit' => 'kg',
            ]],
        ];
        $this->postJson("/api/v1/purchasing/grns/{$billGrn->id}/advance-matches", $payload)->assertOk();

        // Bill batch total_kg is decremented to 0
        $this->assertSame(0.0, (float) $billBatch->fresh()->total_kg);
        // Stock returns to exactly 100 kg
        $this->assertSame(100.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));
    }

    public function test_partial_matching_leaves_correct_stock_and_remaining_bill_quantity(): void
    {
        $date = '2026-10-15';

        // Advance received 100 kg
        [$advGrn, $advItem, $advBatch] = $this->createAdvanceReceipt(100.0, $date);

        // Bill created and confirmed for 120 kg
        [$billGrn, $billItem, $billBatch] = $this->createConfirmedBill(120.0, $date);
        $this->assertSame(220.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));

        // Match 100 kg
        $submissionId = (string) Str::uuid();
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'client_submission_id' => $submissionId,
            'advance_matches' => [[
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'goods_received_item_id' => $billItem->id,
                'purchase_order_item_id' => $billItem->purchase_order_item_id,
                'product_id' => $this->product->id,
                'matched_qty' => 100.0,
                'unit' => 'kg',
            ]],
        ];
        $this->postJson("/api/v1/purchasing/grns/{$billGrn->id}/advance-matches", $payload)->assertOk();

        // Bill batch total_kg becomes 20 kg
        $this->assertSame(20.0, (float) $billBatch->fresh()->total_kg);
        // Total stock is 120 kg (100 kg advance + 20 kg unmatched new bill goods)
        $this->assertSame(120.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));
    }

    public function test_web_receiver_deducts_already_matched_advance_quantity(): void
    {
        $date = '2026-10-15';

        // Advance received 100 kg
        [$advGrn, $advItem, $advBatch] = $this->createAdvanceReceipt(100.0, $date);

        // Bill created (pending warehouse receive) for 100 kg
        [$billGrn, $billItem, $billBatch] = $this->createPendingBill(100.0, $date);

        // Match 100 kg
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'advance_stock_batch_id' => $advBatch->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'purchase_order_id' => $billGrn->purchase_order_id,
            'purchase_order_item_id' => $billItem->purchase_order_item_id,
            'product_id' => $this->product->id,
            'matched_qty' => 100.0,
            'matched_unit' => 'kg',
            'base_qty' => 100.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->user->id,
            'confirmed_at' => now(),
            'client_submission_id' => (string) Str::uuid(),
            'notes' => 'Pre-matched test',
        ]);

        // Now web receiver receives the GRN into warehouse
        $postData = [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                $billItem->id => [
                    'received_qty' => 100.0,
                    'warehouse_id' => $this->warehouse->id,
                    'discrepancy_type' => 'none',
                ],
            ],
            'bypass_advance_match' => true,
            'bypass_reason' => 'Already matched via AdvanceReceiveMatch',
        ];

        $this->post(route('warehouse.receiver.process-receive-grn', $billGrn), $postData)->assertRedirect();

        // Bill batch total_kg is 0, not 100 kg!
        $this->assertSame(0.0, (float) $billBatch->fresh()->total_kg);
        // Stock remains 100 kg
        $this->assertSame(100.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $date));
    }

    public function test_loadout_records_movement_with_source_warehouse_id(): void
    {
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-15',
            'delivery_status' => 'pending_delivery',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'loaded_qty' => 0.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        $this->post(route('warehouse.loadout.save', $order), [
            'items' => [$this->product->id => 15.0],
        ])->assertRedirect(route('warehouse.loadout.show', $order));

        $movement = StockMovement::where('product_id', $this->product->id)
            ->where('type', StockMovementType::Out)
            ->latest('id')
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame($this->warehouse->id, $movement->warehouse_id);
    }

    private function createAdvanceReceipt(float $quantity, string $date): array
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'received_at' => $date,
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->user->id,
        ]);
        $item = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => $quantity,
            'received_unit' => 'kg',
            'grade' => 'A',
        ]);
        $batch = StockBatch::factory()->create([
            'reference' => 'BATCH-ADV-'.uniqid(),
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'total_kg' => $quantity,
            'received_at' => $date,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
        ]);

        return [$grn, $item, $batch];
    }

    private function createPendingBill(float $quantity, string $date): array
    {
        $po = PurchaseOrder::factory()->create([
            'destination_shop_id' => $this->shop->id,
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
            'status' => POStatus::Approved,
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'purchase_unit' => 'kg',
        ]);
        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal_purchase',
            'received_at' => $date,
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->user->id,
        ]);
        $item = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => $quantity,
            'received_unit' => 'kg',
            'grade' => 'A',
        ]);
        $batch = StockBatch::factory()->create([
            'reference' => 'BATCH-BILL-'.uniqid(),
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'total_kg' => $quantity,
            'received_at' => $date,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
        ]);

        return [$grn, $item, $batch];
    }

    private function createConfirmedBill(float $quantity, string $date): array
    {
        [$grn, $item, $batch] = $this->createPendingBill($quantity, $date);
        $batch->update([
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->user->id,
        ]);

        return [$grn, $item, $batch];
    }

    private function createConfirmedReceipt(float $quantity, string $date): array
    {
        $batch = StockBatch::factory()->create([
            'reference' => 'BATCH-REC-'.uniqid(),
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => $quantity,
            'received_at' => $date,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
        ]);

        return [$batch];
    }
}
