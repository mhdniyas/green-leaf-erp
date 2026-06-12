<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseManagerGrnApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->manager = User::factory()->create();
        $this->manager->assignRole('purchase');
    }

    /** @test */
    public function test_purchase_manager_can_view_daily_approval_page(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('purchasing.grns.daily-approval', ['date' => today()->format('Y-m-d')]));

        $response->assertOk();
        $response->assertViewIs('purchase-manager.grns.daily-approval');
    }

    /** @test */
    public function test_daily_approval_page_is_forbidden_for_purchaser_role(): void
    {
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        $response = $this->actingAs($purchaser)
            ->get(route('purchasing.grns.daily-approval'));

        $response->assertForbidden();
    }

    /** @test */
    public function test_daily_approval_page_shows_pending_grns_grouped_by_product(): void
    {
        $date = today()->format('Y-m-d');
        $product = Product::factory()->create(['name' => 'Test Tomato']);

        $po = PurchaseOrder::factory()->create(['order_date' => $date]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_price' => 25.00,
        ]);
        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'status' => 'pending_approval',
            'received_at' => $date,
        ]);
        $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => 100,
            'variance' => 0,
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('purchasing.grns.daily-approval', ['date' => $date]));

        $response->assertOk();
        $response->assertViewHas('totalPending', 1);
    }

    /** @test */
    public function test_approving_all_grns_creates_stock_batches_with_warehouse_pending_flag(): void
    {
        $date = today()->format('Y-m-d');
        $product = Product::factory()->create();

        $po = PurchaseOrder::factory()->create(['order_date' => $date]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 50,
            'unit_price' => 30.00,
        ]);
        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'status' => 'pending_approval',
            'received_at' => $date,
        ]);
        $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => 50,
            'variance' => 0,
        ]);

        $this->actingAs($this->manager)
            ->post(route('purchasing.grns.daily-approval.approve'), ['date' => $date])
            ->assertRedirect(route('purchasing.grns.daily-approval', ['date' => $date]))
            ->assertSessionHas('success');

        // GRN should be approved
        $this->assertDatabaseHas('goods_received', [
            'id' => $grn->id,
            'status' => 'approved',
        ]);

        // Stock batch should exist and have warehouse_receive_pending = true
        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $product->id,
            'total_kg' => 50,
            'warehouse_receive_pending' => true,
        ]);
    }

    /** @test */
    public function test_approve_returns_error_when_no_pending_grns_exist(): void
    {
        $this->actingAs($this->manager)
            ->post(route('purchasing.grns.daily-approval.approve'), ['date' => today()->format('Y-m-d')])
            ->assertSessionHasErrors();
    }

    /** @test */
    public function test_admin_can_access_daily_approval(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('purchasing.grns.daily-approval'))
            ->assertOk();
    }

    /** @test */
    public function test_daily_approval_page_splits_over_purchased_items(): void
    {
        $date = today()->format('Y-m-d');
        $product = Product::factory()->create(['name' => 'Tomato Local']);
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $supplier = Supplier::create([
            'name' => 'Market Supplier A',
            'type' => 'Trader',
            'category' => 'market',
            'contact' => '0987654321',
            'payment_terms' => 'COD',
            'quality_score' => 95.0,
        ]);

        // Create an approved shop order demanding 10kg Tomato
        $shop = Shop::create([
            'code' => 'S1',
            'name' => 'Shop 1',
            'status' => 'active',
        ]);
        $shopOrder = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'approved',
            'submitted_at' => now(),
            'created_by' => $purchaser->id,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 10.00,
            'unit' => 'kg',
            'fulfillment_type' => 'warehouse',
        ]);

        // Purchaser logs a purchase of 20kg Tomato (10kg surplus)
        $response = $this->actingAs($purchaser)
            ->post(route('purchaser.purchase.draft.record'), [
                'product_id' => $product->id,
                'quantity' => 20.00,
                'unit_price' => 30.00,
                'supplier_id' => $supplier->id,
                'date' => $date,
            ]);
        $response->assertRedirect();

        // Submit the draft purchases
        $this->actingAs($purchaser)
            ->post(route('purchaser.purchase.submit'), [
                'date' => $date,
            ]);

        // Verify two GRNs are created in the database (one regular, one extra)
        $this->assertDatabaseHas('goods_received', ['is_extra' => false, 'status' => 'pending_approval']);
        $this->assertDatabaseHas('goods_received', ['is_extra' => true, 'status' => 'pending_approval']);

        // Now Purchase Manager views the Daily Approval page
        $response = $this->actingAs($this->manager)
            ->get(route('purchasing.grns.daily-approval', ['date' => $date]));

        $response->assertOk();
        $dailyItems = $response->viewData('dailyItems');
        $extraItems = $response->viewData('extraItems');

        $this->assertCount(1, $dailyItems);
        $this->assertCount(1, $extraItems);

        $this->assertEquals(10.0, $dailyItems[0]['total_qty']);
        $this->assertEquals(10.0, $extraItems[0]['total_qty']);
        $this->assertTrue($extraItems[0]['is_extra']);
        $this->assertFalse($dailyItems[0]['is_extra']);
    }

    /** @test */
    public function test_daily_approval_page_shows_ad_hoc_purchased_items_in_extras(): void
    {
        $date = today()->format('Y-m-d');
        $product = Product::factory()->create(['name' => 'Potato Adhoc']);
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $supplier = Supplier::create([
            'name' => 'Market Supplier A',
            'type' => 'Trader',
            'category' => 'market',
            'contact' => '0987654321',
            'payment_terms' => 'COD',
            'quality_score' => 95.0,
        ]);

        // Purchaser logs a purchase of 5kg Potato (not in order demands)
        $response = $this->actingAs($purchaser)
            ->post(route('purchaser.purchase.draft.record'), [
                'product_id' => $product->id,
                'quantity' => 5.00,
                'unit_price' => 15.00,
                'supplier_id' => $supplier->id,
                'date' => $date,
            ]);
        $response->assertRedirect();

        // Submit the draft purchases
        $this->actingAs($purchaser)
            ->post(route('purchaser.purchase.submit'), [
                'date' => $date,
            ]);

        // Verify that the GRN is marked as is_extra = true
        $this->assertDatabaseHas('goods_received', ['is_extra' => true, 'status' => 'pending_approval']);
        $this->assertDatabaseMissing('goods_received', ['is_extra' => false, 'status' => 'pending_approval']);

        // Now Purchase Manager views the Daily Approval page
        $response = $this->actingAs($this->manager)
            ->get(route('purchasing.grns.daily-approval', ['date' => $date]));

        $response->assertOk();
        $dailyItems = $response->viewData('dailyItems');
        $extraItems = $response->viewData('extraItems');

        $this->assertCount(0, $dailyItems);
        $this->assertCount(1, $extraItems);

        $this->assertEquals(5.0, $extraItems[0]['total_qty']);
        $this->assertTrue($extraItems[0]['is_extra']);
    }
}
