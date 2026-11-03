<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\ProcurementExpense;
use App\Models\ProcurementExpenseSubCategory;
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
        $category = filled($filters['category'] ?? null) ? (string) $filters['category'] : (filled($filters['expense_type'] ?? null) ? (string) $filters['expense_type'] : null);
        $rawLocation = filled($filters['location'] ?? null) ? (string) $filters['location'] : (filled($filters['sub_category_id'] ?? null) ? (string) $filters['sub_category_id'] : null);
        $search = trim((string) ($filters['search'] ?? ''));

        // Server-side validation: if both category and numeric location provided, verify location belongs to category
        if ($category !== null && $rawLocation !== null && is_numeric($rawLocation)) {
            $validForCategory = ProcurementExpenseSubCategory::query()
                ->whereKey((int) $rawLocation)
                ->whereHas('category', fn ($q) => $q->where('key', $category))
                ->exists();

            if (! $validForCategory) {
                $rawLocation = null;
            }
        }

        // 1. Fetch Purchase Carts (excluded if category or location filter is active)
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

        $carts = ($category !== null || $rawLocation !== null)
            ? collect()
            : $cartsQuery->orderByDesc('business_date')->orderByDesc('id')->get();

        // 2. Fetch Procurement Expenses
        $expensesQuery = ProcurementExpense::query()
            ->with([
                'purchaser:id,name,public_uuid',
                'companyAccountingEntry:id,reference,payment_reference',
                'subCategory:id,procurement_expense_category_id,name,code,is_active',
                'categoryDefinition:id,key,name',
            ])
            ->whereDate('expense_date', '>=', $startDate->toDateString())
            ->whereDate('expense_date', '<=', $endDate->toDateString())
            ->when($purchaserId !== null, fn ($query) => $query->where('user_id', $purchaserId))
            ->when($category !== null, fn ($query) => $query->where('category', $category))
            ->when($rawLocation !== null, function ($query) use ($rawLocation): void {
                if ($rawLocation === 'unassigned') {
                    $query->where(function ($q): void {
                        $q->whereNull('procurement_expense_sub_category_id')
                            ->orWhere('procurement_expense_sub_category_id', 0);
                    });
                } elseif (is_numeric($rawLocation)) {
                    $query->where('procurement_expense_sub_category_id', (int) $rawLocation);
                }
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('note', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('sub_category', 'like', "%{$search}%")
                        ->orWhereHas('subCategory', fn ($sq) => $sq->where('name', 'like', "%{$search}%"))
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
                'category_key' => null,
                'category_name' => '-',
                'location_id' => null,
                'location_name' => '-',
                'display_category_location' => '-',
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

            $catKey = (string) $expense->category;
            $catLabel = $expense->categoryDefinition?->name ?? $expense->categoryLabel();
            $locName = $expense->subCategory?->name ?? $expense->sub_category ?? 'Unassigned';
            $locId = $expense->procurement_expense_sub_category_id;
            $categoryLocationDisplay = $catLabel.' • '.$locName;

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
                'details' => $expense->note ?: $catLabel,
                'expense_type' => $catLabel,
                'category_key' => $catKey,
                'category_name' => $catLabel,
                'location_id' => $locId,
                'location_name' => $locName,
                'display_category_location' => $categoryLocationDisplay,
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

        // 5. Category & Location Breakdown calculation
        $breakdownCategories = collect();
        $grandTotalExpenseAmount = round((float) $expenses->sum('amount'), 2);

        $expensesByCategory = $expenses->groupBy(fn (ProcurementExpense $e) => (string) $e->category);

        foreach ($expensesByCategory as $catKey => $catExpenses) {
            $firstExpense = $catExpenses->first();
            $categoryName = $firstExpense?->categoryDefinition?->name ?? $firstExpense?->categoryLabel() ?? ucfirst((string) $catKey);
            $catTotal = round((float) $catExpenses->sum('amount'), 2);
            $catEntriesCount = $catExpenses->count();
            $catPercentage = $grandTotalExpenseAmount > 0 ? round(($catTotal / $grandTotalExpenseAmount) * 100, 2) : 0.0;

            // Group by location
            $expensesByLocation = $catExpenses->groupBy(function (ProcurementExpense $e): string {
                return $e->procurement_expense_sub_category_id !== null
                    ? 'loc_'.(int) $e->procurement_expense_sub_category_id
                    : 'unassigned';
            });

            $locationRows = collect();

            foreach ($expensesByLocation as $locKey => $locExpenses) {
                $isUnassigned = ($locKey === 'unassigned');
                $firstLocExpense = $locExpenses->first();
                $locationId = $isUnassigned ? null : (int) $firstLocExpense->procurement_expense_sub_category_id;
                $locationName = $isUnassigned
                    ? 'Unassigned'
                    : ($firstLocExpense->subCategory?->name ?? $firstLocExpense->sub_category ?? 'Unassigned');

                $locTotal = round((float) $locExpenses->sum('amount'), 2);
                $locEntriesCount = $locExpenses->count();
                $locPercentage = $grandTotalExpenseAmount > 0 ? round(($locTotal / $grandTotalExpenseAmount) * 100, 2) : 0.0;

                $locationRows->push([
                    'category_key' => (string) $catKey,
                    'category_name' => $categoryName,
                    'location_id' => $locationId,
                    'location_filter_value' => $isUnassigned ? 'unassigned' : (string) $locationId,
                    'location_name' => $locationName,
                    'is_unassigned' => $isUnassigned,
                    'entries_count' => $locEntriesCount,
                    'total_amount' => $locTotal,
                    'percentage' => $locPercentage,
                ]);
            }

            $sortedLocationRows = $locationRows->sortByDesc('total_amount')->values();

            $breakdownCategories->push([
                'category_key' => (string) $catKey,
                'category_name' => $categoryName,
                'total_amount' => $catTotal,
                'entries_count' => $catEntriesCount,
                'percentage' => $catPercentage,
                'locations' => $sortedLocationRows,
            ]);
        }

        $sortedBreakdownCategories = $breakdownCategories->sortByDesc('total_amount')->values();

        $breakdown = [
            'categories' => $sortedBreakdownCategories,
            'grand_total_amount' => $grandTotalExpenseAmount,
            'grand_total_entries' => $expenses->count(),
            'grand_total_percentage' => $grandTotalExpenseAmount > 0 ? 100.0 : 0.0,
        ];

        // 6. Calculate summary metrics
        $totalPurchase = round((float) $purchaseRows->sum('purchase_amount'), 2);
        $totalExpenses = round((float) $expenseRows->sum('expense_amount'), 2);
        $combinedTotal = round($totalPurchase + $totalExpenses, 2);

        // Resolve entry_type filter: 'all', 'purchase', or 'expense'
        $rawEntryType = strtolower(trim((string) ($filters['entry_type'] ?? $filters['record_type'] ?? $filters['type'] ?? 'all')));
        $entryType = match ($rawEntryType) {
            'expense', 'expenses' => 'expense',
            'purchase', 'purchases' => 'purchase',
            default => 'all',
        };

        $summary = [
            'total_purchase' => $totalPurchase,
            'total_expenses' => $totalExpenses,
            'combined_total' => $combinedTotal,
            'bills_count' => $purchaseRows->count(),
            'expenses_count' => $expenseRows->count(),
            'total_entries' => $combinedRows->count(),
            'entry_type' => $entryType,
            'category' => $category,
            'location' => $rawLocation,
            'date_from' => $startDate->format('Y-m-d'),
            'date_to' => $endDate->format('Y-m-d'),
            'date_from_formatted' => $startDate->format('d M Y'),
            'date_to_formatted' => $endDate->format('d M Y'),
            'active_quick_filter' => $activeQuickFilter,
        ];

        // Filter rows if specific entry_type is requested
        $displayRows = match ($entryType) {
            'expense' => $expenseRows,
            'purchase' => $purchaseRows,
            default => $combinedRows,
        };

        // Resolve sort column & direction
        $sortBy = strtolower(trim((string) ($filters['sort_by'] ?? $filters['sort'] ?? 'date')));
        $rawSortDir = strtolower(trim((string) ($filters['sort_dir'] ?? $filters['direction'] ?? 'desc')));
        $sortDir = in_array($rawSortDir, ['asc', 'desc'], true) ? $rawSortDir : 'desc';

        $isNumeric = in_array($sortBy, ['purchase', 'purchase_amount', 'expense', 'expense_amount', 'total'], true);
        $sortKey = match ($sortBy) {
            'type' => 'type',
            'purchaser', 'purchaser_name' => 'purchaser_name',
            'supplier', 'supplier_name' => 'supplier_name',
            'reference' => 'reference',
            'details' => 'details',
            'purchase', 'purchase_amount' => 'purchase_amount',
            'expense', 'expense_amount' => 'expense_amount',
            'total' => 'total',
            default => 'raw_date',
        };

        if ($sortDir === 'asc') {
            $sortedRows = $displayRows->sortBy($sortKey, $isNumeric ? SORT_NUMERIC : SORT_NATURAL | SORT_FLAG_CASE)->values();
        } else {
            $sortedRows = $displayRows->sortByDesc($sortKey, $isNumeric ? SORT_NUMERIC : SORT_NATURAL | SORT_FLAG_CASE)->values();
        }

        $summary['sort_by'] = $sortBy;
        $summary['sort_dir'] = $sortDir;
        $summary['filtered_entries'] = $sortedRows->count();

        // 7. Paginate if perPage is specified
        if ($perPage !== null && $perPage > 0) {
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $pagedRows = $sortedRows->slice(($currentPage - 1) * $perPage, $perPage)->values();
            $paginator = new LengthAwarePaginator(
                $pagedRows,
                $sortedRows->count(),
                $perPage,
                $currentPage,
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()]
            );

            return [
                'summary' => $summary,
                'breakdown' => $breakdown,
                'paginator' => $paginator,
                'rows' => $pagedRows,
                'all_rows' => $sortedRows,
                'filters' => $filters,
            ];
        }

        return [
            'summary' => $summary,
            'breakdown' => $breakdown,
            'paginator' => null,
            'rows' => $sortedRows,
            'all_rows' => $sortedRows,
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
