<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFinalReportExpenseMappingsRequest;
use App\Http\Requests\Admin\UpdateFinalReportProductGroupsRequest;
use App\Http\Requests\Admin\UpdateFinalReportShopHeadingsRequest;
use App\Models\Cashbook\CashbookMonthlyReportExpenseMapping;
use App\Models\CompanyAccountingCategory;
use App\Models\OtherExpense;
use App\Models\ProcurementExpense;
use App\Models\PurchaseProductFilter;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\MonthlyReport\FinalReportSettingsService;
use App\Services\Cashbook\MonthlyReport\ReportHeadingDictionary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinalReportSettingsController extends Controller
{
    public function __construct(
        private readonly FinalReportSettingsService $settingsService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureCanViewSettings($request);

        $readiness = $this->settingsService->getReadinessSummary();
        $filters = PurchaseProductFilter::query()->orderBy('name')->get();
        $clientShops = Shop::query()
            ->with(['client', 'ledgerEntrySettings.entryType'])
            ->whereNotNull('client_id')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $headings = ReportHeadingDictionary::definitions();
        $expenseMappings = CashbookMonthlyReportExpenseMapping::all()->groupBy('source_type');
        $companyCategories = CompanyAccountingCategory::orderBy('name')->get();

        return view('admin.cashbook.settings.final-report.index', [
            'readiness' => $readiness,
            'filters' => $filters,
            'clientShops' => $clientShops,
            'headings' => $headings,
            'expenseMappings' => $expenseMappings,
            'companyCategories' => $companyCategories,
            'procurementCategories' => ProcurementExpense::categories(),
            'otherCategories' => OtherExpense::categories(),
            'canManage' => $this->canManageSettings($request->user()),
        ]);
    }

    public function readiness(Request $request): JsonResponse
    {
        $this->ensureCanViewSettings($request);

        return response()->json($this->settingsService->getReadinessSummary());
    }

    public function updateProductGroups(UpdateFinalReportProductGroupsRequest $request): RedirectResponse
    {
        $this->ensureCanManageSettings($request);

        $this->settingsService->updateProductGroups($request->validated('assignments'), $request->user());

        return back()->with('success', 'Product groups updated successfully.');
    }

    public function updateShopHeadings(UpdateFinalReportShopHeadingsRequest $request): RedirectResponse
    {
        $this->ensureCanManageSettings($request);

        $shopId = (int) $request->validated('shop_id');
        $copyFrom = $request->validated('copy_from_shop_id');

        if ($copyFrom) {
            $this->settingsService->copyShopHeadings((int) $copyFrom, $shopId, $request->user());

            return back()->with('success', 'Shop headings copied and applied successfully.');
        }

        $mappings = $request->validated('mappings', []);
        $this->settingsService->updateShopHeadings($shopId, $mappings, $request->user());

        return back()->with('success', 'Shop report headings updated successfully.');
    }

    public function updateExpenseMappings(UpdateFinalReportExpenseMappingsRequest $request): RedirectResponse
    {
        $this->ensureCanManageSettings($request);

        $this->settingsService->updateExpenseMappings($request->validated('mappings'), $request->user());

        return back()->with('success', 'Expense category mappings updated successfully.');
    }

    private function ensureCanViewSettings(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && (
                $user->isMainAdmin()
                || $user->hasRole('admin')
                || $user->hasRole('accounts')
                || $user->hasRole('accountant')
                || $user->hasRole('account')
                || $user->can('cashbook.monthly-report.settings.view')
                || $user->can('accounting.report.view')
                || $user->can('finance.dashboard.view')
            ),
            403,
            'Unauthorized access to Final Report Settings.'
        );
    }

    private function ensureCanManageSettings(Request $request): void
    {
        abort_unless($this->canManageSettings($request->user()), 403, 'Unauthorized to manage Final Report Settings.');
    }

    private function canManageSettings(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->can('cashbook.monthly-report.settings.manage');
    }
}
