<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FinancialReportController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    public function pnl(Request $request): View
    {
        Gate::authorize('accounting.report.view');

        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->endOfMonth()->format('Y-m-d'));

        $report = $this->ledgerService->getPnLReport((string) $startDate, (string) $endDate);

        return view('finance.reports.pnl', compact('report', 'startDate', 'endDate'));
    }

    public function balanceSheet(Request $request): View
    {
        Gate::authorize('accounting.report.view');

        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $report = $this->ledgerService->getBalanceSheetReport((string) $endDate);

        return view('finance.reports.balance_sheet', compact('report', 'endDate'));
    }

    public function cashFlow(Request $request): View
    {
        Gate::authorize('accounting.report.view');

        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->endOfMonth()->format('Y-m-d'));

        $report = $this->ledgerService->getCashFlowReport((string) $startDate, (string) $endDate);

        return view('finance.reports.cash_flow', compact('report', 'startDate', 'endDate'));
    }
}
