<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DailyPriceApproval;
use App\Models\DailyPricePublication;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
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

    public function test_purchasing_shop_invoice_view_shows_sl_no_and_credit_note_invoice_changes_section(): void
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
        $response->assertSee('Credit Note / Invoice Changes');
        $response->assertSee('Previous Qty');
        $response->assertSee('Revised Qty');
        $response->assertSee('Rate');
        $response->assertSee('Previous Amount');
        $response->assertSee('Revised Amount');
        $response->assertSee('Difference');
        $response->assertSee('Tomato Premium');
        $response->assertSee('10 KG');
        $response->assertSee('8 KG');
        $response->assertSee('₹50.00');
        $response->assertSee('₹500.00');
        $response->assertSee('₹400.00');
        $response->assertSee('-₹100.00');
        $response->assertSee('Previous Invoice Total');
        $response->assertSee('Revised Invoice Total');
        $response->assertSee('Net Difference');
        $response->assertSee('Shop Change Verification:');
    }

    public function test_credit_note_uses_item_adjustment_history_for_changes_and_reconciliation(): void
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
        $response->assertSee('₹26.00');
        $response->assertSee('₹1,300.00');
        $response->assertSee('₹1,040.00');
        $response->assertSee('-₹260.00');
        $response->assertSee('Previous Invoice Total');
        $response->assertSee('Revised Invoice Total');
        $response->assertSee('Net Difference');
    }

    public function test_shop_owner_deliveries_view_shows_unified_credit_note_invoice_changes_section(): void
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
        $response->assertSee('Credit Note / Invoice Changes');
        $response->assertSee('Potato White');
        $response->assertSee('20 KG');
        $response->assertSee('15 KG');
        $response->assertSee('₹20.00');
        $response->assertSee('₹400.00');
        $response->assertSee('₹300.00');
        $response->assertSee('-₹100.00');
        $response->assertSee('Verify Invoice Changes');
    }

    public function test_credit_note_invoice_changes_shows_both_increases_and_reductions_and_shop_verification(): void
    {
        $kakdi = Product::factory()->create(['name' => 'Armenian Cucumber / Kakdi', 'sku' => 'KAK-001', 'unit' => 'kg']);
        $ridge = Product::factory()->create(['name' => 'Ridge Gourd', 'sku' => 'RID-001', 'unit' => 'kg']);
        $chilli = Product::factory()->create(['name' => 'Chilli Spicy / Akash G4', 'sku' => 'CHI-001', 'unit' => 'kg']);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-CASIO-001',
            'business_date' => '2026-09-16',
            'shop_checked_by' => $this->shopOwner->id,
            'shop_checked_at' => now(),
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260916-AV_CASIO_TEST',
            'business_date' => '2026-09-16',
            'subtotal' => 710.00,
            'final_total' => 710.00,
            'status' => 'delivery_review',
        ]);

        $itemKakdi = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $kakdi->id,
            'product_name' => 'Armenian Cucumber / Kakdi',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 4.00,
            'price_quantity' => 4.00,
            'delivered_qty' => 4.00,
            'delivered_price_quantity' => 4.00,
            'unit_price' => 90.00,
            'line_subtotal' => 360.00,
            'final_line_total' => 360.00,
        ]);

        $itemRidge = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $ridge->id,
            'product_name' => 'Ridge Gourd',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 3.00,
            'price_quantity' => 3.00,
            'delivered_qty' => 3.00,
            'delivered_price_quantity' => 3.00,
            'unit_price' => 60.00,
            'line_subtotal' => 180.00,
            'final_line_total' => 180.00,
        ]);

        $itemChilli = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $chilli->id,
            'product_name' => 'Chilli Spicy / Akash G4',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 2.00,
            'price_quantity' => 2.00,
            'delivered_qty' => 2.00,
            'delivered_price_quantity' => 2.00,
            'unit_price' => 85.00,
            'line_subtotal' => 170.00,
            'final_line_total' => 170.00,
        ]);

        // Adjustments: Kakdi 1 -> 4 (+3 @ 90 = +270), Ridge 4 -> 3 (-1 @ 60 = -60), Chilli 1 -> 2 (+1 @ 85 = +85)
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
                'before' => ['item_id' => $itemKakdi->id, 'product_id' => $kakdi->id, 'product_name' => 'Armenian Cucumber / Kakdi', 'qty' => 1, 'price' => 90, 'amount' => 90],
                'after' => ['item_id' => $itemKakdi->id, 'product_id' => $kakdi->id, 'product_name' => 'Armenian Cucumber / Kakdi', 'qty' => 4, 'price' => 90, 'amount' => 360],
            ],
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
                'before' => ['item_id' => $itemRidge->id, 'product_id' => $ridge->id, 'product_name' => 'Ridge Gourd', 'qty' => 4, 'price' => 60, 'amount' => 240],
                'after' => ['item_id' => $itemRidge->id, 'product_id' => $ridge->id, 'product_name' => 'Ridge Gourd', 'qty' => 3, 'price' => 60, 'amount' => 180],
            ],
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
                'before' => ['item_id' => $itemChilli->id, 'product_id' => $chilli->id, 'product_name' => 'Chilli Spicy / Akash G4', 'qty' => 1, 'price' => 85, 'amount' => 85],
                'after' => ['item_id' => $itemChilli->id, 'product_id' => $chilli->id, 'product_name' => 'Chilli Spicy / Akash G4', 'qty' => 2, 'price' => 85, 'amount' => 170],
            ],
        ]);

        Activity::query()->create([
            'log_name' => 'shop_invoice',
            'description' => 'shop_changes_verified',
            'subject_type' => ShopInvoice::class,
            'subject_id' => $invoice->id,
            'causer_id' => $this->shopOwner->id,
            'causer_type' => User::class,
            'event' => 'shop_changes_verified',
            'properties' => [
                'source' => 'shop_changes_verified',
            ],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Credit Note / Invoice Changes');
        $response->assertSee('Armenian Cucumber / Kakdi');
        $response->assertSee('+₹270.00');
        $response->assertSee('Ridge Gourd');
        $response->assertSee('-₹60.00');
        $response->assertSee('Chilli Spicy / Akash G4');
        $response->assertSee('+₹85.00');

        // Totals: Revised = 710, Net Difference = +295, Previous = 710 - 295 = 415
        $response->assertSee('Previous Invoice Total');
        $response->assertSee('₹415.00');
        $response->assertSee('Revised Invoice Total');
        $response->assertSee('₹710.00');
        $response->assertSee('Net Difference');
        $response->assertSee('+₹295.00');

        // Shop Verification shows Verified
        $response->assertSee('Shop Change Verification:');
        $response->assertSee('Verified');
        $response->assertSee($this->shopOwner->name);
    }

    public function test_first_shop_owner_delivery_note_submission_marks_order_delivered_immediately(): void
    {
        $priceGroup = ShopPriceGroup::query()->firstOrCreate(
            ['code' => 'A'],
            ['name' => 'A', 'is_active' => true]
        );
        $priceGroup->update(['name' => 'A', 'is_active' => true]);
        $this->shop->update(['shop_price_group_id' => $priceGroup->id]);

        $product = Product::factory()->create([
            'name' => 'Fresh Mint Leaves',
            'sku' => 'MNT-001',
            'unit' => 'kg',
            'base_price' => 40.00,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-16',
            'purchase_price' => 30.00,
            'price_unit' => 'kg',
            'price_a' => 40.00,
            'price_b' => 40.00,
            'price_c' => 40.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        DailyPricePublication::setPublishStatus('2026-09-16', true, $this->admin);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-DELV-TEST-001',
            'business_date' => '2026-09-16',
            'state' => 'approved',
            'delivery_status' => 'in_transit',
            'delivery_review_status' => 'not_started',
            'is_allocation_completed' => true,
            'is_delivered' => false,
            'delivered_at' => null,
            'delivered_by' => null,
            'shop_checked_at' => null,
            'shop_checked_by' => null,
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'loaded_qty' => 10,
            'sorting_status' => 'loaded',
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 10,
            'requested_unit_conversion_to_base' => 1,
            'locked_price_group_id' => $priceGroup->id,
            'locked_selling_price' => 40.00,
            'unit_cost' => 30.00,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-DELV-TEST-001',
            'business_date' => '2026-09-16',
            'subtotal' => 400.00,
            'final_total' => 400.00,
            'status' => 'issued',
            'delivery_status' => 'pending',
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => 'Fresh Mint Leaves',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 10.00,
            'price_quantity' => 10.00,
            'delivered_qty' => 10.00,
            'delivered_price_quantity' => 10.00,
            'unit_price' => 40.00,
            'line_subtotal' => 400.00,
            'final_line_total' => 400.00,
        ]);

        // Prior to delivery note submission: order is not delivered
        $this->assertFalse((bool) $order->fresh()->is_delivered);
        $this->assertSame('in_transit', $order->fresh()->delivery_status);

        // Shop Owner submits first delivery note
        $response = $this->actingAs($this->shopOwner)
            ->postJson(route('shop-owner.deliveries.items.verify', [$order->order_number, $orderItem]), [
                'received_qty' => 10,
                'note' => 'Received full consignment in good condition.',
            ]);

        $response->assertOk();
        $response->assertJson([
            'order_submitted' => true,
        ]);

        // Assert immediately marked as delivered
        $freshOrder = $order->fresh();
        $this->assertTrue((bool) $freshOrder->is_delivered);
        $this->assertSame('delivered', $freshOrder->delivery_status);
        $this->assertSame('pending', $freshOrder->delivery_review_status);
        $this->assertNotNull($freshOrder->delivered_at);
        $this->assertSame($this->shopOwner->id, $freshOrder->delivered_by);
        $this->assertNotNull($freshOrder->shop_checked_at);
        $this->assertSame($this->shopOwner->id, $freshOrder->shop_checked_by);

        // Assert activity log was created
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => ShopOrder::class,
            'subject_id' => $order->id,
            'causer_id' => $this->shopOwner->id,
            'description' => 'shop_order.delivery_confirmed',
        ]);

        // Repeated submission attempt must be rejected and not create duplicate transitions
        $secondResponse = $this->actingAs($this->shopOwner)
            ->postJson(route('shop-owner.deliveries.items.verify', [$order->order_number, $orderItem]), [
                'received_qty' => 10,
                'note' => 'Repeated attempt',
            ]);

        $secondResponse->assertStatus(422);
    }

    public function test_shop_invoice_change_verification_flow_is_distinct_from_delivery_verification(): void
    {
        $priceGroup = ShopPriceGroup::query()->firstOrCreate(
            ['code' => 'A'],
            ['name' => 'A', 'is_active' => true]
        );
        $this->shop->update(['shop_price_group_id' => $priceGroup->id]);

        $product = Product::factory()->create([
            'name' => 'Carrot Ooty',
            'sku' => 'CAR-001',
            'unit' => 'kg',
            'base_price' => 50.00,
        ]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-CHG-TEST-001',
            'business_date' => '2026-09-16',
            'state' => 'approved',
            'delivery_status' => 'delivered',
            'delivery_review_status' => 'pending',
            'is_allocation_completed' => true,
            'is_delivered' => true,
            'delivered_at' => now(),
            'delivered_by' => $this->shopOwner->id,
            'shop_checked_at' => now(),
            'shop_checked_by' => $this->shopOwner->id,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-CHG-TEST-001',
            'business_date' => '2026-09-16',
            'subtotal' => 500.00,
            'final_total' => 400.00,
            'status' => 'delivery_review',
            'delivery_confirmed_at' => now(),
            'delivery_confirmed_by' => $this->shopOwner->id,
        ]);

        $invoiceItem = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => 'Carrot Ooty',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 10.00,
            'price_quantity' => 10.00,
            'delivered_qty' => 8.00,
            'delivered_price_quantity' => 8.00,
            'unit_price' => 50.00,
            'line_subtotal' => 500.00,
            'final_line_total' => 400.00,
        ]);

        // Admin records an item adjustment: Carrot 10 -> 8 (-2 @ 50 = -100)
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
                'before' => ['item_id' => $invoiceItem->id, 'product_id' => $product->id, 'product_name' => 'Carrot Ooty', 'qty' => 10, 'price' => 50, 'amount' => 500],
                'after' => ['item_id' => $invoiceItem->id, 'product_id' => $product->id, 'product_name' => 'Carrot Ooty', 'qty' => 8, 'price' => 50, 'amount' => 400],
            ],
        ]);

        // 1. Delivery is verified, but invoice changes NOT yet verified -> shows Pending
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.deliveries.show', $order->order_number));

        $response->assertOk();
        $response->assertSee('Delivery Completed');
        $response->assertDontSee('This order is not out for delivery.');
        $response->assertSee('Credit Note / Invoice Changes');
        $response->assertSee('Shop Change Verification:');
        $response->assertSee('Pending');
        $response->assertSee('Verify Invoice Changes');

        // Admin view also shows Pending for shop change verification
        $adminResponse = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.show', $invoice));
        $adminResponse->assertOk();
        $adminResponse->assertSee('Shop Change Verification:');
        $adminResponse->assertSee('Pending');

        // 2. Shop Manager clicks "Verify Invoice Changes" on the delivered order
        $verifyResponse = $this->actingAs($this->shopOwner)
            ->post(route('shop-owner.deliveries.verify-changes', $order->order_number));

        $verifyResponse->assertRedirect();
        $verifyResponse->assertSessionHas('success');

        // Assert activity log was created specifically for shop changes verified
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => ShopInvoice::class,
            'subject_id' => $invoice->id,
            'causer_id' => $this->shopOwner->id,
            'description' => 'shop_changes_verified',
        ]);

        // 3. Now both Shop Owner and Admin view show Verified with Shop Manager name
        $verifiedResponse = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.deliveries.show', $order->order_number));
        $verifiedResponse->assertOk();
        $verifiedResponse->assertSee('Shop Change Verification:');
        $verifiedResponse->assertSee('Verified');
        $verifiedResponse->assertSee($this->shopOwner->name);

        $adminVerifiedResponse = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.show', $invoice));
        $adminVerifiedResponse->assertOk();
        $adminVerifiedResponse->assertSee('Shop Change Verification:');
        $adminVerifiedResponse->assertSee('Verified');
        $adminVerifiedResponse->assertSee($this->shopOwner->name);
    }
}
