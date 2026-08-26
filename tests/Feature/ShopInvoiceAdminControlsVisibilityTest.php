<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDailyProductPrice;
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
            ->assertSee('Shop Bill')
            ->assertSee('Product')
            ->assertSee('Qty')
            ->assertSee('Price')
            ->assertSee('Amount')
            ->assertSee('Subtotal')
            ->assertSee('Discount')
            ->assertSee('Final Total')
            ->assertSee('data-item-edit', false)
            ->assertSee('data-product-name="Tomato"', false)
            ->assertSee('Original Qty')
            ->assertSee('Final Qty')
            ->assertSee('Final Price / Special Price')
            ->assertSee('data-discount-modal', false)
            ->assertSee('Edit Discount')
            ->assertSee('Notes & Changes')
            ->assertSee('Shop not submitted')
            ->assertSee('Finalize Invoice')
            ->assertSee('approved_delivered_qty', false)
            ->assertDontSee('Total discount amount')
            ->assertDontSee('Delivery Note')
            ->assertDontSee('Shop Payment Requests')
            ->assertDontSee('Adjustment History');
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
            ->assertSee('FINALIZED')
            ->assertDontSee('data-item-edit', false)
            ->assertDontSee('Edit Discount')
            ->assertDontSee('Finalize Invoice')
            ->assertSee('Edit Finalized Invoice');
    }

    public function test_bill_detail_renders_all_items_and_mobile_modal_markup(): void
    {
        $invoice = $this->shopInvoice('SINV-20260802-DS_QUICK_MART', '2026-08-02', productNames: ['Tomato', 'Onion']);

        $this->actingAs($this->admin)
            ->get("/purchasing/shop-invoices/{$invoice->invoice_number}")
            ->assertOk()
            ->assertSee('Tomato')
            ->assertSee('Onion')
            ->assertSee('items-end bg-slate-950/50 p-0 sm:items-center sm:p-4', false)
            ->assertSee('rounded-t-lg bg-white', false);
    }

    public function test_admin_invoice_price_edit_route_still_updates_existing_service_path(): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'allow_historical_invoice_repricing'],
            ['value' => 'true'],
        );

        $invoice = $this->shopInvoice('SINV-20260802-DS_QUICK_MART', '2026-08-02');
        $item = $invoice->items()->firstOrFail();

        $this->actingAs($this->admin)
            ->postJson(route('purchasing.bill-prices.invoice-prices.update', $invoice), [
                'prices' => [
                    [
                        'product_id' => $item->product_id,
                        'selling_price' => 30,
                        'price_unit' => 'kg',
                        'reason' => 'Invoice item price edit',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas(ShopDailyProductPrice::class, [
            'shop_id' => $invoice->shop_id,
            'product_id' => $item->product_id,
            'selling_price' => 30,
            'status' => 'approved',
        ]);
    }

    public function test_admin_discount_route_still_updates_existing_service_path(): void
    {
        $invoice = $this->shopInvoice('SINV-20260802-DS_QUICK_MART', '2026-08-02');

        $this->actingAs($this->admin)
            ->patch(route('admin.accounting.shop-invoices.discount', $invoice), [
                'discount_total' => 25,
                'discount_note' => 'Manual discount.',
            ])
            ->assertRedirect();

        $this->assertSame(25.0, (float) $invoice->fresh()->discount_total);
        $this->assertSame('Manual discount.', $invoice->fresh()->discount_note);
    }

    public function test_admin_finalize_on_behalf_route_still_finalizes_invoice(): void
    {
        LedgerEntryType::query()->firstOrCreate(
            ['code' => 'purchase_bill'],
            ['name' => 'Purchase Bill', 'category' => 'expense', 'active' => true],
        );

        $invoice = $this->shopInvoice('SINV-20260802-DS_QUICK_MART', '2026-08-02', shopSubmitted: false);
        $orderItem = $invoice->order->items()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('purchasing.shop-invoices.finalize-on-behalf', $invoice), [
                'approved_delivered_qty' => [
                    $orderItem->id => 16,
                ],
                'item_inventory_actions' => [
                    $orderItem->id => 'add_back',
                ],
                'item_review_notes' => [
                    $orderItem->id => 'Admin corrected.',
                ],
                'review_note' => 'Shop quantity confirmed manually.',
                'invoice_number' => $invoice->invoice_number,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull($invoice->fresh()->finalized_at);
    }

    private function shopInvoice(
        string $invoiceNumber,
        string $businessDate,
        bool $finalized = false,
        bool $shopSubmitted = false,
        array $productNames = ['Tomato'],
    ): ShopInvoice {
        $shop = Shop::factory()->create([
            'code' => 'DS_QUICK_MART',
            'name' => 'DS Quick Mart',
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

        foreach ($productNames as $index => $productName) {
            $product = Product::factory()->create([
                'name' => $productName,
                'unit' => 'kg',
            ]);
            $orderItem = ShopOrderItem::query()->create([
                'shop_order_id' => $order->id,
                'product_id' => $product->id,
                'requested_qty' => 20,
                'approved_qty' => 18,
                'loaded_qty' => 17,
                'delivered_qty' => $finalized ? 16 : 17,
                'unit' => 'kg',
                'locked_selling_price' => 25 + $index,
                'unit_cost' => 20,
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
                'unit_price' => 25 + $index,
                'line_subtotal' => 425 + (17 * $index),
                'final_line_total' => $finalized ? 400 + (16 * $index) : 425 + (17 * $index),
            ]);
        }

        return $invoice;
    }
}
