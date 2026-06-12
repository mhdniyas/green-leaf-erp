<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ShopOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LateRequisitionSubmittedNotification extends Notification
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
            'kind' => 'late_requisition_submitted',
            'title' => 'Late requisition request',
            'message' => sprintf(
                '%s submitted a LATE order request %s for %s.',
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
