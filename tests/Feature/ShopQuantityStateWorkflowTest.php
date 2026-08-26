<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\ShopOrder\Actions\ResolveDeliveryReviewAction;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopQuantityStateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_loadout_delivery_review_and_invoice_preserve_quantity_states(): void
    {
        $admin = $this->adminUser();
        [$order, $item] = $this->approvedOrderWithItem(requestedQty: 20, approvedQty: 18);

        $payload = [
            'items' => [$item->product_id => 17],
        ];

        $this->actingAs($admin)
            ->post(route('warehouse.loadout.save', $order), $payload)
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('warehouse.loadout.save', $order->fresh()), $payload)
            ->assertRedirect();

        $item = $item->fresh();
        $this->assertSame(20.0, (float) $item->requested_qty);
        $this->assertSame(18.0, (float) $item->approved_qty);
        $this->assertSame(17.0, (float) $item->loaded_qty);
        $this->assertSame(1, ShopOrderItem::query()->where('shop_order_id', $order->id)->where('product_id', $item->product_id)->count());

        $this->actingAs($admin)
            ->post(route('warehouse.loadout.move-to-delivery', $order->fresh()))
            ->assertSessionHasErrors();

        $this->actingAs($admin)
            ->post(route('warehouse.loadout.move-to-partial-delivery', $order->fresh()))
            ->assertRedirect();

        $action = app(ResolveDeliveryReviewAction::class);
        $action->submit($order->fresh(), [$item->id => 16], (int) $admin->id, 'Shop counted 16.');
        $action->approve($order->fresh(), [$item->id => 16], [], [], [], [], (int) $admin->id, 'Approved final delivered qty.');

        $item = $item->fresh();
        $invoiceItem = $order->fresh('invoice.items')->invoice->items->firstWhere('product_id', $item->product_id);

        $this->assertSame(20.0, (float) $item->requested_qty);
        $this->assertSame(18.0, (float) $item->approved_qty);
        $this->assertSame(17.0, (float) $item->loaded_qty);
        $this->assertSame(16.0, (float) $item->delivered_qty);
        $this->assertSame(18.0, (float) $invoiceItem->approved_qty);
        $this->assertSame(16.0, (float) $invoiceItem->delivered_qty);
        $this->assertSame(160.0, (float) $invoiceItem->final_line_total);
        $this->assertNotNull($invoiceItem->invoice->fresh()->finalized_at);
    }

    public function test_synchronize_order_invoice_does_not_touch_finalized_invoice_items(): void
    {
        $admin = $this->adminUser();
        [$order, $item] = $this->approvedOrderWithItem(requestedQty: 20, approvedQty: 18);
        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $order->shop_id,
            'shop_order_id' => $order->id,
            'business_date' => $order->business_date->toDateString(),
            'status' => 'payment_pending',
            'delivery_status' => 'received_full',
            'finalized_by' => $admin->id,
            'finalized_at' => now(),
        ]);
        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'approved_qty' => 18,
            'price_quantity' => 18,
            'delivered_qty' => 16,
            'delivered_price_quantity' => 16,
            'unit_price' => 10,
            'line_subtotal' => 180,
            'shortage_qty' => 2,
            'shortage_price_quantity' => 2,
            'shortage_amount' => 20,
            'final_line_total' => 160,
        ]);

        $item->update([
            'loaded_qty' => 17,
            'delivered_qty' => 15,
        ]);

        $synced = app(ShopInvoiceService::class)
            ->synchronizeOrderInvoice($order->fresh(), (int) $admin->id);

        $invoiceItem = $synced->items->firstWhere('product_id', $item->product_id);

        $this->assertSame(18.0, (float) $invoiceItem->approved_qty);
        $this->assertSame(16.0, (float) $invoiceItem->delivered_qty);
        $this->assertSame(160.0, (float) $invoiceItem->final_line_total);
    }

    /**
     * @return array{0: ShopOrder, 1: ShopOrderItem}
     */
    private function approvedOrderWithItem(float $requestedQty, float $approvedQty): array
    {
        $priceGroup = ShopPriceGroup::factory()->create(['name' => 'A']);
        $shop = Shop::factory()->create(['shop_price_group_id' => $priceGroup->id]);
        $product = Product::factory()->create(['unit' => 'kg']);
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => today()->toDateString(),
            'purchase_price' => 5,
            'price_unit' => 'kg',
            'price_a' => 10,
            'price_b' => 10,
            'price_c' => 10,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => today()->toDateString(),
            'delivery_status' => 'pending_delivery',
        ]);
        $item = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => $requestedQty,
            'approved_qty' => $approvedQty,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => $requestedQty,
            'requested_unit_conversion_to_base' => 1,
            'locked_price_group_id' => $priceGroup->id,
            'locked_selling_price' => 10,
            'locked_price_source' => 'manual',
            'line_total' => $approvedQty * 10,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);

        return [$order, $item];
    }

    private function adminUser(): User
    {
        $admin = User::factory()->create();
        Role::query()->firstOrCreate(['name' => 'admin']);
        $admin->assignRole('admin');

        return $admin;
    }
}
