<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PriceBoardSeeder;
use Database\Seeders\PurchaserRoleTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserRoleTestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchaser_role_test_seeder_creates_july_9_2026_shop_owner_orders_for_all_shops(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PriceBoardSeeder::class);
        $this->seed(PurchaserRoleTestSeeder::class);

        $businessDate = '2026-07-09';

        $orders = ShopOrder::query()
            ->whereDate('business_date', $businessDate)
            ->where('order_number', 'like', 'RQ-SHOP-20260709-%')
            ->with('creator')
            ->get();

        $this->assertCount(14, $orders);
        $this->assertSame(
            Shop::query()->where('status', 'active')->count(),
            $orders->pluck('shop_id')->unique()->count()
        );
        $this->assertTrue($orders->every(fn (ShopOrder $order): bool => $order->state === 'submitted'));
        $this->assertTrue($orders->every(fn (ShopOrder $order): bool => $order->creator?->hasRole('shop') ?? false));
        $this->assertTrue($orders->every(fn (ShopOrder $order): bool => $order->creator?->shop_id === $order->shop_id));
        $this->assertTrue($orders->every(fn (ShopOrder $order): bool => $order->submitted_at?->toDateString() === '2026-07-08'));

        $this->assertSame(
            14 * 8,
            ShopOrderItem::query()
                ->whereHas('order', function ($query) use ($businessDate): void {
                    $query->whereDate('business_date', $businessDate);
                })
                ->count()
        );

        $this->assertGreaterThan(
            0,
            StockBatch::query()
                ->where('warehouse_receive_pending', false)
                ->where('reference', 'like', 'SEED-LOADOUT-20260709-%')
                ->count()
        );
    }
}
