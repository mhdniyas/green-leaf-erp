<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\MonthlyClosingSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MonthlyClosingSummaryController extends Controller
{
    public function __construct(
        private readonly MonthlyClosingSummaryService $summaryService,
        private readonly CashbookShopSyncService $shopSyncService,
    ) {}

    /**
     * Display All Shops Monthly Closing Summary matrix.
     * Pure read-only view. Zero DB mutations.
     */
    public function index(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $month = $this->resolveMonth($request);
        $summary = $this->summaryService->getAllShopsSummary($month);
        $availableMonths = $this->availableMonths();
        $shops = $this->summaryService->getActiveClientProfiles();

        return view('admin.cashbook.monthly-closing-summary.index', [
            'month' => $month,
            'summary' => $summary,
            'availableMonths' => $availableMonths,
            'shops' => $shops,
        ]);
    }

    /**
     * Display Single Shop Monthly Closing Summary detail report and drilldowns.
     * Pure read-only view. Zero DB mutations.
     */
    public function show(Request $request, string $shop): View
    {
        $this->ensureMainAdmin($request);

        $month = $this->resolveMonth($request);
        $currentShop = $this->resolveShop($shop);
        $currentShop->load('shop.client', 'client', 'preset');

        abort_unless(
            $this->summaryService->isClientShopEligible($currentShop),
            404,
            'The requested shop is not an active client shop.'
        );

        $detail = $this->summaryService->getShopMonthlyDetail($currentShop, $month);
        $availableMonths = $this->availableMonths();
        $shops = $this->summaryService->getActiveClientProfiles();

        return view('admin.cashbook.monthly-closing-summary.show', [
            'month' => $month,
            'currentShop' => $currentShop,
            'detail' => $detail,
            'availableMonths' => $availableMonths,
            'shops' => $shops,
        ]);
    }

    private function resolveMonth(Request $request): string
    {
        $month = (string) $request->input('month', '');

        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $month;
        }

        return today()->format('Y-m');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function availableMonths(): array
    {
        $months = [];
        $current = today()->startOfMonth();

        for ($i = 0; $i < 24; $i++) {
            $m = $current->copy()->subMonths($i);
            $months[] = [
                'value' => $m->format('Y-m'),
                'label' => $m->format('F Y'),
            ];
        }

        return $months;
    }

    private function resolveShop(string $shopParam): ShopLedgerProfile
    {
        $profile = null;
        if (is_numeric($shopParam)) {
            $profile = ShopLedgerProfile::query()->where('shop_id', (int) $shopParam)->first();
        }

        if (! $profile) {
            $profile = ShopLedgerProfile::query()
                ->where('slug', $shopParam)
                ->orWhere('code', $shopParam)
                ->first();
        }

        if (! $profile) {
            $this->shopSyncService->syncAndGetProfiles();

            if (is_numeric($shopParam)) {
                $profile = ShopLedgerProfile::query()->where('shop_id', (int) $shopParam)->first();
            }

            if (! $profile) {
                $profile = ShopLedgerProfile::query()
                    ->where('slug', $shopParam)
                    ->orWhere('code', $shopParam)
                    ->first();
            }
        }

        if (! $profile) {
            abort(404, 'Shop profile not found.');
        }

        return $profile;
    }

    private function ensureMainAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        if (
            $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->hasRole('accounts')
            || $user->hasRole('accountant')
            || $user->hasRole('account')
            || $user->hasRole('manager')
            || (property_exists($user, 'is_admin') && $user->is_admin)
            || $user->hasAnyPermission([
                'accounting.report.view',
                'accounting.dashboard.view',
                'accounting.ledger.view',
            ])
        ) {
            return;
        }

        abort(403, 'Unauthorized access to Cashbook Monthly Closing Summary.');
    }
}
