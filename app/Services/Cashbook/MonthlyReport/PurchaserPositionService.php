<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport;

use App\Models\OtherExpense;
use App\Models\ProcurementExpense;
use App\Models\PurchaserCredit;
use App\Models\User;
use App\Services\Purchasing\PurchaseReportingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class PurchaserPositionService
{
    public function __construct(
        private readonly PurchaseReportingService $purchaseReportingService,
    ) {}

    /**
     * @return array<int, array{
     *     purchaser_id: int,
     *     purchaser_name: string,
     *     opening_position: float,
     *     cash_given: float,
     *     cash_purchases: float,
     *     purchaser_expenses: float,
     *     cash_returned: float,
     *     closing_position: float,
     *     credit_purchases: float,
     *     total_purchases: float,
     *     status: string,
     *     status_class: string
     * }>
     */
    public function calculate(string $startDate, string $endDate): array
    {
        $purchasers = User::query()
            ->where(function ($query): void {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', 'purchaser'))
                    ->orWhereIn('id', DB::table('purchaser_carts')->select('user_id'))
                    ->orWhereIn('id', DB::table('purchaser_credits')->select('purchaser_id'));
            })
            ->orderBy('name')
            ->get();

        $rows = [];

        // 1. Fetch In-Period Purchases by purchaser from PurchaseReportingService
        $items = $this->purchaseReportingService->filteredItems([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ])->get();

        $purchasesByPurchaser = [];
        foreach ($items as $it) {
            $pId = (int) $it->purchaser_id;
            if (! isset($purchasesByPurchaser[$pId])) {
                $purchasesByPurchaser[$pId] = ['cash' => 0.0, 'credit' => 0.0];
            }
            $net = (float) $it->item_net;
            if ($it->payment_class === 'credit') {
                $purchasesByPurchaser[$pId]['credit'] = round($purchasesByPurchaser[$pId]['credit'] + $net, 2);
            } else {
                $purchasesByPurchaser[$pId]['cash'] = round($purchasesByPurchaser[$pId]['cash'] + $net, 2);
            }
        }

        // 2. Fetch In-Period Purchaser Expenses (funding_source = purchaser_advance)
        $procExpensesInPeriod = ProcurementExpense::query()
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->where('funding_source', 'purchaser_advance')
            ->selectRaw('user_id as purchaser_id, SUM(amount) as total_amount')
            ->groupBy('user_id')
            ->pluck('total_amount', 'purchaser_id');

        $otherExpensesInPeriod = OtherExpense::query()
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->where('funding_source', 'purchaser_advance')
            ->selectRaw('user_id as purchaser_id, SUM(amount) as total_amount')
            ->groupBy('user_id')
            ->pluck('total_amount', 'purchaser_id');

        // 3. Fetch Prior Movements before startDate for Opening Balance
        $priorCredits = DB::table('purchaser_credits')
            ->whereDate('business_date', '<', $startDate)
            ->selectRaw("
                purchaser_id,
                SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out
            ")
            ->groupBy('purchaser_id')
            ->get()
            ->keyBy('purchaser_id');

        $priorProcExpenses = ProcurementExpense::query()
            ->whereDate('expense_date', '<', $startDate)
            ->where('funding_source', 'purchaser_advance')
            ->selectRaw('user_id as purchaser_id, SUM(amount) as total_amount')
            ->groupBy('user_id')
            ->pluck('total_amount', 'purchaser_id');

        $priorOtherExpenses = OtherExpense::query()
            ->whereDate('expense_date', '<', $startDate)
            ->where('funding_source', 'purchaser_advance')
            ->selectRaw('user_id as purchaser_id, SUM(amount) as total_amount')
            ->groupBy('user_id')
            ->pluck('total_amount', 'purchaser_id');

        // 4. In-period Purchaser Credits (inclusive start & end dates)
        $periodCredits = DB::table('purchaser_credits')
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->selectRaw("
                purchaser_id,
                SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as cash_given,
                SUM(CASE WHEN type = 'out' AND purchase_invoice_id IS NULL THEN amount ELSE 0 END) as cash_returned,
                SUM(CASE WHEN type = 'out' AND purchase_invoice_id IS NOT NULL THEN amount ELSE 0 END) as invoice_out
            ")
            ->groupBy('purchaser_id')
            ->get()
            ->keyBy('purchaser_id');

        foreach ($purchasers as $purchaser) {
            $pId = (int) $purchaser->id;

            // Opening Position
            $priorIn = (float) ($priorCredits->get($pId)->total_in ?? 0.0);
            $priorOut = (float) ($priorCredits->get($pId)->total_out ?? 0.0);
            $priorExp = (float) ($priorProcExpenses->get($pId) ?? 0.0) + (float) ($priorOtherExpenses->get($pId) ?? 0.0);
            $openingPosition = round($priorIn - $priorOut - $priorExp, 2);

            // In Period Movements
            $creditRow = $periodCredits->get($pId);
            $cashGiven = round((float) ($creditRow->cash_given ?? 0.0), 2);
            $cashReturned = round((float) ($creditRow->cash_returned ?? 0.0), 2);

            $cashPurchases = round((float) ($purchasesByPurchaser[$pId]['cash'] ?? 0.0), 2);
            $creditPurchases = round((float) ($purchasesByPurchaser[$pId]['credit'] ?? 0.0), 2);
            $totalPurchases = round($cashPurchases + $creditPurchases, 2);

            $peAmt = (float) ($procExpensesInPeriod->get($pId) ?? 0.0);
            $oeAmt = (float) ($otherExpensesInPeriod->get($pId) ?? 0.0);
            $purchaserExpenses = round($peAmt + $oeAmt, 2);

            // Closing Position = Opening + Cash Given - Cash Purchases - Purchaser Expenses - Cash Returned
            $closingPosition = round($openingPosition + $cashGiven - $cashPurchases - $purchaserExpenses - $cashReturned, 2);

            // Skip purchasers with zero balance and zero period activity
            if ($openingPosition == 0.0 && $cashGiven == 0.0 && $totalPurchases == 0.0 && $purchaserExpenses == 0.0 && $cashReturned == 0.0 && $closingPosition == 0.0) {
                continue;
            }

            $status = 'Balanced';
            $statusClass = 'text-slate-600 bg-slate-100 border-slate-200';
            if ($closingPosition > 0.0) {
                $status = 'Positive (Holding Cash)';
                $statusClass = 'text-emerald-700 bg-emerald-50 border-emerald-200';
            } elseif ($closingPosition < 0.0) {
                $status = 'Deficit (Overspent)';
                $statusClass = 'text-rose-700 bg-rose-50 border-rose-200';
            }

            $rows[] = [
                'purchaser_id' => $pId,
                'purchaser_name' => (string) $purchaser->name,
                'opening_position' => $openingPosition,
                'cash_given' => $cashGiven,
                'cash_purchases' => $cashPurchases,
                'purchaser_expenses' => $purchaserExpenses,
                'cash_returned' => $cashReturned,
                'closing_position' => $closingPosition,
                'credit_purchases' => $creditPurchases,
                'total_purchases' => $totalPurchases,
                'status' => $status,
                'status_class' => $statusClass,
            ];
        }

        return $rows;
    }

    /**
     * Get running ledger details for a specific purchaser.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPurchaserTimeline(int $purchaserId, string $startDate, string $endDate): array
    {
        $events = collect();

        // Credits / Cash given / Cash returns
        $credits = PurchaserCredit::query()
            ->where('purchaser_id', $purchaserId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->orderBy('business_date')
            ->get();

        foreach ($credits as $cr) {
            $isGiven = ($cr->type === 'in');
            $events->push([
                'date' => $cr->business_date ? Carbon::parse($cr->business_date)->format('Y-m-d') : $startDate,
                'type' => $isGiven ? 'Cash Given' : ($cr->purchase_invoice_id ? 'Invoice Payment' : 'Cash Returned'),
                'reference' => $cr->reference ?: ('CR-'.$cr->id),
                'description' => $cr->description ?: ($isGiven ? 'Advance given to purchaser' : 'Cash returned from purchaser'),
                'in_amount' => $isGiven ? (float) $cr->amount : 0.0,
                'out_amount' => ! $isGiven ? (float) $cr->amount : 0.0,
            ]);
        }

        // Purchaser Advance Expenses (only expenses funded by purchaser advance reduce position)
        $pe = ProcurementExpense::query()
            ->where('user_id', $purchaserId)
            ->where('funding_source', 'purchaser_advance')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        foreach ($pe as $p) {
            $events->push([
                'date' => $p->expense_date ? Carbon::parse($p->expense_date)->format('Y-m-d') : $startDate,
                'type' => 'Procurement Expense',
                'reference' => 'PE-'.$p->id,
                'description' => $p->note ?: ('Procurement: '.$p->categoryLabel()),
                'in_amount' => 0.0,
                'out_amount' => (float) $p->amount,
            ]);
        }

        $oe = OtherExpense::query()
            ->where('user_id', $purchaserId)
            ->where('funding_source', 'purchaser_advance')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        foreach ($oe as $o) {
            $events->push([
                'date' => $o->expense_date ? Carbon::parse($o->expense_date)->format('Y-m-d') : $startDate,
                'type' => 'Purchaser Other Expense',
                'reference' => 'OE-'.$o->id,
                'description' => $o->note ?: ('Purchaser Expense: '.$o->categoryLabel()),
                'in_amount' => 0.0,
                'out_amount' => (float) $o->amount,
            ]);
        }

        return $events->sortBy('date')->values()->all();
    }
}
