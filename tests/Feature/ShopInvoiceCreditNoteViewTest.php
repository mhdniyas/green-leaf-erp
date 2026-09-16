<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopInvoiceCreditNoteViewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopOwner;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::findOrCreate('admin');
        $adminRole->givePermissionTo([
            Permission::findOrCreate('purchasing.order.view'),
            Permission::findOrCreate('accounting.dashboard.view'),
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $shopRole = Role::findOrCreate('shop');
        $shopRole->givePermissionTo([
            Permission::findOrCreate('sales.order.create'),
        ]);

        $this->shop = Shop::factory()->create();

        $this->shopOwner = User::factory()->create(['shop_id' => $this->shop->id]);
        $this->shopOwner->assignRole($shopRole);
    }

    public function test_purchasing_shop_invoice_view_shows_sl_no_and_credit_note_section(): void
    {
        $product1 = Product::factory()->create(['name' => 'Tomato Premium', 'sku' => 'TOM-001', 'unit' => 'kg']);
        $product2 = Product::factory()->create(['name' => 'Onion Red', 'sku' => 'ONI-002', 'unit' => 'kg']);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-TEST-001',
            'business_date' => '2026-09-16',
        ]);

        $orderItem1 = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product1->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'loaded_qty' => 10,
            'delivered_qty' => 8,
            'shortage_qty' => 2,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 10,
            'requested_unit_conversion_to_base' => 1,
            'unit_cost' => 50,
        ]);

        $orderItem2 = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product2->id,
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'loaded_qty' => 5,
            'delivered_qty' => 5,
            'shortage_qty' => 0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 5,
            'requested_unit_conversion_to_base' => 1,
            'unit_cost' => 30,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-TEST-001',
            'business_date' => '2026-09-16',
            'subtotal' => 550.00,
            'shortage_total' => 100.00,
            'final_total' => 550.00,
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);

        $invoiceItem1 = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem1->id,
            'product_id' => $product1->id,
            'product_name' => 'Tomato Premium',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 10.00,
            'price_quantity' => 10.00,
            'delivered_qty' => 8.00,
            'delivered_price_quantity' => 8.00,
            'shortage_qty' => 2.00,
            'unit_price' => 50.00,
            'line_subtotal' => 500.00,
            'shortage_amount' => 100.00,
            'final_line_total' => 400.00,
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem2->id,
            'product_id' => $product2->id,
            'product_name' => 'Onion Red',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 5.00,
            'price_quantity' => 5.00,
            'delivered_qty' => 5.00,
            'delivered_price_quantity' => 5.00,
            'shortage_qty' => 0.00,
            'unit_price' => 30.00,
            'line_subtotal' => 150.00,
            'shortage_amount' => 0.00,
            'final_line_total' => 150.00,
        ]);

        Activity::query()->create([
            'log_name' => 'shop_invoice',
            'description' => 'item_adjusted',
            'subject_type' => ShopInvoice::class,
            'subject_id' => $invoice->id,
            'causer_id' => $this->admin->id,
            'causer_type' => User::class,
            'event' => 'item_adjusted',
            'properties' => [
                'source' => 'admin_item_adjustment',
                'before' => [
                    'item_id' => $invoiceItem1->id,
                    'product_id' => $product1->id,
                    'product_name' => 'Tomato Premium',
                    'qty' => 10,
                    'price' => 50,
                    'amount' => 500,
                ],
                'after' => [
                    'item_id' => $invoiceItem1->id,
                    'product_id' => $product1->id,
                    'product_name' => 'Tomato Premium',
                    'qty' => 8,
                    'price' => 50,
                    'amount' => 400,
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Sl No');
        $response->assertSee('Credit Note');
        $response->assertSee('Previous Qty');
        $response->assertSee('Final Qty');
        $response->assertSee('Credit Qty');
        $response->assertSee('Rate');
        $response->assertSee('Credit Amount');
        $response->assertSee('Tomato Premium');
        $response->assertSee('10 KG');
        $response->assertSee('8 KG');
        $response->assertSee('2 KG');
        $response->assertSee('₹50.00');
        $response->assertSee('₹100.00');
        $response->assertSee('Original Invoice Total');
        $response->assertSee('Revised Invoice Total');
        $response->assertSee('Credit Note Amount');
    }

    public function test_credit_note_uses_item_adjustment_history_for_quantity_reduction(): void
    {
        $potato = Product::factory()->create(['name' => 'Potato Agra', 'sku' => 'POT-001', 'unit' => 'kg']);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-TEST-003',
            'business_date' => '2026-09-16',
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $potato->id,
            'product_grade' => 'A',
            'requested_qty' => 1,
            'approved_qty' => 50,
            'loaded_qty' => 50,
            'delivered_qty' => 40,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 1,
            'requested_unit_conversion_to_base' => 1,
            'unit_cost' => 20,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-TEST-003',
            'business_date' => '2026-09-16',
            'subtotal' => 1040.00,
            'final_total' => 1040.00,
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);

        $invoiceItem = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $potato->id,
            'product_name' => 'Potato Agra',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 50.00,
            'price_quantity' => 50.00,
            'delivered_qty' => 40.00,
            'delivered_price_quantity' => 40.00,
            'unit_price' => 26.00,
            'line_subtotal' => 1300.00,
            'final_line_total' => 1040.00,
        ]);

        Activity::query()->create([
            'log_name' => 'shop_invoice',
            'description' => 'item_adjusted',
            'subject_type' => ShopInvoice::class,
            'subject_id' => $invoice->id,
            'causer_id' => $this->admin->id,
            'causer_type' => User::class,
            'event' => 'item_adjusted',
            'properties' => [
                'source' => 'admin_item_adjustment',
                'before' => [
                    'item_id' => $invoiceItem->id,
                    'product_id' => $potato->id,
                    'product_name' => 'Potato Agra',
                    'qty' => 50,
                    'price' => 26,
                    'amount' => 1300,
                ],
                'after' => [
                    'item_id' => $invoiceItem->id,
                    'product_id' => $potato->id,
                    'product_name' => 'Potato Agra',
                    'qty' => 40,
                    'price' => 26,
                    'amount' => 1040,
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Potato Agra');
        $response->assertSee('50 KG');
        $response->assertSee('40 KG');
        $response->assertSee('10 KG');
        $response->assertSee('₹26.00');
        $response->assertSee('₹260.00');
        $response->assertSee('Original Invoice Total');
        $response->assertSee('Revised Invoice Total');
        $response->assertSee('Credit Note Amount');
    }

    public function test_shop_owner_deliveries_view_shows_credit_note_section(): void
    {
        $product = Product::factory()->create(['name' => 'Potato White', 'sku' => 'POT-003', 'unit' => 'kg']);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-TEST-002',
            'business_date' => '2026-09-16',
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'loaded_qty' => 20,
            'delivered_qty' => 15,
            'shortage_qty' => 5,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 20,
            'requested_unit_conversion_to_base' => 1,
            'unit_cost' => 20,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-TEST-002',
            'business_date' => '2026-09-16',
            'subtotal' => 300.00,
            'shortage_total' => 100.00,
            'final_total' => 300.00,
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);

        $invoiceItem = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => 'Potato White',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 20.00,
            'price_quantity' => 20.00,
            'delivered_qty' => 15.00,
            'delivered_price_quantity' => 15.00,
            'shortage_qty' => 5.00,
            'unit_price' => 20.00,
            'line_subtotal' => 400.00,
            'shortage_amount' => 100.00,
            'final_line_total' => 300.00,
        ]);

        Activity::query()->create([
            'log_name' => 'shop_invoice',
            'description' => 'item_adjusted',
            'subject_type' => ShopInvoice::class,
            'subject_id' => $invoice->id,
            'causer_id' => $this->admin->id,
            'causer_type' => User::class,
            'event' => 'item_adjusted',
            'properties' => [
                'source' => 'admin_item_adjustment',
                'before' => [
                    'item_id' => $invoiceItem->id,
                    'product_id' => $product->id,
                    'product_name' => 'Potato White',
                    'qty' => 20,
                    'price' => 20,
                    'amount' => 400,
                ],
                'after' => [
                    'item_id' => $invoiceItem->id,
                    'product_id' => $product->id,
                    'product_name' => 'Potato White',
                    'qty' => 15,
                    'price' => 20,
                    'amount' => 300,
                ],
            ],
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.deliveries.show', $order->order_number));

        $response->assertOk();
        $response->assertSee('Credit Note');
        $response->assertSee('Potato White');
        $response->assertSee('5 KG');
        $response->assertSee('₹100.00');
        $response->assertSee('Credit Note Amount');
    }
}
