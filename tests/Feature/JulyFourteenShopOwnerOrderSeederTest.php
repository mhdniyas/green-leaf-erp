<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ShopOrder;
use Database\Seeders\JulyFourteenShopOwnerOrderSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JulyFourteenShopOwnerOrderSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_seeds_four_shop_owner_orders_with_five_products_each_for_july_fourteen(): void
    {
        $this->seed(JulyFourteenShopOwnerOrderSeeder::class);

        $orders = ShopOrder::query()
            ->with(['creator', 'items'])
            ->whereDate('business_date', '2026-07-14')
            ->where('order_number', 'like', 'RQ-SHOP-20260714-%')
            ->orderBy('order_number')
            ->get();

        $this->assertCount(4, $orders);

        foreach ($orders as $order) {
            $this->assertSame('submitted', $order->state);
            $this->assertSame('pending_delivery', $order->delivery_status);
            $this->assertSame('unpaid', $order->payment_status);
            $this->assertNotNull($order->creator);
            $this->assertTrue($order->creator->hasRole('shop'));
            $this->assertCount(5, $order->items);
        }
    }

    public function test_it_can_be_rerun_without_duplicating_orders_or_items(): void
    {
        $this->seed(JulyFourteenShopOwnerOrderSeeder::class);
        $this->seed(JulyFourteenShopOwnerOrderSeeder::class);

        $orders = ShopOrder::query()
            ->whereDate('business_date', '2026-07-14')
            ->where('order_number', 'like', 'RQ-SHOP-20260714-%')
            ->withCount('items')
            ->get();

        $this->assertCount(4, $orders);
        $this->assertSame([5], $orders->pluck('items_count')->unique()->values()->all());
    }
}
