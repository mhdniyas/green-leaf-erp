<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteModelBindingAndFormRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo([
            'inventory.product.view',
            'inventory.product.create',
            'inventory.product.update',
            'inventory.stock.view',
            'inventory.stock.adjust',
            'inventory.sorting.view',
            'inventory.sorting.process',
        ]);

        $this->unauthorizedUser = User::factory()->create();
        // Give only view permission
        $this->unauthorizedUser->givePermissionTo([
            'inventory.product.view',
            'inventory.stock.view',
        ]);

        $this->category = Category::factory()->create();
    }

    public function test_product_routes_use_sku_instead_of_id(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'sku' => 'PROD-SKU-123',
        ]);

        // Assert route() generates URL with SKU
        $editUrl = route('inventory.products.edit', $product);
        $this->assertStringContainsString('PROD-SKU-123', $editUrl);
        $this->assertStringNotContainsString("/{$product->id}/", $editUrl);

        // Assert edit page works using SKU
        $response = $this->actingAs($this->admin)
            ->get($editUrl);
        $response->assertOk();
        $response->assertSee('PROD-SKU-123');

        // Assert updating using SKU route
        $updateResponse = $this->actingAs($this->admin)
            ->put(route('inventory.products.update', $product), [
                'category_id' => $this->category->id,
                'name' => 'Updated Product Name',
                'sku' => 'PROD-SKU-123',
                'unit' => 'kg',
            ]);
        $updateResponse->assertRedirect(route('inventory.products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product Name',
        ]);
    }

    public function test_batch_routes_use_reference_instead_of_id(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);
        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'created_by' => $this->admin->id,
            'reference' => 'BATCH-REF-456',
        ]);

        // Assert route() generates URL with reference
        $showUrl = route('inventory.batches.show', $batch);
        $this->assertStringContainsString('BATCH-REF-456', $showUrl);
        $this->assertStringNotContainsString("/{$batch->id}", $showUrl);

        // Assert show page works using reference
        $response = $this->actingAs($this->admin)
            ->get($showUrl);
        $response->assertOk();
        $response->assertSee('BATCH-REF-456');

        // Assert sort page works using reference
        $sortUrl = route('inventory.batches.sort', $batch);
        $response = $this->actingAs($this->admin)
            ->get($sortUrl);
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_update_product(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        // Unauthorized user cannot update product
        $response = $this->actingAs($this->unauthorizedUser)
            ->put(route('inventory.products.update', $product), [
                'category_id' => $this->category->id,
                'name' => 'Updated Product Name',
                'sku' => 'PROD-SKU-123',
                'unit' => 'kg',
            ]);
        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        // Guest cannot update product and is redirected
        $response = $this->put(route('inventory.products.update', $product), [
            'category_id' => $this->category->id,
            'name' => 'Updated Product Name',
            'sku' => 'PROD-SKU-123',
            'unit' => 'kg',
        ]);
        $response->assertRedirect(route('login'));
    }
}
