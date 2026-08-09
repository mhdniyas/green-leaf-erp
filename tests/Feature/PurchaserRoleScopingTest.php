<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserRoleScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchaser_supplier_list_only_contains_vendors_with_purchaser_bills(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $purchaser = User::factory()->create();
        $purchaser->syncRoles(['purchase']);

        $supplierA = Supplier::create([
            'name' => 'Supplier Alpha (Purchaser Vendor)',
            'type' => 'farmer',
            'category' => 'general',
            'is_active' => true,
        ]);

        $supplierB = Supplier::create([
            'name' => 'Supplier Beta (Other Vendor)',
            'type' => 'farmer',
            'category' => 'general',
            'is_active' => true,
        ]);

        // Create a cart for supplierA with purchaser
        PurchaserCart::create([
            'cart_number' => 'CART-TEST-001',
            'user_id' => $purchaser->id,
            'supplier_id' => $supplierA->id,
            'business_date' => '2026-08-03',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($purchaser)->get(route('purchaser.vendors', ['date' => '2026-08-03']));

        $response->assertStatus(200);
        $response->assertSee('Supplier Alpha');
        $response->assertDontSee('Supplier Beta');
    }

    public function test_purchaser_product_matrix_only_contains_assigned_categories(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $cat1 = Category::create(['name' => 'Category One', 'is_active' => true]);
        $cat2 = Category::create(['name' => 'Category Two', 'is_active' => true]);

        $purchaser = User::factory()->create([
            'assigned_category_ids' => [$cat1->id],
        ]);
        $purchaser->syncRoles(['purchase']);

        $prod1 = Product::create([
            'name' => 'Assigned Product 1',
            'sku' => 'PROD-1',
            'category_id' => $cat1->id,
            'unit' => 'KG',
            'base_price' => 10.00,
            'is_active' => true,
        ]);

        $prod2 = Product::create([
            'name' => 'Unassigned Product 2',
            'sku' => 'PROD-2',
            'category_id' => $cat2->id,
            'unit' => 'KG',
            'base_price' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($purchaser)->get(route('purchasing.prices.matrix.index', ['date' => '2026-08-03']));

        $response->assertStatus(200);
        $response->assertSee('Assigned Product 1');
        $response->assertDontSee('Unassigned Product 2');
    }

    public function test_admin_sees_all_suppliers_and_products(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $cat = Category::create(['name' => 'General Veggies', 'is_active' => true]);

        $prod = Product::create([
            'name' => 'Global Product',
            'sku' => 'GLOB-01',
            'category_id' => $cat->id,
            'unit' => 'KG',
            'base_price' => 50.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('purchasing.prices.matrix.index', ['date' => '2026-08-03']));

        $response->assertStatus(200);
        $response->assertSee('Global Product');
    }

    public function test_purchaser_daily_prices_search_shows_products_outside_assigned_categories(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $assignedCategory = Category::create(['name' => 'Assigned Category', 'is_active' => true]);
        $otherCategory = Category::create(['name' => 'Other Category', 'is_active' => true]);

        $purchaser = User::factory()->create([
            'assigned_category_ids' => [$assignedCategory->id],
        ]);
        $purchaser->syncRoles(['purchase']);

        Product::create([
            'name' => 'Bill Tomato',
            'sku' => 'BILL-01',
            'category_id' => $otherCategory->id,
            'unit' => 'KG',
            'base_price' => 12.00,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Assigned Only Product',
            'sku' => 'ASS-01',
            'category_id' => $assignedCategory->id,
            'unit' => 'KG',
            'base_price' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($purchaser)->get(route('purchasing.prices.index', [
            'search' => 'BILL',
            'category_id' => 'all',
            'date' => '2026-08-02',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Bill Tomato');
        $response->assertDontSee('Assigned Only Product');
    }

    public function test_purchaser_product_codes_page_is_category_scoped_and_searchable(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $assignedCategory = Category::create(['name' => 'Leaf Items', 'is_active' => true]);
        $otherCategory = Category::create(['name' => 'Fruit Items', 'is_active' => true]);

        $purchaser = User::factory()->create([
            'assigned_category_ids' => [$assignedCategory->id],
        ]);
        $purchaser->syncRoles(['purchaser']);

        Product::create([
            'name' => 'Catalog Spinach',
            'sku' => 'CAT-101',
            'category_id' => $assignedCategory->id,
            'unit' => 'kg',
            'base_price' => 10.00,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Hidden Apple',
            'sku' => 'CAT-202',
            'category_id' => $otherCategory->id,
            'unit' => 'kg',
            'base_price' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($purchaser)->get(route('purchaser.products', [
            'search' => 'CAT',
        ]));

        $response->assertOk();
        $response->assertSee('Product Codes');
        $response->assertSee('Catalog Spinach');
        $response->assertSee('CAT-101');
        $response->assertDontSee('Hidden Apple');
        $response->assertDontSee('CAT-202');
    }

    public function test_admin_can_make_and_clear_default_purchaser(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $purchaser = User::factory()->create(['name' => 'Default Buyer']);
        $purchaser->syncRoles(['purchaser']);

        $this->actingAs($admin)
            ->post(route('admin.accounting.purchasers.default', $purchaser->public_uuid))
            ->assertRedirect();

        $this->assertSame($purchaser->id, $admin->refresh()->own_purchase_purchaser_id);

        $this->actingAs($admin)
            ->delete(route('admin.accounting.purchasers.default.clear'))
            ->assertRedirect();

        $this->assertNull($admin->refresh()->own_purchase_purchaser_id);
    }

    public function test_admin_product_codes_page_uses_default_purchaser_category_scope(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $assignedCategory = Category::create(['name' => 'Default Buyer Category', 'is_active' => true]);
        $otherCategory = Category::create(['name' => 'Admin Wide Category', 'is_active' => true]);

        $purchaser = User::factory()->create([
            'assigned_category_ids' => [$assignedCategory->id],
        ]);
        $purchaser->syncRoles(['purchaser']);

        $admin = User::factory()->create([
            'own_purchase_purchaser_id' => $purchaser->id,
        ]);
        $admin->syncRoles(['admin']);

        Product::create([
            'name' => 'Default Scoped Spinach',
            'sku' => 'DEF-101',
            'category_id' => $assignedCategory->id,
            'unit' => 'kg',
            'base_price' => 10.00,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Admin Hidden Apple',
            'sku' => 'DEF-202',
            'category_id' => $otherCategory->id,
            'unit' => 'kg',
            'base_price' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('purchaser.products', [
            'search' => 'DEF',
        ]));

        $response->assertOk();
        $response->assertSee('Default Scoped Spinach');
        $response->assertSee('DEF-101');
        $response->assertSee('Scoped to '.$purchaser->name);
        $response->assertDontSee('Admin Hidden Apple');
        $response->assertDontSee('DEF-202');
    }
}
