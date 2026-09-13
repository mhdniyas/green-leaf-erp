<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DailyPendingAdvanceWhatsAppService
{
    public function __construct(
        private readonly WarehouseReceiptReadScope $readScope,
        private readonly AdvanceAvailableBalanceCalculator $balanceCalculator,
    ) {}

    /**
     * Retrieve daily unmatched advances and classify them for WhatsApp sharing.
     *
     * @param  array<int, int>|null  $authorizedWarehouseIds
     * @return array{
     *     date: string,
     *     warehouse: Warehouse|null,
     *     warehouse_name: string,
     *     standard_items: array<int, array{
     *         product_name: string,
     *         remaining_qty: float,
     *         unit: string,
     *         grn_number: string,
     *         supplier_name: string|null,
     *         is_partial: bool
     *     }>,
     *     unit_issues: array<int, array{
     *         product_name: string,
     *         advance_qty: float,
     *         advance_unit: string,
     *         bill_qty: float,
     *         bill_unit: string,
     *         reason: string,
     *         grn_number: string,
     *         supplier_name: string|null
     *     }>,
     *     total_pending_count: int
     * }
     */
    public function getDailyPendingAdvances(string $date, ?int $warehouseId = null, ?array $authorizedWarehouseIds = null): array
    {
        $targetDate = Carbon::parse($date)->toDateString();
        $targetWarehouseIds = $warehouseId !== null ? [$warehouseId] : $authorizedWarehouseIds;

        $warehouse = null;
        if ($warehouseId !== null) {
            $warehouse = Warehouse::find($warehouseId);
        } elseif ($authorizedWarehouseIds !== null && count($authorizedWarehouseIds) === 1) {
            $warehouse = Warehouse::find($authorizedWarehouseIds[0]);
        }

        // 1. Load confirmed open advances for the exact business date and warehouse
        $openAdvancesQuery = GoodsReceived::query()
            ->where(function (Builder $typeQuery): void {
                $typeQuery->where('goods_received.receipt_type', 'warehouse_advance')
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('goods_received.receipt_type')
                            ->whereNull('goods_received.purchase_order_id');
                    });
            })
            ->where('goods_received.status', '!=', 'cancelled')
            ->where('goods_received.bill_status', 'bill_pending')
            ->where(function (Builder $dateQuery) use ($targetDate): void {
                $dateQuery->whereDate('goods_received.received_at', $targetDate)
                    ->orWhere(function (Builder $fallbackDate) use ($targetDate): void {
                        $fallbackDate->whereNull('goods_received.received_at')
                            ->whereDate('goods_received.created_at', $targetDate);
                    });
            });

        $this->readScope->receipts($openAdvancesQuery, $targetWarehouseIds);

        $openAdvances = $openAdvancesQuery
            ->with([
                'items.product.orderUnits',
                'warehouse:id,name,code',
                'purchaseOrder.supplier:id,name',
            ])
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        if ($openAdvances->isEmpty()) {
            return [
                'date' => $targetDate,
                'warehouse' => $warehouse,
                'warehouse_name' => $warehouse?->name ?? 'All Warehouses',
                'standard_items' => [],
                'unit_issues' => [],
                'total_pending_count' => 0,
            ];
        }

        // 2. Preload existing matches for these advances
        $advanceIds = $openAdvances->pluck('id')->all();
        $existingMatches = AdvanceReceiveMatch::query()
            ->whereIn('advance_goods_received_id', $advanceIds)
            ->get();

        // 3. Preload same-day purchase orders for the same date and warehouse to detect unit issues
        $sameDayOrdersQuery = PurchaseOrder::query()
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->whereDate('order_date', $targetDate);

        $this->readScope->orders($sameDayOrdersQuery, $targetWarehouseIds);

        $sameDayOrders = $sameDayOrdersQuery
            ->with(['items.product.orderUnits'])
            ->get();

        $sameDayOrderItemsByProduct = $sameDayOrders
            ->flatMap(fn (PurchaseOrder $po) => $po->items)
            ->groupBy('product_id');

        $standardItems = [];
        $unitIssues = [];

        foreach ($openAdvances as $adv) {
            $itemBalances = $this->balanceCalculator->calculateItemAvailableBase($adv, null, $existingMatches);
            $supplierName = $adv->purchaseOrder?->supplier?->name;

            foreach ($adv->items as $item) {
                $remBase = (float) ($itemBalances[$item->id] ?? 0.0);
                if ($remBase <= 0.0001) {
                    continue; // Fully matched or cleared
                }

                /** @var Product|null $product */
                $product = $item->product;
                $advUnit = $item->received_unit ?: ($product?->unit ?? 'KG');
                $advConv = $this->balanceCalculator->resolveStrictUnitConversion($product, $advUnit) ?? 1.0;
                $remQty = $advConv > 0 ? round($remBase / $advConv, 3) : $remBase;

                $receivedQty = (float) $item->received_qty;
                $alreadyMatchedQty = max(0.0, round($receivedQty - $remQty, 3));
                $isPartial = $alreadyMatchedQty > 0.0001;

                // Check for unit issues with same-day purchase order items
                $productPoItems = $sameDayOrderItemsByProduct->get($item->product_id, collect());
                $unitIssuePoItem = null;

                foreach ($productPoItems as $poItem) {
                    $billUnit = $poItem->purchase_unit ?: ($poItem->unit ?? ($product?->unit ?? 'KG'));
                    $billConv = $this->balanceCalculator->resolveStrictUnitConversion($product, $billUnit);
                    if ($billConv === null) {
                        $unitIssuePoItem = $poItem;
                        break;
                    }
                }

                if ($unitIssuePoItem !== null) {
                    $billUnit = $unitIssuePoItem->purchase_unit ?: ($unitIssuePoItem->unit ?? ($product?->unit ?? 'KG'));
                    $unitIssues[] = [
                        'product_name' => $product?->name ?? "Product #{$item->product_id}",
                        'advance_qty' => $remQty,
                        'advance_unit' => $advUnit,
                        'bill_qty' => (float) $unitIssuePoItem->quantity,
                        'bill_unit' => $billUnit,
                        'reason' => 'Unit conversion missing',
                        'grn_number' => $adv->grn_number,
                        'supplier_name' => $supplierName,
                    ];
                } else {
                    $standardItems[] = [
                        'product_name' => $product?->name ?? "Product #{$item->product_id}",
                        'advance_qty' => $receivedQty,
                        'matched_qty' => $alreadyMatchedQty,
                        'remaining_qty' => $remQty,
                        'unit' => $advUnit,
                        'grn_number' => $adv->grn_number,
                        'supplier_name' => $supplierName,
                        'is_partial' => $isPartial,
                    ];
                }
            }
        }

        $totalCount = count($standardItems) + count($unitIssues);

        return [
            'date' => $targetDate,
            'warehouse' => $warehouse,
            'warehouse_name' => $warehouse?->name ?? ($openAdvances->first()?->warehouse?->name ?? 'All Warehouses'),
            'standard_items' => $standardItems,
            'unit_issues' => $unitIssues,
            'total_pending_count' => $totalCount,
        ];
    }

    /**
     * Build the human-readable WhatsApp message string.
     *
     * @param  array{
     *     date: string,
     *     warehouse: Warehouse|null,
     *     warehouse_name: string,
     *     standard_items: array<int, array{
     *         product_name: string,
     *         remaining_qty: float,
     *         unit: string,
     *         grn_number: string,
     *         supplier_name: string|null,
     *         is_partial: bool
     *     }>,
     *     unit_issues: array<int, array{
     *         product_name: string,
     *         advance_qty: float,
     *         advance_unit: string,
     *         bill_qty: float,
     *         bill_unit: string,
     *         reason: string,
     *         grn_number: string,
     *         supplier_name: string|null
     *     }>,
     *     total_pending_count: int
     * }  $data
     */
    public function buildWhatsAppMessage(array $data): string
    {
        if ($data['total_pending_count'] === 0) {
            return '';
        }

        $formattedDate = Carbon::parse($data['date'])->format('d M Y');
        $lines = [
            'GREEN LEAF',
            'Advance Bills Pending',
            $formattedDate,
            $data['warehouse_name'],
            '',
        ];

        // 1. Standard Pending Advances (No bill & Partial remaining)
        $idx = 1;
        foreach ($data['standard_items'] as $item) {
            $advanceQty = $this->formatQuantity((float) ($item['advance_qty'] ?? $item['remaining_qty']));
            $matchedQty = $this->formatQuantity((float) ($item['matched_qty'] ?? 0));
            $formattedQty = $this->formatQuantity($item['remaining_qty']);
            $lines[] = "{$idx}. {$item['product_name']} — {$formattedQty} {$item['unit']}";
            $lines[] = "   Advance: {$advanceQty} {$item['unit']} | Matched: {$matchedQty} {$item['unit']} | Remaining: {$formattedQty} {$item['unit']}";
            $lines[] = "   GRN: {$item['grn_number']}";
            if (! empty($item['supplier_name'])) {
                $lines[] = "   Supplier: {$item['supplier_name']}";
            }
            $lines[] = '';
            $idx++;
        }

        // 2. Unit Issues
        if (! empty($data['unit_issues'])) {
            $lines[] = 'UNIT ISSUE';
            $lines[] = '';
            foreach ($data['unit_issues'] as $issue) {
                $advQtyFormatted = $this->formatQuantity($issue['advance_qty']);
                $billQtyFormatted = $this->formatQuantity($issue['bill_qty']);

                $lines[] = $issue['product_name'];
                $lines[] = "Advance: {$advQtyFormatted} {$issue['advance_unit']}";
                $lines[] = "Bill: {$billQtyFormatted} {$issue['bill_unit']}";
                $lines[] = "Reason: {$issue['reason']}";
                $lines[] = "GRN: {$issue['grn_number']}";
                if (! empty($issue['supplier_name'])) {
                    $lines[] = "Supplier: {$issue['supplier_name']}";
                }
                $lines[] = '';
            }
        }

        $lines[] = "Total Pending Advance Lines: {$data['total_pending_count']}";
        $lines[] = '';
        $lines[] = 'Please create / provide the pending purchaser bills for these Advance Receives.';

        return implode("\n", $lines);
    }

    /**
     * Generate the complete WhatsApp share URL or return null if empty.
     *
     * @param  array<int, int>|null  $authorizedWarehouseIds
     */
    public function generateShareUrl(string $date, ?int $warehouseId = null, ?array $authorizedWarehouseIds = null): ?string
    {
        $data = $this->getDailyPendingAdvances($date, $warehouseId, $authorizedWarehouseIds);
        if ($data['total_pending_count'] === 0) {
            return null;
        }

        $message = $this->buildWhatsAppMessage($data);

        return 'https://api.whatsapp.com/send?text='.rawurlencode($message);
    }

    /**
     * Format decimal quantity without redundant trailing zeros.
     */
    private function formatQuantity(float $qty): string
    {
        if ((float) ((int) $qty) === $qty) {
            return (string) (int) $qty;
        }

        return rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
    }
}
