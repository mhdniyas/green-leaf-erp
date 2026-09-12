<?php

declare(strict_types=1);

namespace App\Repositories\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Models\StockBatch;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

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

    public function generateReference(?string $date = null): string
    {
        $today = $date ?? now()->format('Ymd');
        $prefix = "BATCH-{$today}-";
        $latest = $this->query()
            ->withTrashed()
            ->where('reference', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(reference) DESC, reference DESC')
            ->value('reference');

        $nextSeq = 1;
        if ($latest !== null && preg_match('/-(\d+)$/', (string) $latest, $m)) {
            $nextSeq = (int) $m[1] + 1;
        }

        do {
            $reference = $prefix.str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
            $nextSeq++;
        } while ($this->query()->withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    public function create(array $data): Model
    {
        $maxAttempts = 3;
        $attempt = 0;

        while (true) {
            $attempt++;
            try {
                if (empty($data['reference'])) {
                    $data['reference'] = $this->generateReference();
                }

                return parent::create($data);
            } catch (Throwable $e) {
                if ($attempt >= $maxAttempts || ! $this->isReferenceDuplicateException($e)) {
                    throw $e;
                }

                // Reference collision: generate next available sequence and retry
                $data['reference'] = $this->generateReference();
            }
        }
    }

    public function isReferenceDuplicateException(Throwable $e): bool
    {
        if ($e instanceof UniqueConstraintViolationException) {
            return true;
        }

        if ($e instanceof QueryException) {
            $message = $e->getMessage();

            return str_contains($message, 'stock_batches_reference_unique')
                || str_contains($message, 'stock_batches.stock_batches_reference_unique')
                || str_contains($message, 'stock_batches.reference')
                || (str_contains($message, 'Duplicate entry') && str_contains($message, 'reference'))
                || str_contains($message, 'UNIQUE constraint failed: stock_batches.reference');
        }

        $message = $e->getMessage();

        return str_contains($message, 'stock_batches_reference_unique')
            || str_contains($message, 'UNIQUE constraint failed: stock_batches.reference');
    }
}
