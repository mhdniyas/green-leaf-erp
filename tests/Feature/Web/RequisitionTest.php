<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RequisitionTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $shopOwner;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);

        $this->shop = Shop::create([
            'code' => 'SHOP_TEST',
            'name' => 'Test Hypermarket',
        ]);

        $this->shopOwner = User::factory()->create([
            'shop_id' => $this->shop->id,
        ]);
        $this->shopOwner->assignRole('shop-owner');

        $this->product = Product::first();
    }

    public function test_shop_owner_can_submit_requisition_and_redirect_to_show_page(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(18, 0, 0)); // 6:00 PM, before 9:30 PM cutoff

        $response = $this->actingAs($this->shopOwner)
            ->postJson(route('requisitions.store'), [
                'items' => [
                    $this->product->sku => 15.50,
                ],
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'order_number', 'redirect_url']);

        $orderNumber = $response->json('order_number');
        $this->assertDatabaseHas('shop_orders', [
            'order_number' => $orderNumber,
            'shop_id' => $this->shop->id,
            'state' => 'submitted',
        ]);

        $this->assertDatabaseHas('shop_order_items', [
            'product_id' => $this->product->id,
            'requested_qty' => 15.50,
        ]);

        // Verify format: RQ-YYYYMMDD-XXXX
        $dateStr = Carbon::tomorrow()->format('Ymd');
        $this->assertMatchesRegularExpression("/^RQ-{$dateStr}-[A-Z0-9]{4}$/", $orderNumber);
    }

    public function test_shop_owner_can_view_own_requisition_details(): void
    {
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 12.00,
            'unit' => $this->product->unit,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.show', $order->order_number));

        $response->assertOk();
        $response->assertSee($order->order_number);
        $response->assertSee($this->product->name);
    }

    public function test_shop_owner_cannot_view_other_shops_requisition(): void
    {
        $otherShop = Shop::create([
            'code' => 'SHOP_OTHER',
            'name' => 'Other Shop',
        ]);
        $otherOwner = User::factory()->create([
            'shop_id' => $otherShop->id,
        ]);

        $order = ShopOrder::create([
            'shop_id' => $otherShop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $otherOwner->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.show', $order->order_number));

        $response->assertStatus(403);
    }

    public function test_shop_owner_can_edit_requisition_before_cutoff(): void
    {
        // Target delivery tomorrow. Cutoff is today 9:30 PM.
        // Set time to today 8:00 PM (before cutoff)
        Carbon::setTestNow(Carbon::today()->setTime(20, 0, 0));

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'unit' => $this->product->unit,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.edit', $order->order_number));
        $response->assertOk();

        // Submit edit
        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.update', $order->order_number), [
                'items' => [
                    $item->id => 20.00,
                ],
            ]);

        $response->assertRedirect(route('requisitions.show', $order->order_number));
        $this->assertDatabaseHas('shop_order_items', [
            'id' => $item->id,
            'requested_qty' => 20.00,
        ]);
    }

    public function test_shop_owner_cannot_edit_requisition_after_cutoff(): void
    {
        // Target delivery tomorrow. Cutoff is today 9:30 PM.
        // Set time to today 10:00 PM (after cutoff)
        Carbon::setTestNow(Carbon::today()->setTime(22, 0, 0));

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'unit' => $this->product->unit,
        ]);

        // Try getting edit view
        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.edit', $order->order_number));
        $response->assertRedirect(route('requisitions.show', $order->order_number));

        // Try updating quantities
        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.update', $order->order_number), [
                'items' => [
                    $item->id => 20.00,
                ],
            ]);

        $response->assertRedirect(route('requisitions.show', $order->order_number));
        // Verify requested_qty is unchanged
        $this->assertDatabaseHas('shop_order_items', [
            'id' => $item->id,
            'requested_qty' => 10.00,
        ]);
    }

    public function test_shop_owner_can_request_update_after_cutoff(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(22, 0, 0)); // after cutoff

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.update-request', $order->order_number), [
                'reason' => 'Need to add 5 kg of Tomatoes.',
            ]);

        $response->assertRedirect(route('requisitions.show', $order->order_number));
        $this->assertDatabaseHas('shop_orders', [
            'id' => $order->id,
            'state' => 'update_requested',
            'update_reason' => 'Need to add 5 kg of Tomatoes.',
        ]);
    }

    public function test_export_routes_return_correct_responses(): void
    {
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'unit' => $this->product->unit,
        ]);

        // CSV Export
        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.export.csv', $order->order_number));
        $response->assertOk();
        $this->assertEquals('text/csv; charset=utf-8', $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString($order->order_number, $content);
        $this->assertStringContainsString($this->product->sku, $content);

        // PDF / Print View
        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.export.pdf', $order->order_number));
        $response->assertOk();
        $response->assertSee('window.print()');
    }
}
