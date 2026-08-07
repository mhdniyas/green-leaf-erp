<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $user->syncRoles(['purchase']);

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
            'approved_by' => $user->id,
        ]);
    }

    public function test_matrix_update_saves_submitted_prices(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->syncRoles(['purchase']);

        $category = Category::create(['name' => 'Veggies', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Potato',
            'sku' => 'POT-1',
            'category_id' => $category->id,
            'unit' => 'KG',
            'base_price' => 30.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(url('/purchasing/prices/matrix'), [
            'date' => '2026-08-06',
            'matrix_category' => 'a',
            'action' => 'update',
            'matrix_prices' => [
                $product->id => [
                    '2026-08-06' => '35.00',
                ],
            ],
            'matrix_price_units' => [
                $product->id => [
                    '2026-08-06' => 'kg',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('daily_price_approvals', [
            'product_id' => $product->id,
            'price_a' => 35.00,
            'status' => 'approved',
            'approved_by' => $user->id,
        ]);
    }

    public function test_next_day_matrix_is_seeded_from_previous_day(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $category = Category::create(['name' => 'Veggies', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Cabbage',
            'sku' => 'CAB-1',
            'category_id' => $category->id,
            'unit' => 'KG',
            'base_price' => 20.00,
            'is_active' => true,
        ]);

        DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-08-06',
            'purchase_price' => 18.00,
            'price_unit' => 'kg',
            'price_a' => 25.00,
            'price_b' => 22.50,
            'price_c' => 20.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        Artisan::call('greenleaf:seed-daily-price-matrix', [
            '--date' => '2026-08-07',
        ]);

        $this->assertDatabaseHas('daily_price_approvals', [
            'product_id' => $product->id,
            'business_date' => '2026-08-07 00:00:00',
            'price_a' => 25.00,
            'price_b' => 22.50,
            'price_c' => 20.00,
            'status' => 'approved',
        ]);
    }
}
