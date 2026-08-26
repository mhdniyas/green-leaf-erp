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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopInvoiceAdminControlsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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
    }

    public function test_selected_day_invoice_is_visible_with_review_bill_action(): void
    {
        $this->shopInvoice('SINV-20260802-DS_QUICK_MART', '2026-08-02');

        $this->actingAs($this->admin)
            ->get('/purchasing/shop-invoices?date=2026-08-02')
            ->assertOk()
            ->assertSee('SINV-20260802-DS_QUICK_MART')
            ->assertSee('Review Bill');
    }

    public function test_admin_controls_are_visible_when_shop_has_not_submitted(): void
    {
        $this->shopInvoice('SINV-20260802-DS_QUICK_MART', '2026-08-02', shopSubmitted: false);

        $this->actingAs($this->admin)
            ->get('/purchasing/shop-invoices/SINV-20260802-DS_QUICK_MART')
            ->assertOk()
            ->assertSee('approved_delivered_qty', false)
            ->assertSee('Edit Prices')
            ->assertSee('Total discount amount')
            ->assertSee('Delivery Note')
            ->assertSee('Shop not submitted')
            ->assertSee('Review on behalf of Shop')
            ->assertSee('Finalize Invoice');
    }

    public function test_finalized_invoice_hides_normal_edit_and_shows_admin_finalized_edit_action(): void
    {
        $this->shopInvoice(
            'SINV-20260802-DS_QUICK_MART',
            '2026-08-02',
            finalized: true,
            shopSubmitted: true,
        );

        $this->actingAs($this->admin)
            ->get('/purchasing/shop-invoices/SINV-20260802-DS_QUICK_MART')
            ->assertOk()
            ->assertDontSee('approved_delivered_qty', false)
            ->assertDontSee('Total discount amount')
            ->assertSee('Edit Finalized Invoice');
    }

    private function shopInvoice(
        string $invoiceNumber,
        string $businessDate,
        bool $finalized = false,
        bool $shopSubmitted = false,
    ): ShopInvoice {
        $shop = Shop::factory()->create([
            'code' => 'DS_QUICK_MART',
            'name' => 'DS Quick Mart',
        ]);
        $product = Product::factory()->create([
            'name' => 'Tomato',
            'unit' => 'kg',
        ]);
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => $businessDate,
            'delivery_status' => $finalized ? 'delivered' : 'in_transit',
            'delivery_review_status' => $finalized ? 'approved' : 'not_started',
            'shop_checked_at' => $shopSubmitted ? now() : null,
            'shop_checked_by' => $shopSubmitted ? $this->admin->id : null,
            'is_delivered' => $finalized,
            'delivered_at' => $finalized ? now() : null,
        ]);
        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 20,
            'approved_qty' => 18,
            'loaded_qty' => 17,
            'delivered_qty' => $finalized ? 16 : 17,
            'unit' => 'kg',
            'locked_selling_price' => 25,
            'unit_cost' => 20,
        ]);
        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'status' => $finalized ? 'payment_pending' : 'generated',
            'delivery_status' => $finalized ? 'received_full' : 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 425,
            'final_total' => 425,
            'balance_amount' => 425,
            'finalized_by' => $finalized ? $this->admin->id : null,
            'finalized_at' => $finalized ? now() : null,
        ]);
        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 17,
            'price_quantity' => 17,
            'delivered_qty' => $finalized ? 16 : 17,
            'delivered_price_quantity' => $finalized ? 16 : 17,
            'unit_price' => 25,
            'line_subtotal' => 425,
            'final_line_total' => $finalized ? 400 : 425,
        ]);

        return $invoice;
    }
}
