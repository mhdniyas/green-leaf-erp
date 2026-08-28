<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\WarehouseReceiptStateResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseReceiptContractTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private Warehouse $warehouse;

    private Warehouse $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->warehouse = Warehouse::factory()->create();
        $this->other = Warehouse::factory()->create();
        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->warehouses()->attach($this->warehouse);
        Sanctum::actingAs($this->receiver);
    }

    private function receipt(string $status = 'pending_approval', ?int $warehouseId = null): GoodsReceived
    {
        $product = Product::factory()->create(['default_warehouse_id' => $warehouseId ?? $this->warehouse->id, 'unit' => 'kg']);
        $order = PurchaseOrder::factory()->create(['status' => 'received']);
        $item = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 10, 'unit_price' => 20, 'price_basis' => 'per_kg']);
        $receipt = GoodsReceived::factory()->create(['purchase_order_id' => $order->id, 'status' => $status, 'received_at' => now()->toDateString(), 'bill_status' => 'bill_available']);
        GoodsReceivedItem::factory()->create(['goods_received_id' => $receipt->id, 'purchase_order_item_id' => $item->id, 'product_id' => $product->id, 'received_qty' => 10]);

        return $receipt;
    }

    private function batch(GoodsReceived $receipt, bool $pending = true, ?int $warehouseId = null): StockBatch
    {
        return StockBatch::factory()->create([
            'goods_received_id' => $receipt->id,
            'goods_received_item_id' => $receipt->items->first()->id,
            'product_id' => $receipt->items->first()->product_id,
            'warehouse_id' => $warehouseId ?? $this->warehouse->id,
            'warehouse_receive_pending' => $pending,
            'warehouse_confirmed_at' => $pending ? null : now(),
            'warehouse_confirmed_by' => $pending ? null : $this->receiver->id,
            'received_at' => now()->toDateString(),
        ]);
    }

    public function test_submitted_invoice_without_batches_stays_pending_and_reads_do_not_mutate(): void
    {
        $receipt = $this->receipt();
        PurchaseInvoice::factory()->create(['goods_received_id' => $receipt->id]);
        $before = $this->counts();
        $this->getJson('/api/v1/purchasing/orders?status=pending')->assertOk()
            ->assertJsonPath('data.0.id', $receipt->purchase_order_id)
            ->assertJsonPath('data.0.status', 'received')
            ->assertJsonPath('data.0.receipt_status', 'pending')
            ->assertJsonPath('data.0.can_create_receipt', false);
        $this->getJson('/api/v1/purchasing/grns?receipt_status=received')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/purchasing/grns?receipt_status=pending')->assertOk()
            ->assertJsonPath('data.0.receipt_status_label', 'PENDING WAREHOUSE RECEIVE')
            ->assertJsonPath('data.0.warehouse_receive_pending', true)
            ->assertJsonPath('data.0.inventory_posted', false)
            ->assertJsonPath('data.0.bill_status_label', 'BILL AVAILABLE');
        $this->getJson('/api/v1/purchasing/grns/'.$receipt->id)->assertOk()->assertJsonPath('data.inventory_posted', false);
        $this->getJson('/warehouse-receiver/tab/pending?date='.now()->toDateString())->assertOk()->assertJsonPath('pending_grns.0.id', $receipt->id);
        $this->assertSame($before, $this->counts());
    }

    public function test_approved_pending_batches_and_closed_po_stay_pending_until_every_batch_is_confirmed(): void
    {
        $receipt = $this->receipt('approved');
        $receipt->purchaseOrder->update(['status' => 'closed']);
        $this->batch($receipt, false);
        $pending = $this->batch($receipt);
        $this->getJson('/api/v1/purchasing/orders?status=pending')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/purchasing/grns?receipt_status=received')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/purchasing/grns?receipt_status=pending')->assertOk()->assertJsonPath('data.0.inventory_posted', false);
        $pending->update(['warehouse_receive_pending' => false, 'warehouse_confirmed_at' => now(), 'warehouse_confirmed_by' => $this->receiver->id]);
        $this->getJson('/api/v1/purchasing/orders?status=pending')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/purchasing/grns?receipt_status=received')->assertOk()
            ->assertJsonPath('data.0.receipt_status', 'received')->assertJsonPath('data.0.inventory_posted', true)
            ->assertJsonPath('data.0.warehouse_confirmed_by', $this->receiver->id)
            ->assertJsonPath('data.0.bill_status', 'bill_pending');
        $this->getJson('/warehouse-receiver/tab/pending?date='.now()->toDateString())->assertOk()->assertJsonCount(0, 'pending_batches')->assertJsonCount(0, 'pending_grns');
    }

    public function test_approval_without_batches_and_confirmation_without_approval_are_not_received(): void
    {
        $approved = $this->receipt('approved');
        $unapproved = $this->receipt();
        $this->batch($unapproved, false);
        $this->getJson('/api/v1/purchasing/grns?receipt_status=received')->assertOk()->assertJsonCount(0, 'data');
        $this->assertFalse(app(WarehouseReceiptStateResolver::class)->forReceipt($approved)['inventory_posted']);
    }

    public function test_regular_and_addon_receipts_share_receipt_state_but_not_bill_state(): void
    {
        $regular = $this->receipt();
        $addon = $this->receipt();
        $addon->update(['is_extra' => true]);
        PurchaseInvoice::factory()->create(['goods_received_id' => $regular->id]);
        $response = $this->getJson('/api/v1/purchasing/grns?receipt_status=pending')->assertOk()->assertJsonCount(2, 'data');
        $states = collect($response->json('data'))->keyBy('id');
        $this->assertSame('pending', $states[$regular->id]['receipt_status']);
        $this->assertSame('pending', $states[$addon->id]['receipt_status']);
        $this->assertSame('bill_available', $states[$regular->id]['bill_status']);
        $this->assertSame('bill_pending', $states[$addon->id]['bill_status']);
        $this->getJson('/api/v1/purchasing/orders?status=pending')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_actual_warehouse_overrides_product_default_and_scope_applies_without_selection(): void
    {
        $own = $this->receipt('approved');
        $this->batch($own, false);
        $foreign = $this->receipt('approved');
        $this->batch($foreign, false, $this->other->id);
        $mixed = $this->receipt('approved');
        $this->batch($mixed, false);
        $this->batch($mixed, true, $this->other->id);
        foreach (['', '&warehouse_id='.$this->warehouse->id] as $selection) {
            $this->getJson('/api/v1/purchasing/grns?receipt_status=received'.$selection)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
            $this->getJson('/api/v1/purchasing/orders?status=pending'.$selection)->assertOk()->assertJsonCount(0, 'data');
        }
        $this->getJson('/api/v1/purchasing/grns/'.$foreign->id)->assertForbidden();
        $this->getJson('/api/v1/purchasing/orders/'.$foreign->purchase_order_id)->assertForbidden();
        $this->getJson('/api/v1/purchasing/grns?warehouse_id='.$this->other->id)->assertForbidden();
        $this->getJson('/warehouse-receiver/tab/pending?warehouse_id='.$this->other->id)->assertForbidden();
    }

    public function test_unassigned_and_unauthorized_users_cannot_read_receipts(): void
    {
        $receipt = $this->receipt();
        $this->receiver->warehouses()->detach();
        $this->getJson('/api/v1/purchasing/orders?status=pending')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/purchasing/grns?receipt_status=pending')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/purchasing/grns/'.$receipt->id)->assertForbidden();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/purchasing/orders?status=pending')->assertForbidden();
        $this->getJson('/api/v1/purchasing/grns?receipt_status=received')->assertForbidden();
    }

    public function test_legacy_exact_batch_link_works_without_matching_a_different_grn_or_conflicting_fk(): void
    {
        $receipt = $this->receipt('approved');
        $batch = $this->batch($receipt, false);
        $batch->update(['goods_received_id' => null, 'notes' => 'Auto-created from GRN: '.$receipt->grn_number]);
        $this->getJson('/api/v1/purchasing/grns?receipt_status=received')->assertOk()->assertJsonCount(1, 'data');
        $batch->update(['notes' => 'Auto-created from GRN: '.$receipt->grn_number.'0']);
        $this->getJson('/api/v1/purchasing/grns?receipt_status=received')->assertOk()->assertJsonCount(0, 'data');
        $other = $this->receipt('approved');
        $batch->update(['goods_received_id' => $other->id, 'notes' => 'Auto-created from GRN: '.$receipt->grn_number]);
        $this->assertSame('pending', app(WarehouseReceiptStateResolver::class)->forReceipt($receipt)['receipt_status']);
        $batch->delete();
        $this->assertSame('pending', app(WarehouseReceiptStateResolver::class)->forReceipt($other)['receipt_status']);
    }

    public function test_existing_single_receive_and_receive_all_still_confirm_without_duplicate_artifacts(): void
    {
        $this->actingAs($this->receiver, 'web');
        $receipt = $this->receipt();
        $item = $receipt->items->first();
        $this->post(route('warehouse.receiver.process-receive-grn', $receipt), [
            'warehouse_id' => $this->warehouse->id,
            'items' => [$item->id => ['warehouse_id' => $this->warehouse->id, 'received_qty' => 10, 'discrepancy_type' => 'none']],
        ])->assertRedirect();
        $this->assertSame('received', app(WarehouseReceiptStateResolver::class)->forReceipt($receipt->fresh())['receipt_status']);
        $second = $this->receipt();
        $second->update(['purchase_grade' => 'B', 'is_extra' => true]);
        $second->items()->update(['grade' => 'B']);
        $this->post(route('warehouse.receiver.process-receive-grns.all'), ['date' => now()->toDateString()])->assertRedirect();
        $this->assertSame('received', app(WarehouseReceiptStateResolver::class)->forReceipt($second->fresh())['receipt_status']);
        $this->assertSame(2, StockBatch::count());
        $this->assertSame(1, StockMovement::where('type', 'in')->count());
        $before = $this->counts();
        $this->post(route('warehouse.receiver.process-receive-grns.all'), ['date' => now()->toDateString()])->assertRedirect();
        $this->getJson('/api/v1/purchasing/grns?receipt_status=received')->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame($before, $this->counts());
    }

    public function test_advance_and_manager_approved_receipts_remain_pending_and_filters_validate(): void
    {
        $advance = $this->receipt('approved');
        $advance->update(['purchase_order_id' => null, 'bill_status' => 'bill_pending']);
        $this->batch($advance);
        $this->getJson('/api/v1/purchasing/grns?receipt_status=pending&bill_status=bill_pending')->assertOk()->assertJsonPath('data.0.source', 'ADVANCE')->assertJsonPath('data.0.inventory_posted', false);
        $this->getJson('/api/v1/purchasing/grns?receipt_status=unknown')->assertUnprocessable();
    }

    /** @return array<int, int> */
    private function counts(): array
    {
        return [GoodsReceived::count(), StockBatch::count(), StockMovement::count(), PurchaseInvoice::count(), JournalEntry::count()];
    }
}
