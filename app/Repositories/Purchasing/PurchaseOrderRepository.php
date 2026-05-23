<?php

declare(strict_types=1);

namespace App\Repositories\Purchasing;

use App\Models\PurchaseOrder;
use App\Repositories\BaseRepository;

class PurchaseOrderRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return PurchaseOrder::class;
    }

    public function generatePoNumber(): string
    {
        $today = now()->format('Ymd');
        $prefix = "PO-{$today}-";

        // Use withTrashed to ensure we don't reuse numbers from deleted POs
        $count = $this->query()
            ->withTrashed()
            ->where('po_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
