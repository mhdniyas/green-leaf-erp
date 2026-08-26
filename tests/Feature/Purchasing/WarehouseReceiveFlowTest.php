<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseReceiveFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $admin;

    private Supplier $supplier;

    private Shop $warehouse;

    private Product $productA;

    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::firstOrCreate(['name' => 'warehouse_receiver']);
        Role::firstOrCreate(['name' => 'admin']);

        // Ensure permissions exist
        Permission::firstOrCreate(['name' => 'purchasing.order.view']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.view']);
        Permission::firstOrCreate(['name' => 'purchasing.grn.view']);
        Permission::firstOrCreate(['name' => 'purchasing.grn.create']);

        $this->receiver = User::factory()->create(['name' => 'John Receiver']);
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->givePermissionTo(['purchasing.order.view', 'warehouse.receive.view', 'purchasing.grn.view', 'purchasing.grn.create']);

        $this->admin = User::factory()->create(['name' => 'Admin Boss']);
        $this->admin->assignRole('admin');

        $this->supplier = Supplier::factory()->create(['name' => 'Muneer Wayanad']);
        $this->warehouse = Shop::factory()->create(['name' => 'Central Hub']);

        $this->productA = Product::factory()->create(['name' => 'Apple Royal', 'unit' => 'KG']);
        $this->productB = Product::factory()->create(['name' => 'Orange Nagpur', 'unit' => 'KG']);
    }

    public function test_can_fetch_purchase_order_details_by_po_number_and_id(): void
    {
        Sanctum::actingAs($this->receiver);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->warehouse->id,
            'po_number' => 'PO-C3E4',
            'status' => POStatus::Approved,
            'order_date' => now()->toDateString(),
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->productA->id,
            'quantity' => 100.00,
            'unit_price' => 50.00,
        ]);

        // 1. By po_number
        $response1 = $this->getJson("/api/v1/purchasing/orders/{$po->po_number}");
        $response1->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.po_number', 'PO-C3E4')
            ->assertJsonPath('data.supplier.name', 'Muneer Wayanad')
            ->assertJsonCount(1, 'data.items');

        // 2. By numeric ID
        $response2 = $this->getJson("/api/v1/purchasing/orders/{$po->id}");
        $response2->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.po_number', 'PO-C3E4');
    }

    public function test_warehouse_receive_bill_pending_creates_grn_and_inventory_without_fake_invoice(): void
    {
        Sanctum::actingAs($this->receiver);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->warehouse->id,
            'po_number' => 'PO-TEST-001',
            'status' => POStatus::Approved,
            'order_date' => now()->toDateString(),
        ]);

        $poItem1 = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->productA->id,
            'quantity' => 50.00,
            'unit_price' => 40.00,
        ]);

        $poItem2 = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->productB->id,
            'quantity' => 30.00,
            'unit_price' => 60.00,
        ]);

        $payload = [
            'purchase_order_id' => $po->id,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_pending',
            'notes' => 'Physical load received in evening, bill will come tomorrow',
            'transport_cost' => 500.00,
            'labour_cost' => 200.00,
            'items' => [
                [
                    'purchase_order_item_id' => $poItem1->id,
                    'product_id' => $this->productA->id,
                    'received_qty' => 50.00,
                ],
                [
                    'purchase_order_item_id' => $poItem2->id,
                    'product_id' => $this->productB->id,
                    'received_qty' => 30.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/purchasing/grns', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bill_status', 'bill_pending')
            ->assertJsonPath('data.is_bill_pending', true)
            ->assertJsonPath('data.status_label', 'BILL PENDING');

        $grnId = $response->json('data.id');

        // Verify inventory StockBatch created immediately
        $this->assertDatabaseHas('stock_batches', [
            'goods_received_id' => $grnId,
            'product_id' => $this->productA->id,
            'total_kg' => 50.00,
        ]);
        $this->assertDatabaseHas('stock_batches', [
            'goods_received_id' => $grnId,
            'product_id' => $this->productB->id,
            'total_kg' => 30.00,
        ]);

        // Verify NO fake PurchaseInvoice created
        $this->assertDatabaseMissing('purchase_invoices', [
            'goods_received_id' => $grnId,
        ]);
    }

    public function test_later_bill_linking_to_existing_grn_does_not_duplicate_stock(): void
    {
        Sanctum::actingAs($this->receiver);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->warehouse->id,
            'po_number' => 'PO-LINK-001',
            'status' => POStatus::Approved,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->productA->id,
            'quantity' => 100.00,
            'unit_price' => 50.00,
        ]);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $po->id,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_pending',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->productA->id,
                    'received_qty' => 100.00,
                ],
            ],
        ]);
        $grnId = $response->json('data.id');

        // StockBatches count before link
        $initialBatchCount = StockBatch::where('goods_received_id', $grnId)->count();
        $this->assertEquals(1, $initialBatchCount);
        $this->assertEquals(100.00, (float) StockBatch::where('goods_received_id', $grnId)->first()->total_kg);

        // Later purchaser links real vendor bill
        Sanctum::actingAs($this->admin);

        $linkResponse = $this->postJson("/api/v1/purchasing/grns/{$grnId}/link-bill", [
            'invoice_number' => 'INV-WAYANAD-7788',
            'amount' => 5000.00,
            'notes' => 'Linked physical receipt to official GST invoice',
        ]);

        $linkResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bill_status', 'bill_available')
            ->assertJsonPath('data.is_bill_pending', false)
            ->assertJsonPath('data.bill_number', 'INV-WAYANAD-7788');

        // Check PurchaseInvoice was created
        $this->assertDatabaseHas('purchase_invoices', [
            'goods_received_id' => $grnId,
            'invoice_number' => 'INV-WAYANAD-7788',
            'amount' => 5000.00,
        ]);

        // Stock batches MUST NOT be duplicated
        $this->assertEquals($initialBatchCount, StockBatch::where('goods_received_id', $grnId)->count());
        $this->assertEquals(100.00, (float) StockBatch::where('goods_received_id', $grnId)->first()->total_kg);
    }

    public function test_updating_item_quantities_adjusts_stock_by_difference_only_and_records_audit(): void
    {
        Sanctum::actingAs($this->receiver);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->warehouse->id,
            'po_number' => 'PO-DIFF-001',
            'status' => POStatus::Approved,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->productA->id,
            'quantity' => 100.00,
            'unit_price' => 50.00,
        ]);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $po->id,
            'received_at' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->productA->id,
                    'received_qty' => 100.00,
                ],
            ],
        ]);
        $response->assertCreated();
        $grnId = $response->json('data.id');
        $grn = GoodsReceived::where('id', $grnId)->orWhere('public_uuid', $grnId)->firstOrFail();
        $grnItem = $grn->items()->firstOrFail();

        $batch = StockBatch::where('goods_received_id', $grnId)->first();
        $this->assertEquals(100.00, (float) $batch->total_kg);

        // Edit received quantity from 100 to 115 (+15 difference)
        Sanctum::actingAs($this->admin);

        $updateResponse = $this->putJson("/api/v1/purchasing/grns/{$grnId}/items", [
            'items' => [
                [
                    'id' => $grnItem->id,
                    'received_qty' => 115.00,
                ],
            ],
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('success', true);

        // Batch total must now be 115.00 (adjusted by +15)
        $batch->refresh();
        $this->assertEquals(115.00, (float) $batch->total_kg);

        // Audit activity logged
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $grnId,
            'subject_type' => GoodsReceived::class,
            'description' => 'goods_received.quantities_adjusted',
        ]);
    }

    public function test_admin_cashbook_inventory_page_loads_successfully(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin/cashbook/inventory');
        $response->assertOk()
            ->assertSee('Cashbook Inventory')
            ->assertSee('Bill Pending')
            ->assertSee('Loadout Not Billed');
    }

    public function test_advance_goods_receipt_without_po_creates_bill_pending_receipt_and_stock_batches_for_multiple_products(): void
    {
        Sanctum::actingAs($this->receiver);

        // Advance goods receive with 2 products, no PO, no fake invoice
        $response = $this->postJson('/api/v1/purchasing/grns', [
            'destination_shop_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_pending',
            'notes' => 'Received from direct grower truck before bill',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'received_qty' => 250.00,
                ],
                [
                    'product_id' => $this->productB->id,
                    'received_qty' => 120.00,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bill_status', 'bill_pending')
            ->assertJsonPath('data.is_bill_pending', true)
            ->assertJsonPath('data.status_label', 'BILL PENDING')
            ->assertJsonCount(2, 'data.items');

        $grnId = $response->json('data.id');

        // Check persistent Bill Pending GRN
        $grn = GoodsReceived::findOrFail($grnId);
        $this->assertEquals('bill_pending', $grn->bill_status);
        $this->assertNull($grn->purchase_order_id);
        $this->assertEquals($this->warehouse->id, $grn->destination_shop_id);

        // Check NO fake PurchaseInvoice created
        $this->assertDatabaseMissing('purchase_invoices', [
            'goods_received_id' => $grnId,
        ]);

        // Check Inventory / StockBatches created immediately
        $batches = StockBatch::where('goods_received_id', $grnId)->get();
        $this->assertCount(2, $batches);
        $this->assertEquals(250.00, (float) $batches->where('product_id', $this->productA->id)->first()->total_kg);
        $this->assertEquals(120.00, (float) $batches->where('product_id', $this->productB->id)->first()->total_kg);
    }

    public function test_pending_receipt_suggestions_and_matching_bill_marks_receipt_cleared_with_audit_and_no_stock_duplication(): void
    {
        Sanctum::actingAs($this->receiver);

        // 1. Create advance receipt
        $grnResponse = $this->postJson('/api/v1/purchasing/grns', [
            'destination_shop_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_pending',
            'notes' => 'Direct harvest load',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'received_qty' => 300.00,
                ],
            ],
        ]);
        $grnResponse->assertCreated();
        $grnId = $grnResponse->json('data.id');

        // 2. Purchaser queries pending suggestions
        Sanctum::actingAs($this->admin);

        $suggestResponse = $this->getJson("/api/v1/purchasing/grns/pending-suggestions?destination_shop_id={$this->warehouse->id}&product_ids[]={$this->productA->id}");
        $suggestResponse->assertOk()
            ->assertJsonPath('success', true);
        $suggestedIds = collect($suggestResponse->json('data'))->pluck('id')->all();
        $this->assertContains($grnId, $suggestedIds);

        // Count stock batches before matching
        $batchCountBefore = StockBatch::where('goods_received_id', $grnId)->count();
        $totalStockBefore = StockBatch::where('goods_received_id', $grnId)->sum('total_kg');
        $this->assertEquals(1, $batchCountBefore);
        $this->assertEquals(300.00, (float) $totalStockBefore);

        // 3. Match Bill later
        $matchResponse = $this->postJson("/api/v1/purchasing/grns/{$grnId}/match-bill", [
            'invoice_number' => 'INV-SUP-9988',
            'supplier_id' => $this->supplier->id,
            'amount' => 15000.00,
            'notes' => 'Matched against vendor invoice INV-SUP-9988',
        ]);

        $matchResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bill_status', 'bill_available')
            ->assertJsonPath('data.is_bill_pending', false)
            ->assertJsonPath('data.status_label', 'RECEIVED WITH BILL')
            ->assertJsonPath('data.bill_number', 'INV-SUP-9988');

        // 4. Verify status updated and matched_by / matched_at set
        $grn = GoodsReceived::findOrFail($grnId);
        $this->assertEquals('bill_available', $grn->bill_status);
        $this->assertEquals('INV-SUP-9988', $grn->bill_number);
        $this->assertEquals($this->admin->id, $grn->matched_by);
        $this->assertNotNull($grn->matched_at);

        // 5. Verify PurchaseInvoice attached
        $this->assertDatabaseHas('purchase_invoices', [
            'goods_received_id' => $grnId,
            'invoice_number' => 'INV-SUP-9988',
            'supplier_id' => $this->supplier->id,
            'amount' => 15000.00,
        ]);

        // 6. Verify inventory was NOT duplicated
        $batchCountAfter = StockBatch::where('goods_received_id', $grnId)->count();
        $totalStockAfter = StockBatch::where('goods_received_id', $grnId)->sum('total_kg');
        $this->assertEquals($batchCountBefore, $batchCountAfter);
        $this->assertEquals($totalStockBefore, $totalStockAfter);

        // 7. Verify activity log
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $grnId,
            'subject_type' => GoodsReceived::class,
            'description' => 'goods_received.bill_matched',
        ]);
    }
}
