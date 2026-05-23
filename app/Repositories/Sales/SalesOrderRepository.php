<?php

declare(strict_types=1);

namespace App\Repositories\Sales;

use App\Models\SalesOrder;
use App\Repositories\BaseRepository;

class SalesOrderRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return SalesOrder::class;
    }

    public function generateSoNumber(): string
    {
        $today = now()->format('Ymd');
        $prefix = "SO-{$today}-";

        $count = $this->query()
            ->withTrashed()
            ->where('so_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
