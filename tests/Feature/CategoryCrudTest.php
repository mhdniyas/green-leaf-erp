<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_access_categories(): void
    {
        $this->get(route('inventory.categories.index'))
            ->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_access_categories(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.categories.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_categories_list(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['inventory.product.view', 'inventory.category.view']);

        Category::factory()->create(['name' => 'Vegetables Test']);

        $this->actingAs($user)
            ->get(route('inventory.categories.index'))
            ->assertOk()
            ->assertSee('Vegetables Test');
    }

    public function test_authorized_user_can_create_category(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['inventory.product.view', 'inventory.category.create']);

        $this->actingAs($user)
            ->post(route('inventory.categories.store'), [
                'name' => 'Fruits Category',
                'description' => 'Delicious fruits',
                'is_active' => '1',
            ])
            ->assertRedirect(route('inventory.categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Fruits Category',
            'description' => 'Delicious fruits',
            'is_active' => true,
        ]);
    }

    public function test_authorized_user_can_update_category(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['inventory.product.view', 'inventory.category.update']);

        $category = Category::factory()->create([
            'name' => 'Original Name',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('inventory.categories.update', $category), [
                'name' => 'Updated Name',
                'description' => 'Updated desc',
                'is_active' => '0',
            ])
            ->assertRedirect(route('inventory.categories.index'));

        $category->refresh();

        $this->assertSame('Updated Name', $category->name);
        $this->assertSame('Updated desc', $category->description);
        $this->assertFalse($category->is_active);
    }

    public function test_authorized_user_can_delete_empty_category(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $category = Category::factory()->create(['name' => 'Empty Category']);

        $this->actingAs($admin)
            ->delete(route('inventory.categories.destroy', $category))
            ->assertRedirect(route('inventory.categories.index'));

        $this->assertSoftDeleted($category);
    }

    public function test_cannot_delete_category_with_associated_products(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $category = Category::factory()->create(['name' => 'Active Category']);
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Apple',
        ]);

        $this->actingAs($admin)
            ->delete(route('inventory.categories.destroy', $category))
            ->assertRedirect(route('inventory.categories.index'));

        // Should not be deleted
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'deleted_at' => null,
        ]);
    }

    public function test_authorized_user_can_view_category_products_assignment_page(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['inventory.product.view', 'inventory.category.update']);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['name' => 'Associated Product', 'category_id' => $category->id]);
        $otherProduct = Product::factory()->create(['name' => 'Unassociated Product']);

        $this->actingAs($user)
            ->get(route('inventory.categories.products', $category))
            ->assertOk()
            ->assertSee('Associated Product')
            ->assertSee('Unassociated Product');
    }

    public function test_authorized_user_can_bulk_assign_products_to_category(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['inventory.product.view', 'inventory.category.update']);

        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();
        $productToAssign = Product::factory()->create(['name' => 'To Assign', 'category_id' => $category1->id]);

        $this->actingAs($user)
            ->post(route('inventory.categories.products.update', $category2), [
                'product_ids' => [$productToAssign->id],
            ])
            ->assertRedirect(route('inventory.categories.index'));

        $this->assertEquals($category2->id, $productToAssign->fresh()->category_id);
    }
}
