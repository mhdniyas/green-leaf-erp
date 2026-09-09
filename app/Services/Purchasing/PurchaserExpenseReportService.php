<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\ProcurementExpense;
use App\Models\PurchaserCart;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PurchaserExpenseReportService
{
    /**
     * Resolve date range from raw filter input array.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public function resolveDateRange(array $filters): array
    {
        $quickFilter = (string) ($filters['quick_filter'] ?? $filters['chip'] ?? '');
        $month = (string) ($filters['month'] ?? '');
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');

        $today = now('Asia/Kolkata')->startOfDay();

        if ($quickFilter === 'today') {
            return [$today->copy(), $today->copy()->endOfDay(), 'today'];
        }

        if ($quickFilter === 'yesterday') {
            $yesterday = $today->copy()->subDay();

            return [$yesterday->copy(), $yesterday->copy()->endOfDay(), 'yesterday'];
        }

        if ($quickFilter === 'this_month') {
            $start = $today->copy()->startOfMonth();
            $end = $today->copy()->endOfMonth()->endOfDay();

            return [$start, $end, 'this_month'];
        }

        if ($quickFilter === 'last_month') {
            $lastMonth = $today->copy()->subMonth();
            $start = $lastMonth->copy()->startOfMonth();
            $end = $lastMonth->copy()->endOfMonth()->endOfDay();

            return [$start, $end, 'last_month'];
        }

        if (filled($dateFrom) && filled($dateTo)) {
            $start = Carbon::parse($dateFrom, 'Asia/Kolkata')->startOfDay();
            $end = Carbon::parse($dateTo, 'Asia/Kolkata')->endOfDay();

            return [$start, $end, 'custom'];
        }

        if (filled($dateFrom)) {
            $start = Carbon::parse($dateFrom, 'Asia/Kolkata')->startOfDay();
            $end = $start->copy()->endOfDay();

            return [$start, $end, 'custom'];
        }

        if (filled($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $parsedMonth = Carbon::createFromFormat('Y-m', $month, 'Asia/Kolkata');
            if ($parsedMonth !== false) {
                return [$parsedMonth->copy()->startOfMonth(), $parsedMonth->copy()->endOfMonth()->endOfDay(), 'month'];
            }
        }

        // Default: current month
        $start = $today->copy()->startOfMonth();
        $end = $today->copy()->endOfMonth()->endOfDay();

        return [$start, $end, 'this_month'];
    }

    /**
     * Build unified report payload containing items collection (or paginated items) and summary totals.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getReportData(array $filters, ?int $perPage = 25): array
    {
        [$startDate, $endDate, $activeQuickFilter] = $this->resolveDateRange($filters);

        $purchaserId = $this->resolvePurchaserId($filters['purchaser'] ?? $filters['purchaser_id'] ?? null);
        $supplierId = filled($filters['supplier_id'] ?? null) ? (int) $filters['supplier_id'] : null;
        $expenseType = filled($filters['expense_type'] ?? null) ? (string) $filters['expense_type'] : null;
        $search = trim((string) ($filters['search'] ?? ''));

        // 1. Fetch Purchase Carts
        $cartsQuery = PurchaserCart::query()
            ->with([
                'user:id,name,public_uuid',
                'supplier:id,name,public_uuid',
                'items.product:id,name,sku',
                'purchaseOrder:id,po_number',
                'goodsReceived:id,grn_number',
                'purchaseInvoice:id,invoice_number,amount,discount_amount,paid_amount',
            ])
            ->whereDate('business_date', '>=', $startDate->toDateString())
            ->whereDate('business_date', '<=', $endDate->toDateString())
            ->when($purchaserId !== null, fn ($query) => $query->where('user_id', $purchaserId))
            ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('cart_number', 'like', "%{$search}%")
                        ->orWhere('bill_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('items.product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                        ->orWhereHas('purchaseInvoice', fn ($iq) => $iq->where('invoice_number', 'like', "%{$search}%"));
                });
            });

        // If expense_type filter is specifically active, purchase carts are excluded
        $carts = $expenseType !== null ? collect() : $cartsQuery->orderByDesc('business_date')->orderByDesc('id')->get();

        // 2. Fetch Procurement Expenses
        $expensesQuery = ProcurementExpense::query()
            ->with([
                'purchaser:id,name,public_uuid',
                'companyAccountingEntry:id,reference,payment_reference',
            ])
            ->whereDate('expense_date', '>=', $startDate->toDateString())
            ->whereDate('expense_date', '<=', $endDate->toDateString())
            ->when($purchaserId !== null, fn ($query) => $query->where('user_id', $purchaserId))
            ->when($expenseType !== null, fn ($query) => $query->where('category', $expenseType))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('note', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('purchaser', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('companyAccountingEntry', fn ($eq) => $eq->where('reference', 'like', "%{$search}%")->orWhere('payment_reference', 'like', "%{$search}%"));
                });
            });

        // If supplier_id filter is specifically active, procurement expenses (which don't have supplier_id) are excluded
        $expenses = $supplierId !== null ? collect() : $expensesQuery->orderByDesc('expense_date')->orderByDesc('id')->get();

        // 3. Map to unified entry objects
        $purchaseRows = $carts->map(function (PurchaserCart $cart): array {
            $purchaseAmount = $cart->purchaseInvoice
                ? max(0.0, (float) $cart->purchaseInvoice->amount - (float) $cart->purchaseInvoice->discount_amount)
                : max(0.0, (float) $cart->items->sum('line_total') - (float) $cart->discount_amount);

            $productsSummary = $cart->items->map(function ($item): string {
                $productName = $item->product?->name ?? 'Unknown Item';

                return "{$productName} (".(float) $item->quantity.' '.($item->unit ?? 'kg').')';
            })->filter()->implode(', ');

            $reference = $cart->purchaseInvoice?->invoice_number
                ?? $cart->bill_number
                ?? $cart->purchaseOrder?->po_number
                ?? $cart->cart_number;

            return [
                'id' => 'cart-'.$cart->id,
                'type' => 'purchase',
                'type_label' => 'Purchase',
                'date' => $cart->business_date?->format('Y-m-d') ?? '',
                'date_formatted' => $cart->business_date?->format('d M Y') ?? '',
                'purchaser_id' => $cart->user_id,
                'purchaser_name' => $cart->user?->name ?? 'N/A',
                'purchaser_uuid' => $cart->user?->public_uuid ?? '',
                'supplier_id' => $cart->supplier_id,
                'supplier_name' => $cart->supplier?->name ?? 'N/A',
                'reference' => $reference,
                'details' => $productsSummary ?: 'Direct Purchase',
                'expense_type' => '-',
                'purchase_amount' => round($purchaseAmount, 2),
                'expense_amount' => 0.0,
                'total' => round($purchaseAmount, 2),
                'raw_date' => $cart->business_date?->format('Y-m-d H:i:s') ?? '',
                'updated_at' => $cart->updated_at?->toIso8601String() ?? '',
            ];
        });

        $expenseRows = $expenses->map(function (ProcurementExpense $expense): array {
            $expenseAmount = (float) $expense->amount;

            $reference = $expense->companyAccountingEntry?->reference
                ?? $expense->companyAccountingEntry?->payment_reference
                ?? ('EXP-'.$expense->id);

            return [
                'id' => 'expense-'.$expense->id,
                'type' => 'expense',
                'type_label' => 'Expense',
                'date' => $expense->expense_date?->format('Y-m-d') ?? '',
                'date_formatted' => $expense->expense_date?->format('d M Y') ?? '',
                'purchaser_id' => $expense->user_id,
                'purchaser_name' => $expense->purchaser?->name ?? 'N/A',
                'purchaser_uuid' => $expense->purchaser?->public_uuid ?? '',
                'supplier_id' => null,
                'supplier_name' => '-',
                'reference' => $reference,
                'details' => $expense->note ?: $expense->categoryLabel(),
                'expense_type' => $expense->categoryLabel(),
                'purchase_amount' => 0.0,
                'expense_amount' => round($expenseAmount, 2),
                'total' => round($expenseAmount, 2),
                'raw_date' => $expense->expense_date?->format('Y-m-d H:i:s') ?? '',
                'updated_at' => $expense->updated_at?->toIso8601String() ?? '',
            ];
        });

        // 4. Merge & Sort entries by date desc
        $combinedRows = $purchaseRows->concat($expenseRows)
            ->sortByDesc('raw_date')
            ->values();

        // 5. Calculate summary metrics
        $totalPurchase = round((float) $purchaseRows->sum('purchase_amount'), 2);
        $totalExpenses = round((float) $expenseRows->sum('expense_amount'), 2);
        $combinedTotal = round($totalPurchase + $totalExpenses, 2);

        $summary = [
            'total_purchase' => $totalPurchase,
            'total_expenses' => $totalExpenses,
            'combined_total' => $combinedTotal,
            'bills_count' => $purchaseRows->count(),
            'expenses_count' => $expenseRows->count(),
            'total_entries' => $combinedRows->count(),
            'date_from' => $startDate->format('Y-m-d'),
            'date_to' => $endDate->format('Y-m-d'),
            'date_from_formatted' => $startDate->format('d M Y'),
            'date_to_formatted' => $endDate->format('d M Y'),
            'active_quick_filter' => $activeQuickFilter,
        ];

        // 6. Paginate if perPage is specified
        if ($perPage !== null && $perPage > 0) {
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $pagedRows = $combinedRows->slice(($currentPage - 1) * $perPage, $perPage)->values();
            $paginator = new LengthAwarePaginator(
                $pagedRows,
                $combinedRows->count(),
                $perPage,
                $currentPage,
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()]
            );

            return [
                'summary' => $summary,
                'paginator' => $paginator,
                'rows' => $pagedRows,
                'all_rows' => $combinedRows,
                'filters' => $filters,
            ];
        }

        return [
            'summary' => $summary,
            'paginator' => null,
            'rows' => $combinedRows,
            'all_rows' => $combinedRows,
            'filters' => $filters,
        ];
    }

    /**
     * Resolve purchaser ID from integer, string ID, or User public_uuid.
     */
    private function resolvePurchaserId(mixed $value): ?int
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $user = User::where('public_uuid', $value)->first();

            return $user ? (int) $user->id : null;
        }

        return null;
    }
}
