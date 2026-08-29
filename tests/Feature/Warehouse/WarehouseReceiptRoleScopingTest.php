<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseReceiptRoleScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $warehouseUser1;

    private User $warehouseUser2;

    private User $warehouseUserUnassigned;

    private Warehouse $warehouse1;

    private Warehouse $warehouse2;

    private Supplier $supplier;

    private Product $product1;

    private Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->warehouse1 = Warehouse::factory()->create(['name' => 'North Hub', 'code' => 'NH1', 'is_active' => true]);
        $this->warehouse2 = Warehouse::factory()->create(['name' => 'South Hub', 'code' => 'SH2', 'is_active' => true]);

        $category = Category::factory()->create(['name' => 'Produce']);
        $this->product1 = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'North Onion',
            'sku' => 'ONION-N',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse1->id,
        ]);
        $this->product2 = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'South Potato',
            'sku' => 'POTATO-S',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse2->id,
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Fresh Agro']);

        $this->admin = User::factory()->create(['name' => 'Super Admin']);
        $this->admin->assignRole('admin');

        $this->warehouseUser1 = User::factory()->create(['name' => 'Receiver North']);
        $this->warehouseUser1->assignRole('warehouse_receiver');
        $this->warehouseUser1->warehouses()->attach($this->warehouse1->id, ['is_default' => true]);

        $this->warehouseUser2 = User::factory()->create(['name' => 'Receiver South']);
        $this->warehouseUser2->assignRole('warehouse_receiver');
        $this->warehouseUser2->warehouses()->attach($this->warehouse2->id, ['is_default' => true]);

        $this->warehouseUserUnassigned = User::factory()->create(['name' => 'Receiver Unassigned']);
        $this->warehouseUserUnassigned->assignRole('warehouse_receiver');
    }

    /** 1. Admin sees all warehouse receipts */
    public function test_admin_sees_all_warehouse_receipts(): void
    {
        $this->createGrn($this->warehouse1, $this->product1, 'normal_purchase', 'pending_approval');
        $this->createGrn($this->warehouse2, $this->product2, 'normal_purchase', 'pending_approval');
        $this->createGrn($this->warehouse1, $this->product1, 'warehouse_advance', 'approved');
        $this->createGrn($this->warehouse2, $this->product2, 'warehouse_advance', 'approved');

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/purchasing/grns');
        $response->assertOk();
        $this->assertCount(4, $response->json('data'));
    }

    /** 2. Warehouse user sees Pending for assigned warehouse */
    public function test_warehouse_user_sees_pending_for_assigned_warehouse(): void
    {
        $grn1 = $this->createGrn($this->warehouse1, $this->product1, 'normal_purchase', 'pending_approval');
        $this->createGrn($this->warehouse2, $this->product2, 'normal_purchase', 'pending_approval');

        Sanctum::actingAs($this->warehouseUser1);

        $response = $this->getJson('/api/v1/purchasing/grns?receipt_status=pending');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($grn1->grn_number, $response->json('data.0.grn_number'));
    }

    /** 3. Warehouse user sees warehouse_advance for assigned warehouse */
    public function test_warehouse_user_sees_warehouse_advance_for_assigned_warehouse(): void
    {
        $adv1 = $this->createGrn($this->warehouse1, $this->product1, 'warehouse_advance', 'approved');
        $this->createGrn($this->warehouse2, $this->product2, 'warehouse_advance', 'approved');

        Sanctum::actingAs($this->warehouseUser1);

        $response = $this->getJson('/api/v1/purchasing/grns?receipt_type=warehouse_advance');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($adv1->grn_number, $response->json('data.0.grn_number'));
    }

    /** 4. Warehouse user cannot see another warehouse's records */
    public function test_warehouse_user_cannot_see_another_warehouse_records(): void
    {
        $this->createGrn($this->warehouse2, $this->product2, 'normal_purchase', 'pending_approval');
        $this->createGrn($this->warehouse2, $this->product2, 'warehouse_advance', 'approved');

        Sanctum::actingAs($this->warehouseUser1);

        $response = $this->getJson('/api/v1/purchasing/grns');
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    /** 5. Receipt type filtering works for warehouse role */
    public function test_receipt_type_filtering_works_for_warehouse_role(): void
    {
        $normal = $this->createGrn($this->warehouse1, $this->product1, 'normal_purchase', 'pending_approval');
        $advance = $this->createGrn($this->warehouse1, $this->product1, 'warehouse_advance', 'approved');

        Sanctum::actingAs($this->warehouseUser1);

        // Filter normal_purchase
        $resNormal = $this->getJson('/api/v1/purchasing/grns?receipt_type=normal_purchase');
        $resNormal->assertOk();
        $this->assertCount(1, $resNormal->json('data'));
        $this->assertSame($normal->grn_number, $resNormal->json('data.0.grn_number'));

        // Filter warehouse_advance
        $resAdvance = $this->getJson('/api/v1/purchasing/grns?receipt_type=warehouse_advance');
        $resAdvance->assertOk();
        $this->assertCount(1, $resAdvance->json('data'));
        $this->assertSame($advance->grn_number, $resAdvance->json('data.0.grn_number'));
    }

    /** 6. Counts and list use identical warehouse scope */
    public function test_counts_and_list_use_identical_warehouse_scope(): void
    {
        $this->createGrn($this->warehouse1, $this->product1, 'normal_purchase', 'pending_approval');
        $this->createGrn($this->warehouse1, $this->product1, 'warehouse_advance', 'approved');
        $this->createGrn($this->warehouse2, $this->product2, 'normal_purchase', 'pending_approval');
        $this->createGrn($this->warehouse2, $this->product2, 'warehouse_advance', 'approved');

        Sanctum::actingAs($this->warehouseUser1);

        $countsRes = $this->getJson('/api/v1/purchasing/grns/receive-counts');
        $countsRes->assertOk();
        $countsData = $countsRes->json('data');

        $this->assertEquals(1, $countsData['open_advance']);

        $advanceList = $this->getJson('/api/v1/purchasing/grns?receipt_type=warehouse_advance');
        $advanceList->assertOk();
        $this->assertCount(1, $advanceList->json('data'));
    }

    /** 7. Unassigned warehouse user sees zero records and cannot access foreign warehouses */
    public function test_unassigned_warehouse_user_sees_zero_records(): void
    {
        $this->createGrn($this->warehouse1, $this->product1, 'normal_purchase', 'pending_approval');
        $this->createGrn($this->warehouse2, $this->product2, 'warehouse_advance', 'approved');

        Sanctum::actingAs($this->warehouseUserUnassigned);

        $response = $this->getJson('/api/v1/purchasing/grns');
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));

        $countsRes = $this->getJson('/api/v1/purchasing/grns/receive-counts');
        $countsRes->assertOk();
        $this->assertEquals(0, $countsRes->json('data.open_advance'));
        $this->assertEquals(0, $countsRes->json('data.pending_total'));
    }

    private function createGrn(Warehouse $wh, Product $product, string $receiptType, string $status): GoodsReceived
    {
        $po = null;
        if ($receiptType === 'normal_purchase') {
            $po = PurchaseOrder::factory()->create([
                'supplier_id' => $this->supplier->id,
                'status' => 'approved',
                'order_date' => now(),
            ]);
            $poItem = PurchaseOrderItem::factory()->create([
                'purchase_order_id' => $po->id,
                'product_id' => $product->id,
                'quantity' => 20,
                'unit_price' => 15,
            ]);
        }

        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po?->id,
            'warehouse_id' => $wh->id,
            'receipt_type' => $receiptType,
            'status' => $status,
            'bill_status' => 'bill_pending',
            'received_at' => now(),
        ]);

        $item = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $po ? $po->items->first()->id : null,
            'product_id' => $product->id,
            'received_qty' => 20,
            'received_unit' => 'kg',
        ]);

        StockBatch::factory()->create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'product_id' => $product->id,
            'warehouse_id' => $wh->id,
            'total_kg' => 20,
            'warehouse_receive_pending' => $status !== 'approved',
        ]);

        return $grn;
    }
}
