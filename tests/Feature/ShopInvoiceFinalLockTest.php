<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Purchasing\POStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\ShopInvoices\ShopInvoiceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopInvoiceFinalLockTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_purchase_order_items_cannot_be_changed_after_linked_shop_invoice_is_finalized(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        [$purchaseOrder, $purchaseOrderItem] = $this->createLinkedFinalizedInvoiceFixture();

        $response = $this
            ->actingAs($admin)
            ->from(route('purchasing.orders.show', $purchaseOrder))
            ->put(route('purchasing.orders.items.update', $purchaseOrder), [
                'items' => [
                    [
                        'id' => $purchaseOrderItem->id,
                        'product_id' => $purchaseOrderItem->product_id,
                        'purchase_unit' => 'kg',
                        'packet_qty' => null,
                        'weight_per_packet' => null,
                        'actual_weight' => null,
                        'unit_price' => 99,
                        'price_basis' => 'per_kg',
                        'quantity' => 50,
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('purchasing.orders.show', $purchaseOrder))
            ->assertSessionHas('error', 'This purchase order is linked to a finalized shop invoice. Create an adjustment instead of changing the original order.');

        $purchaseOrderItem->refresh();

        $this->assertSame('10.0000', $purchaseOrderItem->unit_price);
        $this->assertSame('25.000', $purchaseOrderItem->quantity);
    }

    public function test_finalized_shop_invoice_cannot_be_repriced(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        [, , $invoice] = $this->createLinkedFinalizedInvoiceFixture();

        $response = $this
            ->actingAs($admin)
            ->from(route('purchasing.shop-invoices.show', $invoice))
            ->patch(route('purchasing.shop-invoices.reprice', $invoice), [
                'reason' => 'Late price board correction',
            ]);

        $response
            ->assertRedirect(route('purchasing.shop-invoices.show', $invoice))
            ->assertSessionHasErrors('invoice');
    }

    public function test_invoice_sync_does_not_mutate_finalized_invoice_lines(): void
    {
        [$purchaseOrder, , $invoice, $shopOrderItem] = $this->createLinkedFinalizedInvoiceFixture();

        $shopOrderItem->update([
            'approved_qty' => 99,
            'locked_selling_price' => 99,
        ]);

        app(ShopInvoiceService::class)->synchronizeOrderInvoice($shopOrderItem->order, (int) $purchaseOrder->created_by);

        $invoiceItem = $invoice->items()->firstOrFail();

        $this->assertSame('25.00', $invoiceItem->approved_qty);
        $this->assertSame('15.00', $invoiceItem->unit_price);
        $this->assertSame('375.00', $invoiceItem->line_subtotal);
    }

    /**
     * @return array{0: PurchaseOrder, 1: PurchaseOrderItem, 2: ShopInvoice, 3: ShopOrderItem}
     */
    private function createLinkedFinalizedInvoiceFixture(): array
    {
        $businessDate = '2026-07-17';
        $user = User::factory()->create();
        $shop = Shop::factory()->create();
        $product = Product::factory()->create(['unit' => 'kg']);

        $shopOrder = ShopOrder::query()->create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'delivery_status' => 'delivered',
            'delivery_review_status' => 'approved',
            'payment_status' => 'unpaid',
            'business_date' => $businessDate,
            'created_by' => $user->id,
            'is_delivered' => true,
        ]);

        $shopOrderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $product->id,
            'requested_qty' => 25,
            'approved_qty' => 25,
            'delivered_qty' => 25,
            'shortage_qty' => 0,
            'unit' => 'kg',
            'locked_selling_price' => 15,
            'line_total' => 375,
            'unit_cost' => 10,
            'fulfillment_type' => 'warehouse',
        ]);

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $shopOrder->id,
            'invoice_number' => 'SINV-20260717-FINALLOCK',
            'business_date' => $businessDate,
            'status' => 'payment_pending',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'subtotal' => 375,
            'shortage_total' => 0,
            'discount_total' => 0,
            'final_total' => 375,
            'paid_amount' => 0,
            'balance_amount' => 375,
            'generated_by' => $user->id,
        ]);

        ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $shopOrderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'kg',
            'approved_qty' => 25,
            'delivered_qty' => 25,
            'shortage_qty' => 0,
            'unit_price' => 15,
            'line_subtotal' => 375,
            'shortage_amount' => 0,
            'final_line_total' => 375,
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create([
            'status' => POStatus::Approved,
            'order_date' => $businessDate,
            'created_by' => $user->id,
        ]);

        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'purchase_unit' => 'kg',
            'quantity' => 25,
            'unit_price' => 10,
            'price_basis' => 'per_kg',
        ]);

        return [$purchaseOrder, $purchaseOrderItem, $invoice, $shopOrderItem->fresh('order')];
    }
}
