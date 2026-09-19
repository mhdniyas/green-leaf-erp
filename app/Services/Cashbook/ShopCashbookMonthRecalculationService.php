<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopCashbookMonthConfigSnapshot;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShopCashbookMonthRecalculationService
{
    public function __construct(
        private readonly ShopCashbookMonthConfigService $monthConfigService,
        private readonly BalanceCalculator $balanceCalculator,
        private readonly RelationSettlementCalculator $relationCalculator,
    ) {}

    /**
     * Refresh and recalculate all Cashbook calculations for a shop for a given month.
     *
     * @return array{
     *     success: bool,
     *     shop_id: int,
     *     month: string,
     *     days_processed: int,
     *     categories_processed: int,
     *     relations_processed: int,
     *     recalculated_at: Carbon,
     *     formatted_recalculated_at: string,
     *     summary: array<string, mixed>
     * }
     */
    public function recalculateMonth(int|Shop $shop, string $month, ?int $userId = null): array
    {
        $shopId = $shop instanceof Shop ? (int) $shop->id : (int) $shop;

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new RuntimeException("Invalid month format [{$month}]. Expected YYYY-MM.");
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
        $isCurrentMonth = ($month === Carbon::now()->format('Y-m'));

        return DB::transaction(function () use ($shopId, $month, $monthStart, $monthEnd, $isCurrentMonth, $userId): array {
            // 1. Resolve configuration (live for current month, snapshot for historical month)
            $monthConfig = $this->monthConfigService->getConfigurationForMonth($shopId, $month);

            /** @var EloquentCollection<int, ShopLedgerEntrySetting> $settings */
            $settings = $monthConfig['settings'] ?? new EloquentCollection;
            /** @var EloquentCollection<int, ShopLedgerHeaderGroup> $headers */
            $headers = $monthConfig['headers'] ?? new EloquentCollection;
            /** @var EloquentCollection<int, ShopCashbookRelation> $relations */
            $relations = $monthConfig['relations'] ?? new EloquentCollection;

            $settingsByEntryTypeId = $settings->keyBy('entry_type_id');
            $settingsById = $settings->keyBy('id');

            // 2. Query all source transactions for the month using business_date
            $transactions = ShopLedgerTransaction::query()
                ->with(['entryType', 'companyAccount'])
                ->where('shop_id', $shopId)
                ->whereBetween('business_date', [$monthStart, $monthEnd])
                ->whereNotIn('status', ['void', 'voided', 'reversed'])
                ->whereNull('voided_at')
                ->lockForUpdate()
                ->get();

            // 3. Re-derive daily ledger balance snapshots across the month in chronological order
            $distinctDates = $transactions->pluck('business_date')
                ->map(fn ($d): string => $d instanceof CarbonInterface ? $d->toDateString() : (string) $d)
                ->unique()
                ->sort()
                ->values();

            $daysProcessed = 0;
            foreach ($distinctDates as $dateStr) {
                $this->balanceCalculator->recalculate($shopId, $dateStr);
                $daysProcessed++;
            }

            // 4. Calculate Category & Header Totals
            $salesTotal = 0.0;
            $incomeTotal = 0.0;
            $expenseTotal = 0.0;
            $vendorPurchaseCashTotal = 0.0;
            $vendorPurchaseCreditTotal = 0.0;
            $categoryAmounts = [];
            $categoryFundingSplits = [];

            foreach ($settings as $setting) {
                $categoryAmounts[$setting->id] = 0.0;
                $categoryFundingSplits[$setting->id] = [];
            }

            foreach ($transactions as $tx) {
                $entryTypeId = (int) $tx->entry_type_id;
                $setting = $settingsByEntryTypeId->get($entryTypeId);
                $amount = (float) $tx->amount;
                $fundingSource = (string) ($tx->funding_source ?: 'sales');

                if ($setting) {
                    $categoryAmounts[$setting->id] = ($categoryAmounts[$setting->id] ?? 0.0) + $amount;
                    $categoryFundingSplits[$setting->id][$fundingSource] = ($categoryFundingSplits[$setting->id][$fundingSource] ?? 0.0) + $amount;

                    if ($setting->is_vendor_purchase) {
                        if ($setting->vendor_purchase_payment_type === 'cash' || $fundingSource === 'shop_cash' || $fundingSource === 'cash') {
                            $vendorPurchaseCashTotal += $amount;
                        } else {
                            $vendorPurchaseCreditTotal += $amount;
                        }
                    }

                    if ($setting->include_in_sales || $setting->include_in_income) {
                        $incomeTotal += $amount;
                        if ($setting->include_in_sales) {
                            $salesTotal += $amount;
                        }
                    } elseif ($setting->include_in_expense) {
                        $expenseTotal += $amount;
                    }
                } else {
                    if ($tx->direction === 'income' || $tx->affects_income || $tx->affects_sales) {
                        $incomeTotal += $amount;
                        if ($tx->affects_sales) {
                            $salesTotal += $amount;
                        }
                    } elseif ($tx->direction === 'expense' || $tx->affects_expense) {
                        $expenseTotal += $amount;
                    }
                }
            }

            // 5. Calculate Header Totals
            $headerTotals = [];
            foreach ($headers as $header) {
                $headerSettings = $settings->where('header_group_id', $header->id);
                $hTotal = 0.0;
                $childBreakdown = [];
                foreach ($headerSettings as $hs) {
                    $hsAmt = (float) ($categoryAmounts[$hs->id] ?? 0.0);
                    $hTotal += $hsAmt;
                    $childBreakdown[] = [
                        'setting_id' => $hs->id,
                        'name' => $hs->displayName(),
                        'amount' => $hsAmt,
                        'funding_split' => $categoryFundingSplits[$hs->id] ?? [],
                    ];
                }
                $headerTotals[$header->id] = [
                    'header_id' => $header->id,
                    'name' => $header->name,
                    'type' => $header->type,
                    'total' => round($hTotal, 2),
                    'categories' => $childBreakdown,
                ];
            }

            // 6. Calculate Relation & Settlement Breakdown
            $relationResults = [];
            foreach ($relations as $relation) {
                $relCalc = $this->relationCalculator->calculate($relation, $categoryAmounts);
                $relationResults[$relation->id] = [
                    'relation_id' => $relation->id,
                    'name' => $relation->name,
                    'type' => $relation->type ?? $relation->relation_type,
                    'gross_additions' => round((float) ($relCalc['gross_additions'] ?? $relCalc['grossAdditions'] ?? 0.0), 2),
                    'gross_deductions' => round((float) ($relCalc['gross_deductions'] ?? $relCalc['grossDeductions'] ?? 0.0), 2),
                    'net_settlement' => round((float) ($relCalc['net_settlement'] ?? $relCalc['netSettlement'] ?? 0.0), 2),
                    'settled_amount' => round((float) ($relCalc['settled_amount'] ?? $relCalc['settledAmount'] ?? 0.0), 2),
                    'remaining_due' => round((float) ($relCalc['remaining_settlement_payable'] ?? $relCalc['remainingSettlementPayable'] ?? 0.0), 2),
                ];
            }

            // 7. Calculate Petty Position
            $openingPetty = (float) (ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shopId)
                ->where('business_date', '<', $monthStart)
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->value('closing_petty') ?? 0.0);

            $pettyIn = (float) $transactions->where('petty_delta', '>', 0)->sum('petty_delta');
            $pettyOut = abs((float) $transactions->where('petty_delta', '<', 0)->sum('petty_delta'));
            $closingPetty = (float) (ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shopId)
                ->where('business_date', '<=', $monthEnd)
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->value('closing_petty') ?? ($openingPetty + $pettyIn - $pettyOut));

            $recalcSummary = [
                'sales_total' => round($salesTotal, 2),
                'income_total' => round($incomeTotal, 2),
                'expense_total' => round($expenseTotal, 2),
                'net_sales' => round($salesTotal - $expenseTotal, 2),
                'vendor_purchase_cash' => round($vendorPurchaseCashTotal, 2),
                'vendor_purchase_credit' => round($vendorPurchaseCreditTotal, 2),
                'petty' => [
                    'opening' => round($openingPetty, 2),
                    'funded' => round($pettyIn, 2),
                    'used' => round($pettyOut, 2),
                    'closing' => round($closingPetty, 2),
                ],
                'headers' => $headerTotals,
                'relations' => $relationResults,
                'days_processed' => $daysProcessed,
                'categories_processed' => $settings->count(),
                'relations_processed' => $relations->count(),
            ];

            $now = Carbon::now();

            // 8. Update or create snapshot with recalculated_at and recalculation_summary
            $snapshot = ShopCashbookMonthConfigSnapshot::query()
                ->where('shop_id', $shopId)
                ->where('month', $month)
                ->first();

            if ($snapshot) {
                $snapshot->update([
                    'recalculated_at' => $now,
                    'recalculation_summary' => $recalcSummary,
                ]);
            } else {
                $snapshot = $this->monthConfigService->captureSnapshot(
                    $shopId,
                    $month,
                    $isCurrentMonth ? 'live_frozen' : 'legacy_reconstruction',
                    false,
                    $userId,
                    'Recalculated on '.$now->toDateTimeString()
                );
                $snapshot->update([
                    'recalculated_at' => $now,
                    'recalculation_summary' => $recalcSummary,
                ]);
            }

            return [
                'success' => true,
                'shop_id' => $shopId,
                'month' => $month,
                'days_processed' => $daysProcessed,
                'categories_processed' => $settings->count(),
                'relations_processed' => $relations->count(),
                'recalculated_at' => $now,
                'formatted_recalculated_at' => $now->format('d M Y h:i A'),
                'summary' => $recalcSummary,
            ];
        }, attempts: 3);
    }
}
