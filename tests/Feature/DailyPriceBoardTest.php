<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyPriceBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);
    }

    public function test_purchase_manager_can_view_daily_price_board(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->get(route('purchasing.prices.index'));

        $response->assertOk();
        $response->assertSee('Daily Price Board');
        $response->assertSee('Base Price');
        $response->assertSee('Daily Price');
    }

    public function test_admin_can_update_daily_price_board(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::firstOrFail();
        $date = Carbon::tomorrow()->toDateString();

        $response = $this->actingAs($admin)
            ->post(route('purchasing.prices.update'), [
                'date' => $date,
                'base_prices' => [
                    $product->id => 88.50,
                ],
                'daily_prices' => [
                    $product->id => 93.75,
                ],
            ]);

        $response->assertRedirect(route('purchasing.prices.index', ['date' => $date]));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'base_price' => 88.50,
        ]);

        $priceOverride = DailyProductPrice::query()
            ->where('product_id', $product->id)
            ->whereDate('price_date', $date)
            ->first();

        $this->assertNotNull($priceOverride);
        $this->assertSame(93.75, (float) $priceOverride->price);
    }

    public function test_shop_owner_cannot_access_daily_price_board(): void
    {
        $shopOwner = User::factory()->create();
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)
            ->get(route('purchasing.prices.index'));

        $response->assertForbidden();
    }

    public function test_blank_daily_price_removes_override(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $product = Product::firstOrFail();
        $date = Carbon::tomorrow()->toDateString();

        DailyProductPrice::create([
            'product_id' => $product->id,
            'price_date' => $date,
            'price' => 101.25,
        ]);

        $response = $this->actingAs($manager)
            ->post(route('purchasing.prices.update'), [
                'date' => $date,
                'base_prices' => [
                    $product->id => (float) $product->base_price,
                ],
                'daily_prices' => [
                    $product->id => '',
                ],
            ]);

        $response->assertRedirect(route('purchasing.prices.index', ['date' => $date]));
        $this->assertDatabaseMissing('daily_product_prices', [
            'product_id' => $product->id,
            'price_date' => $date,
        ]);
    }
}
