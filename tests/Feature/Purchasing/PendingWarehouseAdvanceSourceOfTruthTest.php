<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PendingWarehouseAdvanceSourceOfTruthTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $warehouseUser;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Product $appleProduct;

    private Supplier $supplier;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->warehouseUser = User::factory()->create();
        $this->warehouseUser->assignRole('warehouse_receiver');
        $this->warehouseUser->givePermissionTo([
            'inventory.stock.view',
            'inventory.product.view',
            'purchasing.order.view',
            'purchasing.grn.view',
            'purchasing.grn.create',
            'purchasing.grn.approve',
            'warehouse.receive.view',
            'warehouse.receive.confirm',
        ]);

        $this->warehouseA = Warehouse::create([
            'name' => 'Warehouse Alpha',
            'code' => 'WH-A',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'Warehouse Beta',
            'code' => 'WH-B',
            'is_active' => true,
        ]);

        $this->warehouseUser->warehouses()->attach([$this->warehouseA->id, $this->warehouseB->id]);

        $this->appleProduct = Product::factory()->create([
            'name' => 'Shimla Apple',
            'sku' => 'APP-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
            'base_price' => 50.0,
            'is_active' => true,
        ]);

        // Box unit for Apple: 1 box = 10 kg
        ProductUnit::create([
            'product_id' => $this->appleProduct->id,
            'unit' => 'box',
            'label' => 'Box (10kg)',
            'conversion_to_base' => 10.0,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Fruit Orchards Ltd',
        ]);

        $this->shop = Shop::create([
            'name' => 'Central Shop',
            'code' => 'SHOP-01',
            'is_active' => true,
        ]);
    }

    private function createAdvanceGrn(
        Warehouse $warehouse,
        float $receivedQty = 100.0,
        string $status = 'approved',
        string $billStatus = 'bill_pending',
        ?string $receiptType = 'warehouse_advance',
        bool $confirmedStock = true,
        ?int $poId = null,
        ?Product $product = null,
        string $unit = 'kg',
        string $batchStatus = 'pending'
    ): GoodsReceived {
        $prod = $product ?? $this->appleProduct;
        $grn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $warehouse->id,
            'destination_shop_id' => $this->shop->id,
            'purchase_order_id' => $poId,
            'grn_number' => 'ADV-'.uniqid(),
            'status' => $status,
            'bill_status' => $billStatus,
            'receipt_type' => $receiptType,
            'received_by' => $this->warehouseUser->id,
            'received_at' => now()->toDateString(),
            'approved_at' => $status === 'approved' ? now() : null,
            'approved_by' => $status === 'approved' ? $this->warehouseUser->id : null,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $prod->id,
            'received_qty' => $receivedQty,
            'received_unit' => $unit,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'product_id' => $prod->id,
            'warehouse_id' => $warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-'.uniqid(),
            'received_at' => now(),
            'total_kg' => $batchStatus === 'closed' ? 0.0 : $receivedQty,
            'cost_per_kg' => 50.0,
            'status' => $batchStatus === 'closed' ? BatchStatus::Closed : BatchStatus::Pending,
            'warehouse_receive_pending' => ! $confirmedStock,
            'warehouse_confirmed_at' => $confirmedStock ? now() : null,
            'warehouse_confirmed_by' => $confirmedStock ? $this->warehouseUser->id : null,
        ]);

        return $grn->fresh(['items', 'stockBatches']);
    }

    public function test_closed_or_depleted_batch_with_unbilled_balance_remains_in_list_badge_and_candidates(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advance 100 kg, partially matched 60 kg, remaining 40 kg unbilled.
        // Stock batch total_kg = 0 and status = closed (all stock was dispatched to shop).
        $adv = $this->createAdvanceGrn(
            warehouse: $this->warehouseA,
            receivedQty: 100.0,
            status: 'approved',
            billStatus: 'bill_pending',
            receiptType: 'warehouse_advance',
            confirmedStock: true,
            batchStatus: 'closed'
        );
        $advItem = $adv->items->first();

        // 60 kg matched
        $billGrn = $this->createAdvanceGrn($this->warehouseA, 60.0, 'approved', 'bill_available', 'normal_purchase');
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $adv->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billGrn->items->first()->id,
            'product_id' => $this->appleProduct->id,
            'matched_qty' => 60.0,
            'matched_unit' => 'kg',
            'base_qty' => 60.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        // 1. Advance list
        $listRes = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}");

        $listRes->assertOk();
        $this->assertEquals(1, $listRes->json('meta.total'));
        $this->assertEquals(100.0, (float) $listRes->json('data.0.received_base_qty'));
        $this->assertEquals(60.0, (float) $listRes->json('data.0.bill_matched_base_qty'));
        $this->assertEquals(40.0, (float) $listRes->json('data.0.unbilled_base_qty'));

        // 2. Advance badge count
        $countsRes = $this->getJson("/api/v1/purchasing/grns/receive-counts?warehouse_id={$this->warehouseA->id}");
        $countsRes->assertOk();
        $this->assertEquals(1, $countsRes->json('data.open_advance'));

        // 3. Match candidates
        $service = app(AdvanceReceiveReconciliationService::class);
        $candidates = $service->getOpenAdvanceCandidatesForProduct($this->appleProduct->id, $this->warehouseA->id);
        $this->assertCount(1, $candidates);
        $this->assertEquals(40.0, $candidates[0]['available_base_qty']);
    }

    public function test_negative_or_depleted_physical_stock_does_not_change_unbilled_quantity(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvanceGrn($this->warehouseA, 50.0);
        // Deplete stock to negative in inventory
        $batch = $adv->stockBatches->first();
        $batch->update(['total_kg' => -10.0]);

        $listRes = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}");
        $listRes->assertOk();
        $this->assertEquals(50.0, (float) $listRes->json('data.0.received_base_qty'));
        $this->assertEquals(0.0, (float) $listRes->json('data.0.bill_matched_base_qty'));
        $this->assertEquals(50.0, (float) $listRes->json('data.0.unbilled_base_qty'));
    }

    public function test_mixed_unit_conversion_computes_accurate_base_quantities(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advance: 2 BOX (1 box = 10 kg -> 20 kg base)
        $adv = $this->createAdvanceGrn(
            warehouse: $this->warehouseA,
            receivedQty: 2.0,
            status: 'approved',
            billStatus: 'bill_pending',
            receiptType: 'warehouse_advance',
            confirmedStock: true,
            unit: 'box'
        );
        $advItem = $adv->items->first();

        // Match: 15 KG matched
        $billGrn = $this->createAdvanceGrn($this->warehouseA, 15.0, 'approved', 'bill_available', 'normal_purchase');
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $adv->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billGrn->items->first()->id,
            'product_id' => $this->appleProduct->id,
            'matched_qty' => 1.5,
            'matched_unit' => 'box',
            'base_qty' => 15.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        $listRes = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}");
        $listRes->assertOk();
        $this->assertEquals(1, $listRes->json('meta.total'));
        $this->assertEquals(20.0, (float) $listRes->json('data.0.received_base_qty'));
        $this->assertEquals(15.0, (float) $listRes->json('data.0.bill_matched_base_qty'));
        $this->assertEquals(5.0, (float) $listRes->json('data.0.unbilled_base_qty'));

        // Matching candidates should expose 5 kg (0.5 box)
        $service = app(AdvanceReceiveReconciliationService::class);
        $candidates = $service->getOpenAdvanceCandidatesForProduct($this->appleProduct->id, $this->warehouseA->id);
        $this->assertCount(1, $candidates);
        $this->assertEquals(5.0, $candidates[0]['available_base_qty']);
        $this->assertEquals(0.5, $candidates[0]['available_qty']);
    }

    public function test_fully_matched_mixed_unit_advance_is_excluded(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // 2 BOX = 20 kg
        $adv = $this->createAdvanceGrn($this->warehouseA, 2.0, 'approved', 'bill_pending', 'warehouse_advance', true, null, null, 'box');
        $advItem = $adv->items->first();

        // 20 kg base matched
        $billGrn = $this->createAdvanceGrn($this->warehouseA, 20.0, 'approved', 'bill_available', 'normal_purchase');
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $adv->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billGrn->items->first()->id,
            'product_id' => $this->appleProduct->id,
            'matched_qty' => 2.0,
            'matched_unit' => 'box',
            'base_qty' => 20.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        $listRes = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}");
        $listRes->assertOk();
        $this->assertEquals(0, $listRes->json('meta.total'));

        $countsRes = $this->getJson("/api/v1/purchasing/grns/receive-counts?warehouse_id={$this->warehouseA->id}");
        $countsRes->assertOk();
        $this->assertEquals(0, $countsRes->json('data.open_advance'));

        $service = app(AdvanceReceiveReconciliationService::class);
        $candidates = $service->getOpenAdvanceCandidatesForProduct($this->appleProduct->id, $this->warehouseA->id);
        $this->assertEmpty($candidates);
    }

    public function test_multiple_products_with_different_units_produce_correct_grn_totals(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $orange = Product::factory()->create([
            'name' => 'Nagpur Orange',
            'sku' => 'ORG-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        // Item 1: 2 BOX Apples (20 kg base)
        $adv = $this->createAdvanceGrn($this->warehouseA, 2.0, 'approved', 'bill_pending', 'warehouse_advance', true, null, null, 'box');
        $appleItem = $adv->items->first();

        // Item 2: 30 KG Oranges
        $orangeItem = GoodsReceivedItem::create([
            'goods_received_id' => $adv->id,
            'product_id' => $orange->id,
            'received_qty' => 30.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'product_id' => $orange->id,
            'warehouse_id' => $this->warehouseA->id,
            'goods_received_id' => $adv->id,
            'goods_received_item_id' => $orangeItem->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-'.uniqid(),
            'received_at' => now(),
            'total_kg' => 30.0,
            'cost_per_kg' => 40.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->warehouseUser->id,
        ]);

        // Match 15 kg Apples
        $billGrn = $this->createAdvanceGrn($this->warehouseA, 15.0, 'approved', 'bill_available', 'normal_purchase');
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $adv->id,
            'advance_goods_received_item_id' => $appleItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billGrn->items->first()->id,
            'product_id' => $this->appleProduct->id,
            'matched_qty' => 1.5,
            'matched_unit' => 'box',
            'base_qty' => 15.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        $listRes = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}");
        $listRes->assertOk();
        $this->assertEquals(50.0, (float) $listRes->json('data.0.received_base_qty'));
        $this->assertEquals(15.0, (float) $listRes->json('data.0.bill_matched_base_qty'));
        $this->assertEquals(35.0, (float) $listRes->json('data.0.unbilled_base_qty'));
    }

    public function test_two_lines_of_same_product_do_not_double_subtract_legacy_matches(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advance with 2 items of Apples: 60 kg + 40 kg = 100 kg total
        $adv = $this->createAdvanceGrn($this->warehouseA, 60.0);
        $item1 = $adv->items->first();

        $item2 = GoodsReceivedItem::create([
            'goods_received_id' => $adv->id,
            'product_id' => $this->appleProduct->id,
            'received_qty' => 40.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'product_id' => $this->appleProduct->id,
            'warehouse_id' => $this->warehouseA->id,
            'goods_received_id' => $adv->id,
            'goods_received_item_id' => $item2->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-'.uniqid(),
            'received_at' => now(),
            'total_kg' => 40.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->warehouseUser->id,
        ]);

        // Legacy match: 50 kg matched on the product (advance_goods_received_item_id is NULL)
        $billGrn = $this->createAdvanceGrn($this->warehouseA, 50.0, 'approved', 'bill_available', 'normal_purchase');
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $adv->id,
            'advance_goods_received_item_id' => null,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billGrn->items->first()->id,
            'product_id' => $this->appleProduct->id,
            'matched_qty' => 50.0,
            'matched_unit' => 'kg',
            'base_qty' => 50.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        $listRes = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}");
        $listRes->assertOk();
        $this->assertEquals(100.0, (float) $listRes->json('data.0.received_base_qty'));
        $this->assertEquals(50.0, (float) $listRes->json('data.0.bill_matched_base_qty'));
        $this->assertEquals(50.0, (float) $listRes->json('data.0.unbilled_base_qty'));

        // Matching candidates sum to 50 kg total available without double subtraction
        $service = app(AdvanceReceiveReconciliationService::class);
        $candidates = $service->getOpenAdvanceCandidatesForProduct($this->appleProduct->id, $this->warehouseA->id);
        $totalCandidateAvailable = array_sum(array_column($candidates, 'available_base_qty'));
        $this->assertEquals(50.0, $totalCandidateAvailable);
    }

    public function test_item_specific_and_legacy_product_level_matches_do_not_double_count(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advance with 2 items of Apples: 60 kg + 40 kg = 100 kg total
        $adv = $this->createAdvanceGrn($this->warehouseA, 60.0);
        $item1 = $adv->items->first();

        $item2 = GoodsReceivedItem::create([
            'goods_received_id' => $adv->id,
            'product_id' => $this->appleProduct->id,
            'received_qty' => 40.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'product_id' => $this->appleProduct->id,
            'warehouse_id' => $this->warehouseA->id,
            'goods_received_id' => $adv->id,
            'goods_received_item_id' => $item2->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-'.uniqid(),
            'received_at' => now(),
            'total_kg' => 40.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->warehouseUser->id,
        ]);

        $billGrn = $this->createAdvanceGrn($this->warehouseA, 50.0, 'approved', 'bill_available', 'normal_purchase');

        // 1. Explicit item-level match: 30 kg on item1
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $adv->id,
            'advance_goods_received_item_id' => $item1->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billGrn->items->first()->id,
            'product_id' => $this->appleProduct->id,
            'matched_qty' => 30.0,
            'matched_unit' => 'kg',
            'base_qty' => 30.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        // 2. Legacy product-level match: 20 kg unassigned
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $adv->id,
            'advance_goods_received_item_id' => null,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billGrn->items->first()->id,
            'product_id' => $this->appleProduct->id,
            'matched_qty' => 20.0,
            'matched_unit' => 'kg',
            'base_qty' => 20.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        $listRes = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}");
        $listRes->assertOk();
        $this->assertEquals(100.0, (float) $listRes->json('data.0.received_base_qty'));
        $this->assertEquals(50.0, (float) $listRes->json('data.0.bill_matched_base_qty'));
        $this->assertEquals(50.0, (float) $listRes->json('data.0.unbilled_base_qty'));

        $service = app(AdvanceReceiveReconciliationService::class);
        $candidates = $service->getOpenAdvanceCandidatesForProduct($this->appleProduct->id, $this->warehouseA->id);
        $totalCandidateAvailable = array_sum(array_column($candidates, 'available_base_qty'));
        $this->assertEquals(50.0, $totalCandidateAvailable);
    }

    public function test_truly_unconfirmed_advance_is_consistently_excluded(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advance created but warehouse physical intake is pending confirmation (warehouse_receive_pending = true)
        $this->createAdvanceGrn(
            warehouse: $this->warehouseA,
            receivedQty: 100.0,
            status: 'approved',
            billStatus: 'bill_pending',
            receiptType: 'warehouse_advance',
            confirmedStock: false
        );

        $listRes = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}");
        $listRes->assertOk();
        $this->assertEquals(0, $listRes->json('meta.total'));

        $countsRes = $this->getJson("/api/v1/purchasing/grns/receive-counts?warehouse_id={$this->warehouseA->id}");
        $countsRes->assertOk();
        $this->assertEquals(0, $countsRes->json('data.open_advance'));

        $service = app(AdvanceReceiveReconciliationService::class);
        $candidates = $service->getOpenAdvanceCandidatesForProduct($this->appleProduct->id, $this->warehouseA->id);
        $this->assertEmpty($candidates);
    }

    public function test_resource_serialization_query_count_remains_bounded_with_twenty_advances(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Create 20 advances
        for ($i = 0; $i < 20; $i++) {
            $this->createAdvanceGrn($this->warehouseA, 50.0 + $i);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Warm-up request to cache auth and permissions
        $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}&per_page=1")->assertOk();

        DB::flushQueryLog();

        // 1. Fetch 5 items
        $res5 = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}&per_page=5");
        $res5->assertOk();
        $this->assertCount(5, $res5->json('data'));
        $queryCountFor5 = count(DB::getQueryLog());

        DB::flushQueryLog();

        // 2. Fetch 20 items
        $res20 = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}&per_page=20");
        $res20->assertOk();
        $this->assertCount(20, $res20->json('data'));
        $queryCountFor20 = count(DB::getQueryLog());

        // Zero per-row queries: query count for 20 advances MUST EQUAL query count for 5 advances (O(1) complexity)
        $this->assertEquals(
            $queryCountFor5,
            $queryCountFor20,
            "Query count for 20 items ({$queryCountFor20}) differed from 5 items ({$queryCountFor5}), indicating per-row N+1 queries."
        );
        $this->assertLessThanOrEqual(15, $queryCountFor20);
    }

    public function test_legacy_pending_advance_included_only_according_to_verified_definition(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Valid legacy advance: receipt_type IS NULL and purchase_order_id IS NULL and bill_status = bill_pending
        $legacyAdvance = $this->createAdvanceGrn($this->warehouseA, 80.0, 'approved', 'bill_pending', null, true, null);

        // Commercial GRN with receipt_type IS NULL but purchase_order_id IS NOT NULL
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-LEGACY-001',
            'status' => POStatus::Approved,
            'order_date' => now()->toDateString(),
            'created_by' => $this->warehouseUser->id,
        ]);
        $this->createAdvanceGrn($this->warehouseA, 120.0, 'approved', 'bill_pending', null, true, $po->id);

        $listRes = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}");
        $listRes->assertOk();
        $this->assertEquals(1, $listRes->json('meta.total'));
        $this->assertEquals($legacyAdvance->id, $listRes->json('data.0.id'));

        $countsRes = $this->getJson("/api/v1/purchasing/grns/receive-counts?warehouse_id={$this->warehouseA->id}");
        $countsRes->assertOk();
        $this->assertEquals(1, $countsRes->json('data.open_advance'));
    }
    public function test_advances_across_all_dates_28_to_31_aug_are_returned_ordered_newest_first(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advances on 28, 29, 30, 31 Aug
        $adv28 = $this->createAdvanceGrn($this->warehouseA, 50.0, 'approved', 'bill_pending', 'warehouse_advance', true, null);
        $adv28->update(['received_at' => '2026-08-28 10:00:00']);

        $adv29 = $this->createAdvanceGrn($this->warehouseA, 60.0, 'approved', 'bill_pending', 'warehouse_advance', true, null);
        $adv29->update(['received_at' => '2026-08-29 11:00:00']);

        $adv30 = $this->createAdvanceGrn($this->warehouseA, 70.0, 'approved', 'bill_pending', 'warehouse_advance', true, null);
        $adv30->update(['received_at' => '2026-08-30 12:00:00']);

        $adv31 = $this->createAdvanceGrn($this->warehouseA, 80.0, 'approved', 'bill_pending', 'warehouse_advance', true, null);
        $adv31->update(['received_at' => '2026-08-31 09:00:00']);

        $listRes = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}");
        $listRes->assertOk();
        $this->assertEquals(4, $listRes->json('meta.total'));

        $ids = array_column($listRes->json('data'), 'id');
        // Ordered newest first (received_at DESC, id DESC)
        $this->assertEquals([$adv31->id, $adv30->id, $adv29->id, $adv28->id], $ids);

        $countsRes = $this->getJson("/api/v1/purchasing/grns/receive-counts?warehouse_id={$this->warehouseA->id}");
        $countsRes->assertOk();
        $this->assertEquals(4, $countsRes->json('data.open_advance'));
    }

    public function test_fast_advance_intake_via_api_is_automatically_confirmed_and_appears_immediately(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $payload = [
            'warehouse_id' => $this->warehouseA->id,
            'bill_status' => 'bill_pending',
            'received_at' => '2026-08-31',
            'client_submission_id' => (string) \Illuminate\Support\Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->appleProduct->id,
                    'received_qty' => 125.0,
                    'received_unit' => 'kg',
                ],
            ],
        ];

        $res = $this->postJson('/api/v1/purchasing/grns', $payload);
        $res->assertCreated();

        $grnId = $res->json('data.id');
        $grn = GoodsReceived::findOrFail($grnId);

        $this->assertEquals('warehouse_advance', $grn->receipt_type);
        $this->assertEquals('bill_pending', $grn->bill_status);
        $this->assertEquals('approved', $grn->status);

        // Stock batch is automatically confirmed without warehouse_receive_pending = true
        $batch = $grn->stockBatches->first();
        $this->assertNotNull($batch);
        $this->assertFalse((bool) $batch->warehouse_receive_pending);

        // Immediately appears in open advances and receive counts
        $this->assertTrue(GoodsReceived::openWarehouseAdvance($this->warehouseA->id)->whereKey($grnId)->exists());

        $listRes = $this->getJson("/api/v1/purchasing/grns?bill_status=bill_pending&warehouse_id={$this->warehouseA->id}");
        $listRes->assertOk();
        $this->assertEquals(1, $listRes->json('meta.total'));
        $this->assertEquals($grnId, $listRes->json('data.0.id'));

        $countsRes = $this->getJson("/api/v1/purchasing/grns/receive-counts?warehouse_id={$this->warehouseA->id}");
        $countsRes->assertOk();
        $this->assertEquals(1, $countsRes->json('data.open_advance'));
    }
}
