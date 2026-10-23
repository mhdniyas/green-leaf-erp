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
            $status === 'pending' || $status === 'received' || $status === 'partially_received' || $status === 'all' => null,
            is_string($status) && str_contains($status, ',') => explode(',', $status),
            is_string($status) && $status !== '' => [$status],
            default => null,
        };

        $query = $this->repository->query()
            ->select(['id', 'supplier_id', 'po_number', 'status', 'order_date', 'created_by', 'created_at', 'updated_at', 'notes', 'purchase_grade', 'fulfillment_type', 'destination_shop_id'])
            ->with([
                'supplier:id,name',
                'items.product:id,name,sku,unit',
                'goodsReceiveds' => fn ($receipts) => app(WarehouseReceiptStateResolver::class)->withFacts($receipts->select('goods_received.*'))->withCount('purchaseInvoices')->with('purchaseInvoices:id,goods_received_id,invoice_number'),
            ])
            ->withCount('items');

        $requestedFulfillment = $filters['fulfillment_type'] ?? null;
        if ($requestedFulfillment === 'shop') {
            $query->where('fulfillment_type', 'shop');
        } elseif ($requestedFulfillment !== 'all') {
            $query->where('fulfillment_type', '!=', 'shop')
                ->whereDoesntHave('purchaserCart', fn ($cart) => $cart->where('purchase_source', 'shop'));
        }

        $query
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
                        })
                        ->orWhereHas('items.product', function ($productQuery) use ($search): void {
                            $productQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%");
                        })
                        ->orWhereHas('goodsReceiveds', function ($grnQuery) use ($search): void {
                            $grnQuery->where('bill_number', 'like', "%{$search}%")
                                ->orWhere('grn_number', 'like', "%{$search}%")
                                ->orWhereHas('purchaseInvoices', fn ($invQ) => $invQ->where('invoice_number', 'like', "%{$search}%"));
                        });
                });
            })
            ->when(! empty($filters['product_id']), function ($query) use ($filters): void {
                $query->whereHas('items', fn ($iq) => $iq->where('product_id', (int) $filters['product_id']));
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        if ($status === 'pending') {
            $query->whereNotIn('status', ['draft', 'cancelled', 'rejected'])->where(function ($pending): void {
                $pending->whereHas('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'))
                    ->orWhere(function ($withoutReceipt): void {
                        $withoutReceipt->whereDoesntHave('goodsReceiveds')->whereIn('status', ['approved', 'sent_to_supplier', 'partially_received', 'received']);
                    });
            });
        } elseif ($status === 'received') {
            $query->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
                ->whereHas('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'received'))
                ->whereDoesntHave('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'));
        } elseif ($status === 'partially_received') {
            $query->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
                ->where(function ($q): void {
                    $q->where('status', POStatus::PartiallyReceived->value)
                        ->orWhere(function ($sub): void {
                            $sub->whereHas('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'received'))
                                ->whereHas('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'));
                        });
                });
        } elseif ($status === 'all') {
            $query->whereNotIn('status', ['draft', 'cancelled', 'rejected']);
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
