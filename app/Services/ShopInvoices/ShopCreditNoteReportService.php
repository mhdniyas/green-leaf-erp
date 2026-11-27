<?php

declare(strict_types=1);

namespace App\Services\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Shop;
use App\Models\ShopCreditApplication;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteItem;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared, read-only reporting service for Shop Credit Notes.
 *
 * Reuses the authoritative ShopCreditNote status/direction rules and
 * ShopFinalBillService for bill reconciliation so on-screen views, PDFs,
 * Excel workbooks, and CSV exports reconcile 100% identically.
 */
class ShopCreditNoteReportService
{
    public function __construct(
        private readonly ShopFinalBillService $finalBillService,
    ) {}

    /**
     * Resolve authorized shop IDs for the given user.
     * Returns null when the user is authorized for all shops.
     *
     * @return array<int, int>|null
     */
    public function resolveAuthorizedShopIds(?User $user): ?array
    {
        if (! $user instanceof User) {
            return [];
        }

        if (
            $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->hasRole('super_admin')
        ) {
            return null;
        }

        $ids = [];
        if ($user->shop_id) {
            $ids[] = (int) $user->shop_id;
        }

        $assigned = $user->ownedShopAssignments()
            ->pluck('shop_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $scopedIds = array_values(array_unique(array_merge($ids, $assigned)));

        if ($user->hasRole('accounts')) {
            return $scopedIds !== [] ? $scopedIds : null;
        }

        // Non-admin/non-accounts roles (shop, manager, custom) are strictly scoped
        // to their assigned shops; users with no assigned shop receive [] (no access).
        return $scopedIds;
    }

    /**
     * Resolve and validate report filters using the configured business timezone.
     *
     * @param  array<string, mixed>  $input
     * @return array{
     *     timeframe: string,
     *     from_date: string,
     *     to_date: string,
     *     shop_id: int|null,
     *     shop_code: string|null,
     *     shop_name: string,
     *     report_type: string,
     *     authorized_shop_ids: array<int, int>|null,
     *     period_label: string
     * }
     */
    public function resolveFilters(array $input, ?User $user = null): array
    {
        $tz = (string) config('app.timezone', 'Asia/Kolkata');
        $today = Carbon::today($tz);

        $rawFrom = $input['from_date'] ?? $input['start_date'] ?? null;
        $rawTo = $input['to_date'] ?? $input['end_date'] ?? null;
        $timeframe = (string) ($input['timeframe'] ?? '');

        if ($timeframe === '' && filled($rawFrom) && filled($rawTo)) {
            $timeframe = 'custom';
        } elseif ($timeframe === '') {
            $timeframe = 'today';
        }

        [$start, $end] = match ($timeframe) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'prev_month' => [$today->copy()->subMonth()->startOfMonth(), $today->copy()->subMonth()->endOfMonth()],
            'custom' => [
                filled($rawFrom) ? Carbon::parse((string) $rawFrom, $tz)->startOfDay() : $today->copy(),
                filled($rawTo) ? Carbon::parse((string) $rawTo, $tz)->startOfDay() : $today->copy(),
            ],
            default => [$today->copy(), $today->copy()],
        };

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $authorizedShopIds = $this->resolveAuthorizedShopIds($user);
        $shopId = isset($input['shop_id']) && $input['shop_id'] !== '' ? (int) $input['shop_id'] : null;

        if ($shopId !== null && $authorizedShopIds !== null && ! in_array($shopId, $authorizedShopIds, true)) {
            abort(403, 'You are not authorized to view credit notes for the selected shop.');
        }

        $shop = $shopId !== null ? Shop::query()->select(['id', 'code', 'name'])->find($shopId) : null;
        if ($shopId !== null && ! $shop) {
            abort(404, 'Selected shop not found.');
        }

        $reportType = in_array(($input['report_type'] ?? ''), ['detailed', 'summary'], true)
            ? (string) $input['report_type']
            : 'detailed';

        $fromDate = $start->toDateString();
        $toDate = $end->toDateString();
        $periodLabel = $fromDate === $toDate
            ? $start->format('d M Y')
            : $start->format('d M Y').' to '.$end->format('d M Y');

        return [
            'timeframe' => $timeframe,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'shop_id' => $shopId,
            'shop_code' => $shop?->code,
            'shop_name' => $shop ? "{$shop->name} ({$shop->code})" : 'All Shops',
            'report_type' => $reportType,
            'authorized_shop_ids' => $authorizedShopIds,
            'period_label' => $periodLabel,
        ];
    }

    /**
     * Get authorized shops for the filter dropdown.
     *
     * @param  array<int, int>|null  $authorizedShopIds
     * @return Collection<int, Shop>
     */
    public function getAuthorizedShops(?array $authorizedShopIds): Collection
    {
        return Shop::query()
            ->select(['id', 'code', 'name'])
            ->when($authorizedShopIds !== null, fn (Builder $q) => $q->whereIn('id', $authorizedShopIds))
            ->orderBy('name')
            ->get();
    }

    /**
     * Base query for financially effective (Approved, Credit-direction) ShopCreditNotes in period.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<ShopCreditNote>
     */
    public function baseEffectiveQuery(array $filters): Builder
    {
        $query = ShopCreditNote::query()
            ->whereDate('shop_credit_notes.credit_note_date', '>=', $filters['from_date'])
            ->whereDate('shop_credit_notes.credit_note_date', '<=', $filters['to_date'])
            ->where('shop_credit_notes.status', ShopCreditNoteStatus::Approved->value)
            ->where('shop_credit_notes.direction', ShopCreditNoteDirection::Credit->value);

        if ($filters['shop_id'] !== null) {
            $query->where('shop_credit_notes.shop_id', $filters['shop_id']);
        } elseif ($filters['authorized_shop_ids'] !== null) {
            $query->whereIn('shop_credit_notes.shop_id', $filters['authorized_shop_ids']);
        }

        return $query;
    }

    /**
     * Build the lightweight Summary Report dataset using database-level aggregation.
     * Avoids loading item-level data for optimal L2 performance.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildSummaryReport(array $filters): array
    {
        $base = $this->baseEffectiveQuery($filters);

        // 1. Period Overview totals (distinct credit notes, distinct shops, sum of total_credit_amount)
        $overviewRow = (clone $base)
            ->selectRaw('COUNT(DISTINCT shop_credit_notes.id) as total_credit_notes')
            ->selectRaw('COALESCE(SUM(shop_credit_notes.total_credit_amount), 0) as total_credit_amount')
            ->selectRaw('COUNT(DISTINCT shop_credit_notes.shop_id) as total_affected_shops')
            ->first();

        $totalCreditNotes = (int) ($overviewRow->total_credit_notes ?? 0);
        $totalCreditAmount = round((float) ($overviewRow->total_credit_amount ?? 0), 2);
        $totalAffectedShops = (int) ($overviewRow->total_affected_shops ?? 0);

        // Distinct affected invoices across legacy shop_invoice_id and active shop_credit_applications
        $noteInvoicePairs = $this->fetchNoteInvoicePairs($filters);
        $totalAffectedInvoices = $noteInvoicePairs
            ->pluck('invoice_id')
            ->filter()
            ->unique()
            ->count();

        // Map note_id -> distinct invoice_ids for fast per-day and per-shop distinct bill counts
        $invoicesByNote = [];
        foreach ($noteInvoicePairs as $pair) {
            if ($pair->invoice_id !== null) {
                $invoicesByNote[(int) $pair->note_id][] = (int) $pair->invoice_id;
            }
        }

        // 2. Fetch minimal note headers for daily & shop-wise aggregation (1 query, no items)
        $noteHeaders = (clone $base)
            ->join('shops', 'shops.id', '=', 'shop_credit_notes.shop_id')
            ->select([
                'shop_credit_notes.id',
                DB::raw('DATE(shop_credit_notes.credit_note_date) as report_date'),
                'shop_credit_notes.shop_id',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'shop_credit_notes.total_credit_amount',
            ])
            ->orderBy('report_date')
            ->orderBy('shops.name')
            ->get();

        // Group by date for Daily Summary (every date in period, including zero-credit dates)
        $dailyBuckets = [];
        $shopBuckets = [];
        $dailyShopBuckets = [];

        foreach ($noteHeaders as $row) {
            $date = (string) $row->report_date;
            $shopId = (int) $row->shop_id;
            $noteId = (int) $row->id;
            $amount = round((float) $row->total_credit_amount, 2);
            $invIds = $invoicesByNote[$noteId] ?? [];

            // Daily bucket
            if (! isset($dailyBuckets[$date])) {
                $dailyBuckets[$date] = ['shops' => [], 'bills' => [], 'notes' => [], 'amount' => 0.0];
            }
            $dailyBuckets[$date]['shops'][$shopId] = true;
            $dailyBuckets[$date]['notes'][$noteId] = true;
            $dailyBuckets[$date]['amount'] = round($dailyBuckets[$date]['amount'] + $amount, 2);
            foreach ($invIds as $invId) {
                $dailyBuckets[$date]['bills'][$invId] = true;
            }

            // Shop-wise period bucket
            if (! isset($shopBuckets[$shopId])) {
                $shopBuckets[$shopId] = [
                    'shop_id' => $shopId,
                    'shop_code' => (string) $row->shop_code,
                    'shop_name' => (string) $row->shop_name,
                    'bills' => [],
                    'notes' => [],
                    'amount' => 0.0,
                ];
            }
            $shopBuckets[$shopId]['notes'][$noteId] = true;
            $shopBuckets[$shopId]['amount'] = round($shopBuckets[$shopId]['amount'] + $amount, 2);
            foreach ($invIds as $invId) {
                $shopBuckets[$shopId]['bills'][$invId] = true;
            }

            // Daily shop-wise bucket
            $dsKey = "{$date}|{$shopId}";
            if (! isset($dailyShopBuckets[$dsKey])) {
                $dailyShopBuckets[$dsKey] = [
                    'date' => $date,
                    'shop_id' => $shopId,
                    'shop_code' => (string) $row->shop_code,
                    'shop_name' => (string) $row->shop_name,
                    'bills' => [],
                    'notes' => [],
                    'amount' => 0.0,
                ];
            }
            $dailyShopBuckets[$dsKey]['notes'][$noteId] = true;
            $dailyShopBuckets[$dsKey]['amount'] = round($dailyShopBuckets[$dsKey]['amount'] + $amount, 2);
            foreach ($invIds as $invId) {
                $dailyShopBuckets[$dsKey]['bills'][$invId] = true;
            }
        }

        // Section B: Daily Summary (all dates in selected period)
        $dailySummary = [];
        $period = CarbonPeriod::create($filters['from_date'], $filters['to_date']);
        foreach ($period as $carbonDate) {
            $d = $carbonDate->toDateString();
            $b = $dailyBuckets[$d] ?? null;
            $dailySummary[] = [
                'date' => $d,
                'formatted_date' => $carbonDate->format('d M Y'),
                'shops_count' => $b ? count($b['shops']) : 0,
                'bills_count' => $b ? count($b['bills']) : 0,
                'credit_notes_count' => $b ? count($b['notes']) : 0,
                'credit_amount' => $b ? $b['amount'] : 0.00,
            ];
        }

        // Section C: Shop-wise Period Summary (sorted by total credit amount descending)
        $shopSummary = collect($shopBuckets)
            ->map(fn (array $b): array => [
                'shop_id' => $b['shop_id'],
                'shop_code' => $b['shop_code'],
                'shop_name' => $b['shop_name'],
                'bills_count' => count($b['bills']),
                'credit_notes_count' => count($b['notes']),
                'credit_amount' => $b['amount'],
            ])
            ->sortByDesc('credit_amount')
            ->values()
            ->all();

        // Section D: Daily Shop-wise Summary grouped by date for subtotals
        $dailyShopRows = collect($dailyShopBuckets)
            ->map(fn (array $b): array => [
                'date' => $b['date'],
                'formatted_date' => Carbon::parse($b['date'])->format('d M Y'),
                'shop_id' => $b['shop_id'],
                'shop_code' => $b['shop_code'],
                'shop_name' => $b['shop_name'],
                'bills_count' => count($b['bills']),
                'credit_notes_count' => count($b['notes']),
                'credit_amount' => $b['amount'],
            ])
            ->sortBy([
                ['date', 'asc'],
                ['credit_amount', 'desc'],
            ])
            ->values()
            ->all();

        $dailyShopGrouped = [];
        foreach ($dailyShopRows as $dsRow) {
            $d = $dsRow['date'];
            if (! isset($dailyShopGrouped[$d])) {
                $dailyShopGrouped[$d] = [
                    'date' => $d,
                    'formatted_date' => $dsRow['formatted_date'],
                    'rows' => [],
                    'subtotal_bills' => $dailyBuckets[$d] ? count($dailyBuckets[$d]['bills']) : 0,
                    'subtotal_notes' => $dailyBuckets[$d] ? count($dailyBuckets[$d]['notes']) : 0,
                    'subtotal_amount' => $dailyBuckets[$d] ? $dailyBuckets[$d]['amount'] : 0.00,
                ];
            }
            $dailyShopGrouped[$d]['rows'][] = $dsRow;
        }

        return [
            'filters' => $filters,
            'overview' => [
                'period_label' => $filters['period_label'],
                'from_date' => $filters['from_date'],
                'to_date' => $filters['to_date'],
                'shop_name' => $filters['shop_name'],
                'total_credit_notes' => $totalCreditNotes,
                'total_credit_amount' => $totalCreditAmount,
                'total_affected_shops' => $totalAffectedShops,
                'total_affected_invoices' => $totalAffectedInvoices,
            ],
            'daily_summary' => $dailySummary,
            'shop_summary' => $shopSummary,
            'daily_shop_rows' => $dailyShopRows,
            'daily_shop_grouped' => array_values($dailyShopGrouped),
        ];
    }

    /**
     * Build the complete Detailed Report dataset (Date -> Shop -> Invoice -> Individual Credit Notes),
     * including product-level adjustments, invoice reconciliation via ShopFinalBillService,
     * register rows, and summary sheets.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildDetailedReport(array $filters): array
    {
        $summary = $this->buildSummaryReport($filters);

        $creditNotes = $this->baseEffectiveQuery($filters)
            ->with([
                'shop:id,code,name',
                'invoice:id,shop_id,invoice_number,business_date,final_total,paid_amount,status,payment_status,created_at',
                'items.product:id,name,sku,unit',
                'items.invoiceItem:id,shop_invoice_id,product_id,unit_price,delivered_price_quantity,price_quantity,approved_qty',
                'items.sourceInvoice:id,invoice_number,business_date',
                'activeApplications' => fn ($q) => $q->with([
                    'invoice:id,shop_id,invoice_number,business_date,final_total,paid_amount,status,payment_status,created_at',
                    'appliedByUser:id,name',
                ]),
                'creator:id,name',
                'approvedBy:id,name',
            ])
            ->orderBy('shop_credit_notes.credit_note_date')
            ->orderBy('shop_credit_notes.shop_id')
            ->orderBy('shop_credit_notes.id')
            ->get();

        // Collect all distinct affected invoices and compute authoritative FinalBill once via calculateMany()
        $affectedInvoicesMap = [];
        foreach ($creditNotes as $cn) {
            if ($cn->invoice) {
                $affectedInvoicesMap[(int) $cn->invoice->id] = $cn->invoice;
            }
            foreach ($cn->activeApplications as $app) {
                if ($app->invoice) {
                    $affectedInvoicesMap[(int) $app->invoice->id] = $app->invoice;
                }
            }
        }

        $finalBills = $this->finalBillService->calculateMany(
            new \Illuminate\Database\Eloquent\Collection(array_values($affectedInvoicesMap))
        );

        $registerRows = [];
        $productAdjustmentRows = [];
        $invoicePeriodTotals = [];
        $dateGroups = [];

        foreach ($creditNotes as $cn) {
            $reportingDate = $cn->credit_note_date?->toDateString() ?? '';
            $formattedReportingDate = $cn->credit_note_date?->format('d M Y') ?? '—';
            $shopId = (int) $cn->shop_id;
            $shopCode = (string) ($cn->shop?->code ?? '—');
            $shopName = (string) ($cn->shop?->name ?? 'Unknown Shop');
            $reasonLabel = $this->resolveReasonLabel($cn->reason_type);
            $cnTotal = round((float) $cn->total_credit_amount, 2);

            // Build product-level adjustments for this credit note
            $productItems = $this->formatCreditNoteItems($cn, $reasonLabel);

            // Determine invoice allocations for this credit note
            $allocations = $this->resolveCreditNoteAllocations($cn);

            // Register row (one row per individual credit note — never duplicated across product rows)
            $invoiceNumbersList = collect($allocations)
                ->pluck('invoice_number')
                ->filter(fn ($n): bool => $n !== 'Unapplied')
                ->unique()
                ->implode(', ');
            $firstAllocation = $allocations[0];

            $registerRows[] = [
                'credit_note_id' => (int) $cn->id,
                'reporting_date' => $reportingDate,
                'formatted_reporting_date' => $formattedReportingDate,
                'shop_id' => $shopId,
                'shop_code' => $shopCode,
                'shop_name' => $shopName,
                'invoice_number' => $invoiceNumbersList !== '' ? $invoiceNumbersList : 'Unapplied',
                'invoice_date' => $firstAllocation['invoice_date'] ?? '',
                'credit_note_number' => (string) $cn->credit_note_number,
                'credit_note_date' => $reportingDate,
                'applied_date' => $firstAllocation['applied_date_iso'] ?? $reportingDate,
                'reason' => $reasonLabel,
                'notes' => trim((string) ($cn->notes ?? $cn->discount_reason ?? '')),
                'credit_amount' => $cnTotal,
                'applied_by' => $firstAllocation['applied_by'] ?? ($cn->approvedBy?->name ?? $cn->creator?->name ?? 'System'),
                'status' => 'Active',
            ];

            // Product adjustment export rows (flat)
            foreach ($productItems as $pItem) {
                $productAdjustmentRows[] = [
                    'reporting_date' => $reportingDate,
                    'shop_code' => $shopCode,
                    'shop_name' => $shopName,
                    'credit_note_number' => (string) $cn->credit_note_number,
                    'invoice_number' => $pItem['source_invoice_number'] ?? ($invoiceNumbersList !== '' ? $invoiceNumbersList : 'Unapplied'),
                    'product_code' => $pItem['product_code'],
                    'product_name' => $pItem['product_name'],
                    'unit' => $pItem['unit'],
                    'quantity' => $pItem['quantity'],
                    'original_rate' => $pItem['original_rate'],
                    'corrected_rate' => $pItem['corrected_rate'],
                    'difference' => $pItem['difference'],
                    'credit_amount' => $pItem['credit_amount'],
                    'is_monetary_only' => $pItem['is_monetary_only'],
                    'reason' => $pItem['reason'],
                ];
            }

            // Populate Date -> Shop -> Invoice -> Credit Notes hierarchy
            if (! isset($dateGroups[$reportingDate])) {
                $dateGroups[$reportingDate] = [
                    'date' => $reportingDate,
                    'formatted_date' => $formattedReportingDate,
                    'shops' => [],
                    'subtotal_credit_notes' => 0,
                    'subtotal_amount' => 0.0,
                    'distinct_shops' => [],
                    'distinct_bills' => [],
                ];
            }

            if (! isset($dateGroups[$reportingDate]['shops'][$shopId])) {
                $dateGroups[$reportingDate]['shops'][$shopId] = [
                    'shop_id' => $shopId,
                    'shop_code' => $shopCode,
                    'shop_name' => $shopName,
                    'invoices' => [],
                    'subtotal_credit_notes' => 0,
                    'subtotal_amount' => 0.0,
                    'distinct_bills' => [],
                    'distinct_notes' => [],
                ];
            }

            $dateGroups[$reportingDate]['distinct_shops'][$shopId] = true;
            $dateGroups[$reportingDate]['shops'][$shopId]['distinct_notes'][(int) $cn->id] = true;

            foreach ($allocations as $alloc) {
                $invKey = $alloc['invoice_id'] !== null ? 'inv_'.$alloc['invoice_id'] : 'unapplied';
                $invId = $alloc['invoice_id'];
                $allocAmount = $alloc['amount'];

                if ($invId !== null) {
                    $dateGroups[$reportingDate]['distinct_bills'][$invId] = true;
                    $dateGroups[$reportingDate]['shops'][$shopId]['distinct_bills'][$invId] = true;
                    $invoicePeriodTotals[$invId] = round(($invoicePeriodTotals[$invId] ?? 0.0) + $allocAmount, 2);
                }

                if (! isset($dateGroups[$reportingDate]['shops'][$shopId]['invoices'][$invKey])) {
                    /** @var ShopFinalBill|null $bill */
                    $bill = $invId !== null ? $finalBills->get($invId) : null;

                    $dateGroups[$reportingDate]['shops'][$shopId]['invoices'][$invKey] = [
                        'invoice_id' => $invId,
                        'invoice_number' => $alloc['invoice_number'],
                        'invoice_date' => $alloc['invoice_date'],
                        'formatted_invoice_date' => $alloc['formatted_invoice_date'],
                        'is_unapplied' => $invId === null,
                        'reconciliation' => $bill ? [
                            'original_invoice' => $bill->originalInvoiceTotal,
                            'total_credits' => $bill->finalizedCreditTotal,
                            'total_debits' => $bill->finalizedDebitTotal,
                            'effective_net_bill' => $bill->finalNetPayable,
                            'amount_paid' => $bill->amountPaid,
                            'remaining_balance' => $bill->remainingBalance,
                        ] : null,
                        'credit_notes' => [],
                        'period_subtotal' => 0.0,
                    ];
                }

                $dateGroups[$reportingDate]['shops'][$shopId]['invoices'][$invKey]['credit_notes'][] = [
                    'credit_note_id' => (int) $cn->id,
                    'credit_note_number' => (string) $cn->credit_note_number,
                    'credit_note_date' => $reportingDate,
                    'formatted_credit_note_date' => $formattedReportingDate,
                    'applied_date' => $alloc['applied_date_display'],
                    'applied_date_iso' => $alloc['applied_date_iso'],
                    'reason' => $reasonLabel,
                    'notes' => trim((string) ($alloc['notes'] ?: $cn->notes ?: $cn->discount_reason ?: '')),
                    'applied_by' => $alloc['applied_by'],
                    'status' => $alloc['status_label'],
                    'amount' => $allocAmount,
                    'credit_note_total' => $cnTotal,
                    'is_split_allocation' => count($allocations) > 1,
                    'items' => $productItems,
                ];

                $dateGroups[$reportingDate]['shops'][$shopId]['invoices'][$invKey]['period_subtotal'] = round(
                    $dateGroups[$reportingDate]['shops'][$shopId]['invoices'][$invKey]['period_subtotal'] + $allocAmount,
                    2
                );
                $dateGroups[$reportingDate]['shops'][$shopId]['subtotal_amount'] = round(
                    $dateGroups[$reportingDate]['shops'][$shopId]['subtotal_amount'] + $allocAmount,
                    2
                );
                $dateGroups[$reportingDate]['subtotal_amount'] = round(
                    $dateGroups[$reportingDate]['subtotal_amount'] + $allocAmount,
                    2
                );
            }
        }

        // Finalize counts for date & shop groups
        $formattedDateGroups = [];
        foreach ($dateGroups as $dGroup) {
            $shopsList = [];
            $dateNoteIds = [];
            foreach ($dGroup['shops'] as $sGroup) {
                foreach ($sGroup['distinct_notes'] as $nid => $_) {
                    $dateNoteIds[$nid] = true;
                }
                $shopsList[] = [
                    'shop_id' => $sGroup['shop_id'],
                    'shop_code' => $sGroup['shop_code'],
                    'shop_name' => $sGroup['shop_name'],
                    'total_affected_bills' => count($sGroup['distinct_bills']),
                    'total_credit_notes' => count($sGroup['distinct_notes']),
                    'subtotal_amount' => $sGroup['subtotal_amount'],
                    'invoices' => array_values($sGroup['invoices']),
                ];
            }

            $formattedDateGroups[] = [
                'date' => $dGroup['date'],
                'formatted_date' => $dGroup['formatted_date'],
                'total_shops' => count($dGroup['distinct_shops']),
                'total_bills' => count($dGroup['distinct_bills']),
                'total_credit_notes' => count($dateNoteIds),
                'subtotal_amount' => $dGroup['subtotal_amount'],
                'shops' => $shopsList,
            ];
        }

        // Invoice Reconciliation table (one row per distinct affected invoice)
        $invoiceReconciliationRows = [];
        foreach ($affectedInvoicesMap as $invId => $invoice) {
            /** @var ShopFinalBill|null $bill */
            $bill = $finalBills->get($invId);
            if (! $bill) {
                continue;
            }
            $invDate = $invoice->business_date?->toDateString() ?? $invoice->created_at?->toDateString() ?? '';
            $invoiceReconciliationRows[] = [
                'invoice_id' => $invId,
                'invoice_number' => (string) $invoice->invoice_number,
                'invoice_date' => $invDate,
                'formatted_invoice_date' => $invDate !== '' ? Carbon::parse($invDate)->format('d M Y') : '—',
                'shop_code' => (string) ($creditNotes->firstWhere('shop_id', $invoice->shop_id)?->shop?->code ?? $invoice->shop?->code ?? '—'),
                'shop_name' => (string) ($creditNotes->firstWhere('shop_id', $invoice->shop_id)?->shop?->name ?? $invoice->shop?->name ?? '—'),
                'original_invoice_amount' => $bill->originalInvoiceTotal,
                'period_credit_amount' => round((float) ($invoicePeriodTotals[$invId] ?? 0.0), 2),
                'cumulative_credit_amount' => $bill->finalizedCreditTotal,
                'cumulative_debit_amount' => $bill->finalizedDebitTotal,
                'effective_net_bill' => $bill->finalNetPayable,
            ];
        }

        return [
            ...$summary,
            'date_groups' => $formattedDateGroups,
            'register_rows' => $registerRows,
            'invoice_reconciliation_rows' => $invoiceReconciliationRows,
            'product_adjustment_rows' => $productAdjustmentRows,
        ];
    }

    /**
     * Fetch (credit_note_id, invoice_id) pairs for distinct affected bill counts.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function fetchNoteInvoicePairs(array $filters): Collection
    {
        $legacyPairs = $this->baseEffectiveQuery($filters)
            ->whereNotNull('shop_credit_notes.shop_invoice_id')
            ->select([
                'shop_credit_notes.id as note_id',
                'shop_credit_notes.shop_invoice_id as invoice_id',
            ]);

        $appliedPairs = $this->baseEffectiveQuery($filters)
            ->join('shop_credit_applications', function ($join): void {
                $join->on('shop_credit_applications.shop_credit_note_id', '=', 'shop_credit_notes.id')
                    ->whereNull('shop_credit_applications.reversed_at');
            })
            ->select([
                'shop_credit_notes.id as note_id',
                'shop_credit_applications.shop_invoice_id as invoice_id',
            ]);

        return $legacyPairs->union($appliedPairs)->get();
    }

    /**
     * Resolve invoice allocations for a single credit note without double counting.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveCreditNoteAllocations(ShopCreditNote $cn): array
    {
        $cnTotal = round((float) $cn->total_credit_amount, 2);

        // Legacy invoice-linked mode
        if ($cn->isLegacyMode() || $cn->activeApplications->isEmpty()) {
            $invoice = $cn->invoice;
            if ($invoice) {
                $invDate = $invoice->business_date?->toDateString() ?? $invoice->created_at?->toDateString() ?? '';
                $appliedAt = $cn->approved_at ?? $cn->credit_note_date;

                return [[
                    'invoice_id' => (int) $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_number,
                    'invoice_date' => $invDate,
                    'formatted_invoice_date' => $invDate !== '' ? Carbon::parse($invDate)->format('d M Y') : '—',
                    'applied_date_iso' => $appliedAt?->toDateString() ?? ($cn->credit_note_date?->toDateString() ?? ''),
                    'applied_date_display' => $appliedAt?->format('d M Y') ?? '—',
                    'applied_by' => (string) ($cn->approvedBy?->name ?? $cn->creator?->name ?? 'System'),
                    'notes' => (string) ($cn->notes ?? ''),
                    'status_label' => 'Active',
                    'amount' => $cnTotal,
                ]];
            }

            // Unapplied shop-level credit note
            return [[
                'invoice_id' => null,
                'invoice_number' => 'Unapplied',
                'invoice_date' => '',
                'formatted_invoice_date' => '—',
                'applied_date_iso' => '',
                'applied_date_display' => 'Unapplied',
                'applied_by' => (string) ($cn->approvedBy?->name ?? $cn->creator?->name ?? 'System'),
                'notes' => (string) ($cn->notes ?? ''),
                'status_label' => 'Active (Unapplied)',
                'amount' => $cnTotal,
            ]];
        }

        // Shop-level mode with active applications
        $allocations = [];
        $appliedSum = 0.0;

        foreach ($cn->activeApplications as $app) {
            /** @var ShopCreditApplication $app */
            $invoice = $app->invoice;
            $amt = round((float) $app->amount, 2);
            $appliedSum = round($appliedSum + $amt, 2);
            $invDate = $invoice?->business_date?->toDateString() ?? $invoice?->created_at?->toDateString() ?? '';

            $allocations[] = [
                'invoice_id' => $invoice ? (int) $invoice->id : null,
                'invoice_number' => (string) ($invoice?->invoice_number ?? 'Unapplied'),
                'invoice_date' => $invDate,
                'formatted_invoice_date' => $invDate !== '' ? Carbon::parse($invDate)->format('d M Y') : '—',
                'applied_date_iso' => $app->applied_at?->toDateString() ?? '',
                'applied_date_display' => $app->applied_at?->format('d M Y') ?? '—',
                'applied_by' => (string) ($app->appliedByUser?->name ?? $cn->approvedBy?->name ?? 'System'),
                'notes' => (string) ($app->notes ?? ''),
                'status_label' => 'Active',
                'amount' => $amt,
            ];
        }

        $unappliedRemainder = round($cnTotal - $appliedSum, 2);
        if ($unappliedRemainder > 0.009) {
            $allocations[] = [
                'invoice_id' => null,
                'invoice_number' => 'Unapplied Balance',
                'invoice_date' => '',
                'formatted_invoice_date' => '—',
                'applied_date_iso' => '',
                'applied_date_display' => 'Unapplied',
                'applied_by' => (string) ($cn->approvedBy?->name ?? $cn->creator?->name ?? 'System'),
                'notes' => 'Remaining unapplied credit balance',
                'status_label' => 'Active (Unapplied)',
                'amount' => $unappliedRemainder,
            ];
        }

        return $allocations;
    }

    /**
     * Format product-level and direct monetary adjustments for a credit note.
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatCreditNoteItems(ShopCreditNote $cn, string $cnReasonLabel): array
    {
        if ($cn->items->isEmpty()) {
            return [[
                'product_code' => '—',
                'product_name' => $cnReasonLabel,
                'unit' => '',
                'quantity' => null,
                'original_rate' => null,
                'corrected_rate' => null,
                'difference' => null,
                'credit_amount' => round((float) $cn->total_credit_amount, 2),
                'is_monetary_only' => true,
                'reason' => $cnReasonLabel,
                'source_invoice_number' => $cn->invoice?->invoice_number,
            ]];
        }

        $rows = [];
        foreach ($cn->items as $item) {
            /** @var ShopCreditNoteItem $item */
            $itemReason = $item->reason_type ? $this->resolveReasonLabel($item->reason_type) : $cnReasonLabel;
            $sourceInvNum = $item->sourceInvoice?->invoice_number ?? $item->invoiceItem?->invoice?->invoice_number ?? $cn->invoice?->invoice_number;

            if ($item->isCommercialDiscount() || $item->product_id === null) {
                $rows[] = [
                    'product_code' => '—',
                    'product_name' => $item->product_name ?: $itemReason,
                    'unit' => '',
                    'quantity' => null,
                    'original_rate' => null,
                    'corrected_rate' => null,
                    'difference' => null,
                    'credit_amount' => round((float) $item->line_credit_amount, 2),
                    'is_monetary_only' => true,
                    'reason' => $itemReason,
                    'source_invoice_number' => $sourceInvNum,
                ];

                continue;
            }

            $origRate = $item->original_unit_price !== null ? round((float) $item->original_unit_price, 2) : null;
            $corrRate = $item->adjusted_unit_price !== null
                ? round((float) $item->adjusted_unit_price, 2)
                : ($item->credit_rate !== null ? round((float) $item->credit_rate, 2) : null);

            $diff = ($origRate !== null && $corrRate !== null)
                ? round($origRate - $corrRate, 2)
                : ($item->credit_rate !== null ? round((float) $item->credit_rate, 2) : null);

            $rows[] = [
                'product_code' => (string) ($item->product?->sku ?? '—'),
                'product_name' => (string) ($item->product_name ?: $item->product?->name ?: 'Unknown Product'),
                'unit' => (string) ($item->unit ?? $item->product?->unit ?? ''),
                'quantity' => round((float) $item->adjusted_quantity, 3),
                'original_rate' => $origRate,
                'corrected_rate' => $corrRate,
                'difference' => $diff,
                'credit_amount' => round((float) $item->line_credit_amount, 2),
                'is_monetary_only' => false,
                'reason' => $itemReason,
                'source_invoice_number' => $sourceInvNum,
            ];
        }

        return $rows;
    }

    private function resolveReasonLabel(mixed $reason): string
    {
        if ($reason instanceof ShopCreditNoteReason) {
            return $reason->label();
        }

        if (is_string($reason) && $reason !== '') {
            return ShopCreditNoteReason::tryFrom($reason)?->label()
                ?? str($reason)->replace('_', ' ')->title()->toString();
        }

        return 'Adjustment';
    }
}
