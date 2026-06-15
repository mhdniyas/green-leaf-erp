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
        ])->assertRedirect(route('purchaser.history', ['date' => $date]));

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
            'paid_amount' => 0,
            'items' => [
                $cart->items()->firstOrFail()->id => ['unit_price' => 18],
            ],
        ])->assertRedirect(route('purchaser.history', ['date' => $date]));

        $cart->refresh();

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.history', ['date' => $date]));
        $response->assertOk();
        $response->assertSee('Purchase report');
        $response->assertSee('Draft');
        $response->assertSee('Processing');
        $response->assertSee('Completed');
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

    public function test_updating_cart_item_quantity_syncs_daily_order_quantity(): void
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
        $this->assertSame('7.00', (string) ShopOrderItem::query()->firstOrFail()->approved_qty);
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
        $response->assertSee('Daily Carts');
        $response->assertSee($activeCart->cart_number);
        $response->assertSee($deliveredCart->cart_number);
        $response->assertSee('Active');
        $response->assertSee('Received');
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

    private function createSubmittedCartWithInvoice(string $date, float $amount, float $paidAmount): PurchaserCart
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit' => 'kg',
        ]);

        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'cart_number' => 'VC-'.str_replace('-', '', $date).'-FIN'.random_int(100, 999),
            'status' => 'submitted',
            'bill_number' => 'BILL-'.random_int(100, 999),
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
            'supplier_id' => $this->supplier->id,
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
            'name' => 'Bottle Gourd',
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
        $this->assertStringNotContainsString('@ 25.50', rawurldecode($redirectUrl));

        // Scenario 2: show_price is true
        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.send', $cart), [
            'supplier_id' => $this->supplier->id,
            'show_price' => '1',
        ]);

        $response->assertStatus(302);
        $redirectUrl = (string) $response->headers->get('Location');
        $this->assertStringContainsString('api.whatsapp.com/send', $redirectUrl);
        // Message text should contain the price
        $this->assertStringContainsString('@ 25.50', rawurldecode($redirectUrl));
    }

    public function test_daily_summary_includes_past_pending_demands_and_formats_whatsapp_text(): void
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
            'name' => 'Potato Past Pending',
            'sku' => 'POT-PST',
            'unit' => 'kg',
        ]);

        $productC = Product::factory()->create([
            'category_id' => $vegCategory->id,
            'name' => 'Onion Past Completed',
            'sku' => 'ON-COMP',
            'unit' => 'kg',
        ]);

        // 1. Order today (Tomato)
        $this->createApprovedOrder($dateToday, $this->shopA, $productA, 10);

        // 2. Order yesterday pending (Potato)
        $this->createApprovedOrder($dateYesterday, $this->shopA, $productB, 5);

        // 3. Order yesterday completed (Onion)
        $this->createApprovedOrder($dateYesterday, $this->shopA, $productC, 8);
        // Also mark Onion as bought yesterday
        $cartYesterday = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $dateYesterday,
            'cart_number' => 'VC-'.str_replace('-', '', $dateYesterday).'-1',
            'status' => 'submitted',
        ]);
        $cartYesterday->items()->create([
            'product_id' => $productC->id,
            'quantity' => 8,
            'unit_price' => 20,
        ]);

        // Fetch daily page
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => $dateToday, 'chip' => 'All']));
        $response->assertOk();

        // Check dailySummary view data directly
        $dailySummaryData = $response->viewData('dailySummary');
        $this->assertTrue($dailySummaryData->contains('product_name', 'Tomato Today'));
        $this->assertTrue($dailySummaryData->contains('product_name', 'Potato Past Pending'));
        $this->assertFalse($dailySummaryData->contains('product_name', 'Onion Past Completed'));

        // Tomato Today should be visible in HTML
        $response->assertSee('Tomato Today');
        // Potato Past Pending should be visible in HTML
        $response->assertSee('Potato Past Pending');
        // It should show its pending date
        $response->assertSee('Pending ('.Carbon::parse($dateYesterday)->format('d M Y').')');

        // Check the daily share URL text
        $shareUrl = $response->viewData('dailySummaryShareUrl');
        $this->assertStringContainsString('api.whatsapp.com/send', $shareUrl);
        $decodedText = rawurldecode($shareUrl);

        // Tomato should be present without pending prefix
        $this->assertStringContainsString('*Tomato Today*', $decodedText);
        $this->assertStringNotContainsString('*Tomato Today* (Pending', $decodedText);

        // Potato should be present with pending prefix
        $this->assertStringContainsString('*Potato Past Pending* (Pending '.Carbon::parse($dateYesterday)->format('d M Y').')', $decodedText);

        // Onion should not be present
        $this->assertStringNotContainsString('Onion Past Completed', $decodedText);
    }
}
