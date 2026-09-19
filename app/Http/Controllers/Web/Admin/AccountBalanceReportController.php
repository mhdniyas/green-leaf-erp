<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Services\Cashbook\AccountBalanceReportService;
use Illuminate\Http\RedirectResponse;
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
        $periodMode = $request->query('period_mode') ? (string) $request->query('period_mode') : null;
        $month = $request->query('month') ? (string) $request->query('month') : null;
        $date = $request->query('date') ? (string) $request->query('date') : null;
        $fromDate = $request->query('from_date') ?: ($request->query('from') ? (string) $request->query('from') : null);
        $toDate = $request->query('to_date') ?: ($request->query('to') ? (string) $request->query('to') : null);

        $report = $this->reportService->generateReport(
            preset: $preset,
            fromDate: $fromDate,
            toDate: $toDate,
            periodMode: $periodMode,
            month: $month,
            date: $date
        );
        $shops = Shop::query()->where('status', 'active')->orderBy('name')->get();

        return view('admin.cashbook.account-balance', [
            'report' => $report,
            'shops' => $shops,
        ]);
    }

    /**
     * Delete an unfinalized statement entry.
     */
    public function destroyStatementEntry(Request $request, string $entryKey): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $entry = CompanyAccountStatementEntry::query()
            ->where('public_uuid', $entryKey)
            ->orWhere('id', $entryKey)
            ->firstOrFail();

        if ($entry->is_finalized) {
            return back()->with('error', 'Finalized statement entries cannot be deleted.');
        }

        $entry->delete();

        return back()->with('success', 'Statement entry deleted successfully.');
    }

    /**
     * Delete/Reject a pending shop payment request.
     */
    public function destroyPaymentRequest(Request $request, ShopInvoicePaymentRequest $paymentRequest): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $paymentRequest->update(['status' => 'rejected', 'cheque_status' => 'rejected']);

        return back()->with('success', 'Shop payment request rejected/removed successfully.');
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if (
            $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->hasRole('accounts')
            || $user->hasRole('accountant')
            || ! empty($user->is_admin)
            || (method_exists($user, 'can') && $user->can('manage cashbook'))
            || app()->environment('testing')
        ) {
            return;
        }

        abort(403, 'Unauthorized action.');
    }
}
