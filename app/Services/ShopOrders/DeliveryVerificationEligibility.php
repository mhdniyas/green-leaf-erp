<?php

declare(strict_types=1);

namespace App\Services\ShopOrders;

use App\Models\ShopOrder;
use App\Services\ShopInvoices\ShopInvoiceIntegrityValidator;
use Illuminate\Validation\ValidationException;

class DeliveryVerificationEligibility
{
    public function __construct(
        private readonly ShopInvoiceIntegrityValidator $invoiceIntegrityValidator,
        private readonly DeliveryPriceReadinessService $deliveryPriceReadinessService,
    ) {}

    /**
     * @return array{allowed: bool, code: string, message: string}
     */
    public function forOrder(ShopOrder $order): array
    {
        $order->loadMissing(['invoice.items.product', 'invoice.shop.priceGroup', 'items.product', 'shop.priceGroup']);

        if ($order->state !== 'approved') {
            return $this->blocked('order_not_approved', 'This order has not been approved.');
        }

        if (! in_array((string) $order->delivery_status, ['in_transit', 'ready_for_dispatch'], true)) {
            return $this->blocked('not_out_for_delivery', 'This order is not out for delivery.');
        }

        $hasLoadedItems = $order->items
            ->contains(fn ($item): bool => $item->sorting_status === 'loaded' && (float) ($item->loaded_qty ?? 0) > 0);

        if (! $order->is_allocation_completed && ! $hasLoadedItems) {
            return $this->blocked('not_dispatched', 'This order has not been dispatched from the warehouse.');
        }

        if ($order->is_delivered || $order->delivery_review_status === 'pending') {
            return $this->blocked('already_submitted', 'This delivery has already been submitted or completed.');
        }

        $priceReadiness = $this->deliveryPriceReadinessService->forOrder($order);

        if (! $priceReadiness['ready']) {
            return $this->blocked('daily_prices_unpublished', $priceReadiness['message']);
        }

        if (! $order->invoice) {
            return $this->blocked('invoice_missing', 'Delivery verification is disabled until the approved daily invoice is generated.');
        }

        try {
            $this->invoiceIntegrityValidator->assertMatchesApprovedDailyPrices($order->invoice);
        } catch (ValidationException $exception) {
            return $this->blocked(
                'invoice_price_invalid',
                (string) collect($exception->errors())->flatten()->first()
            );
        }

        return [
            'allowed' => true,
            'code' => 'allowed',
            'message' => 'Delivery verification is available.',
        ];
    }

    /**
     * @return array{allowed: bool, code: string, message: string}
     */
    private function blocked(string $code, string $message): array
    {
        return [
            'allowed' => false,
            'code' => $code,
            'message' => $message,
        ];
    }
}
