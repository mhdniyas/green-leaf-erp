<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Client;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ShopInvoices\ShopInvoiceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeliveryVerificationPricingGateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_invoice_generation_requires_admin_approved_daily_price(): void
    {
        $fixture = $this->createDispatchedOrderFixture();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Approved daily price is missing');

        app(ShopInvoiceService::class)->synchronizeOrderInvoice($fixture['order'], (int) $fixture['user']->id);
    }

    public function test_invoice_generation_uses_approved_daily_price_for_shop_category(): void
    {
        $fixture = $this->createDispatchedOrderFixture();

        DailyPriceApproval::query()->create([
            'product_id' => $fixture['product']->id,
            'business_date' => '2026-07-21',
            'purchase_price' => 80,
            'price_a' => 100,
            'price_b' => 120,
            'price_c' => 140,
            'status' => 'approved',
            'approved_by' => $fixture['user']->id,
            'approved_at' => now(),
        ]);

        $invoice = app(ShopInvoiceService::class)->synchronizeOrderInvoice($fixture['order'], (int) $fixture['user']->id);
        $invoiceItem = $invoice->items()->firstOrFail();

        $this->assertSame('120.00', $invoiceItem->unit_price);
        $this->assertSame('600.00', $invoiceItem->line_subtotal);
    }

    public function test_invoice_generation_merges_duplicate_product_order_items(): void
    {
        $fixture = $this->createDispatchedOrderFixture();

        ShopOrderItem::query()->create([
            'shop_order_id' => $fixture['order']->id,
            'product_id' => $fixture['product']->id,
            'product_grade' => 'A',
            'requested_qty' => 7,
            'approved_qty' => 7,
            'loaded_qty' => 7,
            'unit' => 'kg',
            'locked_selling_price' => 75,
            'line_total' => 525,
            'sorting_status' => 'loaded',
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $fixture['product']->id,
            'business_date' => '2026-07-21',
            'purchase_price' => 80,
            'price_a' => 100,
            'price_b' => 120,
            'price_c' => 140,
            'status' => 'approved',
            'approved_by' => $fixture['user']->id,
            'approved_at' => now(),
        ]);

        $invoice = app(ShopInvoiceService::class)->synchronizeOrderInvoice($fixture['order'], (int) $fixture['user']->id);
        $invoiceItem = $invoice->items()->sole();

        $this->assertSame($fixture['product']->id, $invoiceItem->product_id);
        $this->assertSame('12.00', $invoiceItem->approved_qty);
        $this->assertSame('120.00', $invoiceItem->unit_price);
        $this->assertSame('1440.00', $invoiceItem->line_subtotal);
        $this->assertSame('1440.00', $invoice->fresh()->subtotal);
    }

    public function test_shop_delivery_submission_is_blocked_when_invoice_price_mismatches_approved_price(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $fixture = $this->createDispatchedOrderFixture();
        $shopUser = User::factory()->create(['shop_id' => $fixture['shop']->id]);
        $shopUser->assignRole(Role::findByName('shop'));

        DailyPriceApproval::query()->create([
            'product_id' => $fixture['product']->id,
            'business_date' => '2026-07-21',
            'purchase_price' => 80,
            'price_a' => 100,
            'price_b' => 120,
            'price_c' => 140,
            'status' => 'approved',
            'approved_by' => $fixture['user']->id,
            'approved_at' => now(),
        ]);

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $fixture['shop']->id,
            'shop_order_id' => $fixture['order']->id,
            'invoice_number' => 'SINV-20260721-MISMATCH',
            'business_date' => '2026-07-21',
            'status' => 'generated',
            'delivery_status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 500,
            'shortage_total' => 0,
            'discount_total' => 0,
            'final_total' => 500,
            'paid_amount' => 0,
            'balance_amount' => 500,
            'generated_by' => $fixture['user']->id,
        ]);

        $invoice->items()->create([
            'shop_order_item_id' => $fixture['item']->id,
            'product_id' => $fixture['product']->id,
            'product_name' => $fixture['product']->name,
            'unit' => 'kg',
            'approved_qty' => 5,
            'delivered_qty' => 0,
            'shortage_qty' => 0,
            'unit_price' => 100,
            'line_subtotal' => 500,
            'shortage_amount' => 0,
            'final_line_total' => 500,
        ]);

        $csrfToken = 'delivery-pricing-gate-token';

        $response = $this
            ->actingAs($shopUser)
            ->withSession(['_token' => $csrfToken])
            ->from(route('shop-owner.deliveries.show', $fixture['order']->order_number))
            ->post(route('requisitions.delivery.record', $fixture['order']->order_number), [
                '_token' => $csrfToken,
                'delivered_qty' => [
                    $fixture['item']->id => 5,
                ],
                'cash_collected' => 0,
            ]);

        $response
            ->assertRedirect(route('shop-owner.deliveries.show', $fixture['order']->order_number))
            ->assertSessionHas('error', 'Invoice price mismatch for '.$fixture['product']->name.'. Invoice has 100.00 but approved B price is 120.00.');
    }

    public function test_shop_delivery_page_generates_missing_invoice_after_daily_price_is_approved(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $fixture = $this->createDispatchedOrderFixture();
        $shopUser = User::factory()->create(['shop_id' => $fixture['shop']->id]);
        $shopUser->assignRole(Role::findByName('shop'));

        DailyPriceApproval::query()->create([
            'product_id' => $fixture['product']->id,
            'business_date' => '2026-07-21',
            'purchase_price' => 80,
            'price_a' => 100,
            'price_b' => 120,
            'price_c' => 140,
            'status' => 'approved',
            'approved_by' => $fixture['user']->id,
            'approved_at' => now(),
        ]);

        $this->assertDatabaseMissing('shop_invoices', [
            'shop_order_id' => $fixture['order']->id,
        ]);

        $response = $this
            ->actingAs($shopUser)
            ->get(route('shop-owner.deliveries.show', $fixture['order']->order_number));

        $response
            ->assertOk()
            ->assertDontSeeText('Delivery verification is disabled until the approved daily invoice is generated.')
            ->assertSeeText('Approved Invoice Pricing')
            ->assertSeeText('Rs. 120.00')
            ->assertSeeText('Rs. 600.00')
            ->assertSeeText('Submit Each Product')
            ->assertSeeText('Submit');

        $invoice = ShopInvoice::query()
            ->where('shop_order_id', $fixture['order']->id)
            ->firstOrFail();

        $this->assertSame('600.00', $invoice->subtotal);
        $this->assertSame('120.00', $invoice->items()->firstOrFail()->unit_price);
    }

    public function test_shop_owner_can_submit_delivery_verification_one_product_at_a_time_without_reload(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $fixture = $this->createDispatchedOrderFixture();
        $secondProduct = Product::factory()->create([
            'name' => 'Beans',
            'unit' => 'kg',
        ]);
        $secondItem = ShopOrderItem::query()->create([
            'shop_order_id' => $fixture['order']->id,
            'product_id' => $secondProduct->id,
            'product_grade' => 'A',
            'requested_qty' => 4,
            'approved_qty' => 4,
            'loaded_qty' => 4,
            'unit' => 'kg',
            'locked_selling_price' => 75,
            'line_total' => 300,
            'sorting_status' => 'loaded',
        ]);

        foreach ([$fixture['product'], $secondProduct] as $product) {
            DailyPriceApproval::query()->create([
                'product_id' => $product->id,
                'business_date' => '2026-07-21',
                'purchase_price' => 80,
                'price_a' => 100,
                'price_b' => 120,
                'price_c' => 140,
                'status' => 'approved',
                'approved_by' => $fixture['user']->id,
                'approved_at' => now(),
            ]);
        }

        app(ShopInvoiceService::class)->synchronizeOrderInvoice($fixture['order']->fresh('items.product'), (int) $fixture['user']->id);
        $shopUser = User::factory()->create(['shop_id' => $fixture['shop']->id]);
        $shopUser->assignRole(Role::findByName('shop'));

        $this
            ->actingAs($shopUser)
            ->postJson(route('shop-owner.deliveries.items.verify', [$fixture['order']->order_number, $fixture['item']]), [
                'received_qty' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('item.status_label', 'Submitted')
            ->assertJsonPath('progress.label', '1 / 2 products submitted')
            ->assertJsonPath('order_submitted', false);

        $fixture['order']->refresh();
        $fixture['item']->refresh();

        $this->assertSame('in_transit', $fixture['order']->delivery_status);
        $this->assertSame('not_started', $fixture['order']->delivery_review_status);
        $this->assertNotNull($fixture['item']->shop_verified_at);
        $this->assertSame('5.00', $fixture['item']->shop_reported_received_qty);

        $this
            ->actingAs($shopUser)
            ->postJson(route('shop-owner.deliveries.items.verify', [$fixture['order']->order_number, $secondItem]), [
                'received_qty' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('item.status_label', 'Short Submitted')
            ->assertJsonPath('progress.label', '2 / 2 products submitted')
            ->assertJsonPath('order_submitted', true)
            ->assertJsonPath('order_status_label', 'Submitted For Admin Review');

        $fixture['order']->refresh();
        $secondItem->refresh();

        $this->assertSame('pending_approval', $fixture['order']->delivery_status);
        $this->assertSame('pending', $fixture['order']->delivery_review_status);
        $this->assertNotNull($secondItem->shop_verified_at);
        $this->assertSame('3.00', $secondItem->shop_reported_received_qty);
        $this->assertSame('1.00', $secondItem->shop_reported_missing_qty);
    }

    public function test_admin_approval_of_excess_delivery_updates_bill_and_inventory(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $fixture = $this->createDispatchedOrderFixture();

        DailyPriceApproval::query()->create([
            'product_id' => $fixture['product']->id,
            'business_date' => '2026-07-21',
            'purchase_price' => 80,
            'price_a' => 100,
            'price_b' => 120,
            'price_c' => 140,
            'status' => 'approved',
            'approved_by' => $fixture['user']->id,
            'approved_at' => now(),
        ]);

        app(ShopInvoiceService::class)->synchronizeOrderInvoice($fixture['order']->fresh('items.product'), (int) $fixture['user']->id);

        $shopUser = User::factory()->create(['shop_id' => $fixture['shop']->id]);
        $shopUser->assignRole(Role::findByName('shop'));
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $batch = StockBatch::factory()->create([
            'product_id' => $fixture['product']->id,
            'created_by' => $admin->id,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'total_kg' => 10,
            'cost_per_kg' => 80,
            'received_at' => '2026-07-21',
        ]);
        StockMovement::factory()->create([
            'batch_id' => $batch->id,
            'product_id' => $fixture['product']->id,
            'created_by' => $admin->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 10,
            'cost_per_unit' => 80,
        ]);

        $this
            ->actingAs($shopUser)
            ->postJson(route('shop-owner.deliveries.items.verify', [$fixture['order']->order_number, $fixture['item']]), [
                'received_qty' => 7,
            ])
            ->assertOk()
            ->assertJsonPath('item.status_label', 'Excess Submitted')
            ->assertJsonPath('item.excess_qty', '2.00')
            ->assertJsonPath('order_submitted', true);

        $fixture['item']->refresh();

        $this->assertSame('7.00', $fixture['item']->shop_reported_received_qty);
        $this->assertSame('0.00', $fixture['item']->shop_reported_missing_qty);
        $this->assertSame('2.00', $fixture['item']->shop_reported_excess_qty);

        $this
            ->actingAs($admin)
            ->post(route('requisitions.delivery.approve', $fixture['order']->order_number), [
                'invoice_number' => $fixture['order']->fresh('invoice')->invoice->invoice_number,
                'approved_delivered_qty' => [
                    $fixture['item']->id => 7,
                ],
                'review_note' => 'Accepted excess stock at shop.',
            ])
            ->assertRedirect(route('purchasing.shop-invoices.show', $fixture['order']->fresh('invoice')->invoice));

        $invoice = $fixture['order']->fresh('invoice.items')->invoice;
        $invoiceItem = $invoice->items->first();
        $fixture['item']->refresh();

        $this->assertSame('delivered', $fixture['order']->fresh()->delivery_status);
        $this->assertSame('7.00', $fixture['item']->delivered_qty);
        $this->assertSame('2.00', $fixture['item']->excess_qty);
        $this->assertSame('600.00', $invoice->subtotal);
        $this->assertSame('240.00', $invoice->excess_total);
        $this->assertSame('840.00', $invoice->final_total);
        $this->assertSame('2.00', $invoiceItem->excess_qty);
        $this->assertSame('240.00', $invoiceItem->excess_amount);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $fixture['product']->id,
            'shop_order_item_id' => $fixture['item']->id,
            'type' => StockMovementType::Out->value,
            'quantity' => '2.000',
        ]);
    }

    public function test_admin_can_add_short_delivery_quantity_back_to_inventory(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $fixture = $this->createDispatchedOrderFixture();

        DailyPriceApproval::query()->create([
            'product_id' => $fixture['product']->id,
            'business_date' => '2026-07-21',
            'purchase_price' => 80,
            'price_a' => 100,
            'price_b' => 120,
            'price_c' => 140,
            'status' => 'approved',
            'approved_by' => $fixture['user']->id,
            'approved_at' => now(),
        ]);

        app(ShopInvoiceService::class)->synchronizeOrderInvoice($fixture['order']->fresh('items.product'), (int) $fixture['user']->id);

        $shopUser = User::factory()->create(['shop_id' => $fixture['shop']->id]);
        $shopUser->assignRole(Role::findByName('shop'));
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $batch = StockBatch::factory()->create([
            'product_id' => $fixture['product']->id,
            'created_by' => $admin->id,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'total_kg' => 5,
            'cost_per_kg' => 80,
            'received_at' => '2026-07-21',
        ]);
        StockMovement::factory()->create([
            'batch_id' => $batch->id,
            'product_id' => $fixture['product']->id,
            'created_by' => $admin->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::Out,
            'quantity' => 5,
            'cost_per_unit' => 80,
            'notes' => "Loadout dispatch to delivery - Order: {$fixture['order']->order_number}",
        ]);

        $this
            ->actingAs($shopUser)
            ->postJson(route('shop-owner.deliveries.items.verify', [$fixture['order']->order_number, $fixture['item']]), [
                'received_qty' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('item.status_label', 'Short Submitted')
            ->assertJsonPath('order_submitted', true);

        $this
            ->actingAs($admin)
            ->post(route('requisitions.delivery.approve', $fixture['order']->order_number), [
                'invoice_number' => $fixture['order']->fresh('invoice')->invoice->invoice_number,
                'approved_delivered_qty' => [
                    $fixture['item']->id => 3,
                ],
                'item_inventory_actions' => [
                    $fixture['item']->id => 'add_back',
                ],
                'review_note' => 'Short stock returned to inventory.',
            ])
            ->assertRedirect(route('purchasing.shop-invoices.show', $fixture['order']->fresh('invoice')->invoice));

        $invoice = $fixture['order']->fresh('invoice.items')->invoice;
        $fixture['item']->refresh();

        $this->assertSame('partially_delivered', $fixture['order']->fresh()->delivery_status);
        $this->assertSame('3.00', $fixture['item']->delivered_qty);
        $this->assertSame('2.00', $fixture['item']->shortage_qty);
        $this->assertSame('240.00', $invoice->shortage_total);
        $this->assertSame('360.00', $invoice->final_total);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $fixture['product']->id,
            'shop_order_item_id' => $fixture['item']->id,
            'type' => StockMovementType::SaleReversal->value,
            'quantity' => '2.000',
        ]);
    }

    public function test_priced_client_invoice_waiting_for_dispatch_is_visible_in_deliveries_without_submit(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $fixture = $this->createDispatchedOrderFixture();
        $client = Client::query()->firstOrCreate(
            ['code' => 'AISHWARYA_VEG'],
            ['name' => 'Aishwarya Veg', 'status' => 'active'],
        );
        $fixture['shop']->update(['client_id' => $client->id]);
        $fixture['order']->update([
            'delivery_status' => 'pending_delivery',
            'is_allocation_completed' => false,
        ]);
        $fixture['item']->update([
            'loaded_qty' => null,
            'sorting_status' => 'pending',
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $fixture['product']->id,
            'business_date' => '2026-07-21',
            'purchase_price' => 80,
            'price_a' => 100,
            'price_b' => 120,
            'price_c' => 140,
            'status' => 'approved',
            'approved_by' => $fixture['user']->id,
            'approved_at' => now(),
        ]);

        $invoice = app(ShopInvoiceService::class)->synchronizeOrderInvoice($fixture['order']->fresh(), (int) $fixture['user']->id);
        $shopUser = User::factory()->create(['shop_id' => $fixture['shop']->id]);
        $shopUser->assignRole(Role::findByName('shop'));

        $this
            ->actingAs($shopUser)
            ->get(route('shop-owner.deliveries.index'))
            ->assertOk()
            ->assertSeeText($fixture['order']->order_number)
            ->assertDontSeeText('No deliveries yet');

        $this
            ->actingAs($shopUser)
            ->get(route('shop-owner.deliveries.show', $fixture['order']->order_number))
            ->assertOk()
            ->assertSeeText('Client: Aishwarya Veg')
            ->assertSeeText($invoice->invoice_number)
            ->assertSeeText('Tomato')
            ->assertSeeText('Rs. 120.00')
            ->assertSeeText('This order is not out for delivery.')
            ->assertSeeText('Delivery Pending')
            ->assertDontSeeText('Submit Each Product');
    }

    public function test_client_shop_delivery_page_shows_published_and_unpublished_price_products(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $fixture = $this->createDispatchedOrderFixture();
        $client = Client::query()->firstOrCreate(
            ['code' => 'AISHWARYA_VEG'],
            ['name' => 'Aishwarya Veg', 'status' => 'active'],
        );
        $fixture['shop']->update(['client_id' => $client->id]);

        $pendingProduct = Product::factory()->create([
            'name' => 'Onion',
            'unit' => 'kg',
        ]);
        $missingProduct = Product::factory()->create([
            'name' => 'Carrot',
            'unit' => 'kg',
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $fixture['order']->id,
            'product_id' => $pendingProduct->id,
            'product_grade' => 'A',
            'requested_qty' => 3,
            'approved_qty' => 3,
            'loaded_qty' => 3,
            'unit' => 'kg',
            'locked_selling_price' => 50,
            'line_total' => 150,
            'sorting_status' => 'loaded',
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $fixture['order']->id,
            'product_id' => $missingProduct->id,
            'product_grade' => 'A',
            'requested_qty' => 2,
            'approved_qty' => 2,
            'loaded_qty' => 2,
            'unit' => 'kg',
            'locked_selling_price' => 40,
            'line_total' => 80,
            'sorting_status' => 'loaded',
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $fixture['product']->id,
            'business_date' => '2026-07-21',
            'purchase_price' => 80,
            'price_a' => 100,
            'price_b' => 120,
            'price_c' => 140,
            'status' => 'approved',
            'approved_by' => $fixture['user']->id,
            'approved_at' => now(),
        ]);
        DailyPriceApproval::query()->create([
            'product_id' => $pendingProduct->id,
            'business_date' => '2026-07-21',
            'purchase_price' => 30,
            'price_a' => 45,
            'price_b' => 55,
            'price_c' => 65,
            'status' => 'pending',
        ]);

        $shopUser = User::factory()->create(['shop_id' => $fixture['shop']->id]);
        $shopUser->assignRole(Role::findByName('shop'));

        $response = $this
            ->actingAs($shopUser)
            ->get(route('shop-owner.deliveries.show', $fixture['order']->order_number));

        $response
            ->assertOk()
            ->assertSeeText('Client: Aishwarya Veg')
            ->assertSeeText('Tomato')
            ->assertSeeText('Rs. 120.00')
            ->assertSeeText('Onion')
            ->assertSeeText('Carrot')
            ->assertSeeText('Pending')
            ->assertSeeText('Delivery Pending')
            ->assertSeeText('Some products are priced, but delivery verification will open only after every product price is published.')
            ->assertDontSeeText('Submit Each Product');

        $this->assertDatabaseMissing('shop_invoices', [
            'shop_order_id' => $fixture['order']->id,
        ]);
    }

    public function test_admin_price_publish_generates_invoice_for_same_business_date(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $fixture = $this->createDispatchedOrderFixture();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $approval = DailyPriceApproval::query()->create([
            'product_id' => $fixture['product']->id,
            'business_date' => '2026-07-21',
            'purchase_price' => 80,
            'price_a' => 100,
            'price_b' => 120,
            'price_c' => 140,
            'status' => 'pending',
        ]);

        $this->assertDatabaseMissing('shop_invoices', [
            'shop_order_id' => $fixture['order']->id,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('purchasing.prices.update'), [
                'date' => '2026-07-21',
                'prices' => [
                    $approval->id => [
                        'price_a' => 100,
                        'price_b' => 120,
                        'price_c' => 140,
                    ],
                ],
            ])
            ->assertRedirect(route('purchasing.prices.index', ['date' => '2026-07-21']));

        $invoice = ShopInvoice::query()
            ->where('shop_order_id', $fixture['order']->id)
            ->firstOrFail();

        $approval->refresh();

        $this->assertSame('approved', $approval->status);
        $this->assertSame($admin->id, $approval->approved_by);
        $this->assertSame('2026-07-21', $invoice->business_date->toDateString());
        $this->assertSame('600.00', $invoice->subtotal);
        $this->assertSame('120.00', $invoice->items()->firstOrFail()->unit_price);
    }

    public function test_admin_price_publish_skips_invoice_when_order_has_unpriced_not_yet_purchased_product(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $fixture = $this->createDispatchedOrderFixture();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $missingProduct = Product::factory()->create([
            'name' => 'Peeled Onion',
            'unit' => 'kg',
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $fixture['order']->id,
            'product_id' => $missingProduct->id,
            'product_grade' => 'A',
            'requested_qty' => 12,
            'approved_qty' => 12,
            'loaded_qty' => 12,
            'unit' => 'kg',
            'locked_selling_price' => 40,
            'line_total' => 480,
            'sorting_status' => 'loaded',
        ]);

        $approval = DailyPriceApproval::query()->create([
            'product_id' => $fixture['product']->id,
            'business_date' => '2026-07-21',
            'purchase_price' => 80,
            'price_a' => 100,
            'price_b' => 120,
            'price_c' => 140,
            'status' => 'pending',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('purchasing.prices.update'), [
                'date' => '2026-07-21',
                'prices' => [
                    $approval->id => [
                        'price_a' => 100,
                        'price_b' => 120,
                        'price_c' => 140,
                    ],
                ],
            ])
            ->assertRedirect(route('purchasing.prices.index', ['date' => '2026-07-21']))
            ->assertSessionHas('success', 'Daily prices published immediately.')
            ->assertSessionHas('warning', 'Prices saved. 1 order(s) skipped because daily prices are missing for Peeled Onion.');

        $approval->refresh();

        $this->assertSame('approved', $approval->status);
        $this->assertDatabaseMissing('shop_invoices', [
            'shop_order_id' => $fixture['order']->id,
        ]);
    }

    public function test_admin_price_publish_rejects_zero_category_prices(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $fixture = $this->createDispatchedOrderFixture();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $approval = DailyPriceApproval::query()->create([
            'product_id' => $fixture['product']->id,
            'business_date' => '2026-07-21',
            'purchase_price' => 80,
            'price_a' => 100,
            'price_b' => 120,
            'price_c' => 140,
            'status' => 'pending',
        ]);

        $this
            ->actingAs($admin)
            ->from(route('purchasing.prices.index', ['date' => '2026-07-21']))
            ->post(route('purchasing.prices.update'), [
                'date' => '2026-07-21',
                'prices' => [
                    $approval->id => [
                        'price_a' => 0,
                        'price_b' => 120,
                        'price_c' => 140,
                    ],
                ],
            ])
            ->assertRedirect(route('purchasing.prices.index', ['date' => '2026-07-21']))
            ->assertSessionHasErrors("prices.{$approval->id}.price_a");

        $approval->refresh();

        $this->assertSame('pending', $approval->status);
        $this->assertNull($approval->approved_at);
    }

    /**
     * @return array{user: User, shop: Shop, product: Product, order: ShopOrder, item: ShopOrderItem}
     */
    private function createDispatchedOrderFixture(): array
    {
        $user = User::factory()->create();
        $group = ShopPriceGroup::factory()->create([
            'name' => 'B',
            'is_active' => true,
        ]);
        $shop = Shop::factory()->create([
            'shop_price_group_id' => $group->id,
        ]);
        $product = Product::factory()->create([
            'name' => 'Tomato',
            'unit' => 'kg',
        ]);

        $order = ShopOrder::query()->create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'delivery_status' => 'in_transit',
            'delivery_review_status' => 'not_started',
            'payment_status' => 'unpaid',
            'business_date' => '2026-07-21',
            'created_by' => $user->id,
            'is_allocation_completed' => true,
        ]);

        $item = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'loaded_qty' => 5,
            'unit' => 'kg',
            'locked_selling_price' => 75,
            'line_total' => 375,
            'sorting_status' => 'loaded',
        ]);

        return [
            'user' => $user,
            'shop' => $shop,
            'product' => $product,
            'order' => $order,
            'item' => $item,
        ];
    }
}
