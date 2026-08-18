<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminWarehouseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Role::findOrCreate('admin', 'web');
        Permission::findOrCreate('admin.user.view', 'web');
        Permission::findOrCreate('inventory.stock.adjust', 'web');

        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'registration_status' => 'approved',
        ]);
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo(['admin.user.view', 'inventory.stock.adjust']);
    }

    public function test_admin_can_view_warehouses_list(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN-WH',
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Allocated Tomato',
            'sku' => 'TOM-01',
            'default_warehouse_id' => $warehouse->id,
        ]);

        Product::factory()->create([
            'name' => 'Unallocated Potato',
            'sku' => 'POT-01',
            'default_warehouse_id' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.warehouses.index'));

        $response->assertOk();
        $response->assertSee('Main Warehouse');
        $response->assertSee('MAIN-WH');
        $response->assertSee('1 product');
        $response->assertSee('Not Allocated');
    }

    public function test_admin_can_view_unallocated_items_tab(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Cold Storage',
            'code' => 'COLD-01',
            'is_active' => true,
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);

        $allocated = Product::factory()->create([
            'name' => 'Allocated Apple',
            'sku' => 'APP-01',
            'default_warehouse_id' => $warehouse->id,
        ]);

        $unallocated = Product::factory()->create([
            'name' => 'Unallocated Mango',
            'sku' => 'MNG-01',
            'category_id' => $category->id,
            'default_warehouse_id' => null,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.warehouses.index', ['tab' => 'unallocated']));

        $response->assertOk();
        $response->assertSee('Unallocated Mango');
        $response->assertSee('MNG-01');
        $response->assertSee('Vegetables');
        $response->assertDontSee('Allocated Apple');
    }

    public function test_admin_can_filter_unallocated_items_by_search_and_category(): void
    {
        $category1 = Category::factory()->create(['name' => 'Fruits']);
        $category2 = Category::factory()->create(['name' => 'Vegetables']);

        Product::factory()->create([
            'name' => 'Alphonso Mango',
            'sku' => 'MNG-ALP',
            'category_id' => $category1->id,
            'default_warehouse_id' => null,
        ]);

        Product::factory()->create([
            'name' => 'Fresh Onion',
            'sku' => 'ON-01',
            'category_id' => $category2->id,
            'default_warehouse_id' => null,
        ]);

        // Search by query
        $response = $this->actingAs($this->admin)
            ->get(route('admin.warehouses.index', ['tab' => 'unallocated', 'search' => 'Mango']));

        $response->assertOk();
        $response->assertSee('Alphonso Mango');
        $response->assertDontSee('Fresh Onion');

        // Filter by category
        $response = $this->actingAs($this->admin)
            ->get(route('admin.warehouses.index', ['tab' => 'unallocated', 'category_id' => $category2->id]));

        $response->assertOk();
        $response->assertSee('Fresh Onion');
        $response->assertDontSee('Alphonso Mango');
    }

    public function test_admin_can_allocate_single_product_to_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Dry Goods Warehouse',
            'code' => 'DRY-WH',
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'name' => 'Rice 5kg',
            'sku' => 'RICE-05',
            'default_warehouse_id' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.warehouses.allocate-product'), [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals($warehouse->id, $product->fresh()->default_warehouse_id);
    }

    public function test_admin_can_bulk_allocate_products_to_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Central Hub',
            'code' => 'HUB-01',
            'is_active' => true,
        ]);

        $product1 = Product::factory()->create(['default_warehouse_id' => null]);
        $product2 = Product::factory()->create(['default_warehouse_id' => null]);
        $product3 = Product::factory()->create(['default_warehouse_id' => null]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.warehouses.bulk-allocate'), [
                'product_ids' => [$product1->id, $product2->id],
                'warehouse_id' => $warehouse->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals($warehouse->id, $product1->fresh()->default_warehouse_id);
        $this->assertEquals($warehouse->id, $product2->fresh()->default_warehouse_id);
        $this->assertNull($product3->fresh()->default_warehouse_id);
    }
}
