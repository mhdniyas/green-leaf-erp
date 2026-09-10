<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\POStatus;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MatchFiveRulesApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Warehouse $warehouse;

    private Shop $shop;

    private Supplier $supplier;

    private Product $tomato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->warehouse = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'WH-VEG',
            'is_active' => true,
        ]);

        $this->adminUser->warehouses()->attach([$this->warehouse->id]);
        $this->shop = Shop::factory()->create(['name' => 'Central Shop']);
        $this->supplier = Supplier::factory()->create(['name' => 'Green Farmer']);
        $category = Category::factory()->create();

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'TOM-01',
            'unit' => 'kg',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);
    }

    private function createAdvanceGrn(Product $product, float $qty, string $date = '2026-09-08'): GoodsReceived
    {
        $advGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'grn_number' => 'ADV-'.Str::upper(Str::random(6)),
            'supplier_id' => $this->supplier->id,
            'received_at' => $date,
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'created_by' => $this->adminUser->id,
            'received_by' => $this->adminUser->id,
            'total_amount' => 0,
        ]);

        $advItem = GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => 'kg',
            'unit_price' => 10.0,
            'subtotal' => $qty * 10.0,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'reference' => 'BATCH-ADV-'.Str::upper(Str::random(6)),
            'total_kg' => $qty,
            'available_kg' => $qty,
            'cost_per_kg' => 10.0,
            'received_at' => $date,
            'created_by' => $this->adminUser->id,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->adminUser->id,
            'status' => 'pending',
        ]);

        return $advGrn->fresh(['items', 'stockBatches']);
    }

    private function createApprovedPo(Product $product, float $qty, string $date = '2026-09-08'): PurchaseOrder
    {
        $poNum = 'PO-'.Str::upper(Str::random(6));
        $po = PurchaseOrder::create([
            'po_number' => $poNum,
            'order_number' => $poNum,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => $date,
            'status' => POStatus::Approved,
            'total_amount' => $qty * 10.0,
            'created_by' => $this->adminUser->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit' => 'kg',
            'unit_price' => 10.0,
            'subtotal' => $qty * 10.0,
        ]);

        return $po->fresh(['items']);
    }

    /**
     * TEST 1: Pending Bill approve → inventory increases (stock batch created).
     */
    public function test_rule1_pending_bill_approve_increases_inventory(): void
    {
        $po = $this->createApprovedPo($this->tomato, 10.0);

        $initialBatchCount = StockBatch::where('product_id', $this->tomato->id)->count();
        $this->assertEquals(0, $initialBatchCount);

        // Store GRN (Normal Bill Receive without matching advance)
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/purchasing/grns', [
                'purchase_order_id' => $po->id,
                'warehouse_id' => $this->warehouse->id,
                'receipt_type' => 'normal_purchase',
                'received_at' => '2026-09-08',
                'bill_number' => 'BILL-1001',
                'items' => [
                    [
                        'purchase_order_item_id' => $po->items->first()->id,
                        'product_id' => $this->tomato->id,
                        'received_qty' => 10.0,
                        'received_unit' => 'kg',
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertTrue($response->json('success'));

        // Inventory should increase by 10 kg via new stock batch
        $batches = StockBatch::where('product_id', $this->tomato->id)->get();
        $this->assertCount(1, $batches);
        $this->assertEquals(10.0, (float) $batches->first()->total_kg);
    }

    /**
     * TEST 2: Bill + matching Advance → Advance clears → inventory does NOT increase again.
     */
    public function test_rule2_bill_matching_advance_clears_advance_without_double_stock(): void
    {
        // Step 1: Create Advance Receive (Physical inventory already received: 10 kg)
        $advGrn = $this->createAdvanceGrn($this->tomato, 10.0);
        $this->assertEquals(10.0, (float) StockBatch::where('product_id', $this->tomato->id)->sum('total_kg'));
        $initialBatchCount = StockBatch::where('product_id', $this->tomato->id)->count();

        // Step 2: Receive Bill for 10 kg and match Advance
        $po = $this->createApprovedPo($this->tomato, 10.0);
        $advItem = $advGrn->items->first();

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/purchasing/grns', [
                'purchase_order_id' => $po->id,
                'warehouse_id' => $this->warehouse->id,
                'receipt_type' => 'normal_purchase',
                'received_at' => '2026-09-08',
                'bill_number' => 'BILL-1002',
                'items' => [
                    [
                        'purchase_order_item_id' => $po->items->first()->id,
                        'product_id' => $this->tomato->id,
                        'received_qty' => 10.0,
                        'received_unit' => 'kg',
                    ],
                ],
                'advance_matches' => [
                    [
                        'advance_goods_received_id' => $advGrn->id,
                        'advance_goods_received_item_id' => $advItem->id,
                        'purchase_order_item_id' => $po->items->first()->id,
                        'product_id' => $this->tomato->id,
                        'matched_qty' => 10.0,
                        'unit' => 'kg',
                        'base_qty' => 10.0,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        // Advance is cleared
        $this->assertEquals('bill_available', $advGrn->fresh()->bill_status);

        // Total inventory should NOT increase (no new stock batches created, total remains 10 kg)
        $newBatchCount = StockBatch::where('product_id', $this->tomato->id)->count();
        $this->assertEquals($initialBatchCount, $newBatchCount);
        $this->assertEquals(10.0, (float) StockBatch::where('product_id', $this->tomato->id)->sum('total_kg'));
    }

    /**
     * TEST 3: Advance 4 + Bill 10 → match 4 → only 6 new stock created.
     */
    public function test_rule3_advance_4_bill_10_match_4_only_6_new_stock(): void
    {
        // Create Advance Receive of 4 kg
        $advGrn = $this->createAdvanceGrn($this->tomato, 4.0);
        $advItem = $advGrn->items->first();

        // Create PO for 10 kg
        $po = $this->createApprovedPo($this->tomato, 10.0);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/purchasing/grns', [
                'purchase_order_id' => $po->id,
                'warehouse_id' => $this->warehouse->id,
                'receipt_type' => 'normal_purchase',
                'received_at' => '2026-09-08',
                'bill_number' => 'BILL-1003',
                'items' => [
                    [
                        'purchase_order_item_id' => $po->items->first()->id,
                        'product_id' => $this->tomato->id,
                        'received_qty' => 10.0,
                        'received_unit' => 'kg',
                    ],
                ],
                'advance_matches' => [
                    [
                        'advance_goods_received_id' => $advGrn->id,
                        'advance_goods_received_item_id' => $advItem->id,
                        'purchase_order_item_id' => $po->items->first()->id,
                        'product_id' => $this->tomato->id,
                        'matched_qty' => 4.0,
                        'unit' => 'kg',
                        'base_qty' => 4.0,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        // Only 6 kg of new stock should be added (4 from advance + 6 new = 10 total)
        $totalStock = (float) StockBatch::where('product_id', $this->tomato->id)->sum('total_kg');
        $this->assertEquals(10.0, $totalStock);

        // Verify there is 1 advance batch (4kg) and 1 new batch (6kg)
        $batches = StockBatch::where('product_id', $this->tomato->id)->orderBy('id')->get();
        $this->assertCount(2, $batches);
        $this->assertEquals(4.0, (float) $batches[0]->total_kg);
        $this->assertEquals(6.0, (float) $batches[1]->total_kg);
    }

    /**
     * TEST 4: Advance with no Bill → API returns as `bill_status = bill_pending` / `BILL PENDING` (No Bill Matched).
     */
    public function test_rule4_advance_with_no_bill_returns_bill_pending(): void
    {
        $advGrn = $this->createAdvanceGrn($this->tomato, 8.0);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/purchasing/grns/{$advGrn->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('bill_pending', $data['bill_status']);
        $this->assertEquals('BILL PENDING', $data['bill_status_label']);
        $this->assertTrue($data['is_bill_pending']);
        $this->assertEquals(8.0, (float) $data['unbilled_base_qty']);
        $this->assertEquals(0.0, (float) $data['bill_matched_base_qty']);
    }

    /**
     * TEST 5: Loadout with no Bill or Advance → API returns candidate metadata allowing Flutter to show `No Bill Created`.
     */
    public function test_rule5_candidate_with_no_bill_or_advance_returns_unmatched_status(): void
    {
        // Create approved PO without any advance or GRN
        $po = $this->createApprovedPo($this->tomato, 15.0);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/purchasing/grns/advance-match-candidates?warehouse_id='.$this->warehouse->id);

        $response->assertStatus(200);
        $data = $response->json('data');

        $candidate = collect($data)->firstWhere('purchase_order_id', $po->id);
        $this->assertNotNull($candidate);

        // Reconcile status should be unmatched, 0% coverage, 0 matched base qty
        $this->assertEquals('unmatched', $candidate['reconciliation_status']);
        $this->assertEquals(0.0, (float) $candidate['overall_coverage_percentage']);
        $this->assertEquals(0.0, (float) $candidate['total_matched_base_qty']);
        $this->assertEquals(15.0, (float) $candidate['total_bill_base_qty']);
        $this->assertFalse($candidate['has_advance_match']);
    }
}
