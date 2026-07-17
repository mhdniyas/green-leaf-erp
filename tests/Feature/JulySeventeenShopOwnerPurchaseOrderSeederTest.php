<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use Database\Seeders\JulySeventeenShopOwnerPurchaseOrderSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JulySeventeenShopOwnerPurchaseOrderSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_seeds_july_seventeen_purchase_orders_from_july_sixteen_shop_owner_orders(): void
    {
        $this->seed(JulySeventeenShopOwnerPurchaseOrderSeeder::class);

        $orders = ShopOrder::query()
            ->with(['creator', 'items'])
            ->whereDate('business_date', '2026-07-17')
            ->where('order_number', 'like', 'RQ-SHOP-20260717-%')
            ->orderBy('order_number')
            ->get();

        $this->assertCount(4, $orders);

        foreach ($orders as $order) {
            $this->assertSame('approved', $order->state);
            $this->assertSame('pending_delivery', $order->delivery_status);
            $this->assertFalse($order->is_late);
            $this->assertSame('2026-07-16', $order->submitted_at?->toDateString());
            $this->assertSame('2026-07-16 21:30:00', $order->deadline_at?->format('Y-m-d H:i:s'));
            $this->assertNotNull($order->creator);
            $this->assertTrue($order->creator->hasRole('shop'));
            $this->assertCount(5, $order->items);
        }

        $purchaseOrder = PurchaseOrder::query()
            ->with('items')
            ->where('po_number', 'PO-SHOP-20260717-001')
            ->firstOrFail();

        $this->assertSame('2026-07-17', $purchaseOrder->order_date?->toDateString());
        $this->assertSame('approved', $purchaseOrder->status->value);
        $this->assertSame('warehouse', $purchaseOrder->fulfillment_type);
        $this->assertGreaterThan(0, $purchaseOrder->items->count());
        $this->assertSame(0, $purchaseOrder->items->where('unit_price', '<=', 0)->count());

        $approvedShopProductIds = ShopOrderItem::query()
            ->whereHas('order', fn ($query) => $query->where('order_number', 'like', 'RQ-SHOP-20260717-%'))
            ->pluck('product_id')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $purchaseOrderProductIds = $purchaseOrder->items
            ->pluck('product_id')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame($approvedShopProductIds, $purchaseOrderProductIds);
    }

    public function test_it_can_be_rerun_without_duplicating_orders_or_purchase_items(): void
    {
        $this->seed(JulySeventeenShopOwnerPurchaseOrderSeeder::class);
        $this->seed(JulySeventeenShopOwnerPurchaseOrderSeeder::class);

        $this->assertSame(4, ShopOrder::query()
            ->where('order_number', 'like', 'RQ-SHOP-20260717-%')
            ->count());

        $purchaseOrder = PurchaseOrder::query()
            ->where('po_number', 'PO-SHOP-20260717-001')
            ->firstOrFail();

        $this->assertSame(
            ShopOrderItem::query()
                ->whereHas('order', fn ($query) => $query->where('order_number', 'like', 'RQ-SHOP-20260717-%'))
                ->pluck('product_id')
                ->unique()
                ->count(),
            PurchaseOrderItem::query()
                ->where('purchase_order_id', $purchaseOrder->id)
                ->count()
        );
    }
}
