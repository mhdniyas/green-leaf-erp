<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductStatusWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_toggle_product_status_and_audit_is_recorded(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');
        $product = Product::factory()->create(['is_active' => true]);

        $this
            ->actingAs($admin)
            ->patch(route('inventory.products.status.update', $product), [
                'is_active' => '0',
            ])
            ->assertRedirect();

        $product->refresh();

        $this->assertFalse($product->is_active);
        $this->assertSame($admin->id, $product->status_changed_by);
        $this->assertNotNull($product->status_changed_at);
    }

    public function test_admin_product_page_always_shows_status_toggle(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = Product::factory()->create(['name' => 'Tomato H', 'is_active' => true]);

        $this
            ->actingAs($admin)
            ->get(route('inventory.products.index'))
            ->assertOk()
            ->assertSee(route('inventory.products.status.update', $product), false)
            ->assertSee('Toggle product status');
    }

    public function test_product_lists_show_active_products_before_inactive_products(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Product::factory()->create([
            'name' => 'Inactive Product',
            'sku' => '1',
            'is_active' => false,
        ]);
        Product::factory()->create([
            'name' => 'Active Product',
            'sku' => '2',
            'is_active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('inventory.products.index'))
            ->assertOk()
            ->assertSeeInOrder(['Active Product', 'Inactive Product']);
    }

    public function test_admin_can_grant_product_status_permission_to_selected_receiver_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $allowedReceiver = User::factory()->create();
        $blockedReceiver = User::factory()->create();
        $allowedReceiver->assignRole('warehouse_receiver');
        $blockedReceiver->assignRole('warehouse_receiver');

        $this
            ->actingAs($admin)
            ->patch(route('inventory.products.status-permissions.update'), [
                'user_ids' => [$allowedReceiver->id],
            ])
            ->assertRedirect(route('inventory.products.index'));

        $this->assertTrue($allowedReceiver->fresh()->can('inventory.product.status.update'));
        $this->assertFalse($blockedReceiver->fresh()->can('inventory.product.status.update'));
    }

    public function test_receiver_status_toggle_requires_direct_user_permission(): void
    {
        $allowedReceiver = User::factory()->create();
        $blockedReceiver = User::factory()->create();
        $allowedReceiver->assignRole('warehouse_receiver');
        $blockedReceiver->assignRole('warehouse_receiver');
        $allowedReceiver->givePermissionTo('inventory.product.status.update');
        $product = Product::factory()->create(['is_active' => true]);

        $this
            ->actingAs($blockedReceiver)
            ->patch(route('inventory.products.status.update', $product), ['is_active' => '0'])
            ->assertForbidden();

        $this
            ->actingAs($allowedReceiver)
            ->patch(route('inventory.products.status.update', $product), ['is_active' => '0'])
            ->assertRedirect();

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_receiver_product_page_shows_toggle_only_when_user_has_permission(): void
    {
        $allowedReceiver = User::factory()->create();
        $blockedReceiver = User::factory()->create();
        $allowedReceiver->assignRole('warehouse_receiver');
        $blockedReceiver->assignRole('warehouse_receiver');
        $allowedReceiver->givePermissionTo('inventory.product.status.update');
        $product = Product::factory()->create(['name' => 'Tomato H', 'is_active' => true]);

        $this
            ->actingAs($blockedReceiver)
            ->get(route('warehouse.receiver.products.index'))
            ->assertOk()
            ->assertSee('Tomato H')
            ->assertDontSee(route('inventory.products.status.update', $product), false);

        $this
            ->actingAs($allowedReceiver)
            ->get(route('warehouse.receiver.products.index'))
            ->assertOk()
            ->assertSee('Tomato H')
            ->assertSee(route('inventory.products.status.update', $product), false);
    }

    public function test_inactive_products_are_hidden_from_shop_owner_order_selection(): void
    {
        $shop = Shop::factory()->create();
        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');
        $category = Category::factory()->create(['name' => 'Vegetables']);
        Product::factory()->create([
            'name' => 'Active Tomato',
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'Inactive Apple',
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.orders.create'))
            ->assertOk()
            ->assertSee('Active Tomato')
            ->assertDontSee('Inactive Apple');
    }
}
