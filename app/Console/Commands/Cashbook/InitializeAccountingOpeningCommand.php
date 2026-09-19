<?php

declare(strict_types=1);

namespace App\Console\Commands\Cashbook;

use App\Models\Cashbook\ShopAccountingOpening;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\Cashbook\MonthlyClosingSummaryService;
use App\Services\Cashbook\ShopAccountingOpeningService;
use App\Services\Cashbook\ShopPaymentLedgerReconciliationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InitializeAccountingOpeningCommand extends Command
{
    protected $signature = 'cashbook:initialize-accounting-opening
        {--date=2026-09-01 : Accounting start date (YYYY-MM-DD)}
        {--shop= : Specific shop code or ID}
        {--dry-run : Simulate initialization without modifying data}
        {--opening-petty=0.00 : Default opening petty balance}
        {--skip-cross-period-reversal : Skip reversing cross-period allocations spanning across the start date}
        {--skip-snapshot-recalculation : Skip recalculating daily ledger snapshots from start date to current date}';

    protected $description = 'Initialize clean ₹0 Shop Cashbook accounting opening as of a specified date (default: 2026-09-01), with dry-run support, cross-period allocation reversal, and snapshot recalculation.';

    public function handle(
        ShopAccountingOpeningService $openingService,
        ShopPaymentLedgerReconciliationService $reconciliationService,
        MonthlyClosingSummaryService $monthlyClosingService,
        BalanceCalculator $balanceCalculator
    ): int {
        $startDateStr = (string) ($this->option('date') ?: '2026-09-01');
        $dryRun = (bool) $this->option('dry-run');
        $shopFilter = $this->option('shop');
        $defaultOpeningPetty = (float) $this->option('opening-petty');
        $shouldReverseCrossPeriod = ! (bool) $this->option('skip-cross-period-reversal');
        $shouldRecalculateSnapshots = ! (bool) $this->option('skip-snapshot-recalculation');

        try {
            $startDate = Carbon::parse($startDateStr)->startOfDay();
        } catch (\Throwable $e) {
            $this->error("Invalid --date format '{$startDateStr}'. Use YYYY-MM-DD.");

            return self::FAILURE;
        }

        $shopsQuery = Shop::query()->where('status', 'active');
        if (filled($shopFilter)) {
            $shopsQuery->where(function ($q) use ($shopFilter) {
                $q->where('id', $shopFilter)->orWhere('code', $shopFilter);
            });
        }
        $shops = $shopsQuery->orderBy('name')->get();

        if ($shops->isEmpty()) {
            $this->warn('No matching active shops found.');

            return self::SUCCESS;
        }

        $this->info('================================================================================');
        $this->info('Shop Cashbook Accounting Opening Initialization');
        $this->info("Target Accounting Start Date: {$startDate->toDateString()}");
        $this->info('Mode: '.($dryRun ? 'DRY-RUN (Simulated — No changes will be written)' : 'LIVE EXECUTION'));
        $this->info('================================================================================');

        $tableRows = [];
        $totalCrossPeriodAllocations = 0;
        $totalCrossPeriodAmount = 0.0;
        $totalSeptemberTxCount = 0;

        $shopData = [];

        foreach ($shops as $shop) {
            // 1. August closing snapshot (or latest pre-start snapshot)
            $preOpeningSnapshot = ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shop->id)
                ->where('business_date', '<', $startDate->toDateString())
                ->orderByDesc('business_date')
                ->first();

            $augClosingPosition = (float) ($preOpeningSnapshot?->closing_shop_position ?? 0.0);
            $oldSepOpening = $augClosingPosition;
            $newSepOpening = 0.0;

            // 2. Old carried allocation credit
            $augustMonth = $startDate->copy()->subDay()->format('Y-m');
            $augustSummary = $monthlyClosingService->getShopMonthlyDetail((int) $shop->id, $augustMonth);
            $oldCarriedCredit = (float) ($augustSummary['carried_excess_credit'] ?? 0.0);
            $newAllocationOpening = 0.0;

            // 3. Petty opening
            $existingOpening = ShopAccountingOpening::query()
                ->where('shop_id', $shop->id)
                ->where('accounting_start_date', $startDate->toDateString())
                ->first();
            $pettyOpening = $existingOpening !== null
                ? (float) $existingOpening->opening_petty_balance
                : $defaultOpeningPetty;

            // 4. Cross-period allocations requiring reversal
            // Pre-September payment allocated to >= September transaction, OR >= September payment allocated to pre-September transaction
            $crossPeriodAllocations = ShopPaymentLedgerAllocation::query()
                ->where('shop_id', $shop->id)
                ->active()
                ->where(function ($q) use ($startDate) {
                    $q->where(function ($sub) use ($startDate) {
                        $sub->whereHas('paymentRequest', fn ($p) => $p->whereDate('payment_date', '<', $startDate->toDateString()))
                            ->whereHas('ledgerTransaction', fn ($t) => $t->whereDate('business_date', '>=', $startDate->toDateString()));
                    })->orWhere(function ($sub) use ($startDate) {
                        $sub->whereHas('paymentRequest', fn ($p) => $p->whereDate('payment_date', '>=', $startDate->toDateString()))
                            ->whereHas('ledgerTransaction', fn ($t) => $t->whereDate('business_date', '<', $startDate->toDateString()));
                    });
                })
                ->get();

            $crossPeriodCount = $crossPeriodAllocations->count();
            $crossPeriodAmount = (float) $crossPeriodAllocations->sum('amount');
            $totalCrossPeriodAllocations += $crossPeriodCount;
            $totalCrossPeriodAmount += $crossPeriodAmount;

            // 5. Existing September transaction count and net activity
            $sepTransactions = ShopLedgerTransaction::query()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', '>=', $startDate->toDateString())
                ->whereNotIn('status', ['void', 'voided'])
                ->get();

            $sepTxCount = $sepTransactions->count();
            $totalSeptemberTxCount += $sepTxCount;

            $sepNetDelta = (float) $sepTransactions->sum('settlement_delta');
            $expectedSepBaseline = round($newSepOpening + $sepNetDelta, 2);

            $shopData[] = [
                'shop' => $shop,
                'aug_closing' => $augClosingPosition,
                'old_sep_opening' => $oldSepOpening,
                'new_sep_opening' => $newSepOpening,
                'old_carried_credit' => $oldCarriedCredit,
                'new_alloc_opening' => $newAllocationOpening,
                'petty_opening' => $pettyOpening,
                'cross_period_allocations' => $crossPeriodAllocations,
                'cross_period_count' => $crossPeriodCount,
                'cross_period_amount' => $crossPeriodAmount,
                'sep_tx_count' => $sepTxCount,
                'expected_sep_baseline' => $expectedSepBaseline,
            ];

            $tableRows[] = [
                'Shop' => $shop->name." ({$shop->code})",
                'Aug Closing' => '₹'.number_format($augClosingPosition, 2),
                'Old Sep Open' => '₹'.number_format($oldSepOpening, 2),
                'New Sep Open' => '₹'.number_format($newSepOpening, 2),
                'Old Carried Credit' => '₹'.number_format($oldCarriedCredit, 2),
                'New Alloc Open' => '₹'.number_format($newAllocationOpening, 2),
                'Petty Open' => '₹'.number_format($pettyOpening, 2),
                'Cross-Period Alloc' => $crossPeriodCount > 0 ? "{$crossPeriodCount} (₹".number_format($crossPeriodAmount, 2).')' : '0',
                'Sep Txns' => (string) $sepTxCount,
                'Expected Sep Baseline' => '₹'.number_format($expectedSepBaseline, 2),
            ];
        }

        $this->table(
            ['Shop', 'Aug Closing', 'Old Sep Open', 'New Sep Open', 'Old Carried Credit', 'New Alloc Open', 'Petty Open', 'Cross-Period Alloc', 'Sep Txns', 'Expected Sep Baseline'],
            $tableRows
        );

        $this->info('Summary:');
        $this->line('- Active Shops Affected: '.count($shops));
        $this->line("- Cross-Period Allocations to Reverse: {$totalCrossPeriodAllocations} (Total: ₹".number_format($totalCrossPeriodAmount, 2).')');
        $this->line("- Total Existing September Transactions Preserved: {$totalSeptemberTxCount}");

        if ($dryRun) {
            $this->warn("\n[DRY-RUN] No changes were made to the database. Run without --dry-run to apply this cutoff initialization.");

            return self::SUCCESS;
        }

        // Live Execution
        $this->info("\nApplying changes within database transaction...");

        DB::transaction(function () use ($shopData, $startDate, $shouldReverseCrossPeriod, $shouldRecalculateSnapshots, $openingService, $reconciliationService, $balanceCalculator) {
            foreach ($shopData as $data) {
                /** @var Shop $shop */
                $shop = $data['shop'];

                // 1. Create or update shop accounting opening record
                $openingService->setOpening(
                    shop: $shop,
                    startDate: $startDate->toDateString(),
                    attributes: [
                        'opening_shop_company_balance' => 0.00,
                        'opening_balance_direction' => 'settled',
                        'opening_allocation_pending' => 0.00,
                        'opening_petty_balance' => (float) $data['petty_opening'],
                        'notes' => "Official accounting start date initialization for {$startDate->format('F Y')} opening.",
                    ],
                    userId: null
                );

                // 2. Reverse cross-period allocations if any
                if ($shouldReverseCrossPeriod && $data['cross_period_count'] > 0) {
                    foreach ($data['cross_period_allocations'] as $allocation) {
                        $reconciliationService->reverseAllocation(
                            allocation: $allocation,
                            userId: null,
                            reason: "Cutoff {$startDate->toDateString()}: cross-period allocation reversed to isolate {$startDate->format('F Y')} opening from pre-opening period."
                        );
                    }
                }

                // 3. Recalculate daily ledger snapshots from startDate to today
                if ($shouldRecalculateSnapshots) {
                    $today = today();
                    $period = CarbonPeriod::create($startDate, $today);
                    foreach ($period as $date) {
                        $balanceCalculator->recalculate((int) $shop->id, $date->toDateString());
                    }
                }
            }
        });

        $this->info("✓ Successfully initialized accounting opening as of {$startDate->toDateString()} for ".count($shops).' shops.');
        $this->info("✓ Reversed {$totalCrossPeriodAllocations} cross-period allocations.");
        $this->info("✓ Recalculated daily snapshots from {$startDate->toDateString()} to today.");

        return self::SUCCESS;
    }
}
