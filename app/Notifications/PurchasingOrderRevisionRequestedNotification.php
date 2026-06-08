<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ShopOrderRevision;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PurchasingOrderRevisionRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ShopOrderRevision $revision,
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
        $order = $this->revision->shopOrder;

        return [
            'kind' => 'requisition_revision_requested',
            'title' => sprintf('Update #%d requested', $this->revision->revision_no),
            'message' => sprintf(
                '%s requested update #%d for order %s (%d changed items).',
                $order->shop?->name ?? 'A shop',
                $this->revision->revision_no,
                $order->order_number,
                $this->revision->items->count()
            ),
            'order_number' => $order->order_number,
            'shop_name' => $order->shop?->name,
            'business_date' => $order->business_date->format('Y-m-d'),
            'revision_no' => $this->revision->revision_no,
            'changed_items_count' => $this->revision->items->count(),
            'route' => route('requisitions.approved_board', ['date' => $order->business_date->format('Y-m-d')]),
        ];
    }
}
