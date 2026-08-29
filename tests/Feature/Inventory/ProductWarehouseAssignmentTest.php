<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\DTOs\Inventory\ProductData;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\ProductService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductWarehouseAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $vegWarehouse;

    private Warehouse $fruitWarehouse;

    private Warehouse $customWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->vegWarehouse = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'VEG-WH',
            'is_active' => true,
        ]);

        $this->fruitWarehouse = Warehouse::create([
            'name' => 'Fruit Warehouse',
            'code' => 'FRT-WH',
            'is_active' => true,
        ]);

        $this->customWarehouse = Warehouse::create([
            'name' => 'Cold Storage Warehouse',
            'code' => 'COLD-WH',
            'is_active' => true,
        ]);
    }

    public function test_new_product_with_mapped_category_auto_assigns_warehouse(): void
    {
        $category = Category::factory()->create(['name' => 'Exotic Veggies']);
        $category->warehouses()->attach($this->vegWarehouse->id);

        $productService = app(ProductService::class);
        $product = $productService->create(new ProductData(
            categoryId: $category->id,
            defaultWarehouseId: null,
            name: 'Broccoli Exotic',
            sku: 'BROC-001',
            unit: 'kg',
            description: null,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
        ));

        $this->assertSame($this->vegWarehouse->id, $product->default_warehouse_id);
    }

    public function test_explicit_warehouse_overrides_automatic_assignment(): void
    {
        $category = Category::factory()->create(['name' => 'Exotic Veggies']);
        $category->warehouses()->attach($this->vegWarehouse->id);

        $productService = app(ProductService::class);
        $product = $productService->create(new ProductData(
            categoryId: $category->id,
            defaultWarehouseId: $this->customWarehouse->id,
            name: 'Broccoli Cold Special',
            sku: 'BROC-002',
            unit: 'kg',
            description: null,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
        ));

        $this->assertSame($this->customWarehouse->id, $product->default_warehouse_id);
    }

    public function test_unknown_category_remains_unallocated(): void
    {
        $unknownCategory = Category::factory()->create(['name' => 'Unmapped Generic Category']);

        $productService = app(ProductService::class);
        $product = $productService->create(new ProductData(
            categoryId: $unknownCategory->id,
            defaultWarehouseId: null,
            name: 'Mystery Product',
            sku: 'MYST-001',
            unit: 'kg',
            description: null,
            bufferQty: 0.0,
            carryoverEnabled: false,
            isActive: true,
            showInPurchaserOrder: true,
        ));

        $this->assertNull($product->default_warehouse_id);
    }

    public function test_does_not_infer_warehouse_from_other_products_in_same_unmapped_category(): void
    {
        $unmappedCategory = Category::factory()->create(['name' => 'Custom Unmapped Produce']);

        // Explicitly create an existing product in this category pointing to veg warehouse
        Product::create([
            'category_id' => $unmappedCategory->id,
            'default_warehouse_id' => $this->vegWarehouse->id,
            'name' => 'First Product In Unmapped Category',
            'sku' => 'UNMAP-001',
            'unit' => 'kg',
        ]);

        // Creating a new product in the same unmapped category must remain null (not inferred)
        $newProduct = Product::create([
            'category_id' => $unmappedCategory->id,
            'name' => 'Second Product In Unmapped Category',
            'sku' => 'UNMAP-002',
            'unit' => 'kg',
        ]);

        $this->assertNull($newProduct->default_warehouse_id);
    }

    public function test_canonical_domain_mapping_auto_assigns_warehouse_without_guessing_from_name(): void
    {
        // Category with canonical produce name 'Frut' resolves to Fruit Warehouse (FRT-WH)
        $fruitCategory = Category::factory()->create(['name' => 'Frut']);
        $product = Product::create([
            'category_id' => $fruitCategory->id,
            'name' => 'Some Unknown Fruit Name XYZ',
            'sku' => 'FRT-XYZ-01',
            'unit' => 'kg',
        ]);

        $this->assertSame($this->fruitWarehouse->id, $product->default_warehouse_id);
    }

    public function test_import_and_direct_creation_uses_same_resolver_rule(): void
    {
        $category = Category::factory()->create(['name' => 'Leaf']);
        $category->warehouses()->attach($this->vegWarehouse->id);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Palak Spinach',
            'sku' => 'SPIN-999',
            'unit' => 'bunch',
        ]);

        $this->assertSame($this->vegWarehouse->id, $product->default_warehouse_id);
    }

    public function test_existing_allocations_remain_untouched(): void
    {
        $category = Category::factory()->create(['name' => 'Leaf']);
        $category->warehouses()->attach($this->vegWarehouse->id);

        // Product explicitly created with custom warehouse
        $existingProduct = Product::create([
            'category_id' => $category->id,
            'default_warehouse_id' => $this->customWarehouse->id,
            'name' => 'Hydroponic Mint',
            'sku' => 'MINT-001',
            'unit' => 'bunch',
        ]);

        $this->assertSame($this->customWarehouse->id, $existingProduct->fresh()->default_warehouse_id);

        // Updating attributes does not overwrite the existing allocated warehouse
        $existingProduct->update(['name' => 'Hydroponic Mint Updated']);
        $this->assertSame($this->customWarehouse->id, $existingProduct->fresh()->default_warehouse_id);
    }

    public function test_admin_web_product_create_auto_assigns_warehouse(): void
    {
        $category = Category::factory()->create(['name' => 'VEG']);
        $category->warehouses()->attach($this->vegWarehouse->id);

        $this->actingAs($this->admin);

        $response = $this->post(route('inventory.products.store'), [
            'category_id' => $category->id,
            'default_warehouse_id' => '',
            'name' => 'Fresh Beetroot',
            'sku' => 'BEET-101',
            'unit' => 'kg',
            'units' => [
                ['unit' => 'kg', 'label' => 'KG', 'conversion_to_base' => 1.0, 'is_base' => 1, 'is_orderable' => 1],
            ],
        ]);

        $response->assertRedirect(route('inventory.products.index'));

        $product = Product::where('sku', 'BEET-101')->firstOrFail();
        $this->assertSame($this->vegWarehouse->id, $product->default_warehouse_id);
    }

    public function test_admin_can_assign_recommended_warehouses_in_bulk(): void
    {
        $category = Category::factory()->create(['name' => 'English']);
        $category->warehouses()->attach($this->vegWarehouse->id);

        // Create unallocated product bypassing hook
        $unallocatedProduct = Product::withoutEvents(function () use ($category) {
            return Product::create([
                'category_id' => $category->id,
                'default_warehouse_id' => null,
                'name' => 'Zucchini Green',
                'sku' => 'ZUCH-001',
                'unit' => 'kg',
            ]);
        });

        $this->assertNull($unallocatedProduct->default_warehouse_id);

        $this->actingAs($this->admin);

        $response = $this->post(route('admin.warehouses.assign-recommended'));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame($this->vegWarehouse->id, $unallocatedProduct->fresh()->default_warehouse_id);
    }

    public function test_artisan_command_assigns_recommended_warehouses(): void
    {
        $category = Category::factory()->create(['name' => 'Stationary 1']);
        $category->warehouses()->attach($this->vegWarehouse->id);

        $unallocatedProduct = Product::withoutEvents(function () use ($category) {
            return Product::create([
                'category_id' => $category->id,
                'default_warehouse_id' => null,
                'name' => 'Packing Cover XL',
                'sku' => 'COV-XL-01',
                'unit' => 'piece',
            ]);
        });

        $this->artisan('greenleaf:assign-recommended-warehouses --dry-run')
            ->expectsOutputToContain('DRY RUN: 1 products can be assigned, 0 need manual review.')
            ->assertSuccessful();

        $this->assertNull($unallocatedProduct->fresh()->default_warehouse_id);

        $this->artisan('greenleaf:assign-recommended-warehouses')
            ->expectsOutputToContain('Successfully assigned 1 products.')
            ->assertSuccessful();

        $this->assertSame($this->vegWarehouse->id, $unallocatedProduct->fresh()->default_warehouse_id);
    }
}
