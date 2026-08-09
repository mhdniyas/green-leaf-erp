<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Repositories\Inventory\StockMovementRepository;
use App\Support\ShopOwner\ActiveShopResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class DeliveryDashboardController extends Controller
{
    public function __construct(
        private readonly ActiveShopResolver $activeShopResolver,
        private readonly StockMovementRepository $stockMovements,
    ) {}

    /**
     * Display the daily delivery dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $dateInput = $request->input('date');
        $date = $dateInput ? Carbon::parse($dateInput)->format('Y-m-d') : Carbon::today()->format('Y-m-d');
        $selectedCategoryId = $request->integer('category_id') ?: null;
        $selectedShopId = $request->integer('shop_id') ?: null;
        $selectedStatus = (string) $request->input('status', 'all');
        $selectedInvoiceLock = (string) $request->input('invoice_lock', 'all');

        $user = $request->user();
        $isShop = $user->hasRole('shop');
        $shopId = $isShop ? $this->activeShopResolver->resolve($request)->id : $user->shop_id;

        // Fetch all shop orders for the selected business date
        $ordersQuery = ShopOrder::whereDate('business_date', $date)
            ->with([
                'shop',
                'items.product.category',
                'deliveredBy',
                'invoice.items.product.category',
                'invoice.items.orderItem',
            ]);

        if ($isShop && $shopId) {
            $ordersQuery->where('shop_id', $shopId);
        }

        if (! $isShop && $selectedShopId) {
            $ordersQuery->where('shop_id', $selectedShopId);
        }

        if ($selectedCategoryId) {
            $ordersQuery->whereHas('items.product', fn ($query) => $query->where('category_id', $selectedCategoryId));
        }

        if ($selectedStatus !== 'all') {
            match ($selectedStatus) {
                'loadout' => $ordersQuery->whereIn('delivery_status', ['pending_delivery', 'ready_for_dispatch']),
                'in_transit' => $ordersQuery->where('delivery_status', 'in_transit'),
                'review' => $ordersQuery->where('delivery_status', 'pending_approval'),
                'delivered' => $ordersQuery->where(function ($query): void {
                    $query->where('is_delivered', true)
                        ->orWhereIn('delivery_status', ['delivered', 'partially_delivered']);
                }),
                default => null,
            };
        }

        $orders = $ordersQuery->get();

        if ($selectedInvoiceLock === 'locked') {
            $orders = $orders
                ->filter(fn (ShopOrder $order): bool => $order->invoice?->isFinalLocked() ?? false)
                ->values();
        } elseif ($selectedInvoiceLock === 'open') {
            $orders = $orders
                ->filter(fn (ShopOrder $order): bool => ! ($order->invoice?->isFinalLocked() ?? false))
                ->values();
        }

        $warehouseApprovedCount = $orders
            ->filter(fn (ShopOrder $order): bool => $order->warehouseWorkflowStage() === 'approved')
            ->count();
        $packingCount = $orders
            ->filter(fn (ShopOrder $order): bool => $order->warehouseWorkflowStage() === 'packing')
            ->count();
        $inTransitCount = $orders
            ->filter(fn (ShopOrder $order): bool => $order->warehouseWorkflowStage() === 'in_transit')
            ->count();

        // Calculate summary metrics
        $totalOrdersCount = $orders->count();
        $allocationCompletedCount = $orders->where('is_allocation_completed', true)->count();
        $awaitingAllocationCount = $totalOrdersCount - $allocationCompletedCount;
        $deliveredCount = $orders->where('is_delivered', true)->count();
        $awaitingDeliveryCount = $totalOrdersCount - $deliveredCount;

        $totalShortageValue = (float) $orders->sum('total_shortage_value');
        $totalCashCollected = (float) $orders->sum('cash_collected');
        $totalCashDiscrepancy = (float) $orders->sum('cash_discrepancy');

        $receiveQueueCount = PurchaseOrder::query()
            ->whereIn('status', [POStatus::SentToSupplier, POStatus::PartiallyReceived])
            ->count();

        $pendingGrnApprovalCount = GoodsReceived::query()
            ->whereDate('received_at', $date)
            ->where('status', 'pending_approval')
            ->count();
        $stockByProduct = $this->stockMovements
            ->currentStockByProductAndGrade($date)
            ->groupBy('product_id');
        $negativeProductCount = $stockByProduct
            ->filter(fn ($rows) => (float) $rows->sum('current_stock') < -0.001)
            ->count();
        $belowBufferProductCount = $stockByProduct
            ->filter(function ($rows): bool {
                $totalStock = (float) $rows->sum('current_stock');
                $bufferQty = (float) ($rows->first()->buffer_qty ?? 0);

                return $bufferQty > 0 && $totalStock < $bufferQty;
            })
            ->count();
        $carryoverProductCount = $stockByProduct
            ->filter(fn ($rows): bool => (bool) ($rows->first()->carryover_enabled ?? false))
            ->count();

        $shopCards = $orders
            ->map(function (ShopOrder $order): array {
                $items = $order->items;
                $totalItems = $items->count();
                $packedItems = $items->whereIn('sorting_status', ['allocated', 'loaded'])->count();
                $inTransitItems = $items->where('sorting_status', 'loaded')->count();
                $deliveredItems = $items->filter(fn (ShopOrderItem $item): bool => $item->warehouseWorkflowStage() === 'delivered')->count();
                $invoice = $order->invoice;
                $invoiceLocked = $invoice?->isFinalLocked() ?? false;
                $remainingItems = $items
                    ->filter(fn (ShopOrderItem $item): bool => $item->sorting_status !== 'loaded')
                    ->count();
                $loadedItems = $items
                    ->filter(fn (ShopOrderItem $item): bool => $item->sorting_status === 'loaded' && (float) ($item->loaded_qty ?? 0) > 0)
                    ->count();
                $hasDuplicates = $items
                    ->groupBy('product_id')
                    ->contains(fn ($rows): bool => $rows->count() > 1);

                return [
                    'id' => $order->id,
                    'route_key' => $order->getRouteKey(),
                    'order_number' => $order->order_number,
                    'shop_name' => $order->shop?->name ?? 'Unknown Shop',
                    'status_label' => $order->warehouseWorkflowLabel(),
                    'status_tone' => $order->warehouseWorkflowTone(),
                    'total_items' => $totalItems,
                    'packed_items' => $packedItems,
                    'in_transit_items' => $inTransitItems,
                    'delivered_items' => $deliveredItems,
                    'loaded_items' => $loadedItems,
                    'remaining_items' => $remainingItems,
                    'progress_percentage' => $totalItems > 0 ? (int) round(($packedItems / $totalItems) * 100) : 0,
                    'details_url' => route('inventory.sorting.shop-orders', ['date' => $order->business_date->format('Y-m-d')]).'#shop-card-'.$order->id,
                    'loadout_url' => route('warehouse.loadout.show', $order),
                    'slip_url' => route('warehouse.loadout.slip', $order),
                    'invoice_url' => $invoice ? route('purchasing.shop-invoices.show', $invoice) : null,
                    'invoice_pdf_url' => $invoice ? route('purchasing.shop-invoices.pdf', $invoice) : null,
                    'invoice_number' => $invoice?->invoice_number,
                    'invoice_total' => (float) ($invoice?->final_total ?? 0),
                    'invoice_paid' => (float) ($invoice?->paid_amount ?? 0),
                    'invoice_balance' => (float) ($invoice?->balance_amount ?? 0),
                    'invoice_locked' => $invoiceLocked,
                    'invoice_status' => $invoice?->status ?? 'not_generated',
                    'invoice_delivery_status' => $invoice?->delivery_status ?? 'pending',
                    'delivery_status' => (string) ($order->delivery_status ?? 'pending_delivery'),
                    'delivery_review_status' => (string) ($order->delivery_review_status ?? 'not_started'),
                    'shortage_value' => (float) ($order->total_shortage_value ?? 0),
                    'excess_value' => (float) ($order->total_excess_value ?? 0),
                    'cash_collected' => (float) ($order->cash_collected ?? 0),
                    'cash_discrepancy' => (float) ($order->cash_discrepancy ?? 0),
                    'can_move_to_delivery' => ! $invoiceLocked && $order->delivery_status === 'ready_for_dispatch' && $loadedItems > 0 && $remainingItems === 0 && ! $hasDuplicates,
                    'can_move_to_partial_delivery' => ! $invoiceLocked && $order->delivery_status === 'ready_for_dispatch' && $loadedItems > 0 && $remainingItems > 0 && ! $hasDuplicates,
                    'can_reopen_loadout' => ! $invoiceLocked && $order->delivery_status !== 'delivered' && $order->delivery_status !== 'pending_delivery',
                    'can_merge_duplicates' => ! $invoiceLocked && $hasDuplicates && in_array((string) $order->delivery_status, ['pending_delivery', 'ready_for_dispatch'], true),
                    'can_remove_unpriced' => ! $invoiceLocked && $order->delivery_status !== 'delivered',
                    'can_lock_invoice' => ! $invoiceLocked && $invoice && (
                        $order->delivery_status === 'pending_approval'
                        || $order->delivery_review_status === 'approved'
                        || in_array((string) $order->delivery_status, ['delivered', 'partially_delivered'], true)
                    ),
                    'check_in_url' => $order->is_allocation_completed && ! $order->is_delivered
                        ? route('requisitions.delivery.show', $order->order_number)
                        : null,
                ];
            })
            ->sortBy([
                ['progress_percentage', 'asc'],
                ['shop_name', 'asc'],
            ])
            ->values();

        $billDetails = $orders
            ->flatMap(function (ShopOrder $order) use ($selectedCategoryId) {
                $invoice = $order->invoice;

                if ($invoice) {
                    return $invoice->items
                        ->filter(fn ($item): bool => ! $selectedCategoryId || (int) ($item->product?->category_id ?? 0) === $selectedCategoryId)
                        ->map(function ($item) use ($order, $invoice): array {
                            $product = $item->product;

                            return [
                                'shop_name' => $order->shop?->name ?? 'Unknown Shop',
                                'order_number' => $order->order_number,
                                'invoice_number' => $invoice->invoice_number,
                                'category_name' => $product?->category?->name ?? 'Uncategorized',
                                'product_code' => $product?->sku ?? 'NO-CODE',
                                'product_name' => $item->product_name ?: ($product?->name ?? 'Unknown Product'),
                                'unit' => $item->unit,
                                'approved_qty' => (float) $item->approved_qty,
                                'loaded_qty' => (float) ($item->orderItem?->loaded_qty ?? $item->approved_qty),
                                'delivered_qty' => (float) $item->delivered_qty,
                                'shortage_qty' => (float) $item->shortage_qty,
                                'excess_qty' => (float) $item->excess_qty,
                                'unit_price' => (float) $item->unit_price,
                                'line_total' => (float) $item->final_line_total,
                                'locked' => $invoice->isFinalLocked(),
                            ];
                        });
                }

                return $order->items
                    ->filter(fn (ShopOrderItem $item): bool => ! $selectedCategoryId || (int) ($item->product?->category_id ?? 0) === $selectedCategoryId)
                    ->map(function (ShopOrderItem $item) use ($order): array {
                        $product = $item->product;

                        return [
                            'shop_name' => $order->shop?->name ?? 'Unknown Shop',
                            'order_number' => $order->order_number,
                            'invoice_number' => null,
                            'category_name' => $product?->category?->name ?? 'Uncategorized',
                            'product_code' => $product?->sku ?? 'NO-CODE',
                            'product_name' => $product?->name ?? 'Unknown Product',
                            'unit' => $item->unit,
                            'approved_qty' => (float) ($item->approved_qty ?? $item->requested_qty),
                            'loaded_qty' => (float) ($item->loaded_qty ?? 0),
                            'delivered_qty' => (float) ($item->delivered_qty ?? 0),
                            'shortage_qty' => (float) ($item->shortage_qty ?? 0),
                            'excess_qty' => (float) ($item->excess_qty ?? 0),
                            'unit_price' => (float) ($item->locked_selling_price ?? 0),
                            'line_total' => (float) ($item->line_total ?? 0),
                            'locked' => false,
                        ];
                    });
            })
            ->sortBy([
                ['category_name', 'asc'],
                ['product_code', 'asc'],
                ['shop_name', 'asc'],
            ])
            ->values();

        $billDetailsByCategory = $billDetails->groupBy('category_name');

        $shops = $orders
            ->pluck('shop')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $categoryIds = $orders
            ->flatMap(fn (ShopOrder $order) => $order->items)
            ->pluck('product.category_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $categories = Category::query()
            ->where('is_active', true)
            ->when($categoryIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $categoryIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        // Itemized shortages: ShopOrderItems with shortage_qty > 0 on this date
        $shortageQuery = ShopOrderItem::whereHas('order', function ($query) use ($date, $isShop, $shopId): void {
            $query->whereDate('business_date', $date);
            if ($isShop && $shopId) {
                $query->where('shop_id', $shopId);
            }
        })
            ->where('shortage_qty', '>', 0.00)
            ->with(['order.shop', 'product']);

        $shortageItems = $shortageQuery->get();

        // Cash discrepancies: Delivered shop orders with non-zero cash discrepancy
        $discrepancyOrders = $orders->filter(function (ShopOrder $order): bool {
            return $order->is_delivered && abs((float) $order->cash_discrepancy) > 0.01;
        });

        $latestOrderUpdateAt = $orders->max('updated_at');
        $latestItemUpdateAt = $orders
            ->flatMap(fn (ShopOrder $order) => $order->items)
            ->max('updated_at');

        $lastUpdatedAt = collect([$latestOrderUpdateAt, $latestItemUpdateAt])
            ->filter()
            ->sortDesc()
            ->first();

        return response()
            ->view('deliveries.dashboard', compact(
                'date',
                'orders',
                'totalOrdersCount',
                'allocationCompletedCount',
                'awaitingAllocationCount',
                'deliveredCount',
                'awaitingDeliveryCount',
                'warehouseApprovedCount',
                'packingCount',
                'inTransitCount',
                'receiveQueueCount',
                'pendingGrnApprovalCount',
                'negativeProductCount',
                'belowBufferProductCount',
                'carryoverProductCount',
                'shopCards',
                'totalShortageValue',
                'totalCashCollected',
                'totalCashDiscrepancy',
                'shortageItems',
                'discrepancyOrders',
                'lastUpdatedAt',
                'categories',
                'shops',
                'billDetailsByCategory',
                'selectedCategoryId',
                'selectedShopId',
                'selectedStatus',
                'selectedInvoiceLock'
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
    }
}
