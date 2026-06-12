<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $receiver;

    private Warehouse $warehouse1;

    private Warehouse $warehouse2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');

        $this->warehouse1 = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'VEG-WH',
            'is_active' => true,
        ]);

        $this->warehouse2 = Warehouse::create([
            'name' => 'Fruit Warehouse',
            'code' => 'FRT-WH',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_list_warehouses(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.warehouses.index'));

        $response->assertOk();
        $response->assertSee('Vegetable Warehouse');
        $response->assertSee('Fruit Warehouse');
        $response->assertSee('VEG-WH');
        $response->assertSee('FRT-WH');
    }

    public function test_unauthorized_user_cannot_access_warehouse_crud(): void
    {
        $nonAdmin = User::factory()->create();
        $nonAdmin->assignRole('purchaser');

        $response = $this->actingAs($nonAdmin)
            ->get(route('admin.warehouses.index'));
        $response->assertForbidden();

        $response = $this->actingAs($nonAdmin)
            ->post(route('admin.warehouses.store'), [
                'name' => 'New Warehouse',
                'code' => 'NEW-WH',
            ]);
        $response->assertForbidden();
    }

    public function test_admin_can_create_warehouse(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.warehouses.store'), [
                'name' => 'Cold Storage Warehouse',
                'code' => 'COLD-WH',
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.warehouses.index'));
        $this->assertDatabaseHas('warehouses', [
            'name' => 'Cold Storage Warehouse',
            'code' => 'COLD-WH',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_edit_and_update_warehouse(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.warehouses.update', $this->warehouse1), [
                'name' => 'Updated Veg WH',
                'code' => 'VEG-WH-NEW',
                'is_active' => 0,
            ]);

        $response->assertRedirect(route('admin.warehouses.index'));

        $this->warehouse1->refresh();
        $this->assertEquals('Updated Veg WH', $this->warehouse1->name);
        $this->assertEquals('VEG-WH-NEW', $this->warehouse1->code);
        $this->assertFalse($this->warehouse1->is_active);
    }

    public function test_admin_can_delete_warehouse(): void
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.warehouses.destroy', $this->warehouse2));

        $response->assertRedirect(route('admin.warehouses.index'));

        $this->assertSoftDeleted('warehouses', [
            'id' => $this->warehouse2->id,
        ]);
    }

    public function test_product_creation_and_updating_saves_default_warehouse(): void
    {
        $category = Category::create([
            'name' => 'Leafy Vegetables',
            'code' => 'LV',
            'is_active' => true,
        ]);

        // Create product
        $response = $this->actingAs($this->admin)
            ->post(route('inventory.products.store'), [
                'name' => 'Spinach',
                'category_id' => $category->id,
                'sku' => 'SPINACH-101',
                'unit' => 'kg',
                'description' => 'Fresh green spinach',
                'default_warehouse_id' => $this->warehouse1->id,
            ]);

        $response->assertRedirect(route('inventory.products.index'));

        $product = Product::where('sku', 'SPINACH-101')->firstOrFail();
        $this->assertEquals($this->warehouse1->id, $product->default_warehouse_id);

        // Edit product
        $response = $this->actingAs($this->admin)
            ->put(route('inventory.products.update', $product), [
                'name' => 'Spinach Updated',
                'category_id' => $category->id,
                'sku' => 'SPINACH-101',
                'unit' => 'kg',
                'description' => 'Fresh green spinach updated',
                'default_warehouse_id' => $this->warehouse2->id,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('inventory.products.index'));

        $product->refresh();
        $this->assertEquals($this->warehouse2->id, $product->default_warehouse_id);
    }

    public function test_receiver_checklist_renders_warehouse_dropdowns_and_confirms_correctly(): void
    {
        $product = Product::factory()->create([
            'default_warehouse_id' => $this->warehouse1->id,
        ]);

        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'received_at' => Carbon::today(),
            'warehouse_receive_pending' => true,
        ]);

        // View checklist
        $response = $this->actingAs($this->receiver)
            ->get(route('warehouse.receiver.checklist'));

        $response->assertOk();
        $response->assertSee('Vegetable Warehouse');
        $response->assertSee('Fruit Warehouse');

        // Confirm batch physically stored in Fruit Warehouse (warehouse2)
        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse2->id,
            ]);

        $response->assertRedirect();

        $batch->refresh();
        $this->assertFalse($batch->warehouse_receive_pending);
        $this->assertEquals($this->warehouse2->id, $batch->warehouse_id);
    }

    public function test_confirm_all_resolves_product_default_warehouse(): void
    {
        $product1 = Product::factory()->create([
            'default_warehouse_id' => $this->warehouse1->id,
        ]);
        $product2 = Product::factory()->create([
            'default_warehouse_id' => $this->warehouse2->id,
        ]);
        $product3 = Product::factory()->create([
            'default_warehouse_id' => null, // fallback
        ]);

        $date = Carbon::today();

        $batch1 = StockBatch::factory()->create([
            'product_id' => $product1->id,
            'received_at' => $date,
            'warehouse_receive_pending' => true,
        ]);
        $batch2 = StockBatch::factory()->create([
            'product_id' => $product2->id,
            'received_at' => $date,
            'warehouse_receive_pending' => true,
        ]);
        $batch3 = StockBatch::factory()->create([
            'product_id' => $product3->id,
            'received_at' => $date,
            'warehouse_receive_pending' => true,
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm-all', ['date' => $date->format('Y-m-d')]));

        $response->assertRedirect();

        $batch1->refresh();
        $batch2->refresh();
        $batch3->refresh();

        $this->assertEquals($this->warehouse1->id, $batch1->warehouse_id);
        $this->assertEquals($this->warehouse2->id, $batch2->warehouse_id);
        // Fallback should resolve to first active warehouse ($this->warehouse1)
        $this->assertEquals($this->warehouse1->id, $batch3->warehouse_id);
    }

    public function test_loadout_propagates_warehouse_id_to_outgoing_movements(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_X',
            'name' => 'Shop X',
            'status' => 'active',
        ]);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => Carbon::today()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->receiver->id,
        ]);

        $product = Product::factory()->create([
            'default_warehouse_id' => $this->warehouse2->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        // Stock in the Fruit Warehouse (warehouse2)
        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'received_at' => Carbon::today(),
            'warehouse_id' => $this->warehouse2->id,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 5.0,
            'warehouse_id' => $this->warehouse2->id,
        ]);

        // Load item
        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.loadout.item', $item));

        $response->assertRedirect();

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 10.000,
            'warehouse_id' => $this->warehouse2->id, // Should inherit from source batch!
        ]);
    }
}
