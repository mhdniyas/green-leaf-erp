<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PurchaserRoleTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaserRoleTestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchaser_role_test_seeder_creates_previous_day_orders_only(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PurchaserRoleTestSeeder::class);

        $previousDay = Carbon::yesterday()->toDateString();

        $orders = ShopOrder::query()
            ->whereDate('business_date', $previousDay)
            ->where('order_number', 'like', 'RQ-%')
            ->get();

        $this->assertCount(14, $orders);
        $this->assertTrue($orders->every(fn (ShopOrder $order): bool => $order->state === 'approved'));
        $this->assertTrue($orders->every(fn (ShopOrder $order): bool => $order->delivery_status === 'pending_delivery'));

        $this->assertSame(
            14,
            ShopOrder::query()
                ->whereDate('business_date', $previousDay)
                ->distinct('shop_id')
                ->count('shop_id')
        );

        $this->assertSame(
            14,
            ShopOrder::query()
                ->where('order_number', 'like', 'RQ-%')
                ->count()
        );

        $loadItems = ShopOrderItem::query()
            ->whereHas('order', function ($query) use ($previousDay): void {
                $query->whereDate('business_date', $previousDay);
            });

        $this->assertSame(560, $loadItems->count());
        $this->assertGreaterThanOrEqual(200, $loadItems->distinct('product_id')->count('product_id'));

        $tomatoId = Product::query()->where('sku', 'TOMATOH-001')->value('id');
        $this->assertNotNull($tomatoId);

        $this->assertGreaterThanOrEqual(
            3,
            ShopOrderItem::query()
                ->where('product_id', $tomatoId)
                ->where('requested_qty', 5)
                ->whereHas('order', function ($query) use ($previousDay): void {
                    $query->whereDate('business_date', $previousDay);
                })
                ->distinct('shop_order_id')
                ->count('shop_order_id')
        );
    }
}
