<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NegativeStockCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $productName,
        private readonly string $orderNumber,
        private readonly float $negativeQty,
        private readonly string $unit,
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
        return [
            'title' => 'Negative stock created',
            'message' => sprintf(
                '%s stock went negative by %s %s after excess approval for %s.',
                $this->productName,
                number_format($this->negativeQty, 2),
                $this->unit,
                $this->orderNumber,
            ),
            'product_name' => $this->productName,
            'order_number' => $this->orderNumber,
            'negative_qty' => $this->negativeQty,
            'unit' => $this->unit,
        ];
    }
}
