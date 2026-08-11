<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopOrderLoadoutState;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WarehouseLoadoutCompletionApiTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $fruit;

    private Warehouse $vegetable;

    private Warehouse $inactiveWarehouse;

    private User $user;

    private ShopOrder $order;

    private ShopOrderItem $fruitItem;

    private ShopOrderItem $vegetableItem;

    private ShopOrderItem $inactiveItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fruit = $this->warehouse('Fruit Warehouse', 'FRUIT');
        $this->vegetable = $this->warehouse('Vegetable Warehouse', 'VEG');
        $this->inactiveWarehouse = $this->warehouse('Inactive Product Warehouse', 'INACTIVE');
        $this->user = User::factory()->create();
        $this->user->warehouses()->attach([
            $this->fruit->id => ['is_default' => true],
            $this->vegetable->id => ['is_default' => false],
        ]);

        $category = Category::factory()->create(['is_active' => true]);
        $priceGroup = ShopPriceGroup::query()->create(['name' => 'A', 'code' => 'A', 'is_active' => true]);
        $shop = Shop::factory()->create(['shop_price_group_id' => $priceGroup->id]);
        $fruitProduct = $this->product($category, $this->fruit, 'Completion Mango', 'P3-FRUIT', 'KG');
        $vegetableProduct = $this->product($category, $this->vegetable, 'Completion Carrot', 'P3-VEG', 'BOX');
        $inactiveProduct = $this->product($category, $this->inactiveWarehouse, 'Inactive Item', 'P3-INACTIVE', 'KG', false);

        ProductUnit::query()->create([
            'product_id' => $fruitProduct->id,
            'unit' => 'box',
            'label' => 'BOX',
            'conversion_to_base' => 10,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        foreach ([$fruitProduct, $vegetableProduct] as $product) {
            DailyPriceApproval::query()->create([
                'product_id' => $product->id,
                'price_group_id' => $priceGroup->id,
                'business_date' => now()->toDateString(),
                'price_date' => now()->toDateString(),
                'purchase_price' => 10,
                'price_a' => 20,
                'price_b' => 20,
                'price_c' => 20,
                'status' => 'approved',
                'approved_by' => $this->user->id,
                'approved_at' => now(),
            ]);
            $this->stock($product, $product->default_warehouse_id, 100);
        }

        $this->order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'order_number' => 'P3-MIXED-ORDER',
            'order_source' => 'direct_sale',
            'business_date' => now()->toDateString(),
            'delivery_status' => 'pending_delivery',
        ]);
        $this->fruitItem = $this->item($this->order, $fruitProduct, 12.50, 'KG', 'box', 10);
        $this->vegetableItem = $this->item($this->order, $vegetableProduct, 8.00, 'BOX');
        $this->inactiveItem = $this->item($this->order, $inactiveProduct, 4.00, 'KG');
    }

    public function test_first_warehouse_completion_is_partial_and_only_creates_its_stock_effect(): void
    {
        $this->markLoaded($this->fruitItem, 7.25, 0.75);

        $this->complete($this->user, $this->fruit, $this->order)
            ->assertOk()
            ->assertJsonPath('warehouse_status', 'completed')
            ->assertJsonPath('overall_loadout_status', 'partially_completed')
            ->assertJsonPath('delivery_ready', false)
            ->assertJsonPath('can_edit', false);

        $this->assertSame('pending_delivery', $this->order->fresh()->delivery_status);
        $this->assertSame(0, ShopInvoice::query()->count());
        $this->assertDatabaseHas('shop_order_loadout_states', [
            'shop_order_id' => $this->order->id,
            'warehouse_id' => $this->fruit->id,
            'completed_by' => $this->user->id,
        ]);
        $this->assertEquals(7.25, $this->outQuantity($this->fruitItem));
        $this->assertEquals(0.0, $this->outQuantity($this->vegetableItem));
        $this->assertNull($this->vegetableItem->fresh()->loaded_qty);
    }

    public function test_completed_warehouse_is_read_only_while_other_warehouse_remains_editable(): void
    {
        $this->markLoaded($this->fruitItem, 5.50);
        $this->complete($this->user, $this->fruit, $this->order)->assertOk();

        $this->patchItem($this->user, $this->fruit, $this->order, $this->fruitItem, 6.00)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('warehouse');
        $this->patchItem($this->user, $this->vegetable, $this->order, $this->vegetableItem, 3.25)
            ->assertOk()
            ->assertJsonPath('can_edit', true);

        $this->assertEquals(5.50, (float) $this->fruitItem->fresh()->loaded_qty);
        $this->assertEquals(3.25, (float) $this->vegetableItem->fresh()->loaded_qty);
    }

    public function test_duplicate_warehouse_completion_is_idempotent(): void
    {
        $this->markLoaded($this->fruitItem, 6.75);
        $this->complete($this->user, $this->fruit, $this->order)->assertOk();
        $movementCount = $this->outMovements($this->fruitItem)->count();
        $completedAt = ShopOrderLoadoutState::query()
            ->where('shop_order_id', $this->order->id)
            ->where('warehouse_id', $this->fruit->id)
            ->value('completed_at');

        $this->complete($this->user, $this->fruit, $this->order)->assertOk();

        $this->assertSame($movementCount, $this->outMovements($this->fruitItem)->count());
        $this->assertEquals($completedAt, ShopOrderLoadoutState::query()
            ->where('shop_order_id', $this->order->id)
            ->where('warehouse_id', $this->fruit->id)
            ->value('completed_at'));
    }

    public function test_all_not_available_warehouse_completes_without_stock_movement(): void
    {
        $this->fruitItem->update([
            'sorting_status' => 'not_available',
            'loaded_qty' => 0,
            'loadout_discrepancy_type' => 'not_available',
            'loadout_discrepancy_note' => 'No stock',
        ]);

        $this->complete($this->user, $this->fruit, $this->order)
            ->assertOk()
            ->assertJsonPath('warehouse_status', 'completed');

        $this->assertEquals(0.0, $this->outQuantity($this->fruitItem));
    }

    public function test_final_warehouse_completion_makes_delivery_ready_and_creates_one_combined_invoice(): void
    {
        $this->markLoaded($this->fruitItem, 12.50, 1.25);
        $this->markLoaded($this->vegetableItem, 8.00);
        $this->complete($this->user, $this->fruit, $this->order)->assertOk();

        $this->complete($this->user, $this->vegetable, $this->order)
            ->assertOk()
            ->assertJsonPath('warehouse_status', 'completed')
            ->assertJsonPath('overall_loadout_status', 'ready_for_delivery')
            ->assertJsonPath('delivery_ready', true)
            ->assertJsonPath('order.delivery_status', 'ready_for_dispatch');

        $this->assertSame('ready_for_dispatch', $this->order->fresh()->delivery_status);
        $this->assertSame(1, ShopInvoice::query()->where('shop_order_id', $this->order->id)->count());
        $invoice = ShopInvoice::query()->where('shop_order_id', $this->order->id)->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$this->fruitItem->product_id, $this->vegetableItem->product_id],
            $invoice->items()->pluck('product_id')->all(),
        );
        $this->assertEquals(12.50, (float) $invoice->items()->where('product_id', $this->fruitItem->product_id)->value('delivered_qty'));
        $this->assertEquals(8.00, (float) $invoice->items()->where('product_id', $this->vegetableItem->product_id)->value('delivered_qty'));
    }

    public function test_duplicate_final_completion_does_not_duplicate_invoice_or_stock(): void
    {
        $this->markLoaded($this->fruitItem, 4.25);
        $this->markLoaded($this->vegetableItem, 3.50);
        $this->complete($this->user, $this->fruit, $this->order)->assertOk();
        $this->complete($this->user, $this->vegetable, $this->order)->assertOk();
        $movementCount = StockMovement::query()->where('type', StockMovementType::Out->value)->count();

        $this->complete($this->user, $this->vegetable, $this->order)->assertOk();
        $this->complete($this->user, $this->fruit, $this->order)->assertOk();

        $this->assertSame(1, ShopInvoice::query()->where('shop_order_id', $this->order->id)->count());
        $this->assertSame($movementCount, StockMovement::query()->where('type', StockMovementType::Out->value)->count());
    }

    public function test_inactive_product_warehouse_does_not_block_or_allow_completion(): void
    {
        $this->markLoaded($this->fruitItem, 5);
        $this->markLoaded($this->vegetableItem, 5);
        $this->complete($this->user, $this->fruit, $this->order)->assertOk();
        $this->complete($this->user, $this->vegetable, $this->order)
            ->assertOk()
            ->assertJsonPath('overall_loadout_status', 'ready_for_delivery');

        $this->user->warehouses()->attach($this->inactiveWarehouse, ['is_default' => false]);
        $this->complete($this->user, $this->inactiveWarehouse, $this->order)->assertNotFound();
        $this->assertNull($this->inactiveItem->fresh()->loaded_qty);
    }

    public function test_access_and_incomplete_item_validation_are_enforced(): void
    {
        $fruitOnly = User::factory()->create();
        $fruitOnly->warehouses()->attach($this->fruit, ['is_default' => true]);

        $this->complete($fruitOnly, $this->vegetable, $this->order)->assertForbidden();
        $this->complete($fruitOnly, $this->fruit, $this->order)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
        $this->assertSame(0, StockMovement::query()->where('type', StockMovementType::Out->value)->count());
    }

    public function test_all_warehouse_permission_user_can_complete(): void
    {
        $permission = Permission::query()->create(['name' => 'warehouse.loadout.all', 'guard_name' => 'web']);
        $generalUser = User::factory()->create();
        $generalUser->givePermissionTo($permission);
        $this->markLoaded($this->fruitItem, 2.50);

        $this->complete($generalUser, $this->fruit, $this->order)
            ->assertOk()
            ->assertJsonPath('warehouse_status', 'completed');
    }

    public function test_fractional_excess_and_secondary_quantities_remain_exact(): void
    {
        $this->markLoaded($this->fruitItem, 15.75, 1.50);

        $this->complete($this->user, $this->fruit, $this->order)->assertOk();

        $item = $this->fruitItem->fresh();
        $this->assertEquals(15.75, (float) $item->loaded_qty);
        $this->assertEquals(3.25, (float) $item->excess_qty);
        $this->assertEquals(1.50, (float) $item->loaded_order_unit_qty);
        $this->assertEquals(15.75, $this->outQuantity($item));
    }

    public function test_negative_stock_completion_remains_scoped_to_selected_warehouse(): void
    {
        $this->markLoaded($this->fruitItem, 125.50, 12.55);

        $this->complete($this->user, $this->fruit, $this->order)->assertOk();

        $movements = $this->outMovements($this->fruitItem);
        $this->assertEquals(125.50, (float) $movements->sum('quantity'));
        $this->assertTrue($movements->every(fn (StockMovement $movement): bool => (int) $movement->warehouse_id === $this->fruit->id));
        $this->assertEquals(0.0, $this->outQuantity($this->vegetableItem));
    }

    private function warehouse(string $name, string $code): Warehouse
    {
        return Warehouse::query()->create(['name' => $name, 'code' => $code, 'is_active' => true]);
    }

    private function product(
        Category $category,
        Warehouse $warehouse,
        string $name,
        string $sku,
        string $unit,
        bool $active = true,
    ): Product {
        return Product::factory()->create([
            'category_id' => $category->id,
            'default_warehouse_id' => $warehouse->id,
            'name' => $name,
            'sku' => $sku,
            'unit' => $unit,
            'is_active' => $active,
        ]);
    }

    private function item(
        ShopOrder $order,
        Product $product,
        float $quantity,
        string $unit,
        ?string $requestedUnit = null,
        float $conversion = 1,
    ): ShopOrderItem {
        return ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => $quantity,
            'approved_qty' => $quantity,
            'unit' => $unit,
            'requested_unit' => $requestedUnit ?? strtolower($unit),
            'requested_unit_label' => strtoupper($requestedUnit ?? $unit),
            'requested_unit_quantity' => $quantity / $conversion,
            'requested_unit_conversion_to_base' => $conversion,
            'locked_selling_price' => 20,
            'line_total' => $quantity * 20,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
        ]);
    }

    private function stock(Product $product, int $warehouseId, float $quantity): void
    {
        $batch = StockBatch::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'created_by' => $this->user->id,
            'reference' => 'P3-'.$product->sku,
            'received_at' => now(),
            'total_kg' => $quantity,
            'cost_per_kg' => 10,
            'status' => BatchStatus::Sorted,
        ]);
        StockMovement::query()->create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'created_by' => $this->user->id,
            'grade' => 'A',
            'type' => StockMovementType::In,
            'quantity' => $quantity,
            'cost_per_unit' => 10,
            'notes' => 'Phase 3 initial stock',
        ]);
    }

    private function markLoaded(ShopOrderItem $item, float $quantity, ?float $orderUnitQuantity = null): void
    {
        $item->update([
            'loaded_qty' => $quantity,
            'loaded_order_unit_qty' => $orderUnitQuantity,
            'actual_weight' => $quantity,
            'excess_qty' => max(0, $quantity - (float) $item->approved_qty),
            'sorting_status' => 'loaded',
            'is_sorted' => true,
            'sorted_at' => now(),
            'sorted_by' => $this->user->id,
        ]);
    }

    private function complete(User $user, Warehouse $warehouse, ShopOrder $order)
    {
        return $this->actingAs($user)->postJson(
            "/api/v1/warehouse-loadout/{$warehouse->id}/orders/{$order->order_number}/complete"
        );
    }

    private function patchItem(
        User $user,
        Warehouse $warehouse,
        ShopOrder $order,
        ShopOrderItem $item,
        float $quantity,
    ) {
        return $this->actingAs($user)->patchJson(
            "/api/v1/warehouse-loadout/{$warehouse->id}/orders/{$order->order_number}/items",
            ['items' => [[
                'requisition_item_id' => $item->id,
                'loaded_qty' => $quantity,
                'is_not_available' => false,
            ]]],
        );
    }

    private function outMovements(ShopOrderItem $item)
    {
        return StockMovement::query()
            ->where('shop_order_item_id', $item->id)
            ->where('type', StockMovementType::Out->value)
            ->get();
    }

    private function outQuantity(ShopOrderItem $item): float
    {
        return (float) $this->outMovements($item)->sum('quantity');
    }
}
