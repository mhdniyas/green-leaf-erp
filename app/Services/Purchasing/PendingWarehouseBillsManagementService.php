<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PendingWarehouseBillsManagementService
{
    public function __construct(
        private readonly PurchaserBillsNotReceivedService $pendingBillsService,
        private readonly AdvanceReceiveReconciliationService $reconService,
        private readonly ApproveGoodsReceiptAction $approveAction,
    ) {}

    /**
     * Retrieve 5 summary metrics for the KPI cards.
     *
     * @return array{
     *     total_pending_bills: int,
     *     total_outstanding_qty: float,
     *     bills_eligible_for_advance_matching: int,
     *     awaiting_physical_receiving: int,
     *     cleared_today_count: int,
     *     cleared_today_qty: float
     * }
     */
    public function getSummaryMetrics(array|int|string|null $filters = null, bool $ignoreCache = false): array
    {
        $normalizedFilters = is_array($filters) ? $filters : ['warehouse_id' => $filters];
        $whId = ! empty($normalizedFilters['warehouse_id']) ? (int) $normalizedFilters['warehouse_id'] : null;
        $purchaserId = ! empty($normalizedFilters['purchaser_id']) ? (int) $normalizedFilters['purchaser_id'] : null;
        $supplierId = ! empty($normalizedFilters['supplier_id']) ? (int) $normalizedFilters['supplier_id'] : null;
        $datePreset = (string) ($normalizedFilters['date_preset'] ?? 'today');
        $startDate = $normalizedFilters['start_date'] ?? null;
        $endDate = $normalizedFilters['end_date'] ?? null;
        $search = trim((string) ($normalizedFilters['search'] ?? ''));

        $cacheKey = 'pending_warehouse_bills_summary_metrics_'.md5((string) json_encode([
            $whId,
            $purchaserId,
            $supplierId,
            $datePreset,
            $startDate,
            $endDate,
            $search,
        ]));

        if ($ignoreCache) {
            return $this->computeSummaryMetrics($filters);
        }

        return Cache::remember($cacheKey, 60, function () use ($filters): array {
            return $this->computeSummaryMetrics($filters);
        });
    }

    /**
     * Compute the 5 summary metrics accurately.
     */
    public function computeSummaryMetrics(array|int|string|null $filters = null): array
    {
        $normalizedFilters = is_array($filters) ? $filters : ['warehouse_id' => $filters];
        $whId = ! empty($normalizedFilters['warehouse_id']) ? (int) $normalizedFilters['warehouse_id'] : null;
        $purchaserId = ! empty($normalizedFilters['purchaser_id']) ? (int) $normalizedFilters['purchaser_id'] : null;
        $supplierId = ! empty($normalizedFilters['supplier_id']) ? (int) $normalizedFilters['supplier_id'] : null;
        $datePreset = (string) ($normalizedFilters['date_preset'] ?? 'today');
        $startDate = $normalizedFilters['start_date'] ?? null;
        $endDate = $normalizedFilters['end_date'] ?? null;

        $pendingData = $this->pendingBillsService->getPendingBillsData();
        $allPendingBills = collect($pendingData['bills']);
        $allPendingIds = $allPendingBills->pluck('id')->all();

        // Filter pending bills collection according to active filters
        $filteredPendingCollection = $allPendingBills;
        if ($whId !== null) {
            $filteredPendingCollection = $filteredPendingCollection->where('warehouse_id', $whId);
        }
        if ($purchaserId !== null) {
            $filteredPendingCollection = $filteredPendingCollection->where('purchaser_id', $purchaserId);
        }
        if ($supplierId !== null) {
            $filteredPendingCollection = $filteredPendingCollection->where('supplier_id', $supplierId);
        }

        $range = ($datePreset !== 'all') ? $this->resolveDateRange($datePreset, $startDate, $endDate) : null;
        if ($range !== null) {
            $startDateStr = $range['start']->toDateString();
            $endDateStr = $range['end']->toDateString();
            $startTime = $range['start'];
            $endTime = $range['end'];

            $filteredPendingCollection = $filteredPendingCollection->filter(function ($b) use ($startDateStr, $endDateStr, $startTime, $endTime): bool {
                $bDate = $b['business_date'] ?? null;
                $rawDate = $b['raw_date'] ?? null;
                $cDate = $rawDate ? Carbon::parse($rawDate) : null;

                $matchesBusinessDate = $bDate && $bDate >= $startDateStr && $bDate <= $endDateStr;
                $matchesCreatedAt = $cDate && $cDate->betweenIncluded($startTime, $endTime);

                return $matchesBusinessDate || $matchesCreatedAt;
            });
        }

        $totalPendingBills = $filteredPendingCollection->count();
        $totalOutstandingQty = round((float) $filteredPendingCollection->sum('outstanding_qty'), 2);
        $notReceivedCount = $filteredPendingCollection->where('status', 'Not Received')->count();
        $partiallyReceivedCount = $filteredPendingCollection->where('status', 'Partially Received')->count();
        $filteredPendingIds = $filteredPendingCollection->pluck('id')->all();

        // Compute Cleared in Period / Cleared Today metrics
        $clearedBatchesQuery = DB::table('stock_batches')
            ->join('goods_received', 'goods_received.id', '=', 'stock_batches.goods_received_id')
            ->join('purchase_invoices', 'purchase_invoices.goods_received_id', '=', 'goods_received.id')
            ->where('stock_batches.warehouse_receive_pending', false)
            ->whereNull('purchase_invoices.cancelled_at')
            ->whereNotIn('purchase_invoices.status', ['cancelled'])
            ->whereNotIn('purchase_invoices.id', $allPendingIds);

        $clearedMatchesQuery = DB::table('advance_receive_matches')
            ->join('purchase_invoices', 'purchase_invoices.goods_received_id', '=', 'advance_receive_matches.bill_goods_received_id')
            ->join('goods_received', 'goods_received.id', '=', 'advance_receive_matches.bill_goods_received_id')
            ->whereNull('purchase_invoices.cancelled_at')
            ->whereNotIn('purchase_invoices.status', ['cancelled'])
            ->whereNotIn('purchase_invoices.id', $allPendingIds);

        if ($range !== null) {
            $clearedBatchesQuery->whereBetween('stock_batches.warehouse_confirmed_at', [$range['start'], $range['end']]);
            $clearedMatchesQuery->whereBetween('advance_receive_matches.confirmed_at', [$range['start'], $range['end']]);
        }

        if ($whId !== null) {
            $clearedBatchesQuery->where('goods_received.warehouse_id', $whId);
            $clearedMatchesQuery->where('goods_received.warehouse_id', $whId);
        }

        if ($purchaserId !== null) {
            $clearedBatchesQuery->where(function ($q) use ($purchaserId): void {
                $q->where('purchase_invoices.purchaser_submitted_by', $purchaserId)
                    ->orWhereExists(function ($subQ) use ($purchaserId): void {
                        $subQ->select(DB::raw(1))
                            ->from('purchaser_carts')
                            ->whereColumn('purchaser_carts.id', 'purchase_invoices.purchaser_cart_id')
                            ->where('purchaser_carts.user_id', $purchaserId);
                    });
            });
            $clearedMatchesQuery->where(function ($q) use ($purchaserId): void {
                $q->where('purchase_invoices.purchaser_submitted_by', $purchaserId)
                    ->orWhereExists(function ($subQ) use ($purchaserId): void {
                        $subQ->select(DB::raw(1))
                            ->from('purchaser_carts')
                            ->whereColumn('purchaser_carts.id', 'purchase_invoices.purchaser_cart_id')
                            ->where('purchaser_carts.user_id', $purchaserId);
                    });
            });
        }

        if ($supplierId !== null) {
            $clearedBatchesQuery->where('purchase_invoices.supplier_id', $supplierId);
            $clearedMatchesQuery->where('purchase_invoices.supplier_id', $supplierId);
        }

        $clearedBatches = $clearedBatchesQuery->select(
            DB::raw('COUNT(DISTINCT purchase_invoices.id) as bills_count'),
            DB::raw('COALESCE(SUM(stock_batches.total_kg), 0) as total_cleared_kg')
        )->first();

        $clearedMatches = $clearedMatchesQuery->select(
            DB::raw('COUNT(DISTINCT purchase_invoices.id) as bills_count'),
            DB::raw('COALESCE(SUM(advance_receive_matches.base_qty), 0) as total_cleared_kg')
        )->first();

        $clearedCount = (int) max($clearedBatches?->bills_count ?? 0, $clearedMatches?->bills_count ?? 0);
        $clearedQty = round((float) ($clearedBatches?->total_cleared_kg ?? 0) + (float) ($clearedMatches?->total_cleared_kg ?? 0), 2);

        // Count received from warehouse bills
        $receivedQuery = PurchaseInvoice::query()
            ->whereNull('cancelled_at')
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q): void {
                $q->whereNotNull('purchaser_cart_id')
                    ->orWhereNotNull('purchaser_submitted_by');
            })
            ->where(function ($sourceQ): void {
                $sourceQ->whereNull('purchase_source')
                    ->orWhere('purchase_source', '!=', 'shop');
            })
            ->whereNull('shop_id')
            ->whereDoesntHave('purchaserCart', fn ($cartQ) => $cartQ->where('purchase_source', 'shop'))
            ->whereDoesntHave('goodsReceived.purchaseOrder', fn ($poQ) => $poQ->where('fulfillment_type', 'shop'))
            ->whereNotIn('id', $allPendingIds)
            ->whereHas('goodsReceived', function ($grnQ) use ($whId): void {
                $grnQ->where('status', '!=', 'cancelled')
                    ->where('receipt_type', '!=', 'warehouse_advance')
                    ->when($whId !== null, fn ($wQ) => $wQ->where('warehouse_id', $whId))
                    ->whereHas('stockBatches');
            });

        if ($whId !== null) {
            $receivedQuery->whereHas('goodsReceived', fn ($q) => $q->where('warehouse_id', $whId));
        }

        if ($purchaserId !== null) {
            $receivedQuery->where(function ($q) use ($purchaserId): void {
                $q->where('purchaser_submitted_by', $purchaserId)
                    ->orWhereHas('purchaserCart', fn ($cQ) => $cQ->where('user_id', $purchaserId));
            });
        }

        if ($supplierId !== null) {
            $receivedQuery->where('supplier_id', $supplierId);
        }

        if ($range !== null) {
            $startDateStr = $range['start']->toDateString();
            $endDateStr = $range['end']->toDateString();
            $receivedQuery->where(function ($dateQ) use ($range, $startDateStr, $endDateStr): void {
                $dateQ->whereBetween('purchase_invoices.created_at', [$range['start'], $range['end']])
                    ->orWhereBetween('purchase_invoices.original_business_date', [$startDateStr, $endDateStr])
                    ->orWhereHas('purchaserCart', fn ($cQ) => $cQ->whereBetween('business_date', [$startDateStr, $endDateStr]))
                    ->orWhereHas('goodsReceived', function ($grnQ) use ($range): void {
                        $grnQ->whereBetween('received_at', [$range['start'], $range['end']])
                            ->orWhereHas('stockBatches', fn ($bQ) => $bQ->whereBetween('warehouse_confirmed_at', [$range['start'], $range['end']]));
                    });
            });
        }

        $receivedFromWarehouseCount = (int) max($receivedQuery->count(), $clearedCount);

        // Pre-check matchable bills among filtered pending bills
        $eligibleForAdvanceCount = 0;
        if (! empty($filteredPendingIds)) {
            $invoices = PurchaseInvoice::query()
                ->whereIn('id', $filteredPendingIds)
                ->with(['goodsReceived.items', 'purchaserCart.goodsReceived.items'])
                ->get();

            // Load open approved advance candidate product IDs & dates
            $openAdvanceRecords = GoodsReceived::query()
                ->where('receipt_type', 'warehouse_advance')
                ->where('status', 'approved')
                ->when($whId !== null, fn ($wQ) => $wQ->where('warehouse_id', $whId))
                ->with(['items'])
                ->get();

            $openAdvanceMap = [];
            foreach ($openAdvanceRecords as $adv) {
                $d = $adv->received_at instanceof Carbon ? $adv->received_at->format('Y-m-d') : ($adv->received_at ? Carbon::parse($adv->received_at)->format('Y-m-d') : null);
                if (! $d) {
                    continue;
                }
                foreach ($adv->items as $it) {
                    $openAdvanceMap[$d.'_'.$it->product_id] = true;
                }
            }

            foreach ($invoices as $inv) {
                $primaryGrn = $inv->goodsReceived ?: $inv->purchaserCart?->goodsReceived?->first();
                if (! $primaryGrn) {
                    continue;
                }
                $grnDate = $primaryGrn->received_at instanceof Carbon
                    ? $primaryGrn->received_at->format('Y-m-d')
                    : ($primaryGrn->received_at ? Carbon::parse($primaryGrn->received_at)->format('Y-m-d') : null);

                if (! $grnDate) {
                    continue;
                }

                $hasMatch = false;
                foreach ($primaryGrn->items as $item) {
                    if (isset($openAdvanceMap[$grnDate.'_'.$item->product_id])) {
                        $hasMatch = true;
                        break;
                    }
                }

                if ($hasMatch) {
                    $eligibleForAdvanceCount++;
                }
            }
        }

        $awaitingPhysicalReceiving = max(0, $totalPendingBills - $eligibleForAdvanceCount);

        return [
            'total_pending_bills' => $totalPendingBills,
            'total_outstanding_qty' => $totalOutstandingQty,
            'not_received_count' => $notReceivedCount,
            'partially_received_count' => $partiallyReceivedCount,
            'received_from_warehouse_count' => $receivedFromWarehouseCount,
            'awaiting_matching_count' => $eligibleForAdvanceCount,
            'bills_eligible_for_advance_matching' => $eligibleForAdvanceCount,
            'awaiting_physical_receiving' => $awaitingPhysicalReceiving,
            'cleared_today_count' => $clearedCount,
            'cleared_today_qty' => $clearedQty,
        ];
    }

    /**
     * Get paginated pending and/or cleared bills with filtering, sorting, and live action states.
     */
    public function getBillsPaginated(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $statusFilter = (string) ($filters['status'] ?? 'pending');
        $search = trim((string) ($filters['search'] ?? ''));
        $datePreset = (string) ($filters['date_preset'] ?? 'today');
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $purchaserId = ! empty($filters['purchaser_id']) ? (int) $filters['purchaser_id'] : null;
        $supplierId = ! empty($filters['supplier_id']) ? (int) $filters['supplier_id'] : null;
        $warehouseId = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $orderBy = (string) ($filters['order_by'] ?? 'created_at');
        $orderDir = strtolower((string) ($filters['order_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $range = ($datePreset !== 'all') ? $this->resolveDateRange($datePreset, $startDate, $endDate) : null;

        // Retrieve authoritative pending data snapshot
        $allPendingData = $this->pendingBillsService->getPendingBillsData();
        $allPendingBills = collect($allPendingData['bills']);
        $pendingBillsById = $allPendingBills->keyBy('id');
        $allPendingIds = $allPendingBills->pluck('id')->all();

        $filteredPendingCollection = $allPendingBills;
        if ($warehouseId !== null) {
            $filteredPendingCollection = $filteredPendingCollection->where('warehouse_id', $warehouseId);
        }
        if ($purchaserId !== null) {
            $filteredPendingCollection = $filteredPendingCollection->where('purchaser_id', $purchaserId);
        }
        if ($supplierId !== null) {
            $filteredPendingCollection = $filteredPendingCollection->where('supplier_id', $supplierId);
        }

        if ($range !== null) {
            $startDateStr = $range['start']->toDateString();
            $endDateStr = $range['end']->toDateString();
            $startTime = $range['start'];
            $endTime = $range['end'];

            $filteredPendingCollection = $filteredPendingCollection->filter(function ($b) use ($startDateStr, $endDateStr, $startTime, $endTime): bool {
                $bDate = $b['business_date'] ?? null;
                $rawDate = $b['raw_date'] ?? null;
                $cDate = $rawDate ? Carbon::parse($rawDate) : null;

                $matchesBusinessDate = $bDate && $bDate >= $startDateStr && $bDate <= $endDateStr;
                $matchesCreatedAt = $cDate && $cDate->betweenIncluded($startTime, $endTime);

                return $matchesBusinessDate || $matchesCreatedAt;
            });
        }

        $filteredPendingIds = $filteredPendingCollection->pluck('id')->all();

        $query = PurchaseInvoice::query()
            ->whereNull('cancelled_at')
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q): void {
                $q->whereNotNull('purchaser_cart_id')
                    ->orWhereNotNull('purchaser_submitted_by');
            })
            ->where(function ($sourceQ): void {
                $sourceQ->whereNull('purchase_source')
                    ->orWhere('purchase_source', '!=', 'shop');
            })
            ->whereNull('shop_id')
            ->whereDoesntHave('purchaserCart', fn ($cartQ) => $cartQ->where('purchase_source', 'shop'))
            ->whereDoesntHave('goodsReceived.purchaseOrder', fn ($poQ) => $poQ->where('fulfillment_type', 'shop'));

        // Status filter
        if ($statusFilter === 'pending') {
            if (empty($filteredPendingIds)) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('id', $filteredPendingIds);
            }
        } elseif ($statusFilter === 'not_received') {
            $notReceivedIds = $filteredPendingCollection->where('status', 'Not Received')->pluck('id')->all();
            if (empty($notReceivedIds)) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('id', $notReceivedIds);
            }
        } elseif ($statusFilter === 'partially_received') {
            $partiallyReceivedIds = $filteredPendingCollection->where('status', 'Partially Received')->pluck('id')->all();
            if (empty($partiallyReceivedIds)) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('id', $partiallyReceivedIds);
            }
        } elseif ($statusFilter === 'cleared' || $statusFilter === 'received') {
            $query->whereNotIn('id', $allPendingIds)
                ->whereHas('goodsReceived', function ($grnQ) use ($warehouseId): void {
                    $grnQ->where('status', '!=', 'cancelled')
                        ->where('receipt_type', '!=', 'warehouse_advance')
                        ->when($warehouseId !== null, fn ($wQ) => $wQ->where('warehouse_id', $warehouseId))
                        ->whereHas('stockBatches');
                });

            if ($range !== null) {
                $startDateStr = $range['start']->toDateString();
                $endDateStr = $range['end']->toDateString();
                $query->where(function ($dateQ) use ($range, $startDateStr, $endDateStr): void {
                    $dateQ->whereBetween('purchase_invoices.created_at', [$range['start'], $range['end']])
                        ->orWhereBetween('purchase_invoices.original_business_date', [$startDateStr, $endDateStr])
                        ->orWhereHas('purchaserCart', fn ($cQ) => $cQ->whereBetween('business_date', [$startDateStr, $endDateStr]))
                        ->orWhereHas('goodsReceived', function ($grnQ) use ($range): void {
                            $grnQ->whereBetween('received_at', [$range['start'], $range['end']])
                                ->orWhereHas('stockBatches', fn ($bQ) => $bQ->whereBetween('warehouse_confirmed_at', [$range['start'], $range['end']]));
                        });
                });
            }
        } elseif ($statusFilter === 'all') {
            if ($range !== null) {
                $startDateStr = $range['start']->toDateString();
                $endDateStr = $range['end']->toDateString();
                $query->where(function ($dateQ) use ($range, $startDateStr, $endDateStr): void {
                    $dateQ->whereBetween('purchase_invoices.created_at', [$range['start'], $range['end']])
                        ->orWhereBetween('purchase_invoices.original_business_date', [$startDateStr, $endDateStr])
                        ->orWhereHas('purchaserCart', fn ($cQ) => $cQ->whereBetween('business_date', [$startDateStr, $endDateStr]))
                        ->orWhereHas('goodsReceived', function ($grnQ) use ($range): void {
                            $grnQ->whereBetween('received_at', [$range['start'], $range['end']])
                                ->orWhereHas('stockBatches', fn ($bQ) => $bQ->whereBetween('warehouse_confirmed_at', [$range['start'], $range['end']]));
                        });
                });
            }
        }

        // Warehouse filter
        if ($warehouseId) {
            $query->whereHas('goodsReceived', fn ($q) => $q->where('warehouse_id', $warehouseId));
        }

        // Purchaser filter
        if ($purchaserId) {
            $query->where(function ($q) use ($purchaserId): void {
                $q->where('purchaser_submitted_by', $purchaserId)
                    ->orWhereHas('purchaserCart', fn ($cQ) => $cQ->where('user_id', $purchaserId));
            });
        }

        // Supplier filter
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        // Search keyword
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($sQ) => $sQ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('purchaserSubmittedBy', fn ($uQ) => $uQ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('purchaserCart.user', fn ($uQ) => $uQ->where('name', 'like', "%{$search}%"));
            });
        }

        // Ordering
        $allowedOrderColumns = ['id', 'created_at', 'invoice_number', 'amount'];
        if (in_array($orderBy, $allowedOrderColumns, true)) {
            $query->orderBy($orderBy, $orderDir);
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        // Transform records with live receipt, advance suggestions, and action states
        $pageInvoices = $paginator->getCollection();
        $pageInvoices->loadMissing([
            'supplier:id,name',
            'purchaserSubmittedBy:id,name',
            'purchaserCart.user:id,name',
            'purchaserCart.goodsReceived.warehouse',
            'purchaserCart.goodsReceived.items.product',
            'purchaserCart.goodsReceived.stockBatches',
            'purchaserCart.goodsReceived.advanceMatchesAsBill',
            'goodsReceived.warehouse',
            'goodsReceived.items.product',
            'goodsReceived.stockBatches',
            'goodsReceived.advanceMatchesAsBill',
        ]);

        $defaultWarehouse = Warehouse::query()->orderBy('id')->first();
        $fallbackWarehouseId = $defaultWarehouse?->id ?? 1;

        // Build a lightweight lookup of open warehouse advance products matching dates on this page
        $pageDates = [];
        foreach ($pageInvoices as $inv) {
            $primaryGrn = $inv->goodsReceived ?: $inv->purchaserCart?->goodsReceived?->first();
            if (! $primaryGrn) {
                continue;
            }
            $d = $primaryGrn->received_at instanceof Carbon
                ? $primaryGrn->received_at->format('Y-m-d')
                : ($primaryGrn->received_at ? Carbon::parse($primaryGrn->received_at)->format('Y-m-d') : null);
            if ($d) {
                $pageDates[$d] = true;
            }
        }

        $candidateAdvMap = [];
        $pageDatesList = array_keys($pageDates);
        if (! empty($pageDatesList)) {
            $matchingAdvances = GoodsReceived::query()
                ->where('receipt_type', 'warehouse_advance')
                ->where('status', 'approved')
                ->where(function ($q) use ($pageDatesList): void {
                    foreach ($pageDatesList as $d) {
                        $q->orWhereDate('received_at', $d);
                    }
                })
                ->with('items:id,goods_received_id,product_id')
                ->get();

            foreach ($matchingAdvances as $adv) {
                $adDate = $adv->received_at instanceof Carbon
                    ? $adv->received_at->format('Y-m-d')
                    : ($adv->received_at ? Carbon::parse($adv->received_at)->format('Y-m-d') : null);
                if (! $adDate) {
                    continue;
                }
                foreach ($adv->items as $advItem) {
                    $candidateAdvMap[$adDate.'_'.$advItem->product_id] = true;
                }
            }
        }

        $items = $pageInvoices->map(function (PurchaseInvoice $inv) use ($pendingBillsById, $fallbackWarehouseId, $candidateAdvMap): array {
            $primaryGrn = $inv->goodsReceived ?: $inv->purchaserCart?->goodsReceived?->first();

            $pendingSnapshot = $pendingBillsById->get($inv->id);

            $billQty = 0.0;
            $alreadyReceivedQty = 0.0;
            $advanceMatchedQty = 0.0;
            $outstandingQty = 0.0;

            if ($pendingSnapshot) {
                $billQty = (float) $pendingSnapshot['bill_qty'];
                $outstandingQty = (float) $pendingSnapshot['outstanding_qty'];
                $status = $pendingSnapshot['status'];

                if ($primaryGrn) {
                    $advanceMatchedQty = (float) $primaryGrn->advanceMatchesAsBill->sum(
                        fn ($m): float => (float) ($m->base_qty ?? $m->matched_qty ?? 0.0)
                    );
                    $alreadyReceivedQty = max(0.0, round($billQty - $outstandingQty - $advanceMatchedQty, 2));
                }
            } else {
                // Cleared bill calculation
                if ($primaryGrn) {
                    $billQty = (float) $primaryGrn->items->sum('received_qty');
                    $advanceMatchedQty = (float) $primaryGrn->advanceMatchesAsBill->sum(
                        fn ($m): float => (float) ($m->base_qty ?? $m->matched_qty ?? 0.0)
                    );
                    $alreadyReceivedQty = (float) $primaryGrn->stockBatches
                        ->where('warehouse_receive_pending', false)
                        ->sum('total_kg');
                    $outstandingQty = max(0.0, round($billQty - $advanceMatchedQty - $alreadyReceivedQty, 2));
                }
                $status = 'Cleared';
            }

            $warehouseId = $primaryGrn?->warehouse_id ?? $fallbackWarehouseId;
            $warehouseName = $primaryGrn?->warehouse?->name ?? $defaultWarehouse?->name ?? 'Main Warehouse';

            // Check advance match suggestions for this bill if pending and candidate advances exist
            $canMatchAdvance = false;
            $suggestedMatchQty = 0.0;
            $suggestions = null;

            if ($primaryGrn && $outstandingQty > 0.001) {
                $grnDate = $primaryGrn->received_at instanceof Carbon
                    ? $primaryGrn->received_at->format('Y-m-d')
                    : ($primaryGrn->received_at ? Carbon::parse($primaryGrn->received_at)->format('Y-m-d') : null);

                $mightHaveAdvance = false;
                if ($grnDate) {
                    foreach ($primaryGrn->items as $it) {
                        if (isset($candidateAdvMap[$grnDate.'_'.$it->product_id])) {
                            $mightHaveAdvance = true;
                            break;
                        }
                    }
                }

                if ($mightHaveAdvance) {
                    $suggestions = $this->reconService->getSuggestionsForGrn($primaryGrn, $warehouseId);
                    $suggestedMatchQty = round((float) ($suggestions['total_matched_base_qty'] ?? 0.0), 2);
                    $canMatchAdvance = ($suggestions['has_advance_match'] ?? false) || $suggestedMatchQty > 0.001;
                }
            }

            $purchaserName = $inv->purchaserCart?->user?->name
                ?? $inv->purchaserSubmittedBy?->name
                ?? 'Purchaser';

            return [
                'id' => (int) $inv->id,
                'invoice_number' => (string) ($inv->invoice_number ?: 'BILL-#'.$inv->id),
                'date' => $inv->created_at ? $inv->created_at->format('d-m-Y') : now()->format('d-m-Y'),
                'raw_date' => $inv->created_at ? $inv->created_at->toIso8601String() : '',
                'purchaser' => (string) $purchaserName,
                'supplier' => (string) ($inv->supplier?->name ?? 'Unknown'),
                'supplier_id' => $inv->supplier_id,
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $warehouseName,
                'bill_qty' => round($billQty, 2),
                'already_received_qty' => round($alreadyReceivedQty, 2),
                'advance_matched_qty' => round($advanceMatchedQty, 2),
                'outstanding_qty' => round($outstandingQty, 2),
                'status' => $status,
                'can_match_advance' => $canMatchAdvance,
                'suggested_match_qty' => $suggestedMatchQty,
                'can_receive_goods' => $outstandingQty > 0.001,
                'items_count' => $primaryGrn?->items?->count() ?? 0,
                'goods_received_id' => $primaryGrn?->id,
            ];
        });

        return new ConcreteLengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            [
                'path' => ConcreteLengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * Get detailed breakdown of a single purchase invoice for review or modals.
     *
     * @return array<string, mixed>
     */
    public function getBillDetails(PurchaseInvoice $invoice): array
    {
        $invoice->loadMissing([
            'supplier:id,name',
            'purchaserSubmittedBy:id,name',
            'purchaserCart.user:id,name',
            'goodsReceived.warehouse',
            'goodsReceived.items.product',
            'goodsReceived.stockBatches',
            'goodsReceived.advanceMatchesAsBill.advanceGoodsReceived',
        ]);

        $grn = $invoice->goodsReceived ?: $invoice->purchaserCart?->goodsReceived?->first();
        if (! $grn) {
            throw ValidationException::withMessages([
                'invoice' => 'No Goods Received Note linked to this purchase bill.',
            ]);
        }

        $defaultWarehouse = Warehouse::query()->orderBy('id')->first();
        $fallbackWarehouseId = $grn->warehouse_id ?? $defaultWarehouse?->id ?? 1;

        $suggestions = $this->reconService->getSuggestionsForGrn($grn, $fallbackWarehouseId);

        $items = $grn->items->map(function ($item): array {
            $batch = $item->goodsReceived?->stockBatches?->firstWhere('goods_received_item_id', $item->id)
                ?? $item->goodsReceived?->stockBatches?->firstWhere('product_id', $item->product_id);

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? 'Product #'.$item->product_id,
                'product_sku' => $item->product?->sku ?? '',
                'unit' => $item->received_unit ?: $item->product?->unit ?: 'kg',
                'received_qty' => (float) $item->received_qty,
                'cost_per_unit' => (float) ($item->cost_per_unit ?? 0.0),
                'total_price' => round((float) $item->received_qty * (float) ($item->cost_per_unit ?? 0.0), 2),
                'batch_pending' => (bool) ($batch?->warehouse_receive_pending ?? true),
                'batch_total_kg' => (float) ($batch?->total_kg ?? $item->received_qty),
                'grading_mode' => $batch?->grading_mode ?? 'sort_required',
                'grade' => $batch?->purchase_grade ?? $item->grade ?? 'U',
            ];
        })->values()->all();

        $advanceMatches = $grn->advanceMatchesAsBill->map(function ($m): array {
            return [
                'id' => $m->id,
                'advance_grn_number' => $m->advanceGoodsReceived?->grn_number ?? 'ADV-'.$m->advance_goods_received_id,
                'matched_qty' => (float) $m->matched_qty,
                'base_qty' => (float) $m->base_qty,
                'unit' => $m->matched_unit,
                'confirmed_at' => $m->confirmed_at ? Carbon::parse($m->confirmed_at)->format('d-m-Y H:i') : '',
            ];
        })->values()->all();

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number ?: 'BILL-#'.$invoice->id,
            'date' => $invoice->created_at ? $invoice->created_at->format('d-m-Y') : '',
            'supplier_name' => $invoice->supplier?->name ?? 'Unknown',
            'purchaser_name' => $invoice->purchaserCart?->user?->name ?? $invoice->purchaserSubmittedBy?->name ?? 'Purchaser',
            'warehouse_name' => $grn->warehouse?->name ?? $defaultWarehouse?->name ?? 'Main Warehouse',
            'warehouse_id' => $fallbackWarehouseId,
            'grn_id' => $grn->id,
            'grn_number' => $grn->grn_number,
            'grn_status' => $grn->status,
            'items' => $items,
            'advance_matches' => $advanceMatches,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Reconcile/match a bill against available warehouse advance receipts.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string}
    /**
     * Match a pending purchaser bill with warehouse advance receipts.
     * @return array{success: bool, message: string, matched_qty?: float}
     *
     * @throws ValidationException
     */
    public function matchAdvance(PurchaseInvoice $invoice, array $payload, int $userId): array
    {
        $grn = $this->ensureBillEligibleForWarehouseAction(
            $invoice,
            $invoice->goodsReceived ?: $invoice->purchaserCart?->goodsReceived?->first()
        );

        $defaultWarehouse = Warehouse::query()->orderBy('id')->first();
        $warehouseId = (int) ($payload['warehouse_id'] ?? $grn->warehouse_id ?? $defaultWarehouse?->id ?? 1);

        $advanceMatches = $payload['advance_matches'] ?? null;

        // If no explicit matches provided, auto-populate from suggestions
        if (empty($advanceMatches)) {
            $suggestions = $this->reconService->getSuggestionsForGrn($grn, $warehouseId);
            $advanceMatches = [];
            foreach ($suggestions['items'] ?? [] as $sItem) {
                foreach ($sItem['suggested_matches'] ?? [] as $m) {
                    $advanceMatches[] = [
                        'advance_goods_received_id' => $m['advance_goods_received_id'],
                        'advance_goods_received_item_id' => $m['advance_goods_received_item_id'],
                        'advance_stock_batch_id' => $m['advance_stock_batch_id'] ?? null,
                        'goods_received_item_id' => $m['goods_received_item_id'],
                        'product_id' => $m['product_id'],
                        'matched_qty' => $m['matched_qty'],
                        'matched_unit' => $m['unit'],
                        'base_qty' => $m['base_qty'],
                    ];
                }
            }
        }

        if (empty($advanceMatches)) {
            throw ValidationException::withMessages([
                'advance_matches' => 'No matching advance receipts found to allocate.',
            ]);
        }

        $items = $payload['items'] ?? [];

        $totalMatchedInThisOp = (float) collect($advanceMatches)->sum('base_qty');

        $this->reconService->reconcileExistingGrn(
            $grn,
            $items,
            $advanceMatches,
            $warehouseId,
            $userId
        );

        $this->clearCaches();

        return [
            'success' => true,
            'message' => "Bill {$invoice->invoice_number} successfully matched with advance receipt(s).",
            'matched_qty' => round($totalMatchedInThisOp, 2),
        ];
    }

    /**
     * Receive goods physically into warehouse inventory.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string}
     */
    public function receiveGoods(PurchaseInvoice $invoice, array $payload, int $userId): array
    {
        $grn = $this->ensureBillEligibleForWarehouseAction(
            $invoice,
            $invoice->goodsReceived ?: $invoice->purchaserCart?->goodsReceived?->first()
        );

        $defaultWarehouse = Warehouse::query()->orderBy('id')->first();
        $warehouseId = (int) ($payload['warehouse_id'] ?? $grn->warehouse_id ?? $defaultWarehouse?->id ?? 1);

        $this->approveAction->executeAndConfirmReceive($grn, $userId, $warehouseId);

        // Update purchaser cart timestamp if present
        $cart = $grn->purchaseOrder?->purchaserCart ?: $invoice->purchaserCart;
        if ($cart && ! $cart->goods_received_at) {
            $cart->update(['goods_received_at' => now()]);
        }

        $this->clearCaches();

        return [
            'success' => true,
            'message' => "Bill {$invoice->invoice_number} goods physically received into warehouse inventory.",
        ];
    }

    /**
     * Match advance and immediately receive any remaining balance into warehouse inventory.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string}
     */
    public function matchAndReceiveBalance(PurchaseInvoice $invoice, array $payload, int $userId): array
    {
        return DB::transaction(function () use ($invoice, $payload, $userId): array {
            $matchResult = $this->matchAdvance($invoice, $payload, $userId);

            $grn = $invoice->goodsReceived ?: $invoice->purchaserCart?->goodsReceived?->first();
            $hasPendingBatches = $grn && StockBatch::query()
                ->where('goods_received_id', $grn->id)
                ->where('warehouse_receive_pending', true)
                ->where('total_kg', '>', 0)
                ->exists();

            if ($hasPendingBatches) {
                $this->receiveGoods($invoice, $payload, $userId);
            }

            return [
                'success' => true,
                'message' => "Bill {$invoice->invoice_number} matched with advances and remainder received into warehouse inventory.",
            ];
        });
    }

    /**
     * Build preview for bulk advance matching.
     *
     * @param  array<int>  $invoiceIds
     * @return array<string, mixed>
     */
    public function buildBulkMatchPreview(array $invoiceIds, int|string|null $warehouseId, int $userId): array
    {
        $invoices = PurchaseInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->with(['goodsReceived.items.product', 'purchaserCart.goodsReceived.items.product', 'supplier'])
            ->get();

        $defaultWarehouse = Warehouse::query()->orderBy('id')->first();
        $targetWarehouseId = ! empty($warehouseId) ? (int) $warehouseId : ($defaultWarehouse?->id ?? 1);

        $matchableBills = [];
        $skippedBills = [];
        $totalMatchableQty = 0.0;
        $allocatedAdvanceQty = [];

        foreach ($invoices as $inv) {
            $grn = $inv->goodsReceived ?: $inv->purchaserCart?->goodsReceived?->first();
            if (! $grn) {
                $skippedBills[] = [
                    'id' => $inv->id,
                    'bill_number' => $inv->invoice_number ?: 'BILL-#'.$inv->id,
                    'reason' => 'No linked GRN',
                ];

                continue;
            }

            if ($grn->isDirectShopPurchase()) {
                $skippedBills[] = [
                    'id' => $inv->id,
                    'bill_number' => $inv->invoice_number ?: 'BILL-#'.$inv->id,
                    'reason' => 'Direct shop purchase (central warehouse receiving not applicable)',
                ];

                continue;
            }

            $hasBatches = StockBatch::query()->where('goods_received_id', $grn->id)->exists();
            $hasPendingBatches = StockBatch::query()
                ->where('goods_received_id', $grn->id)
                ->where('warehouse_receive_pending', true)
                ->exists();

            if ($grn->status === 'approved' && $hasBatches && ! $hasPendingBatches) {
                $skippedBills[] = [
                    'id' => $inv->id,
                    'bill_number' => $inv->invoice_number ?: 'BILL-#'.$inv->id,
                    'reason' => 'Already received or cleared',
                ];

                continue;
            }

            $suggestions = $this->reconService->getSuggestionsForGrn($grn, $targetWarehouseId);
            $hasMatch = ($suggestions['has_advance_match'] ?? false) || ($suggestions['total_matched_base_qty'] ?? 0.0) > 0.001;

            if ($hasMatch) {
                // Determine net available advance matching for this bill considering prior bills in this preview run
                $billClaimedBase = 0.0;
                $matchesCount = 0;

                foreach ($suggestions['items'] ?? [] as $sItem) {
                    foreach ($sItem['suggested_matches'] ?? [] as $m) {
                        $slotKey = $m['advance_goods_received_id'].'_'.($m['advance_goods_received_item_id'] ?? $m['product_id']);
                        $priorClaimed = $allocatedAdvanceQty[$slotKey] ?? 0.0;
                        $slotAvail = max(0.0, round((float) ($m['available_base_qty_before'] ?? $m['base_qty']) - $priorClaimed, 3));
                        $needed = (float) $m['base_qty'];
                        $claim = min($slotAvail, $needed);

                        if ($claim > 0.0001) {
                            $allocatedAdvanceQty[$slotKey] = round($priorClaimed + $claim, 3);
                            $billClaimedBase = round($billClaimedBase + $claim, 3);
                            $matchesCount++;
                        }
                    }
                }

                if ($billClaimedBase > 0.0001) {
                    $totalMatchableQty += $billClaimedBase;

                    $matchableBills[] = [
                        'id' => $inv->id,
                        'bill_number' => $inv->invoice_number ?: 'BILL-#'.$inv->id,
                        'supplier' => $inv->supplier?->name ?? 'Unknown',
                        'matched_qty' => round($billClaimedBase, 2),
                        'total_bill_qty' => round((float) ($suggestions['total_bill_base_qty'] ?? 0.0), 2),
                        'matches_count' => $matchesCount,
                    ];
                } else {
                    $skippedBills[] = [
                        'id' => $inv->id,
                        'bill_number' => $inv->invoice_number ?: 'BILL-#'.$inv->id,
                        'reason' => 'Advance receipts already allocated to preceding bill in selection',
                    ];
                }
            } else {
                $skippedBills[] = [
                    'id' => $inv->id,
                    'bill_number' => $inv->invoice_number ?: 'BILL-#'.$inv->id,
                    'reason' => 'No open advances available on bill date',
                ];
            }
        }

        return [
            'warehouse_id' => $targetWarehouseId,
            'matchable_bills' => $matchableBills,
            'skipped_bills' => $skippedBills,
            'total_matchable_qty' => round($totalMatchableQty, 2),
            'matchable_count' => count($matchableBills),
            'skipped_count' => count($skippedBills),
        ];
    }

    /**
     * Execute bulk advance matching on selected bills.
     *
     * @param  array<int>  $invoiceIds
     * @return array{processed_count: int, total_matched_qty: float}
     */
    public function executeBulkMatch(array $invoiceIds, int|string|null $warehouseId, int $userId): array
    {
        $whId = ! empty($warehouseId) ? (int) $warehouseId : null;
        $preview = $this->buildBulkMatchPreview($invoiceIds, $whId, $userId);
        $matchableIds = collect($preview['matchable_bills'])->pluck('id')->all();

        $processedCount = 0;
        $totalMatchedQty = 0.0;

        foreach ($matchableIds as $invId) {
            $inv = PurchaseInvoice::find($invId);
            if (! $inv) {
                continue;
            }

            try {
                $res = $this->matchAdvance($inv, ['warehouse_id' => $whId], $userId);
                $processedCount++;
                $totalMatchedQty += (float) ($res['matched_qty'] ?? 0.0);
            } catch (\Throwable) {
                // Ignore individual failure and proceed
            }
        }

        $this->clearCaches();

        return [
            'processed_count' => $processedCount,
            'total_matched_qty' => round($totalMatchedQty, 2),
        ];
    }

    /**
     * Execute bulk physical receiving on selected bills.
     *
     * @param  array<int>  $invoiceIds
     * @return array{received_count: int}
     */
    public function executeBulkReceive(array $invoiceIds, int|string|null $warehouseId, int $userId): array
    {
        $whId = ! empty($warehouseId) ? (int) $warehouseId : null;
        $invoices = PurchaseInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->with(['goodsReceived', 'purchaserCart.goodsReceived'])
            ->get();

        $receivedCount = 0;

        foreach ($invoices as $inv) {
            try {
                $this->receiveGoods($inv, ['warehouse_id' => $whId], $userId);
                $receivedCount++;
            } catch (\Throwable) {
                // Ignore individual failures
            }
        }

        $this->clearCaches();

        return [
            'received_count' => $receivedCount,
        ];
    }

    /**
     * Get filter options for dropdowns.
     *
     * @return array{
     *     purchasers: array<int, array{id: int, name: string}>,
     *     suppliers: array<int, array{id: int, name: string}>,
     *     warehouses: array<int, array{id: int, name: string}>
     * }
     */
    public function getFilterOptions(): array
    {
        $purchasers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'purchaser'))
            ->orWhereIn('id', PurchaseInvoice::query()->whereNotNull('purchaser_submitted_by')->select('purchaser_submitted_by'))
            ->orWhereIn('id', DB::table('purchaser_carts')->select('user_id'))
            ->orderBy('name')
            ->select('id', 'name')
            ->get()
            ->toArray();

        $suppliers = Supplier::query()
            ->orderBy('name')
            ->select('id', 'name')
            ->get()
            ->toArray();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->select('id', 'name')
            ->get()
            ->toArray();

        return [
            'purchasers' => $purchasers,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
        ];
    }

    /**
     * Clear caches to ensure immediate real-time synchronization.
     */
    public function clearCaches(): void
    {
        Cache::forget('purchaser_bills_not_received_summary');
        Cache::forget('pending_warehouse_bills_summary_metrics_all');

        $warehouses = Warehouse::pluck('id');
        foreach ($warehouses as $whId) {
            Cache::forget('pending_warehouse_bills_summary_metrics_'.$whId);
        }
    }

    /**
     * Resolve start and end timestamps for a preset.
     *
     * @return array{start: Carbon, end: Carbon}|null
     */
    public function resolveDateRange(string $preset, ?string $start, ?string $end): ?array
    {
        $tz = 'Asia/Kolkata';
        $today = Carbon::today($tz);

        return match ($preset) {
            'today' => [
                'start' => $today->copy()->startOfDay(),
                'end' => $today->copy()->endOfDay(),
            ],
            'yesterday' => [
                'start' => $today->copy()->subDay()->startOfDay(),
                'end' => $today->copy()->subDay()->endOfDay(),
            ],
            'this_week', 'week' => [
                'start' => $today->copy()->startOfWeek()->startOfDay(),
                'end' => $today->copy()->endOfWeek()->endOfDay(),
            ],
            'last_7_days' => [
                'start' => $today->copy()->subDays(6)->startOfDay(),
                'end' => $today->copy()->endOfDay(),
            ],
            'this_month', 'month' => [
                'start' => $today->copy()->startOfMonth()->startOfDay(),
                'end' => $today->copy()->endOfMonth()->endOfDay(),
            ],
            'prev_month', 'last_month' => [
                'start' => $today->copy()->subMonth()->startOfMonth()->startOfDay(),
                'end' => $today->copy()->subMonth()->endOfMonth()->endOfDay(),
            ],
            'custom' => ($start || $end) ? [
                'start' => Carbon::parse($start ?: $end, $tz)->startOfDay(),
                'end' => Carbon::parse($end ?: $start, $tz)->endOfDay(),
            ] : null,
            default => null,
        };
    }

    /**
     * Verify that the invoice and its GRN are genuinely pending warehouse action.
     *
     * @throws ValidationException
     */
    public function ensureBillEligibleForWarehouseAction(PurchaseInvoice $invoice, ?GoodsReceived $grn): GoodsReceived
    {
        if (! $grn) {
            throw ValidationException::withMessages([
                'invoice' => 'No Goods Received Note linked to this bill.',
            ]);
        }

        if ($grn->isDirectShopPurchase()) {
            throw ValidationException::withMessages([
                'invoice' => "Direct shop purchase {$invoice->invoice_number} cannot be received into central warehouse inventory.",
            ]);
        }

        if ($grn->status === 'cancelled' || $invoice->cancelled_at !== null || $invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'invoice' => "Bill {$invoice->invoice_number} has been cancelled.",
            ]);
        }

        if ($grn->receipt_type === 'warehouse_advance') {
            throw ValidationException::withMessages([
                'invoice' => "Warehouse advance receipt {$grn->grn_number} cannot be processed as a purchaser bill.",
            ]);
        }

        // A bill is already received/cleared if its GRN is approved and it has no batches awaiting warehouse receiving
        $hasBatches = StockBatch::query()->where('goods_received_id', $grn->id)->exists();
        $hasPendingBatches = StockBatch::query()
            ->where('goods_received_id', $grn->id)
            ->where('warehouse_receive_pending', true)
            ->exists();

        if ($grn->status === 'approved' && $hasBatches && ! $hasPendingBatches) {
            throw ValidationException::withMessages([
                'invoice' => "Bill {$invoice->invoice_number} has already been received or cleared.",
            ]);
        }

        return $grn;
    }
}
