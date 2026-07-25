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
            ->assertSeeText('Confirm Delivery Check-In');

        $invoice = ShopInvoice::query()
            ->where('shop_order_id', $fixture['order']->id)
            ->firstOrFail();

        $this->assertSame('600.00', $invoice->subtotal);
        $this->assertSame('120.00', $invoice->items()->firstOrFail()->unit_price);
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
