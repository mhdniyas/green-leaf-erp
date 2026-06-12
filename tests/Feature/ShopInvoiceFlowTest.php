<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\ShopInvoices\ShopInvoiceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_price_approval_generates_one_shop_invoice_per_shop(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $groupA = ShopPriceGroup::create([
            'name' => 'A',
            'default_margin_percent' => 10,
            'is_active' => true,
        ]);

        $shop = Shop::create([
            'code' => 'CASIO',
            'name' => 'Casio Hypermarket',
            'shop_price_group_id' => $groupA->id,
        ]);

        $product = Product::factory()->create();

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->addDay()->toDateString(),
            'created_by' => $admin->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 8,
            'approved_qty' => 8,
            'unit' => $product->unit,
        ]);

        $approval = DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => today()->addDay()->toDateString(),
            'purchase_price' => 40,
            'price_a' => 60,
            'price_b' => 65,
            'price_c' => 70,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.price-approvals.approve'), [
            'approvals' => [$approval->id],
            'date' => today()->addDay()->toDateString(),
        ]);

        $response->assertRedirect();

        $invoice = ShopInvoice::query()->where('shop_order_id', $order->id)->first();

        $this->assertNotNull($invoice);
        $this->assertSame('SINV-'.today()->addDay()->format('Ymd').'-CASIO', $invoice->invoice_number);
        $this->assertEquals(480.00, (float) $invoice->subtotal);
        $this->assertEquals(480.00, (float) $invoice->final_total);
        $this->assertCount(1, $invoice->items);
        $this->assertEquals(60.00, (float) $invoice->items->first()->unit_price);
    }

    public function test_shop_owner_finance_is_scoped_to_own_daily_invoices(): void
    {
        $groupA = ShopPriceGroup::create([
            'name' => 'A',
            'default_margin_percent' => 10,
            'is_active' => true,
        ]);

        $shopA = Shop::create(['code' => 'A1', 'name' => 'Shop A', 'shop_price_group_id' => $groupA->id]);
        $shopB = Shop::create(['code' => 'B1', 'name' => 'Shop B', 'shop_price_group_id' => $groupA->id]);

        $ownerA = User::factory()->create(['shop_id' => $shopA->id]);
        $ownerA->assignRole('shop');

        $ownerB = User::factory()->create(['shop_id' => $shopB->id]);
        $ownerB->assignRole('shop');

        $product = Product::factory()->create();
        $service = app(ShopInvoiceService::class);

        $orderA = ShopOrder::create([
            'shop_id' => $shopA->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $ownerA->id,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $orderA->id,
            'product_id' => $product->id,
            'requested_qty' => 2,
            'approved_qty' => 2,
            'unit' => $product->unit,
            'locked_selling_price' => 30,
        ]);

        $orderB = ShopOrder::create([
            'shop_id' => $shopB->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $ownerB->id,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $orderB->id,
            'product_id' => $product->id,
            'requested_qty' => 3,
            'approved_qty' => 3,
            'unit' => $product->unit,
            'locked_selling_price' => 45,
        ]);

        $invoiceA = $service->synchronizeOrderInvoice($orderA, $ownerA->id);
        $invoiceB = $service->synchronizeOrderInvoice($orderB, $ownerB->id);

        $response = $this->actingAs($ownerA)->get(route('shop-owner.finance.index'));

        $response->assertOk();
        $response->assertSee($invoiceA->invoice_number);
        $response->assertDontSee($invoiceB->invoice_number);
    }

    public function test_shop_owner_can_open_own_invoice_pdf_only(): void
    {
        $groupA = ShopPriceGroup::create([
            'name' => 'A',
            'default_margin_percent' => 10,
            'is_active' => true,
        ]);

        $shopA = Shop::create(['code' => 'A1', 'name' => 'Shop A', 'shop_price_group_id' => $groupA->id]);
        $shopB = Shop::create(['code' => 'B1', 'name' => 'Shop B', 'shop_price_group_id' => $groupA->id]);

        $ownerA = User::factory()->create(['shop_id' => $shopA->id]);
        $ownerA->assignRole('shop');

        $ownerB = User::factory()->create(['shop_id' => $shopB->id]);
        $ownerB->assignRole('shop');

        $product = Product::factory()->create();
        $service = app(ShopInvoiceService::class);

        $orderA = ShopOrder::create([
            'shop_id' => $shopA->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $ownerA->id,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $orderA->id,
            'product_id' => $product->id,
            'requested_qty' => 2,
            'approved_qty' => 2,
            'unit' => $product->unit,
            'locked_selling_price' => 30,
        ]);

        $orderB = ShopOrder::create([
            'shop_id' => $shopB->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $ownerB->id,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $orderB->id,
            'product_id' => $product->id,
            'requested_qty' => 3,
            'approved_qty' => 3,
            'unit' => $product->unit,
            'locked_selling_price' => 45,
        ]);

        $invoiceA = $service->synchronizeOrderInvoice($orderA, $ownerA->id);
        $invoiceB = $service->synchronizeOrderInvoice($orderB, $ownerB->id);

        $this->actingAs($ownerA)
            ->get(route('shop-owner.finance.pdf', $invoiceA))
            ->assertOk()
            ->assertSee('Print / Save PDF')
            ->assertSee($invoiceA->invoice_number);

        $this->actingAs($ownerA)
            ->get(route('shop-owner.finance.pdf', $invoiceB))
            ->assertForbidden();
    }

    public function test_purchase_manager_can_open_shop_invoice_pdf(): void
    {
        $groupA = ShopPriceGroup::create([
            'name' => 'A',
            'default_margin_percent' => 10,
            'is_active' => true,
        ]);

        $shop = Shop::create(['code' => 'PDF1', 'name' => 'Pdf Shop', 'shop_price_group_id' => $groupA->id]);
        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop');

        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $product = Product::factory()->create();
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $owner->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 4,
            'approved_qty' => 4,
            'unit' => $product->unit,
            'locked_selling_price' => 55,
        ]);

        $invoice = app(ShopInvoiceService::class)->synchronizeOrderInvoice($order, $manager->id);

        $this->actingAs($manager)
            ->get(route('purchasing.shop-invoices.pdf', $invoice))
            ->assertOk()
            ->assertSee('Print / Save PDF')
            ->assertSee($shop->name)
            ->assertSee($invoice->invoice_number);
    }

    public function test_delivery_and_payment_approval_update_invoice_totals(): void
    {
        $groupA = ShopPriceGroup::create([
            'name' => 'A',
            'default_margin_percent' => 10,
            'is_active' => true,
        ]);

        $shop = Shop::create(['code' => 'D1', 'name' => 'Delivery Shop', 'shop_price_group_id' => $groupA->id]);

        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop');

        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $product = Product::factory()->create();
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $owner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => $product->unit,
            'locked_selling_price' => 20,
        ]);

        $invoice = app(ShopInvoiceService::class)->synchronizeOrderInvoice($order, $manager->id);

        $deliveryResponse = $this->actingAs($owner)->post(route('requisitions.delivery.record', $order->order_number), [
            'delivered_qty' => [
                $item->id => 3,
            ],
            'delivery_notes' => 'Two units missing.',
        ]);

        $deliveryResponse->assertRedirect(route('shop-owner.deliveries.show', $order->order_number));

        $invoice->refresh();
        $this->assertEquals('received_with_discrepancy', $invoice->delivery_status);
        $this->assertEquals(40.00, (float) $invoice->shortage_total);
        $this->assertEquals(60.00, (float) $invoice->final_total);

        $paymentResponse = $this->actingAs($manager)->patch(route('purchasing.shop-invoices.payment-approval', $invoice), [
            'discount_total' => 10,
            'paid_amount' => 20,
            'payment_note' => 'Approved discount for shortage resolution.',
        ]);

        $paymentResponse->assertRedirect(route('purchasing.shop-invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals(10.00, (float) $invoice->discount_total);
        $this->assertEquals(50.00, (float) $invoice->final_total);
        $this->assertEquals(30.00, (float) $invoice->balance_amount);
        $this->assertSame('partially_paid', $invoice->payment_status);
    }
}
