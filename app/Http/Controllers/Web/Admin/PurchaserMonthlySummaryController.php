<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Cashbook\PurchaserMonthlySummaryService;
use App\Support\CashbookAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PurchaserMonthlySummaryController extends Controller
{
    public function __construct(
        private readonly PurchaserMonthlySummaryService $summaryService,
    ) {}

    /**
     * Display All Purchasers Monthly Summary matrix.
     * Pure read-only view. Zero DB mutations.
     */
    public function index(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $month = $this->resolveMonth($request);
        $summary = $this->summaryService->getAllPurchasersSummary($month);
        $availableMonths = $this->availableMonths();
        $purchasers = $this->summaryService->getActivePurchasers();

        return view('admin.cashbook.finance.purchase.monthly-summary.index', [
            'month' => $month,
            'summary' => $summary,
            'availableMonths' => $availableMonths,
            'purchasers' => $purchasers,
        ]);
    }

    /**
     * Display Single Purchaser Monthly Summary detail report and drilldowns.
     * Pure read-only view. Zero DB mutations.
     */
    public function show(Request $request, string $purchaser): View
    {
        $this->ensureMainAdmin($request);

        $month = $this->resolveMonth($request);
        $currentPurchaser = $this->resolvePurchaser($purchaser);

        abort_unless(
            $this->summaryService->isPurchaserEligible($currentPurchaser),
            404,
            'The requested purchaser is not an active purchaser.'
        );

        $detail = $this->summaryService->getPurchaserMonthlyDetail($currentPurchaser, $month);
        $availableMonths = $this->availableMonths();
        $purchasers = $this->summaryService->getActivePurchasers();

        return view('admin.cashbook.finance.purchase.monthly-summary.show', [
            'month' => $month,
            'currentPurchaser' => $currentPurchaser,
            'detail' => $detail,
            'availableMonths' => $availableMonths,
            'purchasers' => $purchasers,
        ]);
    }

    private function resolveMonth(Request $request): string
    {
        $month = (string) $request->input('month', '');

        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $month;
        }

        return today('Asia/Kolkata')->format('Y-m');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function availableMonths(): array
    {
        $months = [];
        $current = today('Asia/Kolkata')->startOfMonth();

        for ($i = 0; $i < 24; $i++) {
            $m = $current->copy()->subMonths($i);
            $months[] = [
                'value' => $m->format('Y-m'),
                'label' => $m->format('F Y'),
            ];
        }

        return $months;
    }

    private function resolvePurchaser(string $purchaserParam): User
    {
        $purchaser = null;
        if (is_numeric($purchaserParam)) {
            $purchaser = User::query()->find((int) $purchaserParam);
        }

        if (! $purchaser) {
            $purchaser = User::query()->where('public_uuid', $purchaserParam)->first();
        }

        if (! $purchaser) {
            abort(404, 'Purchaser not found.');
        }

        return $purchaser;
    }

    private function ensureMainAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        if (CashbookAccess::allows($user, CashbookAccess::ReconciliationView)) {
            return;
        }

        abort(403, 'Unauthorized access to Purchase Monthly Summary.');
    }
}
