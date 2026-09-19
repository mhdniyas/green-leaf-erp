<?php

declare(strict_types=1);

namespace App\Console\Commands\Cashbook;

use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\VendorSettlementAllocation;
use App\Services\Finance\PurchaseFinanceAccountingOpeningService;
use App\Services\Finance\PurchaserSettlementService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InitializePurchaseFinanceAccountingOpeningCommand extends Command
{
    protected $signature = 'cashbook:initialize-purchase-finance-accounting-opening
        {--date=2026-09-01 : Accounting start date (YYYY-MM-DD)}
        {--purchaser= : Specific purchaser ID, UUID, or email}
        {--dry-run : Simulate initialization without modifying data}
        {--skip-cross-period-reversal : Skip reversing cross-period allocations spanning across the start date}';

    protected $description = 'Initialize Purchaser & Vendor Finance September 2026 accounting opening with continuous credit carry-forward, zero settlement opening, and auditable cross-period allocation cutoff.';

    public function handle(
        PurchaseFinanceAccountingOpeningService $openingService,
        PurchaserSettlementService $settlementService
    ): int {
        $startDateStr = (string) ($this->option('date') ?: PurchaseFinanceAccountingOpeningService::DEFAULT_ACCOUNTING_START_DATE);
        $dryRun = (bool) $this->option('dry-run');
        $purchaserFilter = $this->option('purchaser');
        $shouldReverseCrossPeriod = ! (bool) $this->option('skip-cross-period-reversal');

        try {
            $startDate = Carbon::parse($startDateStr)->startOfDay();
        } catch (\Throwable) {
            $this->error("Invalid --date format '{$startDateStr}'. Use YYYY-MM-DD.");

            return self::FAILURE;
        }

        $purchasersQuery = User::query()
            ->whereHas('roles', fn ($r) => $r->where('name', 'purchaser'))
            ->whereNull('shop_id');

        if (filled($purchaserFilter)) {
            $purchasersQuery->where(function ($q) use ($purchaserFilter): void {
                $q->where('id', $purchaserFilter)
                    ->orWhere('public_uuid', $purchaserFilter)
                    ->orWhere('email', $purchaserFilter);
            });
        }

        $purchasers = $purchasersQuery->orderBy('name')->get();

        if ($purchasers->isEmpty()) {
            $this->warn('No matching active purchasers found.');

            return self::SUCCESS;
        }

        $this->info('================================================================================');
        $this->info('Purchaser & Vendor Finance Accounting Opening Initialization');
        $this->info("Target Accounting Start Date: {$startDate->toDateString()}");
        $this->info('Mode: '.($dryRun ? 'DRY-RUN (Simulated — No changes will be written)' : 'LIVE EXECUTION'));
        $this->info('================================================================================');

        $purchaserTableRows = [];
        $purchaserData = [];

        foreach ($purchasers as $purchaser) {
            $purchaserId = (int) $purchaser->id;

            // 1. August 31 closing credit balance (continuous credit carry-forward)
            $augCredit = $settlementService->openingCreditBalanceBefore($purchaserId, $startDate->toDateString());
            $augCreditBalance = (float) ($augCredit['credit_balance'] ?? 0.0);

            // 2. August settlement position
            $augSettlement = $settlementService->openingBalanceBefore($purchaserId, $startDate->toDateString());
            $augSettlementBalance = (float) ($augSettlement['advance'] ?? 0.0);

            // 3. Existing September activity
            $nextMonthDate = $startDate->copy()->addMonth()->toDateString();
            $sepBills = DB::table('purchase_invoices')
                ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
                ->whereNull('purchase_invoices.deleted_at')
                ->where('purchase_invoices.status', '!=', 'cancelled')
                ->where(function ($q) use ($purchaserId): void {
                    $q->where('purchase_invoices.purchaser_submitted_by', $purchaserId)
                        ->orWhere('purchaser_carts.user_id', $purchaserId);
                })
                ->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.original_business_date), DATE(purchase_invoices.created_at)) >= ?', [$startDate->toDateString()])
                ->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.original_business_date), DATE(purchase_invoices.created_at)) < ?', [$nextMonthDate])
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(purchase_invoices.amount - purchase_invoices.discount_amount), 0) as total')
                ->first();

            $sepCredits = DB::table('purchaser_credits')
                ->where('purchaser_id', $purchaserId)
                ->whereDate('business_date', '>=', $startDate->toDateString())
                ->whereDate('business_date', '<', $nextMonthDate)
                ->selectRaw('
                    COALESCE(SUM(CASE WHEN type = "in" THEN amount ELSE 0 END), 0) as added,
                    COALESCE(SUM(CASE WHEN type = "out" THEN amount ELSE 0 END), 0) as used
                ')
                ->first();

            $sepBillCount = (int) ($sepBills->cnt ?? 0);
            $sepBillTotal = (float) ($sepBills->total ?? 0);
            $sepCreditAdded = (float) ($sepCredits->added ?? 0);
            $sepCreditUsed = (float) ($sepCredits->used ?? 0);

            $purchaserData[] = [
                'purchaser' => $purchaser,
                'aug_credit_balance' => $augCreditBalance,
                'aug_settlement_balance' => $augSettlementBalance,
                'sep_opening_balance' => 0.0,
                'sep_opening_credit' => $augCreditBalance,
                'sep_bill_count' => $sepBillCount,
                'sep_bill_total' => $sepBillTotal,
                'sep_credit_added' => $sepCreditAdded,
                'sep_credit_used' => $sepCreditUsed,
            ];

            $purchaserTableRows[] = [
                $purchaser->name,
                '₹'.number_format($augSettlementBalance, 2),
                '₹0.00 (Settled)',
                '₹'.number_format($augCreditBalance, 2),
                '₹'.number_format($augCreditBalance, 2),
                "{$sepBillCount} (₹".number_format($sepBillTotal, 2).')',
                '₹'.number_format($sepCreditUsed, 2),
            ];
        }

        $this->table([
            'Purchaser',
            'Aug 31 Settlement',
            'Sep 01 Opening Settled',
            'Aug 31 Credit Closing',
            'Sep 01 Opening Credit',
            'Sep Bills (Count / Total)',
            'Sep Credit Used',
        ], $purchaserTableRows);

        // 4. Cross-period vendor settlement allocations
        $crossPeriodQuery = VendorSettlementAllocation::query()
            ->with(['settlement.supplier', 'purchaseInvoice.purchaserCart'])
            ->active()
            ->where(function ($q) use ($startDate): void {
                $q->where(function ($sub) use ($startDate): void {
                    $sub->whereHas('settlement', fn ($s) => $s->whereDate('payment_date', '>=', $startDate->toDateString()))
                        ->whereHas('purchaseInvoice', function ($i) use ($startDate): void {
                            $i->whereRaw('COALESCE(DATE(original_business_date), DATE(created_at)) < ?', [$startDate->toDateString()]);
                        });
                })->orWhere(function ($sub) use ($startDate): void {
                    $sub->whereHas('settlement', fn ($s) => $s->whereDate('payment_date', '<', $startDate->toDateString()))
                        ->whereHas('purchaseInvoice', function ($i) use ($startDate): void {
                            $i->whereRaw('COALESCE(DATE(original_business_date), DATE(created_at)) >= ?', [$startDate->toDateString()]);
                        });
                });
            });

        $crossPeriodAllocations = $crossPeriodQuery->get();
        $crossPeriodCount = $crossPeriodAllocations->count();
        $crossPeriodTotal = (float) $crossPeriodAllocations->sum('total_settled');

        $this->newLine();
        $this->info("Found {$crossPeriodCount} active cross-period vendor settlement allocation(s) totaling ₹".number_format($crossPeriodTotal, 2).'.');

        if ($crossPeriodCount > 0) {
            $sampleRows = $crossPeriodAllocations->take(15)->map(fn ($a) => [
                $a->id,
                $a->settlement?->supplier?->name ?? '—',
                $a->settlement?->payment_date?->toDateString() ?? '—',
                $a->purchaseInvoice?->invoice_number ?? '—',
                $a->purchaseInvoice?->original_business_date?->toDateString() ?? $a->purchaseInvoice?->created_at?->toDateString() ?? '—',
                '₹'.number_format((float) $a->total_settled, 2),
            ]);

            $this->table([
                'Alloc ID',
                'Vendor',
                'Settlement Date',
                'Invoice #',
                'Invoice Date',
                'Total Settled',
            ], $sampleRows);
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('[DRY-RUN] No database modifications were made.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Applying database updates in transaction...');

        DB::transaction(function () use ($purchaserData, $startDate, $openingService, $crossPeriodAllocations, $shouldReverseCrossPeriod): void {
            // A. Set purchaser openings
            foreach ($purchaserData as $pData) {
                $purchaser = $pData['purchaser'];
                $openingService->setPurchaserOpening(
                    $purchaser,
                    $startDate->toDateString(),
                    [
                        'opening_balance' => 0.00,
                        'opening_balance_direction' => 'settled',
                        'opening_credit_balance' => $pData['sep_opening_credit'],
                        'opening_credit_outstanding' => 0.00,
                        'opening_advance_credit' => 0.00,
                        'notes' => 'September 2026 Purchaser Opening — Continuous Credit & Zero Settlement',
                    ]
                );
            }

            // B. Reversal of cross-period vendor allocations if requested
            if ($shouldReverseCrossPeriod && $crossPeriodAllocations->isNotEmpty()) {
                foreach ($crossPeriodAllocations as $alloc) {
                    $alloc->reverse(
                        null,
                        'September 2026 accounting start boundary cutoff'
                    );

                    // Update invoice paid_amount if cash was allocated
                    if ((float) $alloc->cash_allocated > 0) {
                        $invoice = $alloc->purchaseInvoice;
                        if ($invoice instanceof PurchaseInvoice) {
                            $invoice->update([
                                'paid_amount' => round(max(0, (float) $invoice->paid_amount - (float) $alloc->cash_allocated), 2),
                                'payment_status' => $invoice->remainingBalance() <= 0.01 ? 'paid' : ($invoice->paid_amount > 0 ? 'partial' : 'unpaid'),
                            ]);
                        }
                    }
                }
            }
        });

        $this->info('Purchaser & Vendor Finance September 2026 Accounting Opening initialized successfully.');

        return self::SUCCESS;
    }
}
