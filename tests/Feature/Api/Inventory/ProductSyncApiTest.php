<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductSyncApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::firstOrCreate(['name' => 'warehouse_receiver']);
        $this->user = User::factory()->create(['name' => 'Receiver Tester']);
        $this->user->assignRole('warehouse_receiver');
    }

    public function test_full_product_sync_returns_all_active_products(): void
    {
        Sanctum::actingAs($this->user);

        $category = Category::factory()->create(['name' => 'Fresh Vegetables']);

        $p1 = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato Local',
            'sku' => 'TOM-01',
            'unit' => 'KG',
            'is_active' => true,
        ]);

        ProductUnit::create([
            'product_id' => $p1->id,
            'unit' => 'box',
            'label' => 'BOX 10 KG',
            'conversion_to_base' => 10.0,
            'is_base' => false,
            'is_orderable' => true,
            'sort_order' => 1,
        ]);

        $p2 = Product::factory()->create([
            'name' => 'Potato Small',
            'sku' => 'POT-01',
            'unit' => 'KG',
            'is_active' => true,
        ]);

        // Inactive product
        Product::factory()->create([
            'name' => 'Old Spinach',
            'sku' => 'SPN-01',
            'unit' => 'KG',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/inventory/products/sync');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'products',
                    'deleted_or_inactive_ids',
                    'sync_token',
                    'server_time',
                ],
            ]);

        $products = $response->json('data.products');
        $this->assertCount(2, $products);

        $p1Data = collect($products)->firstWhere('sku', 'TOM-01');
        $this->assertNotNull($p1Data);
        $this->assertSame('Tomato Local', $p1Data['name']);
        $this->assertSame('KG', $p1Data['unit']);
        $this->assertSame('Fresh Vegetables', $p1Data['category']);
        $this->assertContains('KG', $p1Data['allowed_units']);
        $this->assertContains('box', $p1Data['allowed_units']);
        $this->assertTrue($p1Data['is_active']);
    }

    public function test_incremental_sync_returns_only_updated_and_deleted_inactive_products(): void
    {
        Sanctum::actingAs($this->user);

        // Product created in past
        $past = now()->subHours(5);
        $p1 = Product::factory()->create([
            'name' => 'Old Unchanged Product',
            'sku' => 'OLD-01',
            'unit' => 'KG',
            'is_active' => true,
        ]);
        Product::where('id', $p1->id)->update(['updated_at' => $past]);

        // Product created/updated recently
        $p2 = Product::factory()->create([
            'name' => 'Recently Added Onion',
            'sku' => 'ONI-01',
            'unit' => 'KG',
            'is_active' => true,
            'updated_at' => now(),
        ]);
        Product::where('id', $p2->id)->update(['updated_at' => now()]);

        // Product modified to inactive recently
        $p3 = Product::factory()->create([
            'name' => 'Deactivated Garlic',
            'sku' => 'GAR-01',
            'unit' => 'KG',
            'is_active' => false,
            'updated_at' => now(),
        ]);
        Product::where('id', $p3->id)->update(['updated_at' => now()]);

        $syncTime = now()->subHours(2)->toIso8601String();

        $response = $this->getJson('/api/v1/inventory/products/sync?updated_after='.urlencode($syncTime));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $products = $response->json('data.products');
        $deletedOrInactiveIds = $response->json('data.deleted_or_inactive_ids');

        // Only p2 in products
        $this->assertCount(1, $products);
        $this->assertSame('Recently Added Onion', $products[0]['name']);

        // p3 in deleted_or_inactive_ids
        $this->assertContains($p3->id, $deletedOrInactiveIds);
        $this->assertNotContains($p1->id, $deletedOrInactiveIds);
    }
}
