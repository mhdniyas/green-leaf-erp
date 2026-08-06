<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DedicatedDailyPriceMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_dedicated_matrix_page_renders_with_filters_and_category_a_default(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->syncRoles(['admin']);

        $category = Category::create(['name' => 'Vegetables', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Tomato H',
            'sku' => 'TOM-H',
            'category_id' => $category->id,
            'unit' => 'KG',
            'base_price' => 22.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('purchasing.prices.matrix.index', [
            'date' => '2026-08-03',
            'search' => 'Tomato',
            'category_id' => $category->id,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('matrixDates');
        $response->assertViewHas('matrixProducts');
        $response->assertViewHas('matrixCategory', 'a');
        $response->assertSee('Daily Price Matrix Table');
        $response->assertSee('Tomato H');
    }

    public function test_matrix_cell_update_endpoint_updates_cell_price(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->syncRoles(['admin']);

        $category = Category::create(['name' => 'Veggies', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Sambar Onion',
            'sku' => 'ONION-S',
            'category_id' => $category->id,
            'unit' => 'KG',
            'base_price' => 80.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('purchasing.prices.matrix.cell.update'), [
            'product_id' => $product->id,
            'date' => '2026-08-03',
            'price_category' => 'a',
            'price' => 85.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'product_id' => $product->id,
            'date' => '2026-08-03',
            'price_category' => 'a',
            'price_a' => 85.00,
        ]);

        $this->assertDatabaseHas('daily_price_approvals', [
            'product_id' => $product->id,
            'price_a' => 85.00,
            'status' => 'approved',
        ]);
    }

    public function test_save_row_returns_approval_aware_message_for_non_admin_users(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'purchase', 'guard_name' => 'web']);
        $user->assignRole($role);

        $category = Category::create(['name' => 'Spices', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Black Pepper',
            'sku' => 'BLK-PEP',
            'category_id' => $category->id,
            'unit' => 'KG',
            'base_price' => 45.00,
            'is_active' => true,
        ]);
        $approval = DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-08-03',
            'purchase_price' => 40.00,
            'price_a' => 44.00,
            'price_b' => 44.00,
            'price_c' => 44.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson(route('purchasing.prices.save-row', $approval), [
            'price_a' => 50.00,
            'price_b' => 50.00,
            'price_c' => 50.00,
            'price_unit' => 'KG',
            'date' => '2026-08-03',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Black Pepper price saved and sent for admin approval.');

        $this->assertDatabaseHas('daily_price_approvals', [
            'id' => $approval->id,
            'status' => 'pending',
            'price_a' => 50.00,
        ]);
    }
}
