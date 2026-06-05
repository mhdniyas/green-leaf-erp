<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Finance;

use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalTransaction;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('accounting.ledger.view');

        // Resolve dates from query filter
        $dateFilter = $request->input('date_filter', 'this_month');
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        if ($dateFilter === 'this_month') {
            $startDate = today()->startOfMonth()->format('Y-m-d');
            $endDate = today()->endOfMonth()->format('Y-m-d');
        } elseif ($dateFilter === 'last_month') {
            $startDate = today()->subMonth()->startOfMonth()->format('Y-m-d');
            $endDate = today()->subMonth()->endOfMonth()->format('Y-m-d');
        } elseif ($dateFilter === 'custom' && $startDateInput && $endDateInput) {
            $startDate = $startDateInput;
            $endDate = $endDateInput;
        } else {
            // Default fallback
            $startDate = today()->startOfMonth()->format('Y-m-d');
            $endDate = today()->endOfMonth()->format('Y-m-d');
        }

        // 1. Overview metrics (current/real-time balances)
        $cashAccount = Account::where('code', '1010')->first();
        $bankAccount = Account::where('code', '1020')->first();
        $availableBalance = ($cashAccount?->balance ?? 0.0) + ($bankAccount?->balance ?? 0.0);

        $apAccount = Account::where('code', '2100')->first();
        $outstandingAmount = $apAccount?->balance ?? 0.0;

        $arAccount = Account::where('code', '1100')->first();
        $expectedCredit = $arAccount?->balance ?? 0.0;

        // This Month Purchases (sum of non-draft POs)
        $thisMonthPurchases = (float) PurchaseOrderItem::whereHas('purchaseOrder', function ($q) {
            $q->whereYear('order_date', today()->year)
                ->whereMonth('order_date', today()->month)
                ->where('status', '!=', POStatus::Draft);
        })->selectRaw('SUM(quantity * unit_price) as total')->value('total');

        // 2. Payments list (filtered by date range)
        $payments = $this->getPaymentsData($startDate, $endDate);

        // 3. Running Ledger data (filtered by date range)
        $ledgerData = $this->getLedgerData($startDate, $endDate);

        $activeTab = $request->input('tab', 'overview');

        return view('finance.index', compact(
            'availableBalance',
            'outstandingAmount',
            'expectedCredit',
            'thisMonthPurchases',
            'payments',
            'ledgerData',
            'startDate',
            'endDate',
            'dateFilter',
            'activeTab'
        ));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::authorize('accounting.ledger.view');

        $startDate = $request->input('start_date', today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', today()->endOfMonth()->format('Y-m-d'));

        $ledgerData = $this->getLedgerData($startDate, $endDate);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ledger_statement_'.$startDate.'_to_'.$endDate.'.csv"',
        ];

        $callback = function () use ($ledgerData, $startDate, $endDate): void {
            $file = fopen('php://output', 'w');
            if ($file) {
                fputcsv($file, ['Ledger Account Statement']);
                fputcsv($file, ['Period', $startDate.' to '.$endDate]);
                fputcsv($file, ['Opening Balance', number_format($ledgerData['opening_balance'], 2)]);
                fputcsv($file, ['Closing Balance', number_format($ledgerData['closing_balance'], 2)]);
                fputcsv($file, []);
                fputcsv($file, ['Date', 'Description', 'Debit (Outflow)', 'Credit (Inflow)', 'Balance']);

                foreach ($ledgerData['lines'] as $line) {
                    fputcsv($file, [
                        $line->date ? $line->date->format('Y-m-d') : 'N/A',
                        $line->description,
                        $line->debit !== null ? number_format($line->debit, 2) : '',
                        $line->credit !== null ? number_format($line->credit, 2) : '',
                        number_format($line->balance, 2),
                    ]);
                }
                fclose($file);
            }
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request): View
    {
        Gate::authorize('accounting.ledger.view');

        $startDate = $request->input('start_date', today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', today()->endOfMonth()->format('Y-m-d'));

        $ledgerData = $this->getLedgerData($startDate, $endDate);

        return view('finance.statement_pdf', compact('ledgerData', 'startDate', 'endDate'));
    }

    private function getLedgerData(string $startDate, string $endDate): array
    {
        $cashAccount = Account::where('code', '1010')->first();
        $bankAccount = Account::where('code', '1020')->first();
        $accountIds = array_filter([$cashAccount?->id, $bankAccount?->id]);

        $openingBalance = 0.0;
        if (! empty($accountIds)) {
            $startCarbon = Carbon::parse($startDate);
            $dayBefore = $startCarbon->copy()->subDay()->format('Y-m-d');

            $cashBalBefore = $cashAccount ? $cashAccount->getBalanceForPeriod(null, $dayBefore) : 0.0;
            $bankBalBefore = $bankAccount ? $bankAccount->getBalanceForPeriod(null, $dayBefore) : 0.0;
            $openingBalance = $cashBalBefore + $bankBalBefore;
        }

        $ledgerLines = [];
        $currentBalance = $openingBalance;

        // First item is opening balance
        $ledgerLines[] = (object) [
            'date' => Carbon::parse($startDate),
            'description' => 'Opening Balance',
            'debit' => null,
            'credit' => null,
            'balance' => $openingBalance,
        ];

        if (! empty($accountIds)) {
            $txs = JournalTransaction::with(['journalEntry.transactions.account'])
                ->whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->where('entry_date', '>=', $startDate)
                        ->where('entry_date', '<=', $endDate);
                })
                ->get()
                ->sortBy(fn ($tx) => $tx->journalEntry->entry_date->timestamp);

            foreach ($txs as $tx) {
                $entry = $tx->journalEntry;
                $amount = (float) $tx->amount;
                $isDebit = $tx->type === 'debit'; // asset debit = inflow = credit in bank statement style

                if ($isDebit) {
                    // Inflow (Bank statement Credit)
                    $debitVal = null;
                    $creditVal = $amount;
                    $currentBalance += $amount;
                } else {
                    // Outflow (Bank statement Debit)
                    $debitVal = $amount;
                    $creditVal = null;
                    $currentBalance -= $amount;
                }

                $ledgerLines[] = (object) [
                    'date' => $entry->entry_date,
                    'description' => $entry->description ?? $entry->reference,
                    'debit' => $debitVal,
                    'credit' => $creditVal,
                    'balance' => $currentBalance,
                ];
            }
        }

        return [
            'opening_balance' => $openingBalance,
            'closing_balance' => $currentBalance,
            'lines' => $ledgerLines,
        ];
    }

    private function getPaymentsData(string $startDate, string $endDate): array
    {
        $cashAccount = Account::where('code', '1010')->first();
        $bankAccount = Account::where('code', '1020')->first();
        $accountIds = array_filter([$cashAccount?->id, $bankAccount?->id]);

        $payments = [];
        if (! empty($accountIds)) {
            $txs = JournalTransaction::with(['journalEntry.transactions.account'])
                ->whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->where('entry_date', '>=', $startDate)
                        ->where('entry_date', '<=', $endDate);
                })
                ->get()
                ->sortByDesc(fn ($tx) => $tx->journalEntry->entry_date->timestamp);

            foreach ($txs as $tx) {
                $entry = $tx->journalEntry;
                $amount = (float) $tx->amount;
                $isDebit = $tx->type === 'debit';

                $type = 'Other';
                if (str_starts_with((string) $entry->reference, 'PAY-')) {
                    $type = 'Sales Payment';
                } elseif (str_starts_with((string) $entry->reference, 'PMT-')) {
                    $type = 'Purchase Payment';
                } elseif (str_starts_with((string) $entry->reference, 'EXP-')) {
                    $type = 'Expense Payment';
                } else {
                    $type = $isDebit ? 'Cash Inflow' : 'Cash Outflow';
                }

                $payments[] = (object) [
                    'date' => $entry->entry_date,
                    'type' => $type,
                    'reference' => $entry->reference,
                    'description' => $entry->description,
                    'amount' => $amount,
                    'flow' => $isDebit ? 'in' : 'out',
                    'status' => 'Paid',
                ];
            }
        }

        return $payments;
    }
}
