<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReceiveWithBillApiTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private Supplier $supplier;

    private Shop $shop;

    private Warehouse $warehouse;

    private Product $tomato;

    private Product $onion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::firstOrCreate(['name' => 'warehouse_receiver']);
        Permission::firstOrCreate(['name' => 'purchasing.grn.create']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.view']);

        $this->receiver = User::factory()->create(['name' => 'Receiver Bill Guy']);
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->givePermissionTo(['purchasing.grn.create', 'warehouse.receive.view']);

        $this->shop = Shop::factory()->create(['name' => 'Central Hub Shop']);
        $this->warehouse = Warehouse::factory()->create(['name' => 'Central Hub', 'code' => 'CH1', 'is_active' => true]);
        $this->receiver->warehouses()->attach($this->warehouse->id, ['is_default' => true]);

        $this->supplier = Supplier::factory()->create(['name' => 'Kisan Farm Direct']);
        $this->tomato = Product::factory()->create(['name' => 'Tomato Hybrid', 'unit' => 'KG', 'default_warehouse_id' => $this->warehouse->id]);
        $this->onion = Product::factory()->create(['name' => 'Onion Nashik', 'unit' => 'KG', 'default_warehouse_id' => $this->warehouse->id]);
    }

    public function test_pending_bills_list_exposes_grade_and_receipt_data(): void
    {
        Sanctum::actingAs($this->receiver);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Approved,
            'order_date' => now()->toDateString(),
            'purchase_grade' => 'MIXED',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => 10.00,
            'unit_price' => 300.00,
            'price_basis' => 'per_unit',
            'packet_qty' => 10,
            'weight_per_packet' => null,
            'grade' => 'MIXED',
        ]);

        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-PENDING-001',
            'received_by' => $this->receiver->id,
            'received_at' => now()->toDateString(),
            'destination_shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending_approval',
            'bill_status' => 'bill_available',
            'bill_number' => 'BILL-SUPP-999',
            'supplier_id' => $this->supplier->id,
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->tomato->id,
            'purchase_order_item_id' => $poItem->id,
            'received_qty' => 10.00,
            'received_unit' => 'KG',
            'purchased_qty' => 10.00,
            'grade' => 'MIXED',
            'variance' => 0.00,
        ]);

        $response = $this->getJson('/api/v1/purchasing/orders?status=pending');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $po->id)
            ->assertJsonPath('data.0.supplier.name', $this->supplier->name)
            ->assertJsonPath('data.0.purchase_grade', 'MIXED')
            ->assertJsonPath('data.0.bill_number', 'BILL-SUPP-999')
            ->assertJsonPath('data.0.grn_number', 'GRN-PENDING-001')
            ->assertJsonPath('data.0.warehouse_receipt_status', 'pending');

        // Detailed view
        $detailResponse = $this->getJson("/api/v1/purchasing/orders/{$po->id}");
        $detailResponse->assertOk()
            ->assertJsonPath('data.purchase_grade', 'MIXED')
            ->assertJsonPath('data.items.0.grade', 'MIXED')
            ->assertJsonPath('data.items.0.packet_qty', 10)
            ->assertJsonPath('data.items.0.price_basis', 'per_unit');
    }

    public function test_confirming_pending_grn_applies_actual_received_weight_and_preserves_bill(): void
    {
        Sanctum::actingAs($this->receiver);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Received, // Purchase recorded, waiting warehouse receipt confirmation
            'order_date' => now()->toDateString(),
            'purchase_grade' => 'MIXED',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => 10.00,
            'unit_price' => 300.00,
            'price_basis' => 'per_unit',
            'packet_qty' => 10,
            'weight_per_packet' => null,
            'grade' => 'MIXED',
        ]);

        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-TEST-BAG-001',
            'received_by' => $this->receiver->id,
            'received_at' => now()->toDateString(),
            'destination_shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending_approval',
            'bill_status' => 'bill_available',
            'bill_number' => 'BILL-BAG-001',
            'supplier_id' => $this->supplier->id,
        ]);

        $grnItem = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->tomato->id,
            'purchase_order_item_id' => $poItem->id,
            'received_qty' => 10.00, // placeholder from PO
            'received_unit' => 'KG',
            'purchased_qty' => 10.00,
            'grade' => 'MIXED',
            'variance' => 0.00,
        ]);

        $invoice = PurchaseInvoice::create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'shop_id' => $this->shop->id,
            'invoice_number' => 'INV-BAG-001',
            'amount' => 3000.00,
            'status' => 'pending',
        ]);

        // Flutter receiver enters actual 200 KG received
        $payload = [
            'client_submission_id' => 'bill-rcv-sub-001',
            'purchase_order_id' => $po->id,
            'destination_shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_available',
            'bill_number' => 'BILL-BAG-001',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 200.00, // Actual weight 200 KG
                    'received_unit' => 'KG',
                    'grade' => 'MIXED',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/purchasing/grns', $payload);

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $grn->id)
            ->assertJsonPath('data.warehouse_receipt_status', 'received');

        // Confirm DB state
        $grnFresh = $grn->fresh();
        $this->assertEquals('approved', $grnFresh->status);

        // Crucial test: GRN Item has actual 200 KG!
        $grnItemFresh = $grnItem->fresh();
        $this->assertEquals(200.00, (float) $grnItemFresh->received_qty);
        $this->assertEquals('MIXED', $grnItemFresh->grade);

        // Crucial test: StockBatch created with 200 KG and sorting required!
        $batch = StockBatch::where('goods_received_id', $grn->id)->first();
        $this->assertNotNull($batch);
        $this->assertEquals(200.00, (float) $batch->total_kg);
        $this->assertEquals('MIXED', $batch->purchase_grade);
        $this->assertEquals('sort_required', $batch->grading_mode);
        $this->assertEquals('pending', $batch->status->value);

        // Crucial test: Supplier bill / invoice total remains unchanged at 3000.00!
        $invoiceFresh = $invoice->fresh();
        $this->assertEquals(3000.00, (float) $invoiceFresh->amount);

        // Crucial test: Second attempt to receive already received bill is blocked!
        $retryResponse = $this->postJson('/api/v1/purchasing/grns', [
            'client_submission_id' => 'bill-rcv-sub-002',
            'purchase_order_id' => $po->id,
            'received_at' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 200.00,
                ],
            ],
        ]);

        $retryResponse->assertStatus(422);
    }

    public function test_grade_b_and_c_are_preserved_into_stock_batches(): void
    {
        Sanctum::actingAs($this->receiver);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Approved,
            'order_date' => now()->toDateString(),
            'purchase_grade' => 'B',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->onion->id,
            'quantity' => 150.00,
            'unit_price' => 25.00,
            'grade' => 'B',
        ]);

        $payload = [
            'client_submission_id' => 'grade-b-rcv-001',
            'purchase_order_id' => $po->id,
            'destination_shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_available',
            'bill_number' => 'BILL-GRADE-B-01',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->onion->id,
                    'received_qty' => 150.00,
                    'grade' => 'B',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/purchasing/grns', $payload);
        $response->assertCreated();

        $grnId = $response->json('data.id');
        $batch = StockBatch::where('goods_received_id', $grnId)->first();
        $this->assertNotNull($batch);
        $this->assertEquals('B', $batch->purchase_grade);
        $this->assertEquals(150.00, (float) $batch->total_kg);
    }

    public function test_past_date_supplier_query_loads_without_sql_error_for_multiple_suppliers_and_dates(): void
    {
        Sanctum::actingAs($this->receiver);

        $supplierA = Supplier::factory()->create(['name' => 'Alpha Agro Supplies']);
        $supplierB = Supplier::factory()->create(['name' => 'Beta Farms Hub']);

        $date15 = '2026-10-15';
        $date18 = '2026-10-18';
        $date19 = '2026-10-19';
        $date20 = '2026-10-20';
        $emptyDate = '2026-10-01';

        // Order 2026-10-15 (Past date) - Supplier A (Grade A)
        PurchaseOrder::factory()->create([
            'supplier_id' => $supplierA->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Approved,
            'order_date' => $date15,
            'purchase_grade' => 'A',
        ]);

        // Order 2026-10-18 (Yesterday) - Supplier B (Grade B)
        PurchaseOrder::factory()->create([
            'supplier_id' => $supplierB->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Approved,
            'order_date' => $date18,
            'purchase_grade' => 'B',
        ]);

        // Order 2026-10-19 - Supplier A (Grade MIXED)
        $po19A = PurchaseOrder::factory()->create([
            'supplier_id' => $supplierA->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Approved,
            'order_date' => $date19,
            'purchase_grade' => 'MIXED',
        ]);

        // Order 2026-10-19 - Supplier B (Grade C) - Multiple suppliers on same past date
        $po19B = PurchaseOrder::factory()->create([
            'supplier_id' => $supplierB->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Approved,
            'order_date' => $date19,
            'purchase_grade' => 'C',
        ]);

        // Order 2026-10-20 - Supplier B (Grade A)
        PurchaseOrder::factory()->create([
            'supplier_id' => $supplierB->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Approved,
            'order_date' => $date20,
            'purchase_grade' => 'A',
        ]);

        // 1. Verify 2026-10-15
        $resp15 = $this->getJson("/api/v1/purchasing/orders?date={$date15}");
        $resp15->assertOk();
        $this->assertCount(1, $resp15->json('data'));
        $this->assertEquals('Alpha Agro Supplies', $resp15->json('data.0.supplier_name'));

        // 2. Verify 2026-10-18
        $resp18 = $this->getJson("/api/v1/purchasing/orders?date={$date18}");
        $resp18->assertOk();
        $this->assertCount(1, $resp18->json('data'));
        $this->assertEquals('Beta Farms Hub', $resp18->json('data.0.supplier_name'));

        // 3. Verify October 19, 2026 with multiple suppliers (no SQL 'code' error)
        $resp19 = $this->getJson("/api/v1/purchasing/orders?date={$date19}");
        $resp19->assertOk();
        $items19 = $resp19->json('data');
        $this->assertCount(2, $items19);

        $supplierNames19 = collect($items19)->pluck('supplier_name')->all();
        $this->assertContains('Alpha Agro Supplies', $supplierNames19);
        $this->assertContains('Beta Farms Hub', $supplierNames19);

        $grades19 = collect($items19)->pluck('purchase_grade')->all();
        $this->assertContains('MIXED', $grades19);
        $this->assertContains('C', $grades19);

        // 4. Verify October 20, 2026
        $resp20 = $this->getJson("/api/v1/purchasing/orders?date={$date20}");
        $resp20->assertOk();
        $this->assertCount(1, $resp20->json('data'));
        $this->assertEquals('Beta Farms Hub', $resp20->json('data.0.supplier_name'));

        // 5. Verify date with no bills
        $respEmpty = $this->getJson("/api/v1/purchasing/orders?date={$emptyDate}");
        $respEmpty->assertOk();
        $this->assertCount(0, $respEmpty->json('data'));

        // 6. Verify status filters (pending, all) on past date
        $respPending = $this->getJson("/api/v1/purchasing/orders?date={$date19}&status=pending");
        $respPending->assertOk();
        $this->assertCount(2, $respPending->json('data'));

        $respAll = $this->getJson("/api/v1/purchasing/orders?date={$date19}&status=all");
        $respAll->assertOk();
        $this->assertCount(2, $respAll->json('data'));

        // 7. Verify search by supplier name does not fail with 'code' error
        $respSearch = $this->getJson('/api/v1/purchasing/orders?search=Alpha');
        $respSearch->assertOk();
        $this->assertTrue(collect($respSearch->json('data'))->contains('supplier_name', 'Alpha Agro Supplies'));
    }
}
