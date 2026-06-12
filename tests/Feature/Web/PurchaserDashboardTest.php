<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
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

    private User $unauthorizedUser;

    private User $purchaseManager;

    private Product $product;

    private Shop $shop;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->unauthorizedUser = User::factory()->create();
        $this->unauthorizedUser->assignRole('shop');

        $this->purchaseManager = User::factory()->create();
        $this->purchaseManager->assignRole('purchase');

        $this->shop = Shop::create([
            'code' => 'TEST_SHOP',
            'name' => 'Test Shop 1',
            'status' => 'active',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Tomato Local',
            'sku' => 'TOM-LOC-123',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Market Supplier A',
            'type' => 'Trader',
            'category' => 'market',
            'contact' => '0987654321',
            'payment_terms' => 'COD',
            'quality_score' => 95.0,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('purchaser.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_access_purchaser_dashboard(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('purchaser.dashboard'));

        $response->assertStatus(403);
    }

    public function test_purchase_manager_cannot_access_purchaser_dashboard(): void
    {
        $response = $this->actingAs($this->purchaseManager)
            ->get(route('purchaser.dashboard'));

        $response->assertForbidden();
    }

    public function test_purchaser_can_access_dashboard_and_see_requirements(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        // Create an approved shop order with items for today
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'state' => 'approved',
            'submitted_at' => now(),
            'created_by' => $this->unauthorizedUser->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 50.00,
            'approved_qty' => 50.00,
            'unit' => 'kg',
            'fulfillment_type' => 'warehouse',
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.dashboard', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('Market Purchase Hub');
        $response->assertSee('Tomato Local');
        $response->assertSee('50.00 kg Needed');
        $response->assertSee('Pending (1)');
        $response->assertSee('Partial (0)');
        $response->assertSee('Full (0)');
        $response->assertSee('app-dialog-root');
        $response->assertSee('window.showAppConfirm');
    }

    public function test_fully_bought_products_are_sorted_below_pending_products(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        $pendingProduct = Product::factory()->create([
            'name' => 'Beans',
            'sku' => 'BEANS-101',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $fullProduct = Product::factory()->create([
            'name' => 'Corriander',
            'sku' => 'CORRIANDER-101',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'state' => 'approved',
            'submitted_at' => now(),
            'created_by' => $this->unauthorizedUser->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $pendingProduct->id,
            'requested_qty' => 15.00,
            'approved_qty' => 15.00,
            'unit' => 'kg',
            'fulfillment_type' => 'warehouse',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $fullProduct->id,
            'requested_qty' => 20.00,
            'approved_qty' => 20.00,
            'unit' => 'kg',
            'fulfillment_type' => 'warehouse',
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-DRAFT-SORT',
            'status' => POStatus::Draft,
            'order_date' => $date,
            'created_by' => $this->purchaser->id,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $fullProduct->id,
            'quantity' => 20.00,
            'unit_price' => 4.00,
            'purchase_unit' => 'kg',
            'price_basis' => 'per_kg',
        ]);

        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-DRAFT-SORT',
            'received_at' => $date,
            'received_by' => $this->purchaser->id,
            'status' => 'draft',
        ]);

        $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $fullProduct->id,
            'received_qty' => 20.00,
            'variance' => 0.00,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.dashboard', ['date' => $date]));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Beans',
            'Corriander',
        ]);
        $response->assertSee('Pending (1)');
        $response->assertSee('Full (1)');
    }

    public function test_single_supplier_is_selected_by_default_on_purchaser_dashboard(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.dashboard', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('Defaulted to the only available supplier.');
        $response->assertSee('type="hidden" name="supplier_id" value="'.$this->supplier->id.'"', false);
        $response->assertSee('<option value="'.$this->supplier->id.'" selected>', false);
    }

    public function test_submitted_purchase_history_shows_product_summary_with_average_price(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'state' => 'approved',
            'submitted_at' => now(),
            'created_by' => $this->unauthorizedUser->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 50.00,
            'approved_qty' => 50.00,
            'unit' => 'kg',
            'fulfillment_type' => 'warehouse',
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-SUMMARY-1',
            'status' => POStatus::Received,
            'order_date' => $date,
            'created_by' => $this->purchaser->id,
        ]);

        $firstPoItem = $po->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10.00,
            'unit_price' => 100.00,
            'purchase_unit' => 'kg',
            'price_basis' => 'per_kg',
        ]);

        $secondPoItem = $po->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 20.00,
            'unit_price' => 200.00,
            'purchase_unit' => 'kg',
            'price_basis' => 'per_kg',
        ]);

        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-SUMMARY-1',
            'received_at' => $date,
            'received_by' => $this->purchaser->id,
            'status' => 'pending_approval',
        ]);

        $grn->items()->create([
            'purchase_order_item_id' => $firstPoItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 10.00,
            'variance' => 0.00,
        ]);

        $grn->items()->create([
            'purchase_order_item_id' => $secondPoItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 20.00,
            'variance' => 0.00,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.dashboard', ['date' => $date, 'tab' => 'history']));

        $response->assertOk();
        $response->assertSeeText('Submitted Purchase Summary');
        $response->assertSeeText('Tomato Local');
        $response->assertSeeText('30.00 kg');
        $response->assertSeeText('INR 166.67/kg');
        $response->assertDontSee('GRN-SUMMARY-1');
    }

    public function test_purchaser_is_redirected_from_main_dashboard(): void
    {
        $response = $this->actingAs($this->purchaser)
            ->get(route('dashboard'));

        $response->assertRedirect(route('purchaser.dashboard'));
    }

    public function test_purchaser_can_record_draft_purchase_successfully(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        // Purchaser logs a draft purchase of 25kg at 2.50 INR
        $response = $this->actingAs($this->purchaser)
            ->from(route('purchaser.dashboard', ['date' => $date]))
            ->post(route('purchaser.purchase.draft.record'), [
                'product_id' => $this->product->id,
                'quantity' => 25.00,
                'unit_price' => 2.50,
                'supplier_id' => $this->supplier->id,
                'date' => $date,
            ]);

        $response->assertRedirect(route('purchaser.dashboard', ['date' => $date]));
        $response->assertSessionHas('success');

        // Check Draft PO exists
        $po = PurchaseOrder::where('supplier_id', $this->supplier->id)
            ->whereDate('order_date', $date)
            ->first();

        $this->assertNotNull($po);
        $this->assertEquals(POStatus::Draft, $po->status);
        $this->assertCount(1, $po->items);
        $this->assertEquals(25.00, $po->items->first()->quantity);

        // Check Draft GRN exists
        $grn = GoodsReceived::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($grn);
        $this->assertEquals('draft', $grn->status);
        $this->assertCount(1, $grn->items);
        $this->assertEquals(25.00, $grn->items->first()->received_qty);
    }

    public function test_purchaser_can_delete_draft_purchase(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        // Log a draft first
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-DRAFT-111',
            'status' => POStatus::Draft,
            'order_date' => $date,
            'created_by' => $this->purchaser->id,
        ]);
        $poItem = $po->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10.00,
            'unit_price' => 2.00,
            'purchase_unit' => 'kg',
            'price_basis' => 'per_kg',
        ]);
        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-DRAFT-111',
            'received_at' => $date,
            'received_by' => $this->purchaser->id,
            'status' => 'draft',
        ]);
        $grnItem = $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 10.00,
            'variance' => 0.00,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->from(route('purchaser.dashboard', ['date' => $date]))
            ->post(route('purchaser.purchase.draft.delete', $grnItem->id));

        $response->assertRedirect(route('purchaser.dashboard', ['date' => $date]));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('goods_received_items', ['id' => $grnItem->id]);
        $this->assertSoftDeleted('goods_received', ['id' => $grn->id]);
        $this->assertSoftDeleted('purchase_orders', ['id' => $po->id]);
    }

    public function test_purchaser_can_submit_purchases_to_manager(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        // Log a draft first
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-DRAFT-222',
            'status' => POStatus::Draft,
            'order_date' => $date,
            'created_by' => $this->purchaser->id,
        ]);
        $poItem = $po->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 15.00,
            'unit_price' => 2.00,
            'purchase_unit' => 'kg',
            'price_basis' => 'per_kg',
        ]);
        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-DRAFT-222',
            'received_at' => $date,
            'received_by' => $this->purchaser->id,
            'status' => 'draft',
        ]);
        $grnItem = $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 15.00,
            'variance' => 0.00,
        ]);

        // Submit purchases
        $response = $this->actingAs($this->purchaser)
            ->from(route('purchaser.dashboard', ['date' => $date]))
            ->post(route('purchaser.purchase.submit'), [
                'date' => $date,
            ]);

        $response->assertRedirect(route('purchaser.dashboard', ['date' => $date]));
        $response->assertSessionHas('success');

        // Check PO status is Received and GRN status is pending_approval
        $po->refresh();
        $this->assertEquals(POStatus::Received, $po->status);

        $grn->refresh();
        $this->assertEquals('pending_approval', $grn->status);
    }

    /** @test */
    public function test_purchaser_can_record_adhoc_draft_purchase_successfully(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        // Potato is not in order demands (daily requirements), but we log a purchase
        $potato = Product::factory()->create([
            'name' => 'Potato White',
            'sku' => 'POT-WHT-999',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->from(route('purchaser.dashboard', ['date' => $date]))
            ->post(route('purchaser.purchase.draft.record'), [
                'product_id' => $potato->id,
                'quantity' => 15.00,
                'unit_price' => 3.20,
                'supplier_id' => $this->supplier->id,
                'date' => $date,
            ]);

        $response->assertRedirect(route('purchaser.dashboard', ['date' => $date]));
        $response->assertSessionHas('success');

        // Check draft PO contains the ad-hoc item
        $po = PurchaseOrder::where('supplier_id', $this->supplier->id)
            ->whereDate('order_date', $date)
            ->first();

        $this->assertNotNull($po);
        $this->assertEquals(POStatus::Draft, $po->status);
        $this->assertCount(1, $po->items);
        $this->assertEquals(15.00, $po->items->first()->quantity);
        $this->assertEquals($potato->id, $po->items->first()->product_id);
    }

    public function test_purchaser_future_date_dashboard_redirects_to_today(): void
    {
        $futureDate = Carbon::tomorrow()->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($this->purchaser)
            ->from(route('purchaser.dashboard'))
            ->get(route('purchaser.dashboard', ['date' => $futureDate]));

        $response->assertRedirect(route('purchaser.dashboard', ['date' => $today]));
        $response->assertSessionHasErrors();
    }

    public function test_purchaser_cannot_record_draft_purchase_for_future_date(): void
    {
        $futureDate = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->actingAs($this->purchaser)
            ->from(route('purchaser.dashboard'))
            ->post(route('purchaser.purchase.draft.record'), [
                'product_id' => $this->product->id,
                'quantity' => 25.00,
                'unit_price' => 2.50,
                'supplier_id' => $this->supplier->id,
                'date' => $futureDate,
            ]);

        $response->assertRedirect(route('purchaser.dashboard'));
        $response->assertSessionHasErrors(['date']);

        $this->assertSame(0, GoodsReceived::query()
            ->where('received_by', $this->purchaser->id)
            ->whereDate('received_at', $futureDate)
            ->count());
    }

    public function test_purchaser_cannot_submit_purchases_for_future_date(): void
    {
        $futureDate = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->actingAs($this->purchaser)
            ->from(route('purchaser.dashboard'))
            ->post(route('purchaser.purchase.submit'), [
                'date' => $futureDate,
            ]);

        $response->assertRedirect(route('purchaser.dashboard'));
        $response->assertSessionHasErrors(['date']);
    }
}
