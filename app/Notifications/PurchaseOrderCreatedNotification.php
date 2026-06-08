<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PurchaseOrderCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PurchaseOrder $purchaseOrder,
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
            'kind' => 'purchase_order_created',
            'title' => 'Purchase order created',
            'message' => sprintf(
                '%s was created for %s.',
                $this->purchaseOrder->po_number,
                $this->purchaseOrder->supplier?->name ?? 'a supplier'
            ),
            'po_number' => $this->purchaseOrder->po_number,
            'supplier_name' => $this->purchaseOrder->supplier?->name,
            'business_date' => $this->purchaseOrder->order_date?->format('Y-m-d'),
            'route' => route('purchasing.orders.show', $this->purchaseOrder),
        ];
    }
}
