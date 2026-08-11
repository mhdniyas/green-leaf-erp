<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function __construct(private readonly StockLedgerService $ledger) {}

    public function reconcile(Product $product, float $submittedSystemQty, float $countedQty, string $businessDate, string $notes, int $userId): ?StockAdjustment
    {
        return DB::transaction(function () use ($product, $submittedSystemQty, $countedQty, $businessDate, $notes, $userId): ?StockAdjustment {
            StockBatch::query()->where('product_id', $product->id)->lockForUpdate()->get(['id']);

            $systemQty = $this->ledger->availableStockForProduct($product->id);
            if (abs($systemQty - $submittedSystemQty) > 0.01) {
                throw ValidationException::withMessages(['counted_qty' => 'Stock changed while you were counting. Refresh the page and try again.']);
            }

            $variance = round($countedQty - $systemQty, 3);
            if (abs($variance) < 0.001) {
                return null;
            }

            $category = $variance > 0 ? 'old_stock' : 'wastage';
            $adjustment = StockAdjustment::query()->create([
                'product_id' => $product->id,
                'created_by' => $userId,
                'business_date' => Carbon::parse($businessDate)->toDateString(),
                'system_qty' => $systemQty,
                'counted_qty' => $countedQty,
                'variance_qty' => $variance,
                'category' => $category,
                'notes' => trim($notes),
            ]);

            if ($variance > 0) {
                $latestBatch = StockBatch::query()->where('product_id', $product->id)->latest('received_at')->latest('id')->first();
                $batch = StockBatch::query()->create([
                    'product_id' => $product->id,
                    'warehouse_id' => $latestBatch?->warehouse_id,
                    'created_by' => $userId,
                    'reference' => 'ADJ-'.now()->format('YmdHis').'-'.$product->id.'-'.$adjustment->id,
                    'received_at' => $businessDate,
                    'total_kg' => $variance,
                    'cost_per_kg' => (float) ($latestBatch?->cost_per_kg ?? 0),
                    'status' => BatchStatus::Sorted->value,
                    'warehouse_receive_pending' => false,
                    'sorted_at' => now(),
                    'notes' => 'Old stock reconciliation #'.$adjustment->id,
                ]);

                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'batch_id' => $batch->id,
                    'warehouse_id' => $batch->warehouse_id,
                    'created_by' => $userId,
                    'grade' => ProductGrade::GradeA->value,
                    'type' => StockMovementType::In->value,
                    'quantity' => $variance,
                    'cost_per_unit' => (float) $batch->cost_per_kg,
                    'notes' => 'Old stock adjustment #'.$adjustment->id.': '.trim($notes),
                ]);
            } else {
                $consumed = $this->ledger->consumeSortedStockForProduct(
                    $product->id,
                    abs($variance),
                    $userId,
                    StockMovementType::Wastage,
                    'Physical count wastage adjustment #'.$adjustment->id.': '.trim($notes),
                );

                if (abs($consumed - abs($variance)) > 0.001) {
                    throw ValidationException::withMessages(['counted_qty' => 'The physical count is lower than the stock currently available. Refresh and retry.']);
                }
            }

            return $adjustment;
        });
    }
}
