<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MonthlyReportDrilldownRequest;
use App\Http\Requests\Admin\MonthlyReportPeriodRequest;
use App\Models\User;
use App\Services\Cashbook\MonthlyReport\DynamicSectionReportService;
use App\Services\Cashbook\MonthlyReport\GreenLeafMonthlyReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GreenLeafMonthlyReportController extends Controller
{
    public function __construct(
        private readonly GreenLeafMonthlyReportService $reportService,
        private readonly DynamicSectionReportService $dynamicSectionReportService,
    ) {}

    public function sectionReports(MonthlyReportPeriodRequest $request): View
    {
        $this->ensureAuthorized($request);

        $sectionKey = $request->query('section');
        $report = $this->dynamicSectionReportService->buildReport(
            $request->validated(),
            is_string($sectionKey) ? $sectionKey : null
        );

        return view('admin.cashbook.monthly-report.section-reports', [
            ...$report,
            'currentRoute' => 'admin.cashbook.monthly-report.section-reports',
        ]);
    }

    public function overview(MonthlyReportPeriodRequest $request): View
    {
        $this->ensureAuthorized($request);

        $report = $this->reportService->overview($request->validated());

        return view('admin.cashbook.monthly-report.overview', [
            ...$report,
            'currentRoute' => 'admin.cashbook.monthly-report.overview',
        ]);
    }

    public function saleSplit(MonthlyReportPeriodRequest $request): View
    {
        $this->ensureAuthorized($request);

        $report = $this->reportService->saleSplit($request->validated());

        return view('admin.cashbook.monthly-report.sale-split', [
            ...$report,
            'currentRoute' => 'admin.cashbook.monthly-report.sale-split',
        ]);
    }

    public function otherExpenses(MonthlyReportPeriodRequest $request): View
    {
        $this->ensureAuthorized($request);

        $report = $this->reportService->otherExpenses($request->validated());

        return view('admin.cashbook.monthly-report.other-expenses', [
            ...$report,
            'currentRoute' => 'admin.cashbook.monthly-report.other-expenses',
        ]);
    }

    public function expenseReport(MonthlyReportPeriodRequest $request): View
    {
        $this->ensureAuthorized($request);

        $report = $this->reportService->expenseReport($request->validated());

        return view('admin.cashbook.monthly-report.expense-report', [
            ...$report,
            'currentRoute' => 'admin.cashbook.monthly-report.expense-report',
        ]);
    }

    public function drilldown(MonthlyReportDrilldownRequest $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $data = $this->reportService->drilldown($request->validated());

        return response()->json($data);
    }

    private function ensureAuthorized(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && (
                $user->isMainAdmin()
                || $user->hasRole('admin')
                || $user->hasRole('accounts')
                || $user->hasRole('accountant')
                || $user->hasRole('account')
                || $user->hasRole('manager')
                || (isset($user->is_admin) && $user->is_admin)
                || $user->can('cashbook.monthly-report.view')
                || $user->can('accounting.report.view')
                || $user->can('finance.dashboard.view')
                || $user->can('accounting.dashboard.view')
                || $user->can('accounting.ledger.view')
            ),
            403,
            'Unauthorized access to Green Leaf Monthly Report.'
        );
    }
}
