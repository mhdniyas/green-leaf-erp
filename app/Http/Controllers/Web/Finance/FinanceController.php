<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\AdminFinancePillarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct(
        private readonly AdminFinancePillarService $financePillars,
    ) {}

    public function index(): RedirectResponse
    {
        Gate::authorize('accounting.ledger.view');

        return redirect()->route('finance.vendors.index');
    }

    public function vendors(Request $request): View
    {
        Gate::authorize('accounting.ledger.view');

        [$startDate, $endDate] = $this->resolvePeriod($request);
        $finance = $this->financePillars->forPeriod($startDate, $endDate);

        return view('finance.vendors.index', compact('startDate', 'endDate', 'finance'));
    }

    public function sales(Request $request): View
    {
        Gate::authorize('accounting.ledger.view');

        [$startDate, $endDate] = $this->resolvePeriod($request);
        $finance = $this->financePillars->forPeriod($startDate, $endDate);

        return view('finance.sales.index', compact('startDate', 'endDate', 'finance'));
    }

    public function vendorsExcel(Request $request): StreamedResponse
    {
        Gate::authorize('accounting.ledger.view');

        [$startDate, $endDate] = $this->resolvePeriod($request);
        $vendor = $this->financePillars->forPeriod($startDate, $endDate)['vendor'];

        return response()->streamDownload(function () use ($vendor): void {
            $file = fopen('php://output', 'w');

            if ($file === false) {
                return;
            }

            fputcsv($file, ['Date', 'Lead Vendor', 'Credit', 'Debit', 'Balance', 'Status']);

            foreach ($vendor['daily_rows'] as $row) {
                fputcsv($file, [
                    $row['date'],
                    $row['lead_label'],
                    number_format((float) $row['credit_amount'], 2, '.', ''),
                    number_format((float) $row['debit_amount'], 2, '.', ''),
                    number_format((float) $row['balance_amount'], 2, '.', ''),
                    $row['status'],
                ]);
            }

            fclose($file);
        }, 'vendor-reports-'.$startDate->toDateString().'_'.$endDate->toDateString().'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function salesExcel(Request $request): StreamedResponse
    {
        Gate::authorize('accounting.ledger.view');

        [$startDate, $endDate] = $this->resolvePeriod($request);
        $sales = $this->financePillars->forPeriod($startDate, $endDate)['sales'];

        return response()->streamDownload(function () use ($sales): void {
            $file = fopen('php://output', 'w');

            if ($file === false) {
                return;
            }

            fputcsv($file, ['Date', 'Lead Shop', 'Credit', 'Debit', 'Balance', 'Status']);

            foreach ($sales['daily_rows'] as $row) {
                fputcsv($file, [
                    $row['date'],
                    $row['lead_label'],
                    number_format((float) $row['credit_amount'], 2, '.', ''),
                    number_format((float) $row['debit_amount'], 2, '.', ''),
                    number_format((float) $row['balance_amount'], 2, '.', ''),
                    $row['status'],
                ]);
            }

            fclose($file);
        }, 'sales-reports-'.$startDate->toDateString().'_'.$endDate->toDateString().'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function vendorsPdf(Request $request): View
    {
        Gate::authorize('accounting.ledger.view');

        [$startDate, $endDate] = $this->resolvePeriod($request);
        $finance = $this->financePillars->forPeriod($startDate, $endDate);

        return view('finance.vendors.pdf', compact('startDate', 'endDate', 'finance'));
    }

    public function salesPdf(Request $request): View
    {
        Gate::authorize('accounting.ledger.view');

        [$startDate, $endDate] = $this->resolvePeriod($request);
        $finance = $this->financePillars->forPeriod($startDate, $endDate);

        return view('finance.sales.pdf', compact('startDate', 'endDate', 'finance'));
    }

    public function vendorDaily(Request $request): View
    {
        Gate::authorize('accounting.ledger.view');

        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $report = $this->financePillars->vendorDailyDetail($date);

        return view('finance.vendor-daily', compact('date', 'report'));
    }

    public function salesDaily(Request $request): View
    {
        Gate::authorize('accounting.ledger.view');

        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $statusFilter = $request->string('status')->toString();
        $statusFilter = in_array($statusFilter, ['all', 'pending', 'settled'], true) ? $statusFilter : 'all';
        $report = $this->financePillars->salesDailyDetail($date, $statusFilter);

        return view('finance.sales-daily', compact('date', 'report', 'statusFilter'));
    }

    public function legacyRedirect(): RedirectResponse
    {
        Gate::authorize('accounting.ledger.view');

        return redirect()->route('finance.vendors.index');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(Request $request): array
    {
        $startDate = Carbon::parse($request->input('start_date', $request->input('date', today()->toDateString())));
        $endDate = Carbon::parse($request->input('end_date', $request->input('date', today()->toDateString())));

        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [$startDate, $endDate];
    }
}
