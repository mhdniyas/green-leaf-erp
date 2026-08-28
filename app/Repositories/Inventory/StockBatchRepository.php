<?php

declare(strict_types=1);

namespace App\Repositories\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Models\StockBatch;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StockBatchRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return StockBatch::class;
    }

    public function paginateFiltered(int $perPage = 15, ?int $productId = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->query()
            ->with(['product', 'createdBy'])
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('received_at')
            ->paginate($perPage);
    }

    public function findPending(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['product'])
            ->where('status', BatchStatus::Pending)
            ->orderByDesc('received_at')
            ->paginate($perPage);
    }

    public function generateReference(): string
    {
        $today = now()->format('Ymd');
        $prefix = "BATCH-{$today}-";
        $latest = $this->query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $nextSeq = 1;
        if ($latest !== null && preg_match('/-(\d+)$/', (string) $latest, $m)) {
            $nextSeq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }
}
