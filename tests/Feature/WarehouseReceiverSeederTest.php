<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaserCart;
use App\Models\ShopOrder;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\WarehouseReceiverSeeder;
use Database\Seeders\WarehouseWorkflowSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WarehouseReceiverSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_seeds_completed_purchaser_purchases_for_warehouse_receiver_checking(): void
    {
        $this->seed(WarehouseReceiverSeeder::class);

        $this->assertSame(4, ShopOrder::query()
            ->whereDate('business_date', '2026-07-14')
            ->where('order_number', 'like', 'RQ-SHOP-20260714-%')
            ->where('state', 'approved')
            ->whereNotNull('reviewed_by')
            ->count());

        $carts = PurchaserCart::query()
            ->whereDate('business_date', '2026-07-14')
            ->where('cart_number', 'like', 'VC-20260714-WH%')
            ->with(['purchaseOrder', 'goodsReceived.items', 'purchaseInvoice'])
            ->orderBy('cart_number')
            ->get();

        $this->assertCount(2, $carts);

        foreach ($carts as $cart) {
            $this->assertSame('submitted', $cart->status);
            $this->assertNotNull($cart->submitted_at);
            $this->assertNotNull($cart->bill_received_at);
            $this->assertNull($cart->goods_received_at);
            $this->assertNotNull($cart->purchaseOrder);
            $this->assertNotNull($cart->goodsReceived);
            $this->assertNotNull($cart->purchaseInvoice);
            $this->assertSame('received', $cart->purchaseOrder->status->value);
            $this->assertSame('pending_approval', $cart->goodsReceived->status);
            $this->assertCount(4, $cart->goodsReceived->items);
            $this->assertSame($cart->goodsReceived->id, $cart->purchaseInvoice->goods_received_id);
            $this->assertSame('pending', $cart->purchaseInvoice->status->value);
        }

        $this->assertSame(2, GoodsReceived::query()
            ->whereDate('received_at', '2026-07-14')
            ->where('status', 'pending_approval')
            ->where('grn_number', 'like', 'GRN-20260714-WH%')
            ->count());

        $this->assertSame(2, PurchaseOrder::query()
            ->whereDate('order_date', '2026-07-14')
            ->where('po_number', 'like', 'PO-20260714-WH%')
            ->count());

        $this->assertSame(0, PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', function ($query): void {
                $query
                    ->whereDate('order_date', '2026-07-14')
                    ->where('po_number', 'like', 'PO-20260714-WH%');
            })
            ->where('unit_price', '<=', 0)
            ->count());

        $this->assertSame(2, PurchaseInvoice::query()
            ->where('invoice_number', 'like', 'PINV-20260714-WH%')
            ->count());
    }

    public function test_it_can_be_rerun_without_duplicating_receiver_handoff_records(): void
    {
        $this->seed(WarehouseReceiverSeeder::class);
        $this->seed(WarehouseReceiverSeeder::class);

        $this->assertSame(2, PurchaserCart::query()
            ->where('cart_number', 'like', 'VC-20260714-WH%')
            ->count());

        $this->assertSame(2, GoodsReceived::query()
            ->where('grn_number', 'like', 'GRN-20260714-WH%')
            ->count());

        $this->assertSame(8, GoodsReceived::query()
            ->where('grn_number', 'like', 'GRN-20260714-WH%')
            ->withCount('items')
            ->get()
            ->sum('items_count'));

        $this->assertSame(2, PurchaseInvoice::query()
            ->where('invoice_number', 'like', 'PINV-20260714-WH%')
            ->count());
    }

    public function test_warehouse_workflow_seeder_populates_the_receiver_checklist(): void
    {
        $this->seed(WarehouseWorkflowSeeder::class);

        $receiver = User::query()->where('email', 'receiver@greenleaf.com')->firstOrFail();

        $this
            ->actingAs($receiver)
            ->get(route('warehouse.receiver.checklist', ['date' => '2026-07-14']))
            ->assertOk()
            ->assertSee('GRN-20260714-WH01')
            ->assertSee('GRN-20260714-WH02')
            ->assertSee('Market A')
            ->assertSee('Market B');
    }

    public function test_receiver_gets_warning_instead_of_server_error_when_purchase_price_is_zero(): void
    {
        $this->seed(WarehouseReceiverSeeder::class);

        $receiver = User::query()->where('email', 'receiver@greenleaf.com')->firstOrFail();
        $warehouse = Warehouse::query()->where('is_active', true)->firstOrFail();
        $grn = GoodsReceived::query()
            ->where('grn_number', 'GRN-20260714-WH01')
            ->with(['items.purchaseOrderItem', 'items.product'])
            ->firstOrFail();

        $zeroPriceItem = $grn->items->first();

        PurchaseOrderItem::query()
            ->whereKey($zeroPriceItem->purchase_order_item_id)
            ->update(['unit_price' => 0]);

        $payload = [
            'warehouse_id' => $warehouse->id,
            'items' => $grn->items
                ->mapWithKeys(fn ($item): array => [
                    $item->id => [
                        'warehouse_id' => $warehouse->id,
                        'received_qty' => (float) $item->received_qty,
                        'discrepancy_type' => 'none',
                        'discrepancy_note' => null,
                    ],
                ])
                ->all(),
        ];

        $response = $this
            ->actingAs($receiver)
            ->from(route('warehouse.receiver.receive-grn', $grn))
            ->post(route('warehouse.receiver.process-receive-grn', $grn), $payload);

        $response
            ->assertRedirect(route('warehouse.receiver.receive-grn', $grn))
            ->assertSessionHas('warning', fn (string $message): bool => str_contains($message, 'Price is zero on:'));

        $this->assertSame('pending_approval', $grn->fresh()->status);
    }
}
