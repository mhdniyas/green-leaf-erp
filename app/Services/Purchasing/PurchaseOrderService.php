<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\DTOs\Purchasing\PurchaseOrderData;
use App\Enums\Purchasing\POStatus;
use App\Models\PurchaseOrder;
use App\Repositories\Purchasing\PurchaseOrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private readonly PurchaseOrderRepository $repository,
        private readonly VendorPriceService $vendorPriceService,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->query()
            ->with(['supplier', 'createdBy'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginateFiltered(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $date = $filters['date'] ?? null;
        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;

        /** @var array<int, string>|null $statusList */
        $statusList = match (true) {
            $status === 'pending' => null,
            $status === 'received' => [
                POStatus::Received->value,
                POStatus::Closed->value,
            ],
            is_string($status) && str_contains($status, ',') => explode(',', $status),
            is_string($status) && $status !== '' => [$status],
            default => null,
        };

        $query = $this->repository->query()
            ->select(['id', 'supplier_id', 'po_number', 'status', 'order_date', 'created_by', 'created_at', 'updated_at'])
            ->with(['supplier:id,name', 'goodsReceiveds' => fn ($receipts) => app(WarehouseReceiptStateResolver::class)->withFacts($receipts->select('goods_received.*'))->withCount('purchaseInvoices')])
            ->withCount('items')
            ->when($statusList !== null, fn ($query) => $query->whereIn('status', $statusList))
            ->when($date, fn ($query) => $query->whereDate('order_date', $date))
            ->when($filters['date_before'] ?? null, fn ($query, $dateBefore) => $query->whereDate('order_date', '<', $dateBefore))
            ->when(($filters['period'] ?? null) === 'today' && empty($date), fn ($query) => $query->whereDate('order_date', now()->toDateString()))
            ->when(($filters['period'] ?? null) === 'older' && empty($date) && empty($filters['date_before']), fn ($query) => $query->whereDate('order_date', '<', now()->toDateString()))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search): void {
                            $supplierQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        if ($status === 'pending') {
            $query->whereNotIn('status', ['draft', 'cancelled', 'rejected'])->where(function ($pending): void {
                $pending->whereHas('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'))
                    ->orWhere(function ($withoutReceipt): void {
                        $withoutReceipt->whereDoesntHave('goodsReceiveds')->whereIn('status', ['approved', 'sent_to_supplier', 'partially_received']);
                    });
            });
        }

        app(WarehouseReceiptReadScope::class)->orders($query, $filters['authorized_warehouse_ids'] ?? ($warehouseId ? [$warehouseId] : null));

        return $query->paginate($perPage);
    }

    public function create(PurchaseOrderData $data, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            $poNumber = $this->repository->generatePoNumber();

            /** @var PurchaseOrder $po */
            $po = $this->repository->create([
                'supplier_id' => $data->supplierId,
                'po_number' => $poNumber,
                'status' => POStatus::Draft->value,
                'order_date' => $data->orderDate,
                'created_by' => $userId,
                'notes' => $data->notes,
                'fulfillment_type' => $data->fulfillmentType,
            ]);

            foreach ($data->items as $item) {
                $po->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'price_basis' => $item['price_basis'],
                ]);
            }

            $this->vendorPriceService->syncMany($data->supplierId, $data->items);

            return $po;
        });
    }

    public function update(PurchaseOrder $po, PurchaseOrderData $data): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $data) {
            $po = $this->repository->update($po, [
                'supplier_id' => $data->supplierId,
                'order_date' => $data->orderDate,
                'notes' => $data->notes,
                'fulfillment_type' => $data->fulfillmentType,
            ]);

            // Sync items (delete existing, create new)
            $po->items()->delete();
            foreach ($data->items as $item) {
                $po->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'price_basis' => $item['price_basis'],
                ]);
            }

            $this->vendorPriceService->syncMany($data->supplierId, $data->items);

            return $po;
        });
    }

    public function approve(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po->update(['status' => POStatus::Approved]);

            return $po->fresh();
        });
    }

    public function delete(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            $po->items()->delete();
            $this->repository->delete($po);
        });
    }
}
