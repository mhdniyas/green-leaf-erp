<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaserDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $shopUser;

    private User $purchaseManager;

    private Shop $shopA;

    private Shop $shopB;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->shopUser = User::factory()->create();
        $this->shopUser->assignRole('shop');

        $this->purchaseManager = User::factory()->create();
        $this->purchaseManager->assignRole('purchase');

        $this->shopA = Shop::create([
            'code' => 'S-A',
            'name' => 'Shop A',
            'status' => 'active',
        ]);

        $this->shopB = Shop::create([
            'code' => 'S-B',
            'name' => 'Shop B',
            'status' => 'active',
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Market Vendor A',
            'type' => 'Vendor',
            'category' => 'market',
            'is_default_purchase' => false,
            'contact' => '9876543210',
            'location' => 'Market Road',
            'mobile_number' => '9876543210',
            'payment_terms' => 'Cash',
            'preferred_payment_method' => 'Cash',
            'credit_approved' => false,
            'credit_terms' => null,
            'quality_score' => 95.0,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('purchaser.daily'))->assertRedirect(route('login'));
    }

    public function test_dashboard_redirects_to_daily_and_non_purchaser_is_forbidden(): void
    {
        $this->actingAs($this->purchaser)
            ->get(route('purchaser.dashboard'))
            ->assertRedirect(route('purchaser.daily'));

        $this->actingAs($this->shopUser)
            ->get(route('purchaser.daily'))
            ->assertForbidden();
    }

    public function test_daily_screen_renders_grouped_buckets_and_stats(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $supplyCategory = Category::create(['name' => 'Supply', 'is_active' => true]);

        $tomato = Product::factory()->create([
            'category_id' => $supplyCategory->id,
            'name' => 'Tomato',
            'sku' => 'TOMATO-001',
            'unit' => 'kg',
        ]);

        $this->createApprovedOrder($date, $this->shopA, $tomato, 5);
        $this->createApprovedOrder($date, $this->shopB, $tomato, 5);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily', [
            'date' => $date,
            'chip' => 'All',
        ]));

        $response->assertOk();
        $response->assertSee('Daily demand');
        $response->assertSee('Tomato');
        $response->assertSee('5kg x 2');
        $response->assertSee('Shop A');
        $response->assertSee('Shop B');
        $response->assertSee('Need');
        $response->assertSee('Share Summary');
    }

    public function test_purchaser_can_add_off_list_product_and_flag_it_as_extra_purchase(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);

        $offListProduct = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Mint',
            'sku' => 'MINT-001',
            'unit' => 'kg',
        ]);

        $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
            'business_date' => $date,
            'product_id' => $offListProduct->id,
            'quantity' => 2,
            'unit_price' => 12,
            'return_to' => 'daily',
        ])->assertRedirect(route('purchaser.daily', ['date' => $date]));

        $item = PurchaserCartItem::query()->firstOrFail();

        $this->assertSame('2.000', (string) $item->quantity);
        $this->assertTrue($item->is_extra_purchase);
    }

    public function test_purchaser_can_create_multiple_carts_send_whatsapp_and_submit_purchase(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);

        $tomato = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Tomato',
            'sku' => 'TOM-100',
            'unit' => 'kg',
        ]);

        $cucumber = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Cucumber',
            'sku' => 'CUC-100',
            'unit' => 'kg',
        ]);

        $this->createApprovedOrder($date, $this->shopA, $tomato, 5);
        $this->createApprovedOrder($date, $this->shopB, $cucumber, 3);

        $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
            'business_date' => $date,
            'product_id' => $tomato->id,
            'quantity' => 5,
            'unit_price' => 20,
            'return_to' => 'daily',
        ])->assertRedirect(route('purchaser.daily', ['date' => $date]));

        $firstCart = PurchaserCart::query()->firstOrFail();

        $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
            'business_date' => $date,
            'cart_id' => $firstCart->id,
            'product_id' => $cucumber->id,
            'quantity' => 3,
            'unit_price' => 10,
            'return_to' => 'cart',
        ])->assertRedirect(route('purchaser.vendors', ['date' => $date]));

        $this->actingAs($this->purchaser)->post(route('purchaser.carts.store'), [
            'business_date' => $date,
        ])->assertRedirect();

        $this->assertSame(2, PurchaserCart::query()->whereDate('business_date', $date)->where('status', 'draft')->count());

        $sendResponse = $this->actingAs($this->purchaser)->post(route('purchaser.carts.send', $firstCart), [
            'supplier_id' => $this->supplier->id,
        ]);

        $sendResponse->assertStatus(302);
        $this->assertStringContainsString('wa.me', (string) $sendResponse->headers->get('Location'));

        $firstCart->refresh();
        $this->assertNotNull($firstCart->whatsapp_sent_at);

        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => $date,
            'cart_id' => $firstCart->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => null,
            'payment_method' => 'Cash',
            'paid_amount' => 130,
            'discount_amount' => 10,
            'payment_note' => 'Settled on spot',
            'payment_details' => 'Cash hand payment',
            'items' => [
                $firstCart->items()->firstOrFail()->id => ['unit_price' => 20],
                $firstCart->items()->latest('id')->firstOrFail()->id => ['unit_price' => 10],
            ],
        ])->assertRedirect(route('purchaser.history', ['date' => $date]));

        $firstCart->refresh();

        $this->assertSame('submitted', $firstCart->status);
        $this->assertNotNull($firstCart->purchase_order_id);
        $this->assertNotNull($firstCart->goods_received_id);
        $this->assertNotNull($firstCart->purchase_invoice_id);
        $this->assertDatabaseCount((new PurchaseOrder)->getTable(), 1);
        $this->assertDatabaseCount((new GoodsReceived)->getTable(), 1);
        $this->assertDatabaseCount((new PurchaseInvoice)->getTable(), 1);
        $this->assertSame(1, PurchaserCart::query()->whereDate('business_date', $date)->where('status', 'draft')->count());

        // Verify we can re-send WhatsApp on a submitted cart with custom mobile number
        $anotherSupplier = Supplier::factory()->create();
        $sendResponse2 = $this->actingAs($this->purchaser)->post(route('purchaser.carts.send', $firstCart), [
            'supplier_id' => $anotherSupplier->id,
            'vendor_mobile_number' => '9876543210',
            'return_to' => 'vendors',
        ]);

        $sendResponse2->assertStatus(302);
        $this->assertStringContainsString('wa.me/9876543210', (string) $sendResponse2->headers->get('Location'));

        $firstCart->refresh()->load(['purchaseOrder', 'goodsReceived', 'purchaseInvoice']);
        $this->assertSame($anotherSupplier->id, $firstCart->supplier_id);
        $this->assertSame($anotherSupplier->id, $firstCart->purchaseOrder->supplier_id);
        $this->assertSame($anotherSupplier->id, $firstCart->purchaseInvoice->supplier_id);
    }

    public function test_credit_payment_requires_approved_vendor(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);

        $product = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Bottle Gourd',
            'sku' => 'BOTTLE-100',
            'unit' => 'kg',
        ]);

        $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
            'business_date' => $date,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 15,
        ])->assertRedirect();

        $cart = PurchaserCart::query()->firstOrFail();

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => $date,
            'cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Credit',
            'items' => [
                $cart->items()->firstOrFail()->id => ['unit_price' => 15],
            ],
        ]);

        $response->assertRedirect(route('purchaser.bill', ['cart' => $cart, 'date' => $date]));
        $response->assertSessionHasErrors('0');
        $this->assertDatabaseCount((new PurchaseInvoice)->getTable(), 0);
    }

    public function test_credit_payment_is_marked_for_manager_approval_for_approved_vendor(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);
        $this->supplier->update([
            'credit_approved' => true,
            'credit_terms' => 'Net 30',
        ]);

        $product = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Pumpkin',
            'sku' => 'PUMP-100',
            'unit' => 'kg',
        ]);

        $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
            'business_date' => $date,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 15,
        ])->assertRedirect();

        $cart = PurchaserCart::query()->firstOrFail();

        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => $date,
            'cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Credit',
            'items' => [
                $cart->items()->firstOrFail()->id => ['unit_price' => 15],
            ],
        ])->assertRedirect(route('purchaser.history', ['date' => $date]));

        $cart->refresh();

        $this->assertSame('credit_pending_approval', $cart->payment_status);
        $this->assertSame('credit_pending_approval', PurchaseInvoice::query()->firstOrFail()->payment_status);
    }

    public function test_history_groups_statuses_and_operational_flags_can_be_updated(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Beans',
            'sku' => 'BEAN-100',
            'unit' => 'kg',
        ]);

        $this->createApprovedOrder($date, $this->shopA, $product, 4);

        $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
            'business_date' => $date,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 18,
        ])->assertRedirect();

        $cart = PurchaserCart::query()->firstOrFail();

        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => $date,
            'cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 72,
            'items' => [
                $cart->items()->firstOrFail()->id => ['unit_price' => 18],
            ],
        ])->assertRedirect(route('purchaser.history', ['date' => $date]));

        $cart->refresh();

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.history', ['date' => $date]));
        $response->assertOk();
        $response->assertSee('Submitted');
        $response->assertSee($cart->cart_number);

        $this->actingAs($this->purchaser)->patch(route('purchaser.carts.status', $cart), [
            'flag' => 'goods_received',
        ])->assertRedirect(route('purchaser.history', ['date' => $date]));

        $cart->refresh();

        $this->assertNotNull($cart->goods_received_at);
    }

    public function test_cart_submission_splits_excess_quantity_into_add_on_grn(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);

        $product = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Ladies Finger',
            'sku' => 'LADIES-100',
            'unit' => 'kg',
        ]);

        $this->createApprovedOrder($date, $this->shopA, $product, 5);

        $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
            'business_date' => $date,
            'product_id' => $product->id,
            'quantity' => 8,
            'unit_price' => 12,
        ])->assertRedirect();

        $cart = PurchaserCart::query()->firstOrFail();

        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => $date,
            'cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 96,
            'items' => [
                $cart->items()->firstOrFail()->id => ['unit_price' => 12],
            ],
        ])->assertRedirect(route('purchaser.history', ['date' => $date]));

        $this->assertDatabaseCount((new GoodsReceived)->getTable(), 2);

        $regularGrn = GoodsReceived::query()->where('is_extra', false)->with('items')->firstOrFail();
        $addOnGrn = GoodsReceived::query()->where('is_extra', true)->with('items')->firstOrFail();

        $this->assertSame('5.000', (string) $regularGrn->items->first()->received_qty);
        $this->assertSame('3.000', (string) $addOnGrn->items->first()->received_qty);
    }

    private function createApprovedOrder(string $date, Shop $shop, Product $product, float $approvedQty): void
    {
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'approved',
            'submitted_at' => now(),
            'created_by' => $this->shopUser->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => $approvedQty,
            'approved_qty' => $approvedQty,
            'unit' => $product->unit,
            'fulfillment_type' => 'warehouse',
        ]);
    }

    public function test_bulk_assign_creates_or_adds_to_cart(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);

        $product1 = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Carrot',
            'sku' => 'CAR-101',
            'unit' => 'kg',
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Potato',
            'sku' => 'POT-101',
            'unit' => 'kg',
        ]);

        $this->createApprovedOrder($date, $this->shopA, $product1, 10);
        $this->createApprovedOrder($date, $this->shopA, $product2, 8);

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.bulk-store'), [
            'business_date' => $date,
            'product_ids' => [$product1->id, $product2->id],
            'supplier_id' => $this->supplier->id,
        ]);

        $response->assertRedirect();

        $cart = PurchaserCart::query()->firstOrFail();
        $this->assertSame($this->supplier->id, $cart->supplier_id);
        $this->assertSame(2, $cart->items()->count());
    }

    public function test_vendors_screen_displays_active_and_delivered_orders(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);

        $product = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Cabbage',
            'sku' => 'CAB-101',
            'unit' => 'kg',
        ]);

        // Create an active cart (goods_received_at is null)
        $activeCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-ACT1',
            'status' => 'draft',
        ]);

        // Create a delivered cart (goods_received_at is set)
        $deliveredCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-DEL1',
            'status' => 'submitted',
            'goods_received_at' => now(),
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.vendors', ['date' => $date]));
        $response->assertOk();
        $response->assertSee('Daily Vendor Orders');
        $response->assertSee($activeCart->cart_number);
        $response->assertSee($deliveredCart->cart_number);
        $response->assertSee('Orders');
        $response->assertSee('Delivered');
    }
}
