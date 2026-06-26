<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Repositories\Inventory\StockMovementRepository;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->user = User::factory()->create();
    }

    public function test_guest_user_cannot_access_delivery_checkin(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->get(route('requisitions.delivery.show', $order->order_number));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_shop_owner_cannot_access_other_shop_delivery_checkin(): void
    {
        $shop1 = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $shop2 = Shop::create(['code' => 'S2', 'name' => 'Shop 2']);

        $owner = User::factory()->create(['shop_id' => $shop1->id]);
        $owner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop2->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('requisitions.delivery.show', $order->order_number));

        $response->assertStatus(403);
    }

    public function test_authorized_shop_owner_can_access_own_shop_delivery_checkin(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $owner->id,
        ]);

        $product = Product::factory()->create();
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($owner)
            ->get(route('requisitions.delivery.show', $order->order_number));

        $response->assertRedirect(route('shop-owner.deliveries.show', $order->order_number));
    }

    public function test_cannot_access_delivery_checkin_if_allocation_not_completed(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => false,
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('requisitions.delivery.show', $order->order_number));

        $response->assertRedirect(route('shop-owner.deliveries.show', $order->order_number));
        $response->assertSessionHas('error', 'This order has not been dispatched/allocated from the warehouse yet.');
    }

    public function test_cannot_access_delivery_checkin_if_already_delivered(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'is_delivered' => true,
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('requisitions.delivery.show', $order->order_number));

        $response->assertRedirect(route('shop-owner.deliveries.show', $order->order_number));
        $response->assertSessionHas('error', 'This order has already been checked-in and marked as delivered.');
    }

    public function test_authorized_shop_owner_can_record_delivery_with_shortages_and_cash_discrepancy(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $owner->id,
        ]);

        $product = Product::factory()->create();

        // Create stock batch to resolve cost
        StockBatch::create([
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'reference' => 'B-01',
            'received_at' => today()->toDateString(),
            'total_kg' => 100,
            'cost_per_kg' => 10.00,
            'status' => BatchStatus::Sorted,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'batch_id' => StockBatch::where('reference', 'B-01')->value('id'),
            'created_by' => $owner->id,
            'grade' => ProductGrade::GradeA->value,
            'type' => StockMovementType::In->value,
            'quantity' => 100,
            'cost_per_unit' => 10.00,
            'notes' => 'Sorted stock for delivery test',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 15,
            'approved_qty' => 15,
            'locked_selling_price' => 10.00,
            'unit' => 'kg',
        ]);

        $payload = [
            'delivered_qty' => [
                $item->id => 12, // shortage of 3
            ],
            'cash_collected' => 100.00, // Expected Delivered Value: 12 * 10 = 120.00. Discrepancy: 120 - 100 = 20.00 shortage.
            'delivery_notes' => 'Some minor shortages, cash collected is Rs. 100.',
            'finance_note' => 'UPI settled partially, remaining balance to be cleared tomorrow.',
        ];

        $response = $this->actingAs($owner)
            ->post(route('requisitions.delivery.record', $order->order_number), $payload);

        $response->assertRedirect(route('shop-owner.deliveries.show', $order->order_number));
        $response->assertSessionHas('success');

        $order->refresh();
        $item->refresh();

        // Verify Order updates
        $this->assertFalse($order->is_delivered);
        $this->assertNull($order->delivered_at);
        $this->assertEquals($owner->id, $order->delivered_by);
        $this->assertEquals('pending_approval', $order->delivery_status);
        $this->assertEquals(100.00, (float) $order->cash_collected);
        $this->assertEquals(20.00, (float) $order->cash_discrepancy);
        $this->assertEquals(20.00, (float) $order->balance_amount);
        $this->assertEquals(30.00, (float) $order->total_shortage_value); // 3 kg shortage * Rs 10 = 30.00
        $this->assertEquals('Some minor shortages, cash collected is Rs. 100.', $order->delivery_notes);
        $this->assertEquals('UPI settled partially, remaining balance to be cleared tomorrow.', $order->finance_note);

        // Verify Item updates
        $this->assertEquals(12.00, (float) $item->delivered_qty);
        $this->assertEquals(3.00, (float) $item->shortage_qty);
        $this->assertEquals(10.00, (float) $item->unit_cost);
        $this->assertEquals(30.00, (float) $item->shortage_value);

        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::Out->value,
            'notes' => "Warehouse delivery out: {$order->order_number}",
        ]);

        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::Wastage->value,
            'notes' => "Delivery shortage discrepancy: {$order->order_number}",
        ]);
    }

    public function test_authorized_manager_can_adjust_delivery_qty_and_approve_discrepancy(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop');

        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $owner->id,
            'delivery_status' => 'pending_approval',
            'is_delivered' => false,
            'cash_collected' => 100.00,
        ]);

        $product = Product::factory()->create();

        // Create stock batch to resolve cost
        StockBatch::create([
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'reference' => 'B-01',
            'received_at' => today()->toDateString(),
            'total_kg' => 100,
            'cost_per_kg' => 10.00,
            'status' => BatchStatus::Sorted,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'batch_id' => StockBatch::where('reference', 'B-01')->value('id'),
            'created_by' => $owner->id,
            'grade' => ProductGrade::GradeA->value,
            'type' => StockMovementType::In->value,
            'quantity' => 100,
            'cost_per_unit' => 10.00,
            'notes' => 'Sorted stock for delivery test',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 15,
            'approved_qty' => 15,
            'delivered_qty' => 12,
            'shortage_qty' => 3,
            'unit_cost' => 10.00,
            'shortage_value' => 30.00,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.delivery.approve', $order->order_number), [
                'approved_delivered_qty' => [
                    $item->id => 14,
                ],
                'item_review_notes' => [
                    $item->id => 'Reported shortage was 3 kg, manager approved only 1 kg after recount.',
                ],
                'review_note' => 'Adjusted discrepancy after recount.',
            ]);

        $response->assertRedirect(route('requisitions.show', $order->order_number));
        $response->assertSessionHas('success');

        $order->refresh();
        $item->refresh();

        $this->assertTrue($order->is_delivered);
        $this->assertNotNull($order->delivered_at);
        $this->assertEquals('partially_delivered', $order->delivery_status);
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals(14.00, (float) $item->delivered_qty);
        $this->assertEquals(1.00, (float) $item->shortage_qty);
        $this->assertEquals(10.00, (float) $item->shortage_value);
        $this->assertStringContainsString('manager approved only 1 kg', (string) $item->notes);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::Out->value,
            'quantity' => 14.000,
            'notes' => "Warehouse delivery out (approved): {$order->order_number}",
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::Wastage->value,
            'quantity' => 1.000,
            'notes' => "Delivery shortage discrepancy (approved): {$order->order_number}",
        ]);
    }

    public function test_delivery_checkin_updates_sorted_stock_levels_for_inventory_screen(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $owner->id,
        ]);

        $product = Product::factory()->create();
        $batch = StockBatch::create([
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'reference' => 'B-STOCK-01',
            'received_at' => today()->toDateString(),
            'total_kg' => 10,
            'cost_per_kg' => 10.00,
            'status' => BatchStatus::Sorted,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'created_by' => $owner->id,
            'grade' => ProductGrade::GradeA->value,
            'type' => StockMovementType::In->value,
            'quantity' => 10,
            'cost_per_unit' => 10.00,
            'notes' => 'Initial sorted stock',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($owner)
            ->post(route('requisitions.delivery.record', $order->order_number), [
                'delivered_qty' => [
                    $item->id => 10,
                ],
                'cash_collected' => 100.00,
            ]);

        $response->assertRedirect(route('shop-owner.deliveries.show', $order->order_number));

        $stock = app(StockMovementRepository::class)->currentStockByProductAndGrade(today()->toDateString());

        $productStock = $stock->firstWhere('product_id', $product->id);

        $this->assertNull($productStock);
    }

    public function test_delivery_checkin_rejects_received_quantity_above_approved_quantity(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $owner->id,
        ]);

        $product = Product::factory()->create();

        StockBatch::create([
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'reference' => 'B-02',
            'received_at' => today()->toDateString(),
            'total_kg' => 100,
            'cost_per_kg' => 10.00,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 15,
            'approved_qty' => 15,
            'unit' => 'kg',
        ]);

        $response = $this->from(route('requisitions.delivery.show', $order->order_number))
            ->actingAs($owner)
            ->post(route('requisitions.delivery.record', $order->order_number), [
                'delivered_qty' => [
                    $item->id => 18,
                ],
                'cash_collected' => 180.00,
            ]);

        $response->assertRedirect(route('requisitions.delivery.show', $order->order_number));
        $response->assertSessionHasErrors("delivered_qty.{$item->id}");

        $order->refresh();

        $this->assertFalse($order->is_delivered);
        $this->assertNull($item->fresh()->delivered_qty);
    }
}
