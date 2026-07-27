<?php

declare(strict_types=1);

namespace App\Services\ShopOrders;

use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;

class DeliveryPriceReadinessService
{
    /**
     * @return array{
     *     ready: bool,
     *     published: array<int, array<string, mixed>>,
     *     unpublished: array<int, array<string, mixed>>,
     *     published_total: float,
     *     message: string
     * }
     */
    public function forOrder(ShopOrder $order): array
    {
        $order->loadMissing(['items.product', 'shop.priceGroup']);

        $published = [];
        $unpublished = [];

        foreach ($order->items as $item) {
            if ((float) ($item->approved_qty ?? 0) <= 0) {
                continue;
            }

            $row = $this->rowForItem($order, $item);

            if ($row['published']) {
                $published[] = $row;

                continue;
            }

            $unpublished[] = $row;
        }

        $publishedTotal = round(array_sum(array_map(
            static fn (array $row): float => (float) ($row['line_total'] ?? 0),
            $published,
        )), 2);

        return [
            'ready' => $unpublished === [] && $published !== [],
            'published' => $published,
            'unpublished' => $unpublished,
            'published_total' => $publishedTotal,
            'message' => $this->messageFor($published, $unpublished),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowForItem(ShopOrder $order, ShopOrderItem $item): array
    {
        $product = $item->product;
        $loadedQty = (float) ($item->loaded_qty ?? $item->approved_qty ?? 0);
        $approvedQty = (float) ($item->approved_qty ?? 0);
        $unit = (string) $item->unit;

        $base = [
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $product?->name ?? 'Unknown Product',
            'sku' => $product?->sku,
            'unit' => $unit,
            'approved_qty' => $approvedQty,
            'loaded_qty' => $loadedQty,
            'price_category' => $this->priceCategory($order),
            'published' => false,
            'status' => 'not_updated',
            'status_label' => 'Not Updated',
            'status_tone' => 'warning',
            'unit_price' => null,
            'line_total' => null,
        ];

        if (! $product instanceof Product || ! $order->business_date) {
            return $base;
        }

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', $order->business_date)
            ->first();

        if (! $approval instanceof DailyPriceApproval) {
            return $base;
        }

        if ($approval->status !== 'approved' || $approval->approved_at === null) {
            return [
                ...$base,
                'status' => 'pending_approval',
                'status_label' => 'Pending Admin Approval',
                'status_tone' => 'info',
            ];
        }

        $priceColumn = $this->priceColumnForOrder($order);
        $unitPrice = $priceColumn !== null ? round((float) $approval->{$priceColumn}, 2) : 0.0;

        if ($unitPrice <= 0.0) {
            return [
                ...$base,
                'status' => 'invalid_price',
                'status_label' => 'Invalid Price',
                'status_tone' => 'danger',
            ];
        }

        return [
            ...$base,
            'published' => true,
            'status' => 'published',
            'status_label' => 'Published',
            'status_tone' => 'success',
            'unit_price' => $unitPrice,
            'line_total' => round($loadedQty * $unitPrice, 2),
        ];
    }

    private function priceCategory(ShopOrder $order): string
    {
        $group = $order->shop?->priceGroup;

        return $group instanceof ShopPriceGroup ? strtoupper((string) $group->name) : '-';
    }

    private function priceColumnForOrder(ShopOrder $order): ?string
    {
        return match ($this->priceCategory($order)) {
            'A' => 'price_a',
            'B' => 'price_b',
            'C' => 'price_c',
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $published
     * @param  array<int, array<string, mixed>>  $unpublished
     */
    private function messageFor(array $published, array $unpublished): string
    {
        if ($unpublished === [] && $published !== []) {
            return 'All products have published prices. Delivery verification is ready for shop submission and admin recheck.';
        }

        if ($published === []) {
            return 'Delivery verification is waiting for daily prices to be published for all products.';
        }

        return 'Some products are priced, but delivery verification will open only after every product price is published.';
    }
}
