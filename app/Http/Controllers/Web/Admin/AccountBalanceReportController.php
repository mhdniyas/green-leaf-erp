<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\Cashbook\AccountBalanceReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountBalanceReportController extends Controller
{
    public function __construct(
        private readonly AccountBalanceReportService $reportService
    ) {}

    /**
     * Display the read-only company-wide Cashbook Account Balance Report.
     */
    public function index(Request $request): View
    {
        $preset = (string) $request->query('preset', 'this_month');
        $fromDate = $request->query('from_date') ? (string) $request->query('from_date') : null;
        $toDate = $request->query('to_date') ? (string) $request->query('to_date') : null;

        $report = $this->reportService->generateReport($preset, $fromDate, $toDate);
        $shops = Shop::query()->where('status', 'active')->orderBy('name')->get();

        return view('admin.cashbook.account-balance', [
            'report' => $report,
            'shops' => $shops,
        ]);
    }
}
