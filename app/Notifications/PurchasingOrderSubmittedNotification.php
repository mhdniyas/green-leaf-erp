<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ShopOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PurchasingOrderSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ShopOrder $shopOrder,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'requisition_submitted',
            'title' => 'New requisition submitted',
            'message' => sprintf(
                '%s submitted order %s for %s.',
                $this->shopOrder->shop?->name ?? 'A shop',
                $this->shopOrder->order_number,
                $this->shopOrder->business_date->format('d M Y')
            ),
            'order_number' => $this->shopOrder->order_number,
            'shop_name' => $this->shopOrder->shop?->name,
            'business_date' => $this->shopOrder->business_date->format('Y-m-d'),
            'route' => route('requisitions.show', $this->shopOrder->order_number),
        ];
    }
}
