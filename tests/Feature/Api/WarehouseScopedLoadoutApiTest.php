<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WarehouseScopedLoadoutApiTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $fruit;

    private Warehouse $vegetable;

    private User $fruitUser;

    private User $multiWarehouseUser;

    private ShopOrder $mixedOrder;

    private ShopOrder $vegetableOnlyOrder;

    private ShopOrderItem $fruitItem;

    private ShopOrderItem $vegetableItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fruit = $this->warehouse('Fruit Warehouse', 'FRUIT');
        $this->vegetable = $this->warehouse('Vegetable Warehouse', 'VEG');

        $this->fruitUser = User::factory()->create();
        $this->fruitUser->warehouses()->attach($this->fruit, ['is_default' => true]);

        $this->multiWarehouseUser = User::factory()->create();
        $this->multiWarehouseUser->warehouses()->attach([
            $this->fruit->id => ['is_default' => true],
            $this->vegetable->id => ['is_default' => false],
        ]);

        $shop = Shop::factory()->create();
        $category = Category::factory()->create(['is_active' => true]);
        $fruitProduct = Product::factory()->create([
            'category_id' => $category->id,
            'default_warehouse_id' => $this->fruit->id,
            'name' => 'Phase Two Mango',
            'sku' => 'P2-FRUIT',
            'unit' => 'KG',
            'is_active' => true,
        ]);
        $vegetableProduct = Product::factory()->create([
            'category_id' => $category->id,
            'default_warehouse_id' => $this->vegetable->id,
            'name' => 'Phase Two Carrot',
            'sku' => 'P2-VEG',
            'unit' => 'BOX',
            'is_active' => true,
        ]);

        $this->mixedOrder = $this->order($shop, 'P2-MIXED');
        $this->fruitItem = $this->item($this->mixedOrder, $fruitProduct, 12.50);
        $this->vegetableItem = $this->item($this->mixedOrder, $vegetableProduct, 8.00);

        $this->vegetableOnlyOrder = $this->order($shop, 'P2-VEG-ONLY');
        $this->item($this->vegetableOnlyOrder, $vegetableProduct, 6.00);
    }

    public function test_assigned_and_multi_warehouse_users_can_list_only_accessible_warehouse_orders(): void
    {
        $this->actingAs($this->fruitUser)
            ->getJson($this->ordersUrl($this->fruit))
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.order_number', $this->mixedOrder->order_number)
            ->assertJsonPath('orders.0.warehouse_item_count', 1);

        $this->actingAs($this->fruitUser)
            ->getJson($this->ordersUrl($this->vegetable))
            ->assertForbidden();

        $this->actingAs($this->multiWarehouseUser)
            ->getJson($this->ordersUrl($this->fruit))
            ->assertOk();
        $this->actingAs($this->multiWarehouseUser)
            ->getJson($this->ordersUrl($this->vegetable))
            ->assertOk()
            ->assertJsonCount(2, 'orders');
    }

    public function test_all_warehouse_permission_can_access_any_warehouse(): void
    {
        $permission = Permission::create(['name' => 'warehouse.loadout.all', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $this->actingAs($user)
            ->getJson($this->ordersUrl($this->vegetable))
            ->assertOk()
            ->assertJsonPath('warehouse.id', $this->vegetable->id);
    }

    public function test_detail_returns_only_selected_warehouse_items_and_default_hydration(): void
    {
        $response = $this->actingAs($this->multiWarehouseUser)
            ->getJson($this->detailUrl($this->fruit, $this->mixedOrder))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.requisition_item_id', $this->fruitItem->id)
            ->assertJsonPath('items.0.product_sku', 'P2-FRUIT')
            ->assertJsonPath('items.0.warehouse_id', $this->fruit->id)
            ->assertJsonPath('items.0.default_loaded_qty', 12.5)
            ->assertJsonPath('has_loadout_started', false);

        $this->assertStringNotContainsString('P2-VEG', $response->getContent());

        $this->actingAs($this->multiWarehouseUser)
            ->getJson($this->detailUrl($this->vegetable, $this->mixedOrder))
            ->assertOk()
            ->assertJsonPath('items.0.unit', 'BOX');
    }

    public function test_detail_returns_not_found_when_order_has_no_selected_warehouse_items(): void
    {
        $this->actingAs($this->multiWarehouseUser)
            ->getJson($this->detailUrl($this->fruit, $this->vegetableOnlyOrder))
            ->assertNotFound();
    }

    public function test_valid_fractional_save_updates_only_submitted_fruit_item(): void
    {
        $this->patchItems($this->fruitUser, $this->fruit, $this->mixedOrder, [[
            'requisition_item_id' => $this->fruitItem->id,
            'loaded_qty' => 3.75,
            'is_not_available' => false,
            'note' => null,
        ]])->assertOk()
            ->assertJsonPath('items.0.loaded_qty', 3.75)
            ->assertJsonPath('items.0.sorting_status', 'loaded')
            ->assertJsonPath('has_loadout_started', true);

        $this->assertDatabaseHas('shop_order_items', [
            'id' => $this->fruitItem->id,
            'loaded_qty' => 3.75,
            'sorting_status' => 'loaded',
        ]);
        $this->assertDatabaseHas('shop_order_items', [
            'id' => $this->vegetableItem->id,
            'loaded_qty' => null,
            'sorting_status' => 'allocated',
        ]);
    }

    public function test_vegetable_save_does_not_change_existing_fruit_quantity(): void
    {
        $this->fruitItem->update(['loaded_qty' => 4.25, 'sorting_status' => 'loaded']);

        $this->patchItems($this->multiWarehouseUser, $this->vegetable, $this->mixedOrder, [[
            'requisition_item_id' => $this->vegetableItem->id,
            'loaded_qty' => 2.50,
            'is_not_available' => false,
        ]])->assertOk();

        $this->assertEquals(4.25, (float) $this->fruitItem->fresh()->loaded_qty);
        $this->assertEquals(2.50, (float) $this->vegetableItem->fresh()->loaded_qty);
    }

    public function test_cross_warehouse_and_cross_requisition_items_are_rejected(): void
    {
        $this->patchItems($this->fruitUser, $this->fruit, $this->mixedOrder, [[
            'requisition_item_id' => $this->vegetableItem->id,
            'loaded_qty' => 2,
            'is_not_available' => false,
        ]])->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $otherOrderItem = $this->vegetableOnlyOrder->items()->firstOrFail();
        $this->patchItems($this->multiWarehouseUser, $this->vegetable, $this->mixedOrder, [[
            'requisition_item_id' => $otherOrderItem->id,
            'loaded_qty' => 2,
            'is_not_available' => false,
        ]])->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_duplicate_item_ids_are_rejected(): void
    {
        $payload = [
            'requisition_item_id' => $this->fruitItem->id,
            'loaded_qty' => 1.25,
            'is_not_available' => false,
        ];

        $this->patchItems($this->fruitUser, $this->fruit, $this->mixedOrder, [$payload, $payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.requisition_item_id');
    }

    public function test_missing_items_are_not_cleared(): void
    {
        $extraProduct = Product::factory()->create([
            'default_warehouse_id' => $this->fruit->id,
            'is_active' => true,
        ]);
        $extra = $this->item($this->mixedOrder, $extraProduct, 5.00);
        $extra->update(['loaded_qty' => 2.25, 'sorting_status' => 'loaded']);

        $this->patchItems($this->fruitUser, $this->fruit, $this->mixedOrder, [[
            'requisition_item_id' => $this->fruitItem->id,
            'loaded_qty' => 1.50,
            'is_not_available' => false,
        ]])->assertOk();

        $this->assertEquals(2.25, (float) $extra->fresh()->loaded_qty);
        $this->assertSame('loaded', $extra->fresh()->sorting_status);
    }

    public function test_not_available_and_notes_remain_warehouse_scoped(): void
    {
        $this->vegetableItem->update([
            'loadout_discrepancy_note' => 'Vegetable note',
            'sorting_status' => 'loaded',
            'loaded_qty' => 2,
        ]);

        $this->patchItems($this->fruitUser, $this->fruit, $this->mixedOrder, [[
            'requisition_item_id' => $this->fruitItem->id,
            'is_not_available' => true,
            'note' => 'Fruit unavailable',
        ]])->assertOk()
            ->assertJsonPath('items.0.is_not_available', true)
            ->assertJsonPath('items.0.note', 'Fruit unavailable');

        $this->assertSame('not_available', $this->fruitItem->fresh()->sorting_status);
        $this->assertSame('Fruit unavailable', $this->fruitItem->fresh()->loadout_discrepancy_note);
        $this->assertSame('Vegetable note', $this->vegetableItem->fresh()->loadout_discrepancy_note);
        $this->assertEquals(2.0, (float) $this->vegetableItem->fresh()->loaded_qty);
    }

    public function test_sequential_independent_warehouse_saves_preserve_both_results(): void
    {
        $this->patchItems($this->multiWarehouseUser, $this->fruit, $this->mixedOrder, [[
            'requisition_item_id' => $this->fruitItem->id,
            'loaded_qty' => 7.25,
            'is_not_available' => false,
        ]])->assertOk();

        $this->patchItems($this->multiWarehouseUser, $this->vegetable, $this->mixedOrder, [[
            'requisition_item_id' => $this->vegetableItem->id,
            'loaded_qty' => 5.50,
            'is_not_available' => false,
        ]])->assertOk();

        $this->assertEquals(7.25, (float) $this->fruitItem->fresh()->loaded_qty);
        $this->assertEquals(5.50, (float) $this->vegetableItem->fresh()->loaded_qty);
    }

    public function test_item_save_does_not_transition_order_or_create_invoice(): void
    {
        $originalStatus = $this->mixedOrder->delivery_status;
        $invoiceCount = ShopInvoice::query()->count();

        $this->patchItems($this->fruitUser, $this->fruit, $this->mixedOrder, [[
            'requisition_item_id' => $this->fruitItem->id,
            'loaded_qty' => 12.50,
            'is_not_available' => false,
        ]])->assertOk();

        $this->assertSame($originalStatus, $this->mixedOrder->fresh()->delivery_status);
        $this->assertSame($invoiceCount, ShopInvoice::query()->count());
    }

    private function warehouse(string $name, string $code): Warehouse
    {
        return Warehouse::query()->create(['name' => $name, 'code' => $code, 'is_active' => true]);
    }

    private function order(Shop $shop, string $number): ShopOrder
    {
        return ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'order_number' => $number,
            'order_source' => 'direct_sale',
            'business_date' => now()->toDateString(),
            'delivery_status' => 'pending_delivery',
        ]);
    }

    private function item(ShopOrder $order, Product $product, float $quantity): ShopOrderItem
    {
        return ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => $quantity,
            'approved_qty' => $quantity,
            'unit' => $product->unit,
            'requested_unit' => strtolower((string) $product->unit),
            'requested_unit_label' => strtoupper((string) $product->unit),
            'requested_unit_quantity' => $quantity,
            'requested_unit_conversion_to_base' => 1,
            'locked_selling_price' => 10,
            'line_total' => $quantity * 10,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);
    }

    private function ordersUrl(Warehouse $warehouse): string
    {
        return "/api/v1/warehouse-loadout/{$warehouse->id}/orders?date=".now()->toDateString();
    }

    private function detailUrl(Warehouse $warehouse, ShopOrder $order): string
    {
        return "/api/v1/warehouse-loadout/{$warehouse->id}/orders/{$order->order_number}";
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function patchItems(User $user, Warehouse $warehouse, ShopOrder $order, array $items)
    {
        return $this->actingAs($user)->patchJson(
            $this->detailUrl($warehouse, $order).'/items',
            ['items' => $items],
        );
    }
}
