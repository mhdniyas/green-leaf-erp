<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    private string $dummyBase64Image;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['inventory.product.create', 'inventory.product.update']);

        $this->category = Category::factory()->create();

        // 1x1 pixel transparent PNG
        $this->dummyBase64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        Storage::fake('public');
    }

    public function test_unauthenticated_user_cannot_create_product(): void
    {
        $response = $this->post(route('inventory.products.store'), [
            'category_id' => $this->category->id,
            'name' => 'Carrot',
            'sku' => 'CAR-001',
            'unit' => 'kg',
            'image_data' => $this->dummyBase64Image,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_product_with_image(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('inventory.products.store'), [
                'category_id' => $this->category->id,
                'name' => 'Carrot',
                'sku' => 'CAR-001',
                'unit' => 'kg',
                'image_data' => $this->dummyBase64Image,
            ]);

        $response->assertRedirect(route('inventory.products.index'));
        $response->assertSessionHas('success', 'Product created successfully.');

        $product = Product::first();
        $this->assertNotNull($product);
        $this->assertNotNull($product->image);
        $this->assertStringStartsWith('products/', $product->image);

        // Verify the file was physically saved to public disk
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_authenticated_user_can_update_product_image(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'image' => 'products/old_image.png',
        ]);

        // Put fake file in storage to assert it gets deleted
        Storage::disk('public')->put('products/old_image.png', 'fake-content');

        $response = $this->actingAs($this->user)
            ->put(route('inventory.products.update', $product), [
                'category_id' => $this->category->id,
                'name' => 'Carrot Updated',
                'sku' => $product->sku,
                'unit' => 'kg',
                'image_data' => $this->dummyBase64Image,
            ]);

        $response->assertRedirect(route('inventory.products.index'));

        // Old file must be deleted
        Storage::disk('public')->assertMissing('products/old_image.png');

        // New file must be present
        $product->refresh();
        $this->assertNotNull($product->image);
        $this->assertNotEquals('products/old_image.png', $product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_authenticated_user_can_remove_product_image(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'image' => 'products/old_image.png',
        ]);

        Storage::disk('public')->put('products/old_image.png', 'fake-content');

        $response = $this->actingAs($this->user)
            ->put(route('inventory.products.update', $product), [
                'category_id' => $this->category->id,
                'name' => 'Carrot Updated',
                'sku' => $product->sku,
                'unit' => 'kg',
                'remove_image' => 1,
            ]);

        $response->assertRedirect(route('inventory.products.index'));

        Storage::disk('public')->assertMissing('products/old_image.png');

        $product->refresh();
        $this->assertNull($product->image);
    }
}
