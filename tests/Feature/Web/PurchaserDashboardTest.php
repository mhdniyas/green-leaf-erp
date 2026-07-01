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
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
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

    private int $submittedCartSequence = 0;

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

    public function test_daily_screen_defaults_to_next_business_date_after_nine_thirty_pm(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24 21:30:00'));

        try {
            $nextDate = '2026-06-25';
            $supplyCategory = Category::create(['name' => 'Supply', 'is_active' => true]);

            $tomato = Product::factory()->create([
                'category_id' => $supplyCategory->id,
                'name' => 'Tomorrow Tomato',
                'sku' => 'TOM-NXT-001',
                'unit' => 'kg',
            ]);

            $this->createApprovedOrder($nextDate, $this->shopA, $tomato, 6);

            $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily'));

            $response->assertOk();
            $response->assertSee('Tomorrow Tomato');
            $this->assertSame($nextDate, $response->viewData('date'));
        } finally {
            Carbon::setTestNow();
        }
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
        $response->assertSee('2 shops');
    }

    public function test_daily_screen_partitions_pending_and_completed_demand_sections(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $supplyCategory = Category::create(['name' => 'Supply', 'is_active' => true]);

        $tomato = Product::factory()->create([
            'category_id' => $supplyCategory->id,
            'name' => 'Tomato',
            'sku' => 'TOMATO-001',
            'unit' => 'kg',
        ]);

        $cucumber = Product::factory()->create([
            'category_id' => $supplyCategory->id,
            'name' => 'Cucumber',
            'sku' => 'CUCUMBER-001',
            'unit' => 'kg',
        ]);

        // Create approved order for Tomato (10 kg) -> Left as pending
        $this->createApprovedOrder($date, $this->shopA, $tomato, 10);

        // Create approved order for Cucumber (5 kg) -> Will be fully added to draft cart
        $this->createApprovedOrder($date, $this->shopA, $cucumber, 5);

        // Add Cucumber fully to cart
        $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
            'business_date' => $date,
            'product_id' => $cucumber->id,
            'quantity' => 5,
            'unit_price' => 15,
            'return_to' => 'daily',
        ])->assertRedirect(route('purchaser.daily', ['date' => $date]));

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily', [
            'date' => $date,
            'chip' => 'All',
        ]));

        $response->assertOk();
        $response->assertSee('Pending Demand (1)');
        $response->assertSee('Completed / In Cart (1)');
        $response->assertSee('Tomato');
        $response->assertSee('Cucumber');
    }

    public function test_daily_share_page_can_filter_by_multiple_products(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $supplyCategory = Category::create(['name' => 'Supply', 'is_active' => true]);
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);
        $fruitCategory = Category::create(['name' => 'Frut', 'is_active' => true]);

        $tomato = Product::factory()->create([
            'category_id' => $supplyCategory->id,
            'name' => 'Tomato',
            'sku' => 'TOMATO-001',
            'unit' => 'kg',
        ]);

        $beans = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Beans',
            'sku' => 'BEANS-001',
            'unit' => 'kg',
        ]);

        $banana = Product::factory()->create([
            'category_id' => $fruitCategory->id,
            'name' => 'Banana',
            'sku' => 'BANANA-001',
            'unit' => 'kg',
        ]);

        $this->createApprovedOrder($date, $this->shopA, $tomato, 5);
        $this->createApprovedOrder($date, $this->shopA, $beans, 3);
        $this->createApprovedOrder($date, $this->shopA, $banana, 7);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $date,
            'share_mode' => 'tag',
            'product_ids' => [$tomato->id, $beans->id],
        ]));

        $response->assertOk();
        $response->assertSee('Daily share summary');

        $shareSummary = $response->viewData('shareSummary');
        $this->assertCount(2, $shareSummary);
        $this->assertTrue($shareSummary->contains('product_name', 'Tomato'));
        $this->assertTrue($shareSummary->contains('product_name', 'Beans'));
        $this->assertFalse($shareSummary->contains('product_name', 'Banana'));

        $sharePreviewText = $response->viewData('sharePreviewText');
        $this->assertStringContainsString('Tomato', $sharePreviewText);
        $this->assertStringContainsString('Beans', $sharePreviewText);
        $this->assertStringNotContainsString('Banana', $sharePreviewText);
    }

    public function test_daily_share_page_can_filter_to_single_product(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $supplyCategory = Category::create(['name' => 'Supply', 'is_active' => true]);

        $tomato = Product::factory()->create([
            'category_id' => $supplyCategory->id,
            'name' => 'Tomato',
            'sku' => 'TOMATO-001',
            'unit' => 'kg',
        ]);

        $onion = Product::factory()->create([
            'category_id' => $supplyCategory->id,
            'name' => 'Onion',
            'sku' => 'ONION-001',
            'unit' => 'kg',
        ]);

        $this->createApprovedOrder($date, $this->shopA, $tomato, 5);
        $this->createApprovedOrder($date, $this->shopA, $onion, 8);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $date,
            'share_mode' => 'product',
            'product_id' => $onion->id,
        ]));

        $response->assertOk();

        $shareSummary = $response->viewData('shareSummary');
        $this->assertCount(1, $shareSummary);
        $this->assertTrue($shareSummary->contains('product_name', 'Onion'));
        $this->assertFalse($shareSummary->contains('product_name', 'Tomato'));

        $sharePreviewText = $response->viewData('sharePreviewText');
        $this->assertStringContainsString('Onion', $sharePreviewText);
        $this->assertStringNotContainsString('Tomato', $sharePreviewText);
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

    public function test_purchaser_redirect_preserves_chip_and_search_filters(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);

        $product = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'unit' => 'kg',
        ]);

        $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
            'business_date' => $date,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 12,
            'return_to' => 'daily',
            'chip' => 'VEG',
            'search' => 'tom',
        ])->assertRedirect(route('purchaser.daily', [
            'date' => $date,
            'chip' => 'VEG',
            'search' => 'tom',
        ]));
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

        $this->assertSame(1, PurchaserCart::query()->whereDate('business_date', $date)->where('status', 'draft')->count());

        $sendResponse = $this->actingAs($this->purchaser)->post(route('purchaser.carts.send', $firstCart), [
            'supplier_id' => $this->supplier->id,
        ]);

        $sendResponse->assertStatus(302);
        $this->assertStringContainsString('api.whatsapp.com/send', (string) $sendResponse->headers->get('Location'));

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
        ])->assertRedirect(route('purchaser.vendors', ['date' => $date, 'tab' => 'pending']));

        $firstCart->refresh();

        $this->assertSame('submitted', $firstCart->status);
        $this->assertNotNull($firstCart->purchase_order_id);
        $this->assertNotNull($firstCart->goods_received_id);
        $this->assertNotNull($firstCart->purchase_invoice_id);
        $this->assertDatabaseCount((new PurchaseOrder)->getTable(), 1);
        $this->assertDatabaseCount((new GoodsReceived)->getTable(), 1);
        $this->assertDatabaseCount((new PurchaseInvoice)->getTable(), 1);
        $this->assertSame(0, PurchaserCart::query()->whereDate('business_date', $date)->where('status', 'draft')->count());

        // Verify we can re-send WhatsApp on a submitted cart with custom mobile number
        $anotherSupplier = Supplier::factory()->create();
        $sendResponse2 = $this->actingAs($this->purchaser)->post(route('purchaser.carts.send', $firstCart), [
            'supplier_id' => $anotherSupplier->id,
            'share_mode' => 'custom',
            'vendor_mobile_number' => '9876543210',
            'return_to' => 'vendors',
        ]);

        $sendResponse2->assertStatus(302);
        $this->assertStringContainsString('api.whatsapp.com/send?phone=919876543210', (string) $sendResponse2->headers->get('Location'));

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
        ])->assertRedirect(route('purchaser.vendors', ['date' => $date, 'tab' => 'pending']));

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
            'paid_amount' => 0,
            'items' => [
                $cart->items()->firstOrFail()->id => ['unit_price' => 18],
            ],
        ])->assertRedirect(route('purchaser.vendors', ['date' => $date, 'tab' => 'pending']));

        $cart->refresh();

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.history', ['date' => $date]));
        $response->assertOk();
        $response->assertSee('Purchase report');
        $response->assertSee('Today');
        $response->assertSee('History');
        $response->assertSee('Processing');
        $response->assertSee($cart->cart_number);

        $this->actingAs($this->purchaser)->patch(route('purchaser.carts.status', $cart), [
            'flag' => 'goods_received',
        ])->assertRedirect(route('purchaser.history', ['date' => $date]));

        $cart->refresh();

        $this->assertNotNull($cart->goods_received_at);
        $this->assertNotNull($cart->bill_received_at);
        $this->assertNull($cart->payment_made_at);
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
        ])->assertRedirect(route('purchaser.vendors', ['date' => $date, 'tab' => 'pending']));

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
            'items' => [
                $product1->id => ['quantity' => 6, 'unit_price' => 18],
                $product2->id => ['quantity' => 4, 'unit_price' => 22],
            ],
        ]);

        $response->assertRedirect();

        $cart = PurchaserCart::query()->firstOrFail();
        $this->assertNull($cart->supplier_id);
        $this->assertSame(2, $cart->items()->count());
        $carrotItem = $cart->items()->where('product_id', $product1->id)->firstOrFail();
        $potatoItem = $cart->items()->where('product_id', $product2->id)->firstOrFail();

        $this->assertSame('6.000', (string) $carrotItem->quantity);
        $this->assertSame('18.0000', (string) $carrotItem->unit_price);
        $this->assertSame('4.000', (string) $potatoItem->quantity);
        $this->assertSame('22.0000', (string) $potatoItem->unit_price);
    }

    public function test_store_cart_reuses_existing_pending_draft_for_the_day(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        $this->actingAs($this->purchaser)->post(route('purchaser.carts.store'), [
            'business_date' => $date,
        ])->assertRedirect(route('purchaser.vendors', ['date' => $date]));

        $this->actingAs($this->purchaser)->post(route('purchaser.carts.store'), [
            'business_date' => $date,
        ])->assertRedirect(route('purchaser.vendors', ['date' => $date]));

        $this->assertSame(1, PurchaserCart::query()
            ->where('user_id', $this->purchaser->id)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->whereNull('supplier_id')
            ->count());
    }

    public function test_assigning_same_supplier_merges_into_existing_open_cart(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);
        $tomato = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Tomato',
            'sku' => 'TOM-101',
            'unit' => 'kg',
        ]);
        $beans = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Beans',
            'sku' => 'BEA-101',
            'unit' => 'kg',
        ]);

        $firstCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-MERGE1',
            'status' => 'draft',
        ]);
        $firstCart->items()->create([
            'product_id' => $tomato->id,
            'quantity' => 2,
            'unit_price' => 20,
            'line_total' => 40,
            'is_extra_purchase' => false,
        ]);

        $secondCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-MERGE2',
            'status' => 'draft',
        ]);
        $secondCart->items()->create([
            'product_id' => $beans->id,
            'quantity' => 3,
            'unit_price' => 18,
            'line_total' => 54,
            'is_extra_purchase' => false,
        ]);

        $this->actingAs($this->purchaser)->patch(route('purchaser.carts.update-supplier', $secondCart), [
            'supplier_id' => $this->supplier->id,
            'return_to' => 'vendors',
        ])->assertRedirect(route('purchaser.vendors', ['date' => $date]));

        $this->assertDatabaseMissing((new PurchaserCart)->getTable(), ['id' => $secondCart->id]);

        $firstCart->refresh();
        $this->assertSame($this->supplier->id, $firstCart->supplier_id);
        $this->assertSame(2, $firstCart->items()->count());
    }

    public function test_same_supplier_gets_new_cart_after_previous_bill_is_processed(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        $submittedCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-SUB1',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $draftCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-DRF1',
            'status' => 'draft',
        ]);

        $this->actingAs($this->purchaser)->patch(route('purchaser.carts.update-supplier', $draftCart), [
            'supplier_id' => $this->supplier->id,
            'return_to' => 'vendors',
        ])->assertRedirect(route('purchaser.vendors', ['date' => $date]));

        $this->assertDatabaseHas((new PurchaserCart)->getTable(), ['id' => $submittedCart->id, 'supplier_id' => $this->supplier->id]);
        $this->assertDatabaseHas((new PurchaserCart)->getTable(), ['id' => $draftCart->id, 'supplier_id' => $this->supplier->id, 'status' => 'draft']);
    }

    public function test_purchaser_can_merge_duplicate_draft_carts_into_one_order(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);
        $tomato = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Tomato',
            'sku' => 'TOM-MRG-1',
            'unit' => 'kg',
        ]);
        $beans = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Beans',
            'sku' => 'BEA-MRG-1',
            'unit' => 'kg',
        ]);

        $olderCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-M1',
            'status' => 'draft',
            'updated_at' => now()->subMinute(),
        ]);
        $olderCart->items()->create([
            'product_id' => $tomato->id,
            'quantity' => 2,
            'unit_price' => 20,
            'line_total' => 40,
            'is_extra_purchase' => false,
        ]);

        $latestCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-M2',
            'status' => 'draft',
        ]);
        $latestCart->items()->create([
            'product_id' => $beans->id,
            'quantity' => 3,
            'unit_price' => 18,
            'line_total' => 54,
            'is_extra_purchase' => false,
        ]);

        $this->actingAs($this->purchaser)->post(route('purchaser.carts.merge-drafts', $latestCart))
            ->assertRedirect(route('purchaser.vendors', ['date' => $date]));

        $this->assertSame(1, PurchaserCart::query()
            ->where('user_id', $this->purchaser->id)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->where('supplier_id', $this->supplier->id)
            ->count());
        $remainingCart = PurchaserCart::query()
            ->where('user_id', $this->purchaser->id)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->where('supplier_id', $this->supplier->id)
            ->firstOrFail();
        $this->assertSame(2, $remainingCart->items()->count());
    }

    public function test_vendors_screen_shows_merge_notification_for_duplicate_drafts(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-N1',
            'status' => 'draft',
        ]);

        PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-N2',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.vendors', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('Merge Suggestion');
        $response->assertSee('Merge as One');
    }

    public function test_updating_cart_item_quantity_keeps_daily_order_quantity_intact(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Brinjal',
            'sku' => 'BRI-101',
            'unit' => 'kg',
        ]);

        $this->createApprovedOrder($date, $this->shopA, $product, 5);

        $this->actingAs($this->purchaser)->post(route('purchaser.cart-items.store'), [
            'business_date' => $date,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 12,
            'return_to' => 'vendors',
        ])->assertRedirect(route('purchaser.vendors', ['date' => $date]));

        $cartItem = PurchaserCartItem::query()->firstOrFail();

        $this->actingAs($this->purchaser)->patch(route('purchaser.cart-items.update', $cartItem), [
            'quantity' => 7,
            'unit_price' => 12,
            'return_to' => 'vendors',
        ])->assertRedirect(route('purchaser.vendors', ['date' => $date]));

        $this->assertSame('7.000', (string) $cartItem->fresh()->quantity);
        $this->assertSame('5.00', (string) ShopOrderItem::query()->firstOrFail()->approved_qty);
    }

    public function test_vendors_screen_displays_draft_pending_and_completed_sections(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);

        $product = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Cabbage',
            'sku' => 'CAB-101',
            'unit' => 'kg',
        ]);

        $draftCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-DRAFT1',
            'status' => 'draft',
        ]);

        $pendingCart = $this->createSubmittedCartWithInvoice($date, 110.0, 0.0);
        $this->attachReceiptState($pendingCart, warehouseConfirmed: true, receiptNotes: 'Payment still open.');

        $completedCart = $this->createSubmittedCartWithInvoice($date, 125.0, 125.0);
        $this->attachReceiptState($completedCart, warehouseConfirmed: true, receiptNotes: 'Fully received and settled.');

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.vendors', ['date' => $date]));
        $response->assertOk();
        $response->assertSee('Daily Carts');
        $response->assertSee($draftCart->cart_number);
        $response->assertSee($pendingCart->cart_number);
        $response->assertSee($completedCart->cart_number);
        $response->assertSee('Draft');
        $response->assertSee('Pending');
        $response->assertSee('Completed');
    }

    public function test_vendors_screen_shows_previous_vendor_price_hint(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $category = Category::create(['name' => 'VEG', 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Ladies Finger',
            'sku' => 'LF-101',
            'unit' => 'kg',
            'vendor_price' => 31.25,
        ]);

        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-HINT',
            'status' => 'draft',
        ]);

        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 0,
            'line_total' => 0,
        ]);

        $this->supplier->products()->attach($product->id, [
            'last_price' => 29.5,
            'last_purchased_at' => now(),
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.vendors', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('Prev ₹29.50');
    }

    public function test_submitting_cart_updates_product_and_vendor_latest_price(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $category = Category::create(['name' => 'VEG', 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Drumstick',
            'sku' => 'DRM-101',
            'unit' => 'kg',
            'vendor_price' => 15,
        ]);

        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-SUB',
            'status' => 'draft',
        ]);

        $cartItem = $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 0,
            'line_total' => 0,
        ]);

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => $date,
            'cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-101',
            'payment_method' => 'Cash',
            'paid_amount' => 0,
            'discount_amount' => 0,
            'payment_note' => null,
            'payment_details' => null,
            'notes' => 'Submitted from test.',
            'items' => [
                (string) $cartItem->id => ['unit_price' => 42.75],
            ],
        ]);

        $response->assertRedirect(route('purchaser.vendors', ['date' => $date, 'tab' => 'pending']));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'vendor_price' => 42.75,
        ]);
        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $product->id,
            'supplier_id' => $this->supplier->id,
            'last_price' => 42.75,
        ]);
    }

    public function test_purchaser_finance_screen_shows_generated_bills(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $cart = $this->createSubmittedCartWithInvoice($date, 120.0, 0.0);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.finance', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('Cart finance');
        $response->assertSee('Bills generated');
        $response->assertSee($cart->purchaseInvoice->invoice_number);
        $response->assertSee($this->supplier->mobile_number);
        $response->assertSee('Business day '.Carbon::parse($date)->format('d M Y'));
    }

    public function test_purchaser_finance_tabs_split_today_and_old_invoices(): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $oldDate = Carbon::today()->subDay()->format('Y-m-d');

        $todayCart = $this->createSubmittedCartWithInvoice($today, 120.0, 0.0);
        $oldCart = $this->createSubmittedCartWithInvoice($oldDate, 95.0, 0.0);
        $oldCart->purchaseInvoice->update([
            'payment_status' => 'partial',
            'paid_amount' => 40,
            'updated_at' => Carbon::parse($today)->setTime(14, 30),
        ]);
        $oldCart->update([
            'payment_status' => 'partial',
            'paid_amount' => 40,
        ]);

        $todayResponse = $this->actingAs($this->purchaser)->get(route('purchaser.finance', [
            'date' => $today,
            'tab' => 'today',
        ]));

        $todayResponse->assertOk();
        $todayResponse->assertSee($todayCart->purchaseInvoice->invoice_number);
        $todayResponse->assertDontSee($oldCart->purchaseInvoice->invoice_number);
        $todayResponse->assertSee('Today');
        $todayResponse->assertSee('Old');

        $oldResponse = $this->actingAs($this->purchaser)->get(route('purchaser.finance', [
            'date' => $today,
            'tab' => 'old',
        ]));

        $oldResponse->assertOk();
        $oldResponse->assertSee($oldCart->purchaseInvoice->invoice_number);
        $oldResponse->assertDontSee($todayCart->purchaseInvoice->invoice_number);
        $oldResponse->assertSee('older invoices before');
        $oldResponse->assertSee('Partially Paid');
        $oldResponse->assertSee('Partially paid on');
        $oldResponse->assertSee(Carbon::parse($today)->format('d M Y'));
    }

    public function test_purchaser_finance_search_filters_today_and_old_tabs(): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $oldDate = Carbon::today()->subDay()->format('Y-m-d');

        $todaySupplier = Supplier::factory()->create(['name' => 'Search Today Vendor', 'mobile_number' => '9000000001']);
        $oldSupplier = Supplier::factory()->create(['name' => 'Search Old Vendor', 'mobile_number' => '9000000002']);

        $todayCart = $this->createSubmittedCartWithInvoice($today, 120.0, 0.0, $todaySupplier);
        $oldCart = $this->createSubmittedCartWithInvoice($oldDate, 95.0, 0.0, $oldSupplier);

        $todayResponse = $this->actingAs($this->purchaser)->get(route('purchaser.finance', [
            'date' => $today,
            'tab' => 'today',
            'search' => 'Search Today Vendor',
        ]));

        $todayResponse->assertOk();
        $todayResponse->assertSee($todayCart->purchaseInvoice->invoice_number);
        $todayResponse->assertDontSee($oldCart->purchaseInvoice->invoice_number);

        $oldResponse = $this->actingAs($this->purchaser)->get(route('purchaser.finance', [
            'date' => $today,
            'tab' => 'old',
            'search' => 'Search Old Vendor',
        ]));

        $oldResponse->assertOk();
        $oldResponse->assertSee($oldCart->purchaseInvoice->invoice_number);
        $oldResponse->assertDontSee($todayCart->purchaseInvoice->invoice_number);
    }

    public function test_purchaser_can_open_own_invoice_pdf(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $cart = $this->createSubmittedCartWithInvoice($date, 120.0, 0.0);

        $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.pdf', $cart->purchaseInvoice))
            ->assertOk()
            ->assertSee($cart->purchaseInvoice->invoice_number);
    }

    public function test_purchaser_can_view_own_invoice_details_page(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $cart = $this->createSubmittedCartWithInvoice($date, 120.0, 0.0);

        $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.show', $cart->purchaseInvoice))
            ->assertOk()
            ->assertSee($cart->purchaseInvoice->invoice_number)
            ->assertSee('Line Items')
            ->assertSee('Open Bill');
    }

    public function test_cart_share_custom_number_uses_india_prefix(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $cart = $this->createSubmittedCartWithInvoice($date, 120.0, 0.0);

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.send', $cart), [
            'supplier_id' => $this->supplier->id,
            'share_mode' => 'custom',
            'vendor_mobile_number' => '9876543210',
            'return_to' => 'vendors',
        ]);

        $response->assertStatus(302);
        $this->assertStringContainsString('api.whatsapp.com/send?phone=919876543210', (string) $response->headers->get('Location'));
    }

    public function test_cart_share_can_open_whatsapp_without_number(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $cart = $this->createSubmittedCartWithInvoice($date, 120.0, 0.0);

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.send', $cart), [
            'supplier_id' => $this->supplier->id,
            'share_mode' => 'any',
            'return_to' => 'vendors',
        ]);

        $response->assertStatus(302);
        $this->assertStringContainsString('api.whatsapp.com/send?text=', (string) $response->headers->get('Location'));
    }

    public function test_partial_payment_updates_finance_but_does_not_complete_cart(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $cart = $this->createSubmittedCartWithInvoice($date, 150.0, 0.0);

        $this->actingAs($this->purchaser)->patch(route('purchaser.invoices.payment', $cart->purchaseInvoice), [
            'payment_method' => 'Cash',
            'paid_amount' => 50,
            'payment_note' => 'Part payment',
            'payment_details' => 'Cash advance',
        ])->assertRedirect(route('purchaser.finance', ['date' => $date]));

        $cart->refresh();
        $invoice = $cart->purchaseInvoice()->firstOrFail();

        $this->assertSame('partial', $cart->payment_status);
        $this->assertSame('partial', $invoice->payment_status);
        $this->assertSame('50.00', (string) $invoice->paid_amount);
        $this->assertNull($cart->payment_made_at);
        $this->assertNull($cart->goods_received_at);
    }

    public function test_additional_payment_amount_is_added_to_existing_paid_total(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $cart = $this->createSubmittedCartWithInvoice($date, 150.0, 50.0);

        $this->actingAs($this->purchaser)->patch(route('purchaser.invoices.payment', $cart->purchaseInvoice), [
            'payment_method' => 'Cash',
            'additional_paid_amount' => 40,
            'payment_note' => 'Additional payment collected',
            'payment_details' => 'Second settlement',
        ])->assertRedirect(route('purchaser.finance', ['date' => $date]));

        $cart->refresh();
        $invoice = $cart->purchaseInvoice()->firstOrFail();

        $this->assertSame('90.00', (string) $invoice->paid_amount);
        $this->assertSame('partial', $invoice->payment_status);
        $this->assertSame('partial', $cart->payment_status);
    }

    public function test_full_payment_marks_cart_and_invoice_as_paid(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $cart = $this->createSubmittedCartWithInvoice($date, 175.0, 0.0);

        $this->actingAs($this->purchaser)->patch(route('purchaser.invoices.payment', $cart->purchaseInvoice), [
            'payment_method' => 'Online',
            'paid_amount' => 175,
            'payment_note' => 'Bank transfer',
            'payment_details' => 'UTR12345',
        ])->assertRedirect(route('purchaser.finance', ['date' => $date]));

        $cart->refresh();
        $invoice = $cart->purchaseInvoice()->firstOrFail();

        $this->assertSame('paid', $cart->payment_status);
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertNotNull($cart->payment_made_at);
        $this->assertNotNull($cart->goods_received_at);
    }

    public function test_full_payment_after_discount_marks_cart_and_invoice_as_paid(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $cart = $this->createSubmittedCartWithInvoice($date, 175.0, 0.0);

        $this->actingAs($this->purchaser)->patch(route('purchaser.invoices.payment', $cart->purchaseInvoice), [
            'payment_method' => 'Cash',
            'discount_amount' => 15,
            'paid_amount' => 160,
            'payment_note' => 'Discount settled with supplier',
            'payment_details' => 'Adjusted after final weighing',
        ])->assertRedirect(route('purchaser.finance', ['date' => $date]));

        $cart->refresh();
        $invoice = $cart->purchaseInvoice()->firstOrFail();

        $this->assertSame('15.00', (string) $invoice->discount_amount);
        $this->assertSame('160.00', (string) $invoice->paid_amount);
        $this->assertSame('paid', $cart->payment_status);
        $this->assertSame('paid', $invoice->payment_status);
    }

    public function test_credit_payment_update_stays_pending_and_not_completed(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $this->supplier->update([
            'credit_approved' => true,
            'credit_terms' => 'Net 15',
        ]);
        $cart = $this->createSubmittedCartWithInvoice($date, 90.0, 0.0);

        $this->actingAs($this->purchaser)->patch(route('purchaser.invoices.payment', $cart->purchaseInvoice), [
            'payment_method' => 'Credit',
            'paid_amount' => 0,
            'payment_note' => 'Approved credit cycle',
            'payment_details' => 'Net 15 agreed',
        ])->assertRedirect(route('purchaser.finance', ['date' => $date]));

        $cart->refresh();
        $invoice = $cart->purchaseInvoice()->firstOrFail();

        $this->assertSame('credit_pending_approval', $cart->payment_status);
        $this->assertSame('credit_pending_approval', $invoice->payment_status);
        $this->assertNull($cart->payment_made_at);
    }

    private function createSubmittedCartWithInvoice(string $date, float $amount, float $paidAmount, ?Supplier $supplier = null): PurchaserCart
    {
        $supplier ??= $this->supplier;
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit' => 'kg',
        ]);

        $this->submittedCartSequence++;

        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-FIN'.str_pad((string) $this->submittedCartSequence, 4, '0', STR_PAD_LEFT),
            'status' => 'submitted',
            'bill_number' => 'BILL-'.str_pad((string) $this->submittedCartSequence, 4, '0', STR_PAD_LEFT),
            'bill_received_at' => now(),
            'submitted_at' => now(),
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => $amount / 2,
            'line_total' => $amount,
            'is_extra_purchase' => false,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'supplier_id' => $supplier->id,
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'PINV-TEST-'.random_int(1000, 9999),
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'status' => 'pending',
            'payment_method' => 'Cash',
            'payment_status' => $paidAmount >= $amount && $amount > 0 ? 'paid' : 'unpaid',
        ]);

        $cart->update([
            'purchase_invoice_id' => $invoice->id,
            'payment_method' => 'Cash',
            'payment_status' => $invoice->payment_status,
            'paid_amount' => $paidAmount,
            'payment_made_at' => $paidAmount >= $amount && $amount > 0 ? now() : null,
        ]);

        return $cart->fresh(['purchaseInvoice']);
    }

    private function attachReceiptState(PurchaserCart $cart, bool $warehouseConfirmed, string $receiptNotes): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $cart->supplier_id ?? $this->supplier->id,
            'created_by' => $this->purchaser->id,
            'order_date' => $cart->business_date->format('Y-m-d'),
        ]);

        $goodsReceived = GoodsReceived::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'received_by' => $this->purchaser->id,
            'received_at' => $cart->business_date->format('Y-m-d'),
            'grn_number' => 'GRN-'.$cart->business_date->format('Ymd').'-'.random_int(10000, 99999),
            'notes' => $receiptNotes."\nCart: ".$cart->cart_number,
        ]);

        $cart->update([
            'purchase_order_id' => $purchaseOrder->id,
            'goods_received_id' => $goodsReceived->id,
            'goods_received_at' => $warehouseConfirmed ? now() : null,
        ]);

        $invoice = $cart->purchaseInvoice()->firstOrFail();
        $invoice->update(['goods_received_id' => $goodsReceived->id]);

        StockBatch::factory()
            ->for($cart->items()->firstOrFail()->product)
            ->create([
                'created_by' => $this->purchaser->id,
                'received_at' => $cart->business_date->format('Y-m-d'),
                'warehouse_receive_pending' => ! $warehouseConfirmed,
                'warehouse_confirmed_at' => $warehouseConfirmed ? now() : null,
                'warehouse_confirmed_by' => $warehouseConfirmed ? $this->purchaser->id : null,
                'notes' => 'Auto-created from GRN: '.$goodsReceived->grn_number,
            ]);
    }

    public function test_daily_screen_displays_names_of_purchasers_who_added_items_to_cart(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);

        $product = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Mint',
            'sku' => 'MINT-001',
            'unit' => 'kg',
        ]);

        $this->createApprovedOrder($date, $this->shopA, $product, 5);

        // Purchaser A adds 2 kg
        $cartA = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $date,
            'cart_number' => 'VC-A',
            'status' => 'draft',
        ]);
        $cartA->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10,
            'line_total' => 20,
            'is_extra_purchase' => false,
        ]);

        // Purchaser B (Manager) adds 3 kg
        $cartB = PurchaserCart::create([
            'user_id' => $this->purchaseManager->id,
            'business_date' => $date,
            'cart_number' => 'VC-B',
            'status' => 'draft',
        ]);
        $cartB->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 10,
            'line_total' => 30,
            'is_extra_purchase' => false,
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily', [
            'date' => $date,
            'chip' => 'All',
        ]));

        $response->assertOk();
        $response->assertSee($this->purchaser->name);
        $response->assertSee($this->purchaseManager->name);
    }

    public function test_purchaser_can_view_bulk_buy_selection_page(): void
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

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', [
            'date' => $date,
        ]));

        $response->assertOk();
        $response->assertSee('Bulk Purchase (Step 1)');
        $response->assertSee('Tomato');
        $response->assertDontSee('id="layout-mobile-nav"');
    }

    public function test_purchaser_can_view_bulk_buy_details_page_with_selected_products(): void
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

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.details', [
            'date' => $date,
            'product_ids' => [$tomato->id],
        ]));

        $response->assertOk();
        $response->assertSee('Bulk Purchase (Step 2)');
        $response->assertSee('Tomato');
        $response->assertSee('New cart');
        $response->assertDontSee('id="layout-mobile-nav"');
    }

    public function test_purchaser_cannot_access_bulk_buy_details_without_selection(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.details', [
            'date' => $date,
        ]));

        $response->assertRedirect(route('purchaser.bulk-buy', ['date' => $date]));
        $response->assertSessionHas('error', 'Please select at least one product.');
    }

    public function test_purchaser_can_view_bulk_buy_details_page_with_box_toggles_for_kg_products(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $supplyCategory = Category::create(['name' => 'Supply', 'is_active' => true]);

        $tomato = Product::factory()->create([
            'category_id' => $supplyCategory->id,
            'name' => 'Tomato',
            'sku' => 'TOMATO-001',
            'unit' => 'kg',
        ]);

        $apple = Product::factory()->create([
            'category_id' => $supplyCategory->id,
            'name' => 'Apple',
            'sku' => 'APPLE-001',
            'unit' => 'box',
        ]);

        $this->createApprovedOrder($date, $this->shopA, $tomato, 5);
        $this->createApprovedOrder($date, $this->shopA, $apple, 2);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.details', [
            'date' => $date,
            'product_ids' => [$tomato->id, $apple->id],
        ]));

        $response->assertOk();
        $response->assertSee('basis-kg-btn-'.$tomato->id);
        $response->assertSee('basis-box-btn-'.$tomato->id);
        $response->assertSee('qty-box-'.$tomato->id);
        $response->assertSee('conv-box-'.$tomato->id);
        $response->assertSee('price-box-'.$tomato->id);

        // Apple is unit 'box', so it should not have basis selection toggle
        $response->assertDontSee('basis-kg-btn-'.$apple->id);
        $response->assertDontSee('basis-box-btn-'.$apple->id);
        $response->assertDontSee('qty-box-'.$apple->id);
    }

    public function test_purchaser_send_whatsapp_with_show_price_toggle(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);

        $product = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Armenian Cucumber Long',
            'sku' => 'BG-01',
            'unit' => 'kg',
        ]);

        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-DRAFT-001',
            'status' => 'draft',
        ]);

        $item = $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 25.50,
        ]);

        // Scenario 1: show_price is false (default)
        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.send', $cart), [
            'supplier_id' => $this->supplier->id,
            'show_price' => '0',
        ]);

        $response->assertStatus(302);
        $redirectUrl = (string) $response->headers->get('Location');
        $this->assertStringContainsString('api.whatsapp.com/send', $redirectUrl);
        // Message text should NOT contain the price
        $decodedWithoutPrice = rawurldecode($redirectUrl);
        $this->assertStringNotContainsString('25.5', $decodedWithoutPrice);
        $this->assertStringNotContainsString('Net Total', $decodedWithoutPrice);

        // Scenario 2: show_price is true and discount is applied
        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.send', $cart), [
            'supplier_id' => $this->supplier->id,
            'show_price' => '1',
            'discount_amount' => '5.50',
        ]);

        $response->assertStatus(302);
        $redirectUrl = (string) $response->headers->get('Location');
        $this->assertStringContainsString('api.whatsapp.com/send', $redirectUrl);
        $decodedWithPrice = rawurldecode($redirectUrl);
        $dateFormatted = \Carbon\Carbon::parse($date)->format('d/m/Y');
        $this->assertStringContainsString("Green Leaf Traders - Purchase Order\nDate:", $decodedWithPrice);
        $this->assertStringContainsString("Date: {$dateFormatted} |", $decodedWithPrice);
        $this->assertStringContainsString("```\nItem", $decodedWithPrice);
        $this->assertStringContainsString('Armenian', $decodedWithPrice);
        $this->assertStringContainsString('Cucumber Long', $decodedWithPrice);
        $this->assertStringContainsString('25.5', $decodedWithPrice);
        $this->assertStringContainsString('255', $decodedWithPrice);
        $this->assertStringContainsString("\n\nTotal", $decodedWithPrice);
        $this->assertStringContainsString('Discount', $decodedWithPrice);
        $this->assertStringContainsString('5.5', $decodedWithPrice);
        $this->assertStringContainsString('Net Total', $decodedWithPrice);
        $this->assertStringContainsString('249.5', $decodedWithPrice);

        // Scenario 3: discount alone should force bill-format share
        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.send', $cart), [
            'supplier_id' => $this->supplier->id,
            'show_price' => '0',
            'discount_amount' => '10.00',
        ]);

        $response->assertStatus(302);
        $decodedDiscountOnly = rawurldecode((string) $response->headers->get('Location'));
        $this->assertStringContainsString('Discount', $decodedDiscountOnly);
        $this->assertStringContainsString('Net Total', $decodedDiscountOnly);
        $this->assertStringContainsString('245', $decodedDiscountOnly);
    }

    public function test_daily_summary_only_shows_selected_business_date_and_matching_cart_quantities(): void
    {
        $dateToday = Carbon::today()->format('Y-m-d');
        $dateYesterday = Carbon::yesterday()->format('Y-m-d');

        $vegCategory = Category::create(['name' => 'VEG', 'is_active' => true]);

        $productA = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Tomato Today',
            'sku' => 'TOM-TDY',
            'unit' => 'kg',
        ]);

        $productB = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Potato Yesterday',
            'sku' => 'POT-PST',
            'unit' => 'kg',
        ]);

        $productC = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Mint Carryover',
            'sku' => 'MINT-CRY',
            'unit' => 'kg',
        ]);

        $this->createApprovedOrder($dateToday, $this->shopA, $productA, 10);
        $this->createApprovedOrder($dateYesterday, $this->shopA, $productB, 5);
        $this->createApprovedOrder($dateToday, $this->shopA, $productC, 6);

        $yesterdayDraftCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $dateYesterday,
            'cart_number' => 'VC-'.str_replace('-', '', $dateYesterday).'-DRAFT',
            'status' => 'draft',
        ]);

        $yesterdayDraftCart->items()->create([
            'product_id' => $productB->id,
            'quantity' => 5,
            'unit_price' => 20,
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => $dateToday, 'chip' => 'All']));
        $response->assertOk();

        $dailySummaryData = $response->viewData('dailySummary');
        $this->assertCount(2, $dailySummaryData);
        $this->assertTrue($dailySummaryData->contains('product_name', 'Tomato Today'));
        $this->assertTrue($dailySummaryData->contains('product_name', 'Mint Carryover'));
        $this->assertFalse($dailySummaryData->contains('product_name', 'Potato Yesterday'));

        $response->assertSee('Tomato Today');
        $response->assertSee('Mint Carryover');
        $response->assertDontSee('Pending ('.Carbon::parse($dateYesterday)->format('d M Y').')');
        $response->assertDontSee('5.00 kg in cart');

        $shareUrl = $response->viewData('dailySummaryShareUrl');
        $this->assertStringContainsString('api.whatsapp.com/send', $shareUrl);
        $decodedText = rawurldecode($shareUrl);

        $this->assertStringContainsString('*Tomato Today*', $decodedText);
        $this->assertStringContainsString('*Mint Carryover*', $decodedText);
        $this->assertStringNotContainsString('Potato Yesterday', $decodedText);
    }

    public function test_deadline_banner_shows_after_four_pm_for_unresolved_same_day_draft_carts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24 16:00:00'));

        try {
            $date = '2026-06-24';

            $cart = PurchaserCart::create([
                'user_id' => $this->purchaser->id,
                'business_date' => $date,
                'cart_number' => 'VC-20260624-WARN',
                'status' => 'draft',
            ]);
            $cart->items()->create([
                'product_id' => Product::factory()->create()->id,
                'quantity' => 2,
                'unit_price' => 0,
                'line_total' => 0,
            ]);

            $response = $this->actingAs($this->purchaser)->get(route('purchaser.vendors', ['date' => $date]));

            $response->assertOk();
            $response->assertSee('Purchaser Action Required');
            $response->assertSee('Vendor pending: 1');
            $response->assertSee('Resolve carts before the 9:30 PM business-day rollover.');
            $response->assertSee('Vendor Hub');
            $this->assertSame(1, $response->viewData('deadlineAlert')['pending_total_count']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_deadline_banner_explains_when_overdue_cart_is_waiting_for_receipt_confirmation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 10:00:00'));

        try {
            $cart = $this->createSubmittedCartWithInvoice('2026-06-24', 120.0, 50.0);
            $this->attachReceiptState($cart, warehouseConfirmed: true, receiptNotes: 'Needs payment update.');

            $response = $this->actingAs($this->purchaser)->get(route('purchaser.history', ['date' => '2026-06-25']));

            $response->assertOk();
            $response->assertDontSee('Receipt pending: 1');
            $response->assertSee('still need warehouse confirmation or payment follow-up');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_history_shows_overdue_processing_payment_pending_and_completed_sections(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 10:00:00'));

        try {
            $overdueCart = PurchaserCart::create([
                'user_id' => $this->purchaser->id,
                'business_date' => '2026-06-24',
                'cart_number' => 'VC-20260624-OVERDUE',
                'status' => 'draft',
            ]);
            $overdueCart->items()->create([
                'product_id' => Product::factory()->create()->id,
                'quantity' => 2,
                'unit_price' => 12,
                'line_total' => 24,
            ]);

            $processingCart = $this->createSubmittedCartWithInvoice('2026-06-25', 100.0, 0.0);
            $this->attachReceiptState($processingCart, warehouseConfirmed: false, receiptNotes: 'Receiver pending for unloading.');

            $paymentPendingCart = $this->createSubmittedCartWithInvoice('2026-06-25', 140.0, 0.0);
            $this->attachReceiptState($paymentPendingCart, warehouseConfirmed: true, receiptNotes: 'Short load checked.');

            $completedCart = $this->createSubmittedCartWithInvoice('2026-06-25', 160.0, 160.0);
            $this->attachReceiptState($completedCart, warehouseConfirmed: true, receiptNotes: 'Stored on rack 2.');

            $response = $this->actingAs($this->purchaser)->get(route('purchaser.history'));

            $response->assertOk();
            $response->assertSee('Processing');
            $response->assertSee('Payment Pending');
            $response->assertSee($overdueCart->cart_number);
            $response->assertSee($processingCart->cart_number);
            $response->assertSee($paymentPendingCart->cart_number);
            $response->assertSee($completedCart->cart_number);
            $response->assertSee('Short load checked.');

            // Verify tab totals (remaining amounts):
            // Overdue draft cart (24.00 remaining)
            $response->assertSee('₹24.00');
            // Payment pending cart (140.00 remaining)
            $response->assertSee('₹140.00');
            // Completed cart (0.00 remaining) and Processing cart (0.00 remaining because pending receipt confirmation)
            $response->assertSee('₹0.00');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_history_continue_cart_link_targets_the_matching_vendor_tab_and_cart(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 10:00:00'));

        try {
            $category = Category::factory()->create();
            $product = Product::factory()->create(['category_id' => $category->id, 'unit' => 'kg']);
            $cart = PurchaserCart::create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $this->supplier->id,
                'business_date' => '2026-06-25',
                'cart_number' => 'VC-20260625-TEST',
                'status' => 'draft',
            ]);
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 45.0,
                'line_total' => 90.0,
                'is_extra_purchase' => false,
            ]);
            $cart->update(['goods_received_at' => now()]);

            $response = $this->actingAs($this->purchaser)->get(route('purchaser.history', ['date' => '2026-06-25']));

            $response->assertOk();
            $response->assertSee('date=2026-06-25&amp;tab=draft&amp;focus_cart='.$cart->id, false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_supplier_hub_can_search_pending_vendor_and_show_recent_history(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $overdueDate = Carbon::today()->subDay()->format('Y-m-d');

        $pendingCart = $this->createSubmittedCartWithInvoice($overdueDate, 125.0, 0.0);
        $this->attachReceiptState($pendingCart, warehouseConfirmed: true, receiptNotes: 'Pending payment with GRN note.');

        $paidSupplier = Supplier::factory()->create([
            'credit_approved' => true,
            'mobile_number' => '9990001111',
        ]);
        $paidCart = $this->createSubmittedCartWithInvoice($date, 90.0, 90.0, $paidSupplier);
        $this->attachReceiptState($paidCart, warehouseConfirmed: true, receiptNotes: 'Settled and stored.');

        $hubResponse = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers', [
            'date' => $date,
            'search' => 'pending',
        ]));

        $hubResponse->assertOk();
        $hubResponse->assertSee('Purchaser vendors');
        $hubResponse->assertSee($this->supplier->name);
        $hubResponse->assertDontSee($paidSupplier->name);
        $hubResponse->assertSee('Vendor Summary');
        $hubResponse->assertSee('Open Vendor History');
        $hubResponse->assertSee('₹125.00', false);

        $allVendorsResponse = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers', [
            'date' => $date,
        ]));
        $allVendorsResponse->assertOk();
        $allVendorsResponse->assertSee($this->supplier->name);
        $allVendorsResponse->assertSee($paidSupplier->name);

        $detailResponse = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers.show', [
            'supplier' => $this->supplier,
            'date' => $date,
        ]));

        $detailResponse->assertOk();
        $detailResponse->assertSee('Vendor history');
        $detailResponse->assertSee('Pending payment with GRN note.');
        $detailResponse->assertSee('Update Payment');
    }

    public function test_supplier_detail_shows_dated_history_and_completed_discrepancy_info(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 10:00:00'));

        try {
            $pendingCart = $this->createSubmittedCartWithInvoice('2026-06-25', 125.0, 0.0);
            $this->attachReceiptState($pendingCart, warehouseConfirmed: true, receiptNotes: 'Payment still open.');

            $completedCart = $this->createSubmittedCartWithInvoice('2026-06-24', 90.0, 90.0);
            $this->attachReceiptState($completedCart, warehouseConfirmed: true, receiptNotes: 'Short bag checked at receiving.');

            $completedCart = $completedCart->fresh(['purchaseOrder', 'goodsReceived', 'items.product']);
            $product = $completedCart->items->firstOrFail()->product;
            $purchaseOrderItem = $completedCart->purchaseOrder->items()->create([
                'product_id' => $product->id,
                'purchase_unit' => 'kg',
                'packet_qty' => 0,
                'weight_per_packet' => 0,
                'actual_weight' => null,
                'quantity' => 2,
                'unit_price' => 45,
                'price_basis' => 'per_kg',
            ]);
            $completedCart->goodsReceived->items()->create([
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'product_id' => $product->id,
                'received_qty' => 1.5,
                'variance' => -0.5,
            ]);

            $response = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers.show', [
                'supplier' => $this->supplier,
                'date' => '2026-06-25',
            ]));

            $response->assertOk();
            $response->assertSee('Vendor history');
            $response->assertSee('25 Jun 2026');
            $response->assertSee('24 Jun 2026');
            $response->assertSee('History Total');
            $response->assertSee('Items Bought');
            $response->assertSee('Discount Total');
            $response->assertSee('Pending Total');
            $response->assertSee('Update Payment');
            $response->assertSee('Delivery Discrepancy');
            $response->assertSee('Short 0.50 kg');
            $response->assertSee('Short bag checked at receiving.');
            $response->assertSee('₹215.00', false);
            $response->assertDontSee('Completed Bills');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_supplier_hub_summary_uses_only_the_carts_active_linked_invoice(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $supplier = Supplier::factory()->create([
            'name' => 'Linked Invoice Vendor',
            'mobile_number' => '9000000001',
        ]);

        $cart = $this->createSubmittedCartWithInvoice($date, 150.0, 150.0, $supplier);
        $this->attachReceiptState($cart, warehouseConfirmed: true, receiptNotes: 'Settled invoice should drive summary.');

        PurchaseInvoice::factory()->create([
            'supplier_id' => $supplier->id,
            'purchaser_cart_id' => $cart->id,
            'goods_received_id' => $cart->goods_received_id,
            'invoice_number' => 'PINV-STALE-'.random_int(1000, 9999),
            'amount' => 400.0,
            'discount_amount' => 0.0,
            'paid_amount' => 0.0,
            'status' => 'pending',
            'payment_method' => 'Cash',
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers', [
            'date' => $date,
        ]));

        $response->assertOk();
        $response->assertSee('Linked Invoice Vendor');
        $response->assertSee('₹150.00', false);
        $response->assertSee('0 pending');
        $response->assertDontSee('₹400.00', false);
    }

    public function test_purchaser_cash_page_shows_in_and_out_ledger(): void
    {
        $invoiceCart = $this->createSubmittedCartWithInvoice(Carbon::today()->format('Y-m-d'), 125.0, 125.0);
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 500.0,
            'description' => 'Cash advance for today buying',
            'created_by' => $this->purchaseManager->id,
            'business_date' => Carbon::today()->toDateString(),
        ]);
        $creditOut = PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 125.0,
            'description' => 'Invoice paid out',
            'purchase_invoice_id' => $invoiceCart->purchase_invoice_id,
            'created_by' => $this->purchaseManager->id,
            'business_date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.cash'));

        $response->assertOk();
        $response->assertSee('Cash in & out', false);
        $response->assertSee('Cash advance for today buying');
        $response->assertSee('Invoice paid out');
        $response->assertSee('₹500.00', false);
        $response->assertSee('₹125.00', false);
        $response->assertSee('₹375.00', false);
        $response->assertSee('Invoice: '.$creditOut->purchaseInvoice->invoice_number);
    }

    public function test_payment_update_from_supplier_detail_redirects_back_to_vendor_history(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $cart = $this->createSubmittedCartWithInvoice($date, 100.0, 20.0);
        $this->attachReceiptState($cart, warehouseConfirmed: true, receiptNotes: 'Follow up later.');

        $response = $this->actingAs($this->purchaser)->patch(route('purchaser.invoices.payment', $cart->purchaseInvoice), [
            'payment_method' => 'Cash',
            'discount_amount' => 5,
            'paid_amount' => 95,
            'payment_note' => 'Settled from vendor history popup',
            'payment_details' => 'Cash settled at market',
            'return_to' => 'supplier_detail',
            'supplier_id' => $this->supplier->id,
            'date' => $date,
        ]);

        $response->assertRedirect(route('purchaser.suppliers.show', [
            'supplier' => $this->supplier,
            'date' => $date,
        ]));
    }

    public function test_supplier_hub_groups_vendor_issues_with_resolve_actions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 10:00:00'));

        try {
            $activeDate = '2026-06-25';
            $overdueDate = '2026-06-24';

            $billPendingSupplier = Supplier::factory()->create(['name' => 'Bill Pending Vendor']);
            $billPendingCart = PurchaserCart::create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $billPendingSupplier->id,
                'business_date' => $activeDate,
                'cart_number' => 'VC-BILL-PENDING-001',
                'status' => 'draft',
            ]);
            $billPendingCart->items()->create([
                'product_id' => Product::factory()->create([
                    'category_id' => Category::factory()->create()->id,
                    'unit' => 'kg',
                ])->id,
                'quantity' => 3,
                'unit_price' => 22,
                'line_total' => 66,
                'is_extra_purchase' => false,
            ]);

            $overdueDraftSupplier = Supplier::factory()->create(['name' => 'Overdue Draft Vendor']);
            $overdueDraftCart = PurchaserCart::create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $overdueDraftSupplier->id,
                'business_date' => $overdueDate,
                'cart_number' => 'VC-OVERDUE-DRAFT-001',
                'status' => 'draft',
            ]);
            $overdueDraftCart->items()->create([
                'product_id' => Product::factory()->create([
                    'category_id' => Category::factory()->create()->id,
                    'unit' => 'kg',
                ])->id,
                'quantity' => 2,
                'unit_price' => 31,
                'line_total' => 62,
                'is_extra_purchase' => false,
            ]);

            $receiptPendingSupplier = Supplier::factory()->create(['name' => 'Warehouse Receipt Vendor']);
            $receiptPendingCart = $this->createSubmittedCartWithInvoice($overdueDate, 140.0, 0.0, $receiptPendingSupplier);
            $this->attachReceiptState($receiptPendingCart, warehouseConfirmed: false, receiptNotes: 'Receipt waiting in warehouse.');

            $overdueSupplier = Supplier::factory()->create(['name' => 'Overdue Vendor']);
            $overdueCart = $this->createSubmittedCartWithInvoice($overdueDate, 180.0, 20.0, $overdueSupplier);
            $this->attachReceiptState($overdueCart, warehouseConfirmed: true, receiptNotes: 'Receipt done but payment follow-up pending.');

            $response = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers', [
                'date' => $activeDate,
            ]));

            $response->assertOk();
            $response->assertSee('Pending vendor follow-up');
            $response->assertSee('Bill Pending');
            $response->assertSee('Overdue Bill Pending');
            $response->assertSee('Receipt Pending');
            $response->assertSee('Payment Pending');
            $response->assertSee('Bill Pending Vendor');
            $response->assertSee('Overdue Draft Vendor');
            $response->assertSee('Overdue Vendor');
            $response->assertSee('Open Bill Page');
            $response->assertSee('View Bill');
            $response->assertSee('Update Payment');
            $response->assertSee('Warehouse Receipt Vendor');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_bulk_buy_price_is_preloaded_into_bill_screen(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $category = Category::create(['name' => 'VEG', 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Preload Potato',
            'sku' => 'PRE-POT-1',
            'unit' => 'kg',
        ]);

        $this->createApprovedOrder($date, $this->shopA, $product, 8);

        $this->actingAs($this->purchaser)->post(route('purchaser.carts.bulk-store'), [
            'business_date' => $date,
            'product_ids' => [$product->id],
            'items' => [
                $product->id => ['quantity' => 5, 'unit_price' => 37.5],
            ],
        ])->assertRedirect();

        $cart = PurchaserCart::query()->firstOrFail();
        $cart->update(['supplier_id' => $this->supplier->id]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bill', [
            'cart' => $cart,
            'date' => $date,
        ]));

        $response->assertOk();
        $response->assertSee('37.5');
    }

    public function test_history_shows_correct_action_buttons_based_on_submitted_cart_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 10:00:00'));

        try {
            // 1. Draft cart for the day -> "Continue Cart"
            $draftCart = PurchaserCart::create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $this->supplier->id,
                'business_date' => '2026-06-25',
                'cart_number' => 'VC-20260625-DRAFT',
                'status' => 'draft',
            ]);
            $draftCart->items()->create([
                'product_id' => Product::factory()->create()->id,
                'quantity' => 2,
                'unit_price' => 20.0,
                'line_total' => 40.0,
                'is_extra_purchase' => false,
            ]);

            // 2. Overdue submitted cart (warehouse receipt pending) -> "View Bill" & "Confirm Receipt"
            $processingCart = $this->createSubmittedCartWithInvoice('2026-06-24', 100.0, 0.0);
            $this->attachReceiptState($processingCart, warehouseConfirmed: false, receiptNotes: 'Processing overdue.');

            // 3. Overdue submitted cart (warehouse receipt confirmed, payment pending) -> "Update Payment"
            $paymentPendingCart = $this->createSubmittedCartWithInvoice('2026-06-24', 120.0, 0.0);
            $this->attachReceiptState($paymentPendingCart, warehouseConfirmed: true, receiptNotes: 'Payment pending overdue.');

            // 4. Overdue submitted cart (completed) -> "View Bill" (no longer overdue, so it shouldn't show up in overdue, but if it is completed it shows View Bill)
            $completedCart = $this->createSubmittedCartWithInvoice('2026-06-24', 150.0, 150.0);
            $this->attachReceiptState($completedCart, warehouseConfirmed: true, receiptNotes: 'Completed overdue.');

            $response = $this->actingAs($this->purchaser)->get(route('purchaser.history', ['date' => '2026-06-25']));

            $response->assertOk();

            // Draft overdue shows Continue Cart
            $response->assertSee('Continue Cart');

            // Processing overdue shows View Bill and does not show Confirm Receipt
            $response->assertDontSee('Confirm Receipt');

            // Payment pending overdue shows Update Payment
            $response->assertSee('Update Payment');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_fully_paid_invoice_does_not_remain_overdue_when_cart_payment_flag_is_stale(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 10:00:00'));

        try {
            $cart = $this->createSubmittedCartWithInvoice('2026-06-24', 150.0, 0.0);
            $this->attachReceiptState($cart, warehouseConfirmed: true, receiptNotes: 'Fully received and settled.');

            $cart->purchaseInvoice()->firstOrFail()->update([
                'payment_status' => 'paid',
                'paid_amount' => 150.0,
                'discount_amount' => 0.0,
            ]);

            $cart->update([
                'payment_status' => 'unpaid',
                'paid_amount' => 0.0,
            ]);

            $historyResponse = $this->actingAs($this->purchaser)->get(route('purchaser.history', ['date' => '2026-06-25']));
            $historyResponse->assertOk();
            $historyResponse->assertSee($cart->cart_number);
            $historyResponse->assertSee('Completed');

            $hubResponse = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers', ['date' => '2026-06-25']));
            $hubResponse->assertOk();
            $hubResponse->assertSee('Pending (0)');
            $hubResponse->assertSee('0 pending');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_vendor_detail_shows_receipt_pending_for_paid_cart_when_warehouse_confirmation_is_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 10:00:00'));

        try {
            $cart = $this->createSubmittedCartWithInvoice('2026-06-24', 140.0, 140.0);
            $this->attachReceiptState($cart, warehouseConfirmed: false, receiptNotes: 'Payment settled. Waiting for warehouse confirmation.');

            $response = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers.show', [
                'supplier' => $this->supplier,
                'date' => '2026-06-25',
            ]));

            $response->assertOk();
            $response->assertSee('Open Issues');
            $response->assertSee('Receipt Pending');
            $response->assertSee('Payment settled. Waiting for warehouse confirmation.');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_cancelled_old_cart_is_not_shown_as_vendor_hub_issue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 10:00:00'));

        try {
            $cart = PurchaserCart::create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $this->supplier->id,
                'business_date' => '2026-06-24',
                'cart_number' => 'VC-CANCELLED-OLD-001',
                'status' => 'cancelled',
            ]);

            $cart->items()->create([
                'product_id' => Product::factory()->create()->id,
                'quantity' => 2,
                'unit_price' => 20,
                'line_total' => 40,
                'is_extra_purchase' => false,
            ]);

            $response = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers', ['date' => '2026-06-25']));

            $response->assertOk();
            $response->assertDontSee('Pending (1)');
            $response->assertSee('Pending (0)');
            $response->assertSee('0 issues');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_supplier_hub_issues_include_payment_modal_details_and_bill_page_link(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 10:00:00'));

        try {
            $activeDate = '2026-06-25';
            $overdueDate = '2026-06-24';

            $supplier = Supplier::factory()->create(['name' => 'Test Vendor', 'credit_approved' => true]);

            // Create previous price hint record
            $product = Product::factory()->create([
                'category_id' => Category::factory()->create()->id,
                'unit' => 'kg',
            ]);
            $draftCart = PurchaserCart::create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $supplier->id,
                'business_date' => $activeDate,
                'cart_number' => 'VC-DRAFT-999',
                'status' => 'draft',
            ]);
            $draftCart->items()->create([
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_price' => 0.0,
                'line_total' => 0.0,
                'is_extra_purchase' => false,
            ]);

            // Create payment pending overdue invoice
            $overdueCart = $this->createSubmittedCartWithInvoice($overdueDate, 200.0, 50.0, $supplier);
            $this->attachReceiptState($overdueCart, warehouseConfirmed: true, receiptNotes: 'Needs payment update');

            $response = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers', [
                'date' => $activeDate,
            ]));

            $response->assertOk();
            $response->assertSee('Open Bill Page');
            $response->assertSee('data-invoice-id="'.$overdueCart->purchaseInvoice->id.'"', false);
            $response->assertSee('data-amount="200"', false);
            $response->assertSee('data-discount-amount="0"', false);
            $response->assertSee('data-paid-amount="50"', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_paid_carts_are_visible_in_vendors_completed_tab_and_payment_redirects_to_vendors(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $cart = $this->createSubmittedCartWithInvoice($date, 150.0, 0.0);
        $this->attachReceiptState($cart, warehouseConfirmed: true, receiptNotes: 'Delivered');

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.vendors', ['date' => $date]));
        $response->assertSee($cart->cart_number);

        $this->actingAs($this->purchaser)->patch(route('purchaser.invoices.payment', $cart->purchaseInvoice), [
            'payment_method' => 'Cash',
            'paid_amount' => 150.0,
            'payment_note' => 'Fully paid',
            'payment_details' => 'Hand cash',
            'return_to' => 'vendors',
            'date' => $date,
        ])->assertRedirect(route('purchaser.vendors', ['date' => $date]));

        $cart->refresh();
        $this->assertSame('paid', $cart->payment_status);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.vendors', ['date' => $date]));
        $response->assertSee($cart->cart_number);
        $response->assertSee('Completed');
    }

    public function test_completed_vendors_card_shows_receipt_discrepancy_summary(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $cart = $this->createSubmittedCartWithInvoice($date, 150.0, 150.0);
        $this->attachReceiptState($cart, warehouseConfirmed: true, receiptNotes: 'Warehouse logged a short bag.');

        $cart = $cart->fresh(['goodsReceived.items', 'items.product']);
        $product = $cart->items->firstOrFail()->product;
        $cart->goodsReceived->items()->create([
            'product_id' => $product->id,
            'received_qty' => 1.5,
            'variance' => -0.5,
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.vendors', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('Delivery Discrepancy');
        $response->assertSee($product->name.': Short 0.50 '.$product->unit);
        $response->assertSee('Warehouse logged a short bag.');
    }
}
