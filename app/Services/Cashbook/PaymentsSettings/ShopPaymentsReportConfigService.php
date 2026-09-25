<?php

declare(strict_types=1);

namespace App\Services\Cashbook\PaymentsSettings;

use App\Models\Cashbook\ShopCashbookMonthConfigSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProductEntry;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoice;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopPaymentsReportConfigService
{
    public const HEADING_TOTAL_SALES = 'total_sales';

    public const HEADING_RENT_EXPENSE = 'rent_expense';

    public const HEADING_CASH_PURCHASE = 'cash_purchase';

    public const HEADING_OTHER_EXPENSE = 'other_expense';

    public const HEADING_NET_OPERATING_BALANCE = 'net_operating_balance';

    public const HEADING_GL_BILLS_REF = 'gl_bills_ref';

    public const SOURCE_PRODUCT_TOTAL = 'product_total';

    public const SOURCE_HEADER_WITH_PRODUCT_TOTAL = 'header_with_product_total';

    public const SOURCE_VENDOR_PURCHASE_CASH = 'vendor_purchase_cash';

    public const SOURCE_VENDOR_PURCHASE_CREDIT = 'vendor_purchase_credit';

    /**
     * Get default report headings structure for a shop.
     *
     * @return array<string, array{title: string, role: string, is_computed: bool, is_informational: bool, sources: array<int, array{type: string, id: int|string, name: string}>}>
     */
    public function getDefaultHeadings(int $shopId): array
    {
        $headers = ShopLedgerHeaderGroup::query()->where('shop_id', $shopId)->get();
        $settings = ShopLedgerEntrySetting::query()->with('entryType')->where('shop_id', $shopId)->where('enabled', true)->get();

        $salesHeader = $headers->first(fn ($h) => str_contains(strtolower($h->name), 'sale'));
        $expenseHeader = $headers->first(fn ($h) => str_contains(strtolower($h->name), 'expense'));
        $purchaseHeader = $headers->first(fn ($h) => str_contains(strtolower($h->name), 'purchase'));

        $salesSources = [];
        if ($salesHeader) {
            $salesSources[] = ['type' => 'header', 'id' => (int) $salesHeader->id, 'name' => $salesHeader->name];
            foreach ($settings->filter(fn ($s) => $s->resolveSalesReportBucket() === 'sales' && (int) $s->header_group_id !== (int) $salesHeader->id) as $s) {
                $salesSources[] = ['type' => 'category', 'id' => (int) $s->id, 'name' => $s->displayName()];
            }
        } else {
            foreach ($settings->filter(fn ($s) => $s->resolveSalesReportBucket() === 'sales') as $s) {
                $salesSources[] = ['type' => 'category', 'id' => (int) $s->id, 'name' => $s->displayName()];
            }
        }

        $rentSources = [];
        foreach ($settings->filter(fn ($s) => $s->resolveSalesReportBucket() === 'rent') as $s) {
            $rentSources[] = ['type' => 'category', 'id' => (int) $s->id, 'name' => $s->displayName()];
        }

        $purchaseSources = [];
        if ($purchaseHeader) {
            $purchaseSources[] = ['type' => 'header', 'id' => (int) $purchaseHeader->id, 'name' => $purchaseHeader->name];
            foreach ($settings->filter(fn ($s) => $s->resolveSalesReportBucket() === 'purchase' && (int) $s->header_group_id !== (int) $purchaseHeader->id && ! $s->is_vendor_purchase && ! in_array($s->entryType?->code, ['vendor_purchase_cash', 'vendor_purchase_credit'], true)) as $s) {
                $purchaseSources[] = ['type' => 'category', 'id' => (int) $s->id, 'name' => $s->displayName()];
            }
        } else {
            foreach ($settings->filter(fn ($s) => $s->resolveSalesReportBucket() === 'purchase' && ! $s->is_vendor_purchase && ! in_array($s->entryType?->code, ['vendor_purchase_cash', 'vendor_purchase_credit'], true)) as $s) {
                $purchaseSources[] = ['type' => 'category', 'id' => (int) $s->id, 'name' => $s->displayName()];
            }
        }
        $purchaseSources[] = ['type' => 'product_total', 'id' => 'direct_vendor_purchases', 'name' => 'Direct Vendor Purchases'];

        $otherExpenseSources = [];
        if ($expenseHeader) {
            $otherExpenseSources[] = ['type' => 'header', 'id' => (int) $expenseHeader->id, 'name' => $expenseHeader->name];
            foreach ($settings->filter(fn ($s) => $s->resolveSalesReportBucket() === 'other_expense' && (int) $s->header_group_id !== (int) $expenseHeader->id) as $s) {
                $otherExpenseSources[] = ['type' => 'category', 'id' => (int) $s->id, 'name' => $s->displayName()];
            }
        } else {
            foreach ($settings->filter(fn ($s) => $s->resolveSalesReportBucket() === 'other_expense') as $s) {
                $otherExpenseSources[] = ['type' => 'category', 'id' => (int) $s->id, 'name' => $s->displayName()];
            }
        }

        return [
            self::HEADING_TOTAL_SALES => [
                'key' => self::HEADING_TOTAL_SALES,
                'title' => 'TOTAL SALES',
                'role' => 'add',
                'is_computed' => false,
                'is_informational' => false,
                'sources' => $salesSources,
            ],
            self::HEADING_RENT_EXPENSE => [
                'key' => self::HEADING_RENT_EXPENSE,
                'title' => 'RENT EXPENSE',
                'role' => 'subtract',
                'is_computed' => false,
                'is_informational' => false,
                'sources' => $rentSources,
            ],
            self::HEADING_CASH_PURCHASE => [
                'key' => self::HEADING_CASH_PURCHASE,
                'title' => 'CASH PURCHASE',
                'role' => 'subtract',
                'is_computed' => false,
                'is_informational' => false,
                'sources' => $purchaseSources,
            ],
            self::HEADING_OTHER_EXPENSE => [
                'key' => self::HEADING_OTHER_EXPENSE,
                'title' => 'OTHER EXPENSE',
                'role' => 'subtract',
                'is_computed' => false,
                'is_informational' => false,
                'sources' => $otherExpenseSources,
            ],
            self::HEADING_NET_OPERATING_BALANCE => [
                'key' => self::HEADING_NET_OPERATING_BALANCE,
                'title' => 'NET OPERATING BALANCE',
                'role' => 'balance',
                'is_computed' => true,
                'is_informational' => false,
                'sources' => [],
            ],
            self::HEADING_GL_BILLS_REF => [
                'key' => self::HEADING_GL_BILLS_REF,
                'title' => 'GL BILLS REF',
                'role' => 'info',
                'is_computed' => false,
                'is_informational' => true,
                'sources' => [
                    ['type' => 'system', 'id' => 'gl_invoices', 'name' => 'System GL Invoices'],
                ],
            ],
        ];
    }

    /**
     * Resolve report headings configuration for a specific month (with historical safety).
     */
    public function getConfigurationForMonth(int $shopId, string $month): array
    {
        // 1. Check historical month snapshot if present
        $snapshot = ShopCashbookMonthConfigSnapshot::query()
            ->where('shop_id', $shopId)
            ->where('month', $month)
            ->first();

        if ($snapshot && isset($snapshot->config_data['reports']['monthly_sales']) && is_array($snapshot->config_data['reports']['monthly_sales'])) {
            return $snapshot->config_data['reports']['monthly_sales'];
        }

        // 2. Check profile's month-specific configuration
        $profile = ShopLedgerProfile::query()->where('shop_id', $shopId)->first();
        $paymentConfig = is_array($profile?->payment_configuration) ? $profile->payment_configuration : [];

        if (isset($paymentConfig['reports']['monthly_sales'][$month]) && is_array($paymentConfig['reports']['monthly_sales'][$month])) {
            return $paymentConfig['reports']['monthly_sales'][$month];
        }

        // 3. Fallback to default headings
        return $this->getDefaultHeadings($shopId);
    }

    /**
     * Save report headings configuration for a specific month (strict shop isolation and historical protection).
     */
    public function saveConfigurationForMonth(int $shopId, string $month, array $headings, int $userId): array
    {
        return DB::transaction(function () use ($shopId, $month, $headings): array {
            $profile = ShopLedgerProfile::query()->where('shop_id', $shopId)->lockForUpdate()->firstOrFail();
            $paymentConfig = is_array($profile->payment_configuration) ? $profile->payment_configuration : [];

            if (! isset($paymentConfig['reports'])) {
                $paymentConfig['reports'] = [];
            }
            if (! isset($paymentConfig['reports']['monthly_sales'])) {
                $paymentConfig['reports']['monthly_sales'] = [];
            }

            // Clean and validate sources
            $validatedHeadings = [];
            $defaults = $this->getDefaultHeadings($shopId);

            foreach ($defaults as $key => $defaultHeading) {
                $inputHeading = $headings[$key] ?? [];
                $sources = (array) ($inputHeading['sources'] ?? $defaultHeading['sources']);

                $cleanSources = [];
                foreach ($sources as $source) {
                    if (! is_array($source) || empty($source['type']) || ! isset($source['id'])) {
                        continue;
                    }
                    $cleanSources[] = $this->validateSource($shopId, $source);
                }

                $this->ensureSourcesDoNotOverlap($shopId, $cleanSources);

                $validatedHeadings[$key] = [
                    'key' => $key,
                    'title' => $defaultHeading['title'],
                    'role' => $defaultHeading['role'],
                    'is_computed' => $defaultHeading['is_computed'],
                    'is_informational' => $defaultHeading['is_informational'],
                    'sources' => $cleanSources,
                ];
            }

            // Save under specific month only
            $paymentConfig['reports']['monthly_sales'][$month] = $validatedHeadings;
            $profile->update(['payment_configuration' => $paymentConfig]);

            // Update snapshot if one exists for this month
            $snapshot = ShopCashbookMonthConfigSnapshot::query()
                ->where('shop_id', $shopId)
                ->where('month', $month)
                ->first();

            if ($snapshot) {
                $configData = is_array($snapshot->config_data) ? $snapshot->config_data : [];
                $configData['reports']['monthly_sales'] = $validatedHeadings;
                $snapshot->update(['config_data' => $configData]);
            }

            return $validatedHeadings;
        });
    }

    /** @param array{type: mixed, id: mixed, name?: mixed} $source */
    private function validateSource(int $shopId, array $source): array
    {
        $type = (string) $source['type'];
        $id = $source['id'];

        if ($type === 'system' && (string) $id === 'gl_invoices') {
            return ['type' => 'system', 'id' => 'gl_invoices', 'name' => 'System GL Invoices'];
        }

        if ($type === 'header') {
            $header = ShopLedgerHeaderGroup::query()->where('shop_id', $shopId)->find($id);
            if (! $header) {
                throw ValidationException::withMessages(['headings' => ['The selected header does not belong to this shop.']]);
            }

            return [
                'type' => $type,
                'id' => (int) $header->id,
                'name' => $header->product_tagging_enabled ? $header->name.' — Other Totals (Skip Products)' : $header->name,
            ];
        }

        if ($type === 'category') {
            $setting = ShopLedgerEntrySetting::query()->where('shop_id', $shopId)->find($id);
            if (! $setting || $setting->headerGroup?->product_tagging_enabled) {
                throw ValidationException::withMessages(['headings' => ['A category under a product-tagged header must be reported through its product total.']]);
            }

            return ['type' => $type, 'id' => (int) $setting->id, 'name' => $setting->displayName()];
        }

        if ($type === self::SOURCE_PRODUCT_TOTAL) {
            if ((string) $id === 'direct_vendor_purchases') {
                return ['type' => $type, 'id' => 'direct_vendor_purchases', 'name' => 'Direct Vendor Purchases'];
            }
            $header = ShopLedgerHeaderGroup::query()->where('shop_id', $shopId)->find($id);
            if (! $header || ! $header->product_tagging_enabled) {
                throw ValidationException::withMessages(['headings' => ['Product Total is available only for a product-tagged header in this shop.']]);
            }

            return ['type' => $type, 'id' => (int) $header->id, 'name' => $header->name.' — Product Total Only'];
        }

        if ($type === self::SOURCE_HEADER_WITH_PRODUCT_TOTAL) {
            $header = ShopLedgerHeaderGroup::query()->where('shop_id', $shopId)->find($id);
            if (! $header || ! $header->product_tagging_enabled) {
                throw ValidationException::withMessages(['headings' => ['The full header and product total option requires a product-tagged header.']]);
            }

            return ['type' => $type, 'id' => (int) $header->id, 'name' => $header->name.' + Product Total (Full)'];
        }

        if (in_array($type, [self::SOURCE_VENDOR_PURCHASE_CASH, self::SOURCE_VENDOR_PURCHASE_CREDIT], true)) {
            $paymentType = $type === self::SOURCE_VENDOR_PURCHASE_CASH ? 'cash' : 'credit';
            $exists = ShopLedgerEntrySetting::query()
                ->where('shop_id', $shopId)
                ->where('enabled', true)
                ->where('is_vendor_purchase', true)
                ->where(function ($query) use ($paymentType): void {
                    $query->where('vendor_purchase_payment_type', $paymentType)
                        ->orWhereHas('entryType', fn ($entryTypeQuery) => $entryTypeQuery->where('code', 'vendor_purchase_'.$paymentType));
                })
                ->exists();
            if (! $exists) {
                throw ValidationException::withMessages(['headings' => ["Vendor {$paymentType} purchases are not enabled for this shop."]]);
            }

            return [
                'type' => $type,
                'id' => $paymentType,
                'name' => $paymentType === 'cash' ? 'Vendor Cash / Debit Purchases' : 'Vendor Credit Purchases',
            ];
        }

        throw ValidationException::withMessages(['headings' => ['The selected report source is not supported.']]);
    }

    /** @param array<int, array{type: string, id: int|string, name: string}> $sources */
    private function ensureSourcesDoNotOverlap(int $shopId, array $sources): void
    {
        $headerIds = collect($sources)
            ->whereIn('type', ['header', self::SOURCE_HEADER_WITH_PRODUCT_TOTAL])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);
        $categoryIds = collect($sources)->where('type', 'category')->pluck('id')->map(fn ($id): int => (int) $id);

        if ($headerIds->isNotEmpty() && ShopLedgerEntrySetting::query()->where('shop_id', $shopId)->whereIn('header_group_id', $headerIds)->whereIn('id', $categoryIds)->exists()) {
            throw ValidationException::withMessages(['headings' => ['A category cannot be added when its header group is already included.']]);
        }

    }

    /**
     * Detect duplicate category inclusions within a report heading or across headers.
     *
     * @return array<int, array{category_id: int, category_name: string, header_id: int, header_name: string, reason: string}>
     */
    public function detectDuplicates(int $shopId, array $sources): array
    {
        $headerIds = [];
        $categoryIds = [];

        foreach ($sources as $src) {
            if ($src['type'] === 'header') {
                $headerIds[] = (int) $src['id'];
            } elseif ($src['type'] === 'category') {
                $categoryIds[] = (int) $src['id'];
            }
        }

        if (empty($headerIds) || empty($categoryIds)) {
            return [];
        }

        $headers = ShopLedgerHeaderGroup::query()
            ->with('entrySettings')
            ->where('shop_id', $shopId)
            ->whereIn('id', $headerIds)
            ->get();

        $duplicates = [];
        foreach ($headers as $header) {
            foreach ($header->entrySettings as $setting) {
                if (in_array($setting->id, $categoryIds, true)) {
                    $duplicates[] = [
                        'category_id' => $setting->id,
                        'category_name' => $setting->displayName(),
                        'header_id' => $header->id,
                        'header_name' => $header->name,
                        'reason' => "Category '{$setting->displayName()}' is already included through Header '{$header->name}'",
                    ];
                }
            }
        }

        return $duplicates;
    }

    /**
     * Single authoritative calculation engine for report headings.
     * Guarantees that: Monthly Sum === SUM(Daily Values).
     */
    public function calculateReport(
        ShopLedgerProfile|Shop|int $shopOrProfile,
        string $startDate,
        string $endDate,
        ?string $month = null
    ): array {
        $shopId = $shopOrProfile instanceof Shop
            ? (int) $shopOrProfile->id
            : ($shopOrProfile instanceof ShopLedgerProfile ? (int) $shopOrProfile->shop_id : (int) $shopOrProfile);

        $monthStr = $month ?: Carbon::parse($startDate)->format('Y-m');
        $headings = $this->getConfigurationForMonth($shopId, $monthStr);

        $settings = ShopLedgerEntrySetting::query()
            ->with(['entryType', 'headerGroup'])
            ->where('shop_id', $shopId)
            ->get()
            ->keyBy('id');

        $headers = ShopLedgerHeaderGroup::query()
            ->with('entrySettings')
            ->where('shop_id', $shopId)
            ->get()
            ->keyBy('id');

        $transactions = ShopLedgerTransaction::query()
            ->with('entryType')
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->whereNull('voided_at')
            ->orderBy('business_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $glBillsTotal = round((float) ShopInvoice::query()
            ->where('shop_id', $shopId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('business_date', [$startDate, $endDate])
            ->sum('final_total'), 2);

        $periodDates = CarbonPeriod::create($startDate, $endDate);
        $txsByDate = $transactions->groupBy(fn (ShopLedgerTransaction $tx): string => (string) ($tx->business_date?->toDateString() ?: $startDate));

        $dailyRows = [];
        $headingMonthlyTotals = [
            self::HEADING_TOTAL_SALES => 0.0,
            self::HEADING_RENT_EXPENSE => 0.0,
            self::HEADING_CASH_PURCHASE => 0.0,
            self::HEADING_OTHER_EXPENSE => 0.0,
            self::HEADING_NET_OPERATING_BALANCE => 0.0,
            self::HEADING_GL_BILLS_REF => $glBillsTotal,
        ];

        // Map settings by entry_type_id for fast lookup
        $settingsByEntryTypeId = $settings->keyBy('entry_type_id');

        foreach ($periodDates as $dateCarbon) {
            $dateStr = $dateCarbon->toDateString();
            $dayTxs = $txsByDate->get($dateStr, collect());

            $dayBreakdowns = $this->buildPeriodBreakdowns($shopId, $headings, $dayTxs, $dateStr, $dateStr, $headers, $settings);

            $dayTotals = [
                self::HEADING_TOTAL_SALES => $dayBreakdowns[self::HEADING_TOTAL_SALES]['total'],
                self::HEADING_RENT_EXPENSE => $dayBreakdowns[self::HEADING_RENT_EXPENSE]['total'],
                self::HEADING_CASH_PURCHASE => $dayBreakdowns[self::HEADING_CASH_PURCHASE]['total'],
                self::HEADING_OTHER_EXPENSE => $dayBreakdowns[self::HEADING_OTHER_EXPENSE]['total'],
            ];

            // Compute daily Net Operating Balance
            $dayNetBalance = round($dayTotals[self::HEADING_TOTAL_SALES] - ($dayTotals[self::HEADING_RENT_EXPENSE] + $dayTotals[self::HEADING_CASH_PURCHASE] + $dayTotals[self::HEADING_OTHER_EXPENSE]), 2);
            $dayTotalExpenses = round($dayTotals[self::HEADING_RENT_EXPENSE] + $dayTotals[self::HEADING_CASH_PURCHASE] + $dayTotals[self::HEADING_OTHER_EXPENSE], 2);

            foreach ([self::HEADING_TOTAL_SALES, self::HEADING_RENT_EXPENSE, self::HEADING_CASH_PURCHASE, self::HEADING_OTHER_EXPENSE] as $hKey) {
                $headingMonthlyTotals[$hKey] += $dayTotals[$hKey];
            }

            // Exclude future dates if all values are zero and there are no transactions
            $isAllZero = abs($dayTotals[self::HEADING_TOTAL_SALES]) < 0.001
                && abs($dayTotals[self::HEADING_RENT_EXPENSE]) < 0.001
                && abs($dayTotals[self::HEADING_CASH_PURCHASE]) < 0.001
                && abs($dayTotals[self::HEADING_OTHER_EXPENSE]) < 0.001
                && $dayTxs->isEmpty();

            if ($dateCarbon->greaterThan(Carbon::today()) && $isAllZero) {
                continue;
            }

            $dailyRows[] = [
                'date' => $dateStr,
                'formatted_date' => $dateCarbon->format('d M Y'),
                'day_name' => $dateCarbon->format('l'),
                'sales' => $dayTotals[self::HEADING_TOTAL_SALES],
                'rent' => $dayTotals[self::HEADING_RENT_EXPENSE],
                'purchase' => $dayTotals[self::HEADING_CASH_PURCHASE],
                'other_expense' => $dayTotals[self::HEADING_OTHER_EXPENSE],
                'total_expenses' => $dayTotalExpenses,
                'net_balance' => $dayNetBalance,
                'breakdowns' => $dayBreakdowns,
            ];
        }

        foreach ([self::HEADING_TOTAL_SALES, self::HEADING_RENT_EXPENSE, self::HEADING_CASH_PURCHASE, self::HEADING_OTHER_EXPENSE] as $hKey) {
            $headingMonthlyTotals[$hKey] = round($headingMonthlyTotals[$hKey], 2);
        }

        $headingMonthlyTotals[self::HEADING_NET_OPERATING_BALANCE] = round(
            $headingMonthlyTotals[self::HEADING_TOTAL_SALES] - (
                $headingMonthlyTotals[self::HEADING_RENT_EXPENSE] +
                $headingMonthlyTotals[self::HEADING_CASH_PURCHASE] +
                $headingMonthlyTotals[self::HEADING_OTHER_EXPENSE]
            ),
            2
        );

        $summaryBreakdowns = $this->buildPeriodBreakdowns($shopId, $headings, $transactions, $startDate, $endDate, $headers, $settings);

        // Sort daily rows descending
        usort($dailyRows, fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return [
            'headings' => $headings,
            'summary' => [
                'total_sales' => $headingMonthlyTotals[self::HEADING_TOTAL_SALES],
                'total_rent' => $headingMonthlyTotals[self::HEADING_RENT_EXPENSE],
                'total_purchase' => $headingMonthlyTotals[self::HEADING_CASH_PURCHASE],
                'total_other_expense' => $headingMonthlyTotals[self::HEADING_OTHER_EXPENSE],
                'total_expenses' => round($headingMonthlyTotals[self::HEADING_RENT_EXPENSE] + $headingMonthlyTotals[self::HEADING_CASH_PURCHASE] + $headingMonthlyTotals[self::HEADING_OTHER_EXPENSE], 2),
                'net_total' => $headingMonthlyTotals[self::HEADING_NET_OPERATING_BALANCE],
                'gl_bills_total' => $glBillsTotal,
            ],
            'daily_rows' => $dailyRows,
            'heading_totals' => $headingMonthlyTotals,
            'summary_breakdowns' => $summaryBreakdowns,
        ];
    }

    /**
     * Build detailed breakdown structure for a period or day.
     *
     * @param  Collection<int, ShopLedgerTransaction>  $txs
     * @param  Collection<int, ShopLedgerHeaderGroup>  $headers
     * @param  Collection<int, ShopLedgerEntrySetting>  $settings
     * @return array<string, array{
     *     heading_key: string,
     *     title: string,
     *     is_balance: bool,
     *     total: float,
     *     sources?: array<int, array{
     *         type: string,
     *         name: string,
     *         total: float,
     *         categories: array<int, array{id: int, name: string, total: float}>,
     *         products: array<int, array{id: int|string, name: string, unit: string, qty: float, total: float}>
     *     }>,
     *     sales?: float,
     *     rent?: float,
     *     purchase?: float,
     *     other_expense?: float
     * }>
     */
    public function buildPeriodBreakdowns(
        int $shopId,
        array $headings,
        Collection $txs,
        string $startDate,
        string $endDate,
        Collection $headers,
        Collection $settings
    ): array {
        $settingsByEntryTypeId = $settings->keyBy('entry_type_id');
        $breakdowns = [];

        // Legacy direct-vendor product source. New product sources below are scoped to their selected header or payment type.
        $productItems = DB::table('purchaser_cart_items')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->leftJoin('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->join('products', 'products.id', '=', 'purchaser_cart_items.product_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where(function (Builder $q) use ($shopId): void {
                $q->where(function (Builder $sq) use ($shopId): void {
                    $sq->where('purchase_invoices.shop_id', $shopId)
                        ->orWhere('purchaser_carts.destination_shop_id', $shopId);
                })->where(function (Builder $sq): void {
                    $sq->where('purchase_invoices.purchase_source', 'shop')
                        ->orWhere('purchaser_carts.purchase_source', 'shop');
                });
            })
            ->whereDate('purchaser_carts.business_date', '>=', $startDate)
            ->whereDate('purchaser_carts.business_date', '<=', $endDate)
            ->selectRaw("
                products.id as product_id,
                products.name as product_name,
                COALESCE(products.unit, 'kg') as item_unit,
                SUM(purchaser_cart_items.quantity) as total_qty,
                SUM(purchaser_cart_items.line_total) as total_line
            ")
            ->groupBy('products.id', 'products.name', 'products.unit')
            ->orderByDesc('total_line')
            ->get();

        $headingTotalsMap = [];

        $taggedProductEntries = ShopLedgerProductEntry::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->get()
            ->groupBy('header_group_id');

        $vendorPurchaseSettingIds = [
            'cash' => $settings->filter(fn (ShopLedgerEntrySetting $setting): bool => $setting->isVendorPurchaseCash())->pluck('id')->all(),
            'credit' => $settings->filter(fn (ShopLedgerEntrySetting $setting): bool => $setting->isVendorPurchaseCredit())->pluck('id')->all(),
        ];

        foreach ([self::HEADING_TOTAL_SALES, self::HEADING_RENT_EXPENSE, self::HEADING_CASH_PURCHASE, self::HEADING_OTHER_EXPENSE] as $hKey) {
            $headingConfig = $headings[$hKey] ?? null;
            $sourcesConfig = $headingConfig['sources'] ?? [];

            $sourceBreakdowns = [];
            $headingTotal = 0.0;
            $claimedTxIds = [];

            foreach ($sourcesConfig as $src) {
                $srcType = (string) ($src['type'] ?? '');
                $srcId = $src['id'] ?? null;
                $srcName = (string) ($src['name'] ?? '');

                $categoriesList = [];
                $productsList = [];
                $sourceTotal = 0.0;

                if (in_array($srcType, ['header', self::SOURCE_HEADER_WITH_PRODUCT_TOTAL], true)) {
                    $header = $headers->get($srcId);
                    if ($header) {
                        foreach ($header->entrySettings as $es) {
                            $catTxs = $txs->filter(fn ($t) => (int) $t->entry_type_id === (int) $es->entry_type_id && ! isset($claimedTxIds[$t->id]));
                            foreach ($catTxs as $ct) {
                                $claimedTxIds[$ct->id] = true;
                            }
                            $catSum = round((float) $catTxs->sum('amount'), 2);

                            $sourceTotal += $catSum;
                            $categoriesList[] = [
                                'id' => (int) $es->id,
                                'name' => $es->displayName(),
                                'total' => $catSum,
                            ];
                        }

                        if ($srcType === self::SOURCE_HEADER_WITH_PRODUCT_TOTAL) {
                            $entries = $taggedProductEntries->get((int) $srcId, collect());
                            foreach ($entries->groupBy(fn (ShopLedgerProductEntry $entry): string => (string) ($entry->product_id ?: $entry->product_name)) as $productEntries) {
                                /** @var ShopLedgerProductEntry $firstEntry */
                                $firstEntry = $productEntries->first();
                                $productsList[] = [
                                    'id' => $firstEntry->product_id ?: $firstEntry->product_name,
                                    'name' => $firstEntry->product_name,
                                    'unit' => $firstEntry->unit ?: '',
                                    'qty' => (float) $productEntries->sum('quantity'),
                                    'total' => round((float) $productEntries->sum('amount'), 2),
                                ];
                            }
                            $sourceTotal += round((float) $entries->sum('amount'), 2);
                        }
                    }
                } elseif ($srcType === 'category') {
                    $setting = $settings->get($srcId);
                    if ($setting) {
                        $catTxs = $txs->filter(fn ($t) => (int) $t->entry_type_id === (int) $setting->entry_type_id && ! isset($claimedTxIds[$t->id]));
                        foreach ($catTxs as $ct) {
                            $claimedTxIds[$ct->id] = true;
                        }
                        $catSum = round((float) $catTxs->sum('amount'), 2);

                        $sourceTotal += $catSum;
                        $categoriesList[] = [
                            'id' => (int) $setting->id,
                            'name' => $setting->displayName(),
                            'total' => $catSum,
                        ];
                    }
                } elseif ($srcType === self::SOURCE_PRODUCT_TOTAL && is_numeric($srcId)) {
                    $entries = $taggedProductEntries->get((int) $srcId, collect());
                    foreach ($entries->groupBy(fn (ShopLedgerProductEntry $entry): string => (string) ($entry->product_id ?: $entry->product_name)) as $productEntries) {
                        /** @var ShopLedgerProductEntry $firstEntry */
                        $firstEntry = $productEntries->first();
                        $productsList[] = [
                            'id' => $firstEntry->product_id ?: $firstEntry->product_name,
                            'name' => $firstEntry->product_name,
                            'unit' => $firstEntry->unit ?: '',
                            'qty' => (float) $productEntries->sum('quantity'),
                            'total' => round((float) $productEntries->sum('amount'), 2),
                        ];
                    }
                    $sourceTotal = round((float) $entries->sum('amount'), 2);
                } elseif (in_array($srcType, [self::SOURCE_VENDOR_PURCHASE_CASH, self::SOURCE_VENDOR_PURCHASE_CREDIT], true)) {
                    $paymentType = $srcType === self::SOURCE_VENDOR_PURCHASE_CASH ? 'cash' : 'credit';
                    $settingIds = $vendorPurchaseSettingIds[$paymentType];
                    [$sourceTotal, $productsList] = $this->vendorPurchaseBreakdown($shopId, $startDate, $endDate, $settingIds);
                } elseif ($srcType === self::SOURCE_PRODUCT_TOTAL) {
                    $vpTxs = $txs->filter(function ($t) use ($settingsByEntryTypeId, $claimedTxIds): bool {
                        if (isset($claimedTxIds[$t->id])) {
                            return false;
                        }
                        $setting = $settingsByEntryTypeId->get($t->entry_type_id);

                        return (bool) ($setting?->is_vendor_purchase || in_array($t->entryType?->code, ['vendor_purchase_cash', 'vendor_purchase_credit'], true));
                    });
                    foreach ($vpTxs as $vt) {
                        $claimedTxIds[$vt->id] = true;
                    }
                    $vendorTxTotal = round((float) $vpTxs->sum('amount'), 2);

                    $prodSumTotal = 0.0;
                    if ($productItems->isNotEmpty()) {
                        foreach ($productItems as $pi) {
                            $pLineTotal = round((float) $pi->total_line, 2);
                            $prodSumTotal += $pLineTotal;
                            $productsList[] = [
                                'id' => $pi->product_id,
                                'name' => $pi->product_name,
                                'unit' => $pi->item_unit,
                                'qty' => (float) $pi->total_qty,
                                'total' => $pLineTotal,
                            ];
                        }
                    }

                    $sourceTotal = max($vendorTxTotal, round($prodSumTotal, 2));

                    if ($productItems->isNotEmpty() && $vendorTxTotal > $prodSumTotal) {
                        $diff = round($vendorTxTotal - $prodSumTotal, 2);
                        if ($diff > 0) {
                            $productsList[] = [
                                'id' => 'other',
                                'name' => 'Other',
                                'unit' => '',
                                'qty' => 0.0,
                                'total' => $diff,
                            ];
                        }
                    }
                }

                $sourceTotal = round($sourceTotal, 2);
                $headingTotal += $sourceTotal;

                $sourceBreakdowns[] = [
                    'type' => $srcType,
                    'name' => $srcName,
                    'total' => $sourceTotal,
                    'categories' => $categoriesList,
                    'products' => $productsList,
                ];
            }

            $headingTotal = round($headingTotal, 2);
            $headingTotalsMap[$hKey] = $headingTotal;

            $breakdowns[$hKey] = [
                'heading_key' => $hKey,
                'title' => match ($hKey) {
                    self::HEADING_TOTAL_SALES => 'SALES BREAKDOWN',
                    self::HEADING_RENT_EXPENSE => 'RENT BREAKDOWN',
                    self::HEADING_CASH_PURCHASE => 'PURCHASE BREAKDOWN',
                    self::HEADING_OTHER_EXPENSE => 'OTHER EXPENSES BREAKDOWN',
                    default => strtoupper((string) ($headingConfig['title'] ?? $hKey)),
                },
                'is_balance' => false,
                'total' => $headingTotal,
                'sources' => $sourceBreakdowns,
            ];
        }

        $netBalance = round(
            ($headingTotalsMap[self::HEADING_TOTAL_SALES] ?? 0.0) - (
                ($headingTotalsMap[self::HEADING_RENT_EXPENSE] ?? 0.0) +
                ($headingTotalsMap[self::HEADING_CASH_PURCHASE] ?? 0.0) +
                ($headingTotalsMap[self::HEADING_OTHER_EXPENSE] ?? 0.0)
            ),
            2
        );

        $breakdowns[self::HEADING_NET_OPERATING_BALANCE] = [
            'heading_key' => self::HEADING_NET_OPERATING_BALANCE,
            'title' => 'BALANCE CALCULATION',
            'is_balance' => true,
            'total' => $netBalance,
            'sales' => $headingTotalsMap[self::HEADING_TOTAL_SALES] ?? 0.0,
            'rent' => $headingTotalsMap[self::HEADING_RENT_EXPENSE] ?? 0.0,
            'purchase' => $headingTotalsMap[self::HEADING_CASH_PURCHASE] ?? 0.0,
            'other_expense' => $headingTotalsMap[self::HEADING_OTHER_EXPENSE] ?? 0.0,
        ];

        return $breakdowns;
    }

    /**
     * @param  array<int, int>  $settingIds
     * @return array{0: float, 1: array<int, array{id: int|string, name: string, unit: string, qty: float, total: float}>}
     */
    private function vendorPurchaseBreakdown(int $shopId, string $startDate, string $endDate, array $settingIds): array
    {
        if ($settingIds === []) {
            return [0.0, []];
        }

        $invoices = DB::table('purchase_invoices')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->where('purchase_invoices.shop_id', $shopId)
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where('purchase_invoices.purchase_source', 'shop')
            ->whereIn('purchase_invoices.shop_ledger_entry_setting_id', $settingIds)
            ->whereBetween('purchaser_carts.business_date', [$startDate, $endDate])
            ->get(['purchase_invoices.id', 'purchase_invoices.amount', 'purchase_invoices.purchaser_cart_id']);

        if ($invoices->isEmpty()) {
            return [0.0, []];
        }

        $products = DB::table('purchaser_cart_items')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->join('products', 'products.id', '=', 'purchaser_cart_items.product_id')
            ->whereIn('purchaser_carts.id', $invoices->pluck('purchaser_cart_id'))
            ->selectRaw("products.id as product_id, products.name as product_name, COALESCE(products.unit, '') as item_unit, SUM(purchaser_cart_items.quantity) as total_qty, SUM(purchaser_cart_items.line_total) as total_line")
            ->groupBy('products.id', 'products.name', 'products.unit')
            ->orderByDesc('total_line')
            ->get()
            ->map(fn ($product): array => [
                'id' => $product->product_id,
                'name' => $product->product_name,
                'unit' => $product->item_unit,
                'qty' => (float) $product->total_qty,
                'total' => round((float) $product->total_line, 2),
            ])
            ->all();

        return [round((float) $invoices->sum('amount'), 2), $products];
    }
}
