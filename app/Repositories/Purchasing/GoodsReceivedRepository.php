<?php

declare(strict_types=1);

namespace App\Repositories\Purchasing;

use App\Models\GoodsReceived;
use App\Repositories\BaseRepository;

class GoodsReceivedRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return GoodsReceived::class;
    }

    public function generateGrnNumber(): string
    {
        $today = now()->format('Ymd');
        $prefix = "GRN-{$today}-";

        $count = $this->query()
            ->withTrashed()
            ->where('grn_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
