<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ShopOwnerLoadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopOwnerLoadSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_owner_load_seeder_creates_previous_day_volume_with_shared_demand_items(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ShopOwnerLoadSeeder::class);

        $previousDay = Carbon::yesterday()->toDateString();

        $loadOrders = ShopOrder::query()
            ->where('order_number', 'like', 'RQ-LOAD-%')
            ->get();

        $this->assertCount(14, $loadOrders);
        $this->assertSame(14, $loadOrders->pluck('shop_id')->filter()->unique()->count());
        $this->assertTrue($loadOrders->every(fn (ShopOrder $order): bool => $order->business_date?->toDateString() === $previousDay));
        $this->assertTrue($loadOrders->every(fn (ShopOrder $order): bool => $order->state === 'approved'));

        $loadItems = ShopOrderItem::query()
            ->whereHas('order', function ($query): void {
                $query->where('order_number', 'like', 'RQ-LOAD-%');
            });

        $this->assertSame(560, $loadItems->count());
        $this->assertGreaterThanOrEqual(200, $loadItems->distinct('product_id')->count('product_id'));

        $tomatoId = Product::query()->where('sku', 'TOMATOH-001')->value('id');
        $this->assertNotNull($tomatoId);

        $tomatoSharedOrders = ShopOrderItem::query()
            ->where('product_id', $tomatoId)
            ->where('requested_qty', 5)
            ->whereHas('order', function ($query): void {
                $query->where('order_number', 'like', 'RQ-LOAD-%');
            })
            ->distinct('shop_order_id')
            ->count('shop_order_id');

        $this->assertGreaterThanOrEqual(3, $tomatoSharedOrders);
    }
}
