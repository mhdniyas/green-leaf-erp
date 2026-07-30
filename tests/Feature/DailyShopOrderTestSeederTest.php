<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Database\Seeders\DailyShopOrderTestSeeder;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DailyShopOrderTestSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Carbon::setTestNow(Carbon::parse('2026-07-30 10:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_seeds_today_orders_submitted_yesterday_with_two_fruits_and_three_vegetables_per_shop(): void
    {
        Role::findOrCreate('admin', 'web');
        User::factory()->create()->assignRole('admin');

        $fruitCategory = Category::factory()->create(['name' => 'Frut']);
        $vegetableCategory = Category::factory()->create(['name' => 'VEG']);
        $leafCategory = Category::factory()->create(['name' => 'Leaf']);

        Product::factory()
            ->count(4)
            ->sequence(fn (Sequence $sequence): array => [
                'category_id' => $fruitCategory->id,
                'name' => 'Fruit '.$sequence->index,
                'sku' => 'FRUIT-'.$sequence->index,
                'unit' => 'kg',
            ])
            ->create();

        Product::factory()
            ->count(5)
            ->sequence(fn (Sequence $sequence): array => [
                'category_id' => $vegetableCategory->id,
                'name' => 'Vegetable '.$sequence->index,
                'sku' => 'VEG-'.$sequence->index,
                'unit' => 'kg',
            ])
            ->create();

        Product::factory()->create([
            'category_id' => $leafCategory->id,
            'name' => 'Leaf Item',
            'sku' => 'LEAF-1',
            'unit' => 'kg',
        ]);

        $shops = Shop::factory()
            ->count(3)
            ->sequence(fn (Sequence $sequence): array => ['code' => 'SHOP'.$sequence->index])
            ->create();

        $this->seed(DailyShopOrderTestSeeder::class);
        $this->seed(DailyShopOrderTestSeeder::class);

        foreach ($shops as $shop) {
            $order = ShopOrder::query()
                ->where('order_source', 'daily_shop_order_test_seed')
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', '2026-07-30')
                ->with('items.product.category')
                ->firstOrFail();

            $this->assertSame('approved', $order->state);
            $this->assertSame('2026-07-29 19:00:00', $order->submitted_at?->format('Y-m-d H:i:s'));
            $this->assertSame(5, $order->items->count());
            $this->assertSame(2, $order->items->filter(fn ($item): bool => $item->product->category->name === 'Frut')->count());
            $this->assertSame(3, $order->items->filter(fn ($item): bool => $item->product->category->name === 'VEG')->count());
        }

        $this->assertSame(3, ShopOrder::query()->where('order_source', 'daily_shop_order_test_seed')->count());
        $this->assertSame(0, ShopOrder::query()->where('order_source', 'daily_shop_order_test_seed')->whereDate('business_date', '2026-07-29')->count());
    }
}
