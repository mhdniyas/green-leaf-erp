<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_flags_page_renders_and_links_to_correct_product_edit_url(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $category = Category::factory()->create();
        $warehouse = Warehouse::factory()->create();

        // Create a product with no approved price today (will trigger flag)
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'default_warehouse_id' => $warehouse->id,
            'name' => 'Alphonso Mango',
            'sku' => '1001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('inventory.products.flags'));

        $response->assertOk();
        $response->assertSee('Alphonso Mango');
        $response->assertSee(route('inventory.products.edit', $product->public_uuid));
    }

    public function test_product_resolve_route_binding_disambiguates_id_and_sku_collision(): void
    {
        $category = Category::factory()->create();

        // Product A: id = 10, sku = '20'
        $productA = Product::factory()->create([
            'id' => 10,
            'category_id' => $category->id,
            'name' => 'Product Ten',
            'sku' => '20',
        ]);

        // Product B: id = 20, sku = '99'
        $productB = Product::factory()->create([
            'id' => 20,
            'category_id' => $category->id,
            'name' => 'Product Twenty',
            'sku' => '99',
        ]);

        $model = new Product;

        // Resolving by public_uuid of productA
        $resolvedByUuid = $model->resolveRouteBinding($productA->public_uuid);
        $this->assertNotNull($resolvedByUuid);
        $this->assertSame($productA->id, $resolvedByUuid->id);

        // Resolving by numeric ID 20 should resolve Product B (id=20), NOT Product A (whose sku='20')
        $resolvedById = $model->resolveRouteBinding('20');
        $this->assertNotNull($resolvedById);
        $this->assertSame(20, $resolvedById->id);
        $this->assertSame('Product Twenty', $resolvedById->name);

        // Resolving by non-existent ID but existing SKU '99' should resolve Product B
        $resolvedBySku = $model->resolveRouteBinding('99');
        $this->assertNotNull($resolvedBySku);
        $this->assertSame($productB->id, $resolvedBySku->id);
    }
}
