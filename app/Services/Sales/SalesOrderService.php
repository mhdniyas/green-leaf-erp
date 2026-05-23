<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\DTOs\Sales\SalesOrderData;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Sales\SOStatus;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Repositories\Sales\SalesOrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesOrderService
{
    public function __construct(
        private readonly SalesOrderRepository $repository,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->query()
            ->with(['customer', 'createdBy'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(SalesOrderData $data, int $userId): SalesOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            $soNumber = $this->repository->generateSoNumber();

            /** @var SalesOrder $so */
            $so = $this->repository->create([
                'customer_id' => $data->customerId,
                'so_number' => $soNumber,
                'status' => SOStatus::Draft->value,
                'order_date' => $data->orderDate,
                'notes' => $data->notes,
                'created_by' => $userId,
            ]);

            foreach ($data->items as $item) {
                $so->items()->create([
                    'product_id' => $item['product_id'],
                    'grade' => $item['grade'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }

            return $so;
        });
    }

    public function update(SalesOrder $so, SalesOrderData $data): SalesOrder
    {
        return DB::transaction(function () use ($so, $data) {
            $so = $this->repository->update($so, [
                'customer_id' => $data->customerId,
                'order_date' => $data->orderDate,
                'notes' => $data->notes,
            ]);

            $so->items()->delete();

            foreach ($data->items as $item) {
                $so->items()->create([
                    'product_id' => $item['product_id'],
                    'grade' => $item['grade'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }

            return $so;
        });
    }

    /**
     * Confirm an order: validate stock availability and deduct from stock movements.
     */
    public function confirm(SalesOrder $so, int $userId): SalesOrder
    {
        if (! $so->status->canBeConfirmed()) {
            throw new RuntimeException("Order {$so->so_number} cannot be confirmed from status: {$so->status->label()}.");
        }

        return DB::transaction(function () use ($so, $userId) {
            $so->load('items.product');

            foreach ($so->items as $item) {
                $available = $this->availableStock((int) $item->product_id, $item->grade->value);

                if ($available < (float) $item->quantity) {
                    throw new RuntimeException(
                        "Insufficient stock for {$item->product->name} ({$item->grade->label()}). "
                        ."Available: {$available} kg, Required: {$item->quantity} kg."
                    );
                }
            }

            // Deduct stock
            foreach ($so->items as $item) {
                // Find latest batch with stock for this product+grade
                $batch = StockMovement::where('product_id', $item->product_id)
                    ->where('grade', $item->grade)
                    ->where('type', StockMovementType::In)
                    ->latest()
                    ->first();

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'batch_id' => $batch?->batch_id,
                    'created_by' => $userId,
                    'grade' => $item->grade,
                    'type' => StockMovementType::Sale,
                    'quantity' => $item->quantity,
                    'cost_per_unit' => $item->unit_price,
                    'notes' => "Sale: {$so->so_number}",
                ]);
            }

            $so->update(['status' => SOStatus::Confirmed]);

            return $so->fresh();
        });
    }

    /**
     * Dispatch a confirmed order.
     */
    public function dispatch(SalesOrder $so): SalesOrder
    {
        if (! $so->status->canBeDispatched()) {
            throw new RuntimeException("Order {$so->so_number} cannot be dispatched from status: {$so->status->label()}.");
        }

        $so->update(['status' => SOStatus::Dispatched]);

        return $so->fresh();
    }

    /**
     * Cancel an order. If confirmed, reverses stock movements.
     */
    public function cancel(SalesOrder $so, int $userId): SalesOrder
    {
        if (! $so->status->canBeCancelled()) {
            throw new RuntimeException("Order {$so->so_number} cannot be cancelled from status: {$so->status->label()}.");
        }

        return DB::transaction(function () use ($so, $userId) {
            if ($so->status === SOStatus::Confirmed) {
                $so->load('items');

                foreach ($so->items as $item) {
                    $originalSale = StockMovement::where('product_id', $item->product_id)
                        ->where('grade', $item->grade)
                        ->where('type', StockMovementType::Sale)
                        ->where('notes', "Sale: {$so->so_number}")
                        ->latest()
                        ->first();

                    $batchId = $originalSale?->batch_id ?? StockMovement::where('product_id', $item->product_id)
                        ->where('grade', $item->grade)
                        ->where('type', StockMovementType::In)
                        ->latest()
                        ->first()?->batch_id;

                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'batch_id' => $batchId,
                        'created_by' => $userId,
                        'grade' => $item->grade,
                        'type' => StockMovementType::SaleReversal,
                        'quantity' => $item->quantity,
                        'cost_per_unit' => $item->unit_price,
                        'notes' => "Reversal: {$so->so_number} cancelled",
                    ]);
                }
            }

            $so->update(['status' => SOStatus::Cancelled]);

            return $so->fresh();
        });
    }

    /**
     * Mark the order as invoiced (called internally when invoice is created).
     */
    public function markInvoiced(SalesOrder $so): SalesOrder
    {
        $so->update(['status' => SOStatus::Invoiced]);

        return $so->fresh();
    }

    /**
     * Calculate available stock for a product+grade combination.
     */
    public function availableStock(int $productId, string $grade): float
    {
        $inTypes = [StockMovementType::In->value];
        $outTypes = [StockMovementType::Out->value, StockMovementType::Wastage->value, StockMovementType::Sale->value];
        $reversalTypes = [StockMovementType::SaleReversal->value];

        $totalIn = StockMovement::where('product_id', $productId)
            ->where('grade', $grade)
            ->whereIn('type', $inTypes)
            ->sum('quantity');

        $totalOut = StockMovement::where('product_id', $productId)
            ->where('grade', $grade)
            ->whereIn('type', $outTypes)
            ->sum('quantity');

        $totalReversal = StockMovement::where('product_id', $productId)
            ->where('grade', $grade)
            ->whereIn('type', $reversalTypes)
            ->sum('quantity');

        return max(0, (float) $totalIn - (float) $totalOut + (float) $totalReversal);
    }

    public function delete(SalesOrder $so): void
    {
        DB::transaction(function () use ($so) {
            $so->items()->delete();
            $this->repository->delete($so);
        });
    }
}
