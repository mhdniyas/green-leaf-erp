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
