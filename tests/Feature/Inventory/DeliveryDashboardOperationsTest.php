<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryDashboardOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_new_dashboard_without_category_bill_audit_table(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $leafy = Category::factory()->create(['name' => 'Leafy']);
        $roots = Category::factory()->create(['name' => 'Roots']);
        $spinach = Product::factory()->create([
            'category_id' => $leafy->id,
            'name' => 'Spinach',
            'sku' => 'SPN-001',
            'unit' => 'KG',
        ]);
        $carrot = Product::factory()->create([
            'category_id' => $roots->id,
            'name' => 'Carrot',
            'sku' => 'CRT-001',
            'unit' => 'KG',
        ]);

        $shop = Shop::factory()->create(['name' => 'Main Road Shop']);
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-02',
            'delivery_status' => 'ready_for_dispatch',
        ]);

        $spinachOrderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $spinach->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'loaded_qty' => 10,
            'unit' => 'KG',
            'locked_selling_price' => 20,
            'line_total' => 200,
            'sorting_status' => 'loaded',
            'is_sorted' => true,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $carrot->id,
            'requested_qty' => 5,
            'approved_qty' => 5,
            'loaded_qty' => 5,
            'unit' => 'KG',
            'locked_selling_price' => 10,
            'line_total' => 50,
            'sorting_status' => 'loaded',
            'is_sorted' => true,
        ]);

        $invoice = ShopInvoice::create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260802-MAIN',
            'business_date' => '2026-08-02',
            'status' => 'generated',
            'delivery_status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 200,
            'final_total' => 200,
            'paid_amount' => 0,
            'balance_amount' => 200,
            'generated_by' => $admin->id,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $spinachOrderItem->id,
            'product_id' => $spinach->id,
            'product_name' => 'Spinach',
            'unit' => 'KG',
            'price_unit' => 'KG',
            'approved_qty' => 10,
            'price_quantity' => 10,
            'delivered_qty' => 0,
            'unit_price' => 20,
            'line_subtotal' => 200,
            'final_line_total' => 200,
        ]);

        $response = $this->actingAs($admin)->get(route('inventory.deliveries.dashboard', [
            'date' => '2026-08-02',
            'category_id' => $leafy->id,
        ]));

        $response->assertOk();
        $response->assertSee('Admin Delivery Desk');
        $response->assertSee('Main Road Shop');
        $response->assertSee('Move Delivery');
        $response->assertDontSee('Category Bill Check');
        $response->assertDontSee('CRT-001');
        $response->assertDontSee('Itemized Shortages Analysis');
    }

    public function test_admin_can_lock_delivered_invoice_from_dashboard(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $shop = Shop::factory()->create();
        $product = Product::factory()->create(['unit' => 'KG']);
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-02',
            'delivery_status' => 'delivered',
            'is_delivered' => true,
        ]);

        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 4,
            'approved_qty' => 4,
            'loaded_qty' => 4,
            'delivered_qty' => 4,
            'unit' => 'KG',
            'locked_selling_price' => 25,
            'line_total' => 100,
            'sorting_status' => 'loaded',
            'is_sorted' => true,
        ]);

        $invoice = ShopInvoice::create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260802-LOCK',
            'business_date' => '2026-08-02',
            'status' => 'generated',
            'delivery_status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 100,
            'final_total' => 100,
            'paid_amount' => 0,
            'balance_amount' => 100,
            'generated_by' => $admin->id,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'KG',
            'price_unit' => 'KG',
            'approved_qty' => 4,
            'price_quantity' => 4,
            'delivered_qty' => 4,
            'unit_price' => 25,
            'line_subtotal' => 100,
            'final_line_total' => 100,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('inventory.deliveries.dashboard', ['date' => '2026-08-02']))
            ->post(route('inventory.deliveries.dashboard.lock-invoice', $order));

        $response->assertRedirect(route('inventory.deliveries.dashboard', ['date' => '2026-08-02']));

        $invoice->refresh();
        $this->assertTrue($invoice->isFinalLocked());
        $this->assertSame('received_full', $invoice->delivery_status);
    }
}
