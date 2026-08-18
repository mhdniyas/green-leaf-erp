<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DedicatedDailyPriceMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_dedicated_matrix_page_renders_with_filters_and_category_a_default(): void
    {
        $this->seed(RolePermissionSeeder::class);
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
        $response->assertSee('Fill Missing');
        $response->assertSee('Remove Future Prices');
        $response->assertSee('Tomato H');
    }

    public function test_matrix_cell_update_endpoint_updates_cell_price(): void
    {
        $this->seed(RolePermissionSeeder::class);
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
        $this->seed(RolePermissionSeeder::class);
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
        $this->seed(RolePermissionSeeder::class);

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

    public function test_matrix_fill_forward_copies_latest_price_into_missing_visible_days(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles(['purchase']);

        $category = Category::create(['name' => 'Veggies', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Ladies Finger',
            'sku' => 'LF-1',
            'category_id' => $category->id,
            'unit' => 'KG',
            'base_price' => 0,
            'vendor_price' => 0,
            'is_active' => true,
        ]);

        DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-08-02',
            'purchase_price' => 35.00,
            'price_unit' => 'kg',
            'price_a' => 40.00,
            'price_b' => 36.00,
            'price_c' => 32.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('purchasing.prices.matrix.fill-forward'), [
            'date' => '2026-08-08',
            'week_start' => '2026-08-02',
            'matrix_category' => 'a',
            'all_product_ids' => [$product->id],
            'all_dates' => [
                '2026-08-02',
                '2026-08-03',
                '2026-08-04',
                '2026-08-05',
                '2026-08-06',
                '2026-08-07',
                '2026-08-08',
            ],
        ]);

        $response->assertRedirect(route('purchasing.prices.matrix.index', [
            'date' => '2026-08-08',
            'week_start' => '2026-08-02',
            'matrix_category' => 'a',
        ]));

        foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07', '2026-08-08'] as $date) {
            $this->assertDatabaseHas('daily_price_approvals', [
                'product_id' => $product->id,
                'business_date' => $date.' 00:00:00',
                'price_a' => 40.00,
                'price_b' => 36.00,
                'price_c' => 32.00,
                'status' => 'approved',
                'approved_by' => $user->id,
            ]);
        }
    }

    public function test_matrix_fill_forward_uses_9999_when_no_previous_price_exists(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles(['purchase']);

        $category = Category::create(['name' => 'Veggies', 'is_active' => true]);
        $product = Product::create([
            'name' => 'No Price Item',
            'sku' => 'NO-PRICE',
            'category_id' => $category->id,
            'unit' => 'KG',
            'base_price' => 0,
            'vendor_price' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('purchasing.prices.matrix.fill-forward'), [
            'date' => '2026-08-08',
            'week_start' => '2026-08-02',
            'matrix_category' => 'a',
            'all_product_ids' => [$product->id],
            'all_dates' => ['2026-08-08'],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('daily_price_approvals', [
            'product_id' => $product->id,
            'business_date' => '2026-08-08 00:00:00',
            'purchase_price' => 9999.0000,
            'price_a' => 9999.00,
            'price_b' => 9999.00,
            'price_c' => 9999.00,
            'status' => 'approved',
            'approved_by' => $user->id,
        ]);
    }

    public function test_matrix_fill_forward_does_not_fill_dates_after_selected_business_date(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles(['purchase']);
        $category = Category::create(['name' => 'Veggies', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Carrot',
            'sku' => 'CARROT-1',
            'category_id' => $category->id,
            'unit' => 'KG',
            'base_price' => 30.00,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('purchasing.prices.matrix.fill-forward'), [
            'date' => '2026-08-11',
            'week_start' => '2026-08-10',
            'matrix_category' => 'a',
            'all_product_ids' => [$product->id],
            'all_dates' => ['2026-08-10', '2026-08-11', '2026-08-12', '2026-08-13'],
        ])->assertRedirect();

        $this->assertDatabaseHas('daily_price_approvals', [
            'product_id' => $product->id,
            'business_date' => '2026-08-11 00:00:00',
        ]);
        $this->assertDatabaseMissing('daily_price_approvals', [
            'product_id' => $product->id,
            'business_date' => '2026-08-12 00:00:00',
        ]);
    }

    public function test_remove_future_prices_only_deletes_visible_dates_after_selected_date(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles(['purchase']);
        $category = Category::create(['name' => 'Veggies', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Beans',
            'sku' => 'BEANS-1',
            'category_id' => $category->id,
            'unit' => 'KG',
            'base_price' => 40.00,
            'is_active' => true,
        ]);

        foreach (['2026-08-11', '2026-08-12', '2026-08-18'] as $date) {
            DailyPriceApproval::create([
                'product_id' => $product->id,
                'business_date' => $date,
                'purchase_price' => 35.00,
                'price_unit' => 'kg',
                'price_a' => 40.00,
                'price_b' => 40.00,
                'price_c' => 40.00,
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }

        $this->actingAs($user)->post(route('purchasing.prices.matrix.remove-future'), [
            'date' => '2026-08-11',
            'week_start' => '2026-08-10',
            'matrix_category' => 'a',
            'all_product_ids' => [$product->id],
            'all_dates' => ['2026-08-10', '2026-08-11', '2026-08-12'],
        ])->assertRedirect();

        $this->assertDatabaseHas('daily_price_approvals', [
            'product_id' => $product->id,
            'business_date' => '2026-08-11 00:00:00',
        ]);
        $this->assertDatabaseMissing('daily_price_approvals', [
            'product_id' => $product->id,
            'business_date' => '2026-08-12 00:00:00',
        ]);
        $this->assertDatabaseHas('daily_price_approvals', [
            'product_id' => $product->id,
            'business_date' => '2026-08-18 00:00:00',
        ]);
    }

    public function test_matrix_supports_multiple_category_selection(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->syncRoles(['admin']);

        $categoryA = Category::create(['name' => 'Vegetables', 'is_active' => true]);
        $categoryB = Category::create(['name' => 'Fruits', 'is_active' => true]);
        $categoryC = Category::create(['name' => 'Dairy', 'is_active' => true]);

        $productA = Product::create([
            'name' => 'Tomato H',
            'sku' => 'TOM-H',
            'category_id' => $categoryA->id,
            'unit' => 'KG',
            'base_price' => 22.00,
            'is_active' => true,
        ]);
        $productB = Product::create([
            'name' => 'Apple Red',
            'sku' => 'APP-R',
            'category_id' => $categoryB->id,
            'unit' => 'KG',
            'base_price' => 120.00,
            'is_active' => true,
        ]);
        $productC = Product::create([
            'name' => 'Fresh Milk',
            'sku' => 'MLK-1',
            'category_id' => $categoryC->id,
            'unit' => 'LTR',
            'base_price' => 50.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('purchasing.prices.matrix.index', [
            'date' => '2026-08-18',
            'category_ids' => [$categoryA->id, $categoryB->id],
        ]));

        $response->assertStatus(200);
        $response->assertSee('Tomato H');
        $response->assertSee('Apple Red');
        $response->assertDontSee('Fresh Milk');
    }
}
