<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ShopOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ShopDeliveryReviewSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ShopOrder $shopOrder,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $invoice = $this->shopOrder->invoice;

        return [
            'kind' => 'shop_delivery_review_submitted',
            'title' => 'Delivery review submitted',
            'message' => sprintf(
                '%s submitted delivery quantities for admin approval.',
                $this->shopOrder->shop?->name ?? 'A shop'
            ),
            'order_number' => $this->shopOrder->order_number,
            'invoice_number' => $invoice?->invoice_number,
            'shop_name' => $this->shopOrder->shop?->name,
            'business_date' => $this->shopOrder->business_date?->format('Y-m-d'),
            'route' => $invoice
                ? route('purchasing.shop-invoices.show', $invoice)
                : route('admin.delivery-reviews.index'),
        ];
    }
}
