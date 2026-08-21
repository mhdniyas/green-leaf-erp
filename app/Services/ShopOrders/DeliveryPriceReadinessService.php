<?php

declare(strict_types=1);

namespace App\Services\ShopOrders;

use App\Models\DailyPricePublication;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Services\Pricing\ApprovedDailyPriceResolver;

class DeliveryPriceReadinessService
{
    public function __construct(
        private readonly ApprovedDailyPriceResolver $approvedDailyPriceResolver,
    ) {}

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

        $isDatePublished = $order->business_date
            ? DailyPricePublication::isPublishedForDate($order->business_date)
            : false;

        $published = [];
        $unpublished = [];

        foreach ($order->items as $item) {
            if ((float) ($item->approved_qty ?? 0) <= 0) {
                continue;
            }

            $row = $this->rowForItem($order, $item);

            if ($row['published'] && $isDatePublished) {
                $published[] = $row;

                continue;
            }

            $unpublished[] = $row;
        }

        $publishedTotal = round(array_sum(array_map(
            static fn (array $row): float => (float) ($row['line_total'] ?? 0),
            $published,
        )), 2);

        $ready = $unpublished === [] && $published !== [] && $isDatePublished;

        return [
            'ready' => $ready,
            'is_date_published' => $isDatePublished,
            'published' => $published,
            'unpublished' => $unpublished,
            'published_total' => $publishedTotal,
            'message' => $isDatePublished
                ? $this->messageFor($published, $unpublished)
                : 'Daily prices for this date are currently being updated by purchasing and will be published shortly.',
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

        try {
            $resolvedPrice = $this->approvedDailyPriceResolver->resolve($product, $order->shop, $order->business_date);
        } catch (\Throwable) {
            return $base;
        }

        $unitPrice = round((float) ($resolvedPrice['price'] ?? 0.0), 2);

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
