<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\ProcurementExpense;
use App\Models\ProcurementExpenseCategory;
use App\Models\ProcurementExpenseSubCategory;
use App\Services\Finance\ProcurementExpensePostingService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProcurementExpenseController extends Controller
{
    public function __construct(
        private readonly ProcurementExpensePostingService $postingService,
        private readonly PurchaserBusinessDayService $businessDayService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensurePurchaser($request);

        $date = Carbon::parse($request->input('date', $this->businessDayService->operationalDate()->toDateString()));
        $view = $request->string('view')->toString();
        if (! in_array($view, ['entries', 'datewise'], true)) {
            $view = 'entries';
        }
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();
        $editingExpense = $request->filled('edit')
            ? ProcurementExpense::query()->whereKey($request->integer('edit'))->where('user_id', $request->user()->id)->first()
            : null;

        $expenses = ProcurementExpense::query()
            ->with(['companyAccountingEntry.journalEntry', 'subCategory', 'categoryDefinition'])
            ->where('user_id', $request->user()->id)
            ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $selectedDateExpenses = $expenses
            ->filter(fn (ProcurementExpense $expense): bool => $expense->expense_date?->isSameDay($date) === true)
            ->values();

        $dateWiseTotals = $expenses
            ->groupBy(fn (ProcurementExpense $expense): string => (string) $expense->expense_date?->toDateString())
            ->map(function ($rows, string $expenseDate): array {
                return [
                    'date' => $expenseDate,
                    'count' => $rows->count(),
                    'total' => round((float) $rows->sum('amount'), 2),
                ];
            })
            ->sortByDesc('date')
            ->values();

        // Load active categories and ensure editing expense's category is included
        $activeCategories = ProcurementExpenseCategory::query()
            ->with(['activeSubCategories'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(function (ProcurementExpenseCategory $cat) use ($editingExpense): bool {
                return $cat->is_active || ($editingExpense && $editingExpense->category === $cat->key);
            })
            ->values();

        // Fallback for unseeded test environments
        if ($activeCategories->isEmpty()) {
            foreach (ProcurementExpense::categories() as $key => $name) {
                $cat = new ProcurementExpenseCategory([
                    'key' => $key,
                    'name' => $name,
                    'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
                $cat->setRelation('activeSubCategories', collect());
                $activeCategories->push($cat);
            }
        }

        $categoryConfigs = $activeCategories->map(function (ProcurementExpenseCategory $cat) use ($editingExpense): array {
            $locations = $cat->activeSubCategories;
            if ($editingExpense && $editingExpense->category === $cat->key && $editingExpense->procurement_expense_sub_category_id) {
                $editingLoc = $cat->subCategories()->find($editingExpense->procurement_expense_sub_category_id);
                if ($editingLoc && ! $locations->contains('id', $editingLoc->id)) {
                    $locations = $locations->push($editingLoc);
                }
            }

            return [
                'key' => $cat->key,
                'name' => $cat->name,
                'mode' => $cat->sub_category_mode,
                'locations' => $locations->map(fn ($loc): array => [
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'code' => $loc->code,
                ])->values()->all(),
            ];
        })->values()->all();

        // Legacy category mapping dictionary for view compatibility
        $categoriesDictionary = $activeCategories->pluck('name', 'key')->all();

        return view('purchasing.purchaser.procurement_expenses', [
            'date' => $date,
            'previousDate' => $date->copy()->subDay()->toDateString(),
            'nextDate' => $date->copy()->addDay()->toDateString(),
            'view' => $view,
            'categories' => $categoriesDictionary,
            'categoryConfigs' => $categoryConfigs,
            'activeCategories' => $activeCategories,
            'expenses' => $expenses,
            'dateWiseTotals' => $dateWiseTotals,
            'editingExpense' => $editingExpense,
            'selectedDateTotal' => round((float) $selectedDateExpenses->sum('amount'), 2),
            'monthlyTotal' => round((float) $expenses->sum('amount'), 2),
            'selectedDateCount' => $selectedDateExpenses->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $validated = $this->validatedPayload($request);

        $categoryKey = (string) $validated['category'];
        $subCategoryId = filled($validated['procurement_expense_sub_category_id'] ?? null)
            ? (int) $validated['procurement_expense_sub_category_id']
            : null;
        $subCategoryModel = $subCategoryId ? ProcurementExpenseSubCategory::find($subCategoryId) : null;
        $subCategorySnapshot = $subCategoryModel?->name;

        $expense = DB::transaction(function () use ($request, $validated, $categoryKey, $subCategoryId, $subCategorySnapshot): ProcurementExpense {
            $expense = ProcurementExpense::query()->create([
                'user_id' => $request->user()->id,
                'expense_date' => Carbon::parse((string) $validated['expense_date'])->toDateString(),
                'category' => $categoryKey,
                'funding_source' => $validated['funding_source'] ?? ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
                'procurement_expense_sub_category_id' => $subCategoryId,
                'sub_category' => $subCategorySnapshot,
                'amount' => round((float) $validated['amount'], 2),
                'note' => filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null,
            ]);

            $this->postingService->syncDate($expense->expense_date, (int) $request->user()->id);

            return $expense->fresh(['companyAccountingEntry', 'subCategory', 'categoryDefinition']) ?? $expense;
        });

        return redirect()
            ->route('purchaser.procurement-expenses.index', ['date' => $expense->expense_date->toDateString()])
            ->with('success', 'Operating expense saved and posted to daily company expense.');
    }

    public function update(Request $request, ProcurementExpense $expense): RedirectResponse
    {
        $this->ensurePurchaser($request);
        $this->ensureExpenseOwner($request, $expense);

        $oldDate = $expense->expense_date?->copy();
        $validated = $this->validatedPayload($request, $expense);
        $newDate = Carbon::parse((string) $validated['expense_date']);

        $categoryKey = (string) $validated['category'];
        $subCategoryId = filled($validated['procurement_expense_sub_category_id'] ?? null)
            ? (int) $validated['procurement_expense_sub_category_id']
            : null;
        $subCategoryModel = $subCategoryId ? ProcurementExpenseSubCategory::find($subCategoryId) : null;
        $subCategorySnapshot = $subCategoryId ? ($subCategoryModel?->name ?? $expense->sub_category) : null;

        DB::transaction(function () use ($expense, $oldDate, $newDate, $request, $validated, $categoryKey, $subCategoryId, $subCategorySnapshot): void {
            $expense->update([
                'expense_date' => $newDate->toDateString(),
                'category' => $categoryKey,
                'funding_source' => $validated['funding_source'] ?? $expense->funding_source ?? ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
                'procurement_expense_sub_category_id' => $subCategoryId,
                'sub_category' => $subCategorySnapshot,
                'amount' => round((float) $validated['amount'], 2),
                'note' => filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null,
            ]);

            if ($oldDate instanceof Carbon && ! $oldDate->isSameDay($newDate)) {
                $this->postingService->syncDate($oldDate, (int) $request->user()->id);
            }

            $this->postingService->syncDate($newDate, (int) $request->user()->id);
        });

        return redirect()
            ->route('purchaser.procurement-expenses.index', ['date' => $newDate->toDateString()])
            ->with('success', 'Operating expense updated and daily company expense resynced.');
    }

    public function destroy(Request $request, ProcurementExpense $expense): RedirectResponse
    {
        $this->ensurePurchaser($request);
        $this->ensureExpenseOwner($request, $expense);

        $date = $expense->expense_date?->copy() ?? today();
        DB::transaction(function () use ($expense, $date, $request): void {
            $expense->delete();
            $this->postingService->syncDate($date, (int) $request->user()->id);
        });

        return redirect()
            ->route('purchaser.procurement-expenses.index', ['date' => $date->toDateString()])
            ->with('success', 'Operating expense deleted and daily company expense resynced.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?ProcurementExpense $editingExpense = null): array
    {
        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string'],
            'procurement_expense_sub_category_id' => ['nullable', 'integer'],
            'funding_source' => ['nullable', 'string', Rule::in([
                ProcurementExpense::FUNDING_PURCHASER_ADVANCE,
                ProcurementExpense::FUNDING_COMPANY_CASH,
                ProcurementExpense::FUNDING_COMPANY_BANK,
            ])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $categoryKey = (string) $validated['category'];
        $categoryModel = ProcurementExpenseCategory::where('key', $categoryKey)->first();

        if (! $categoryModel) {
            $isTableEmpty = ProcurementExpenseCategory::query()->doesntExist();
            if ($isTableEmpty && array_key_exists($categoryKey, ProcurementExpense::categories())) {
                // Table unseeded (e.g. legacy unit test): default categories permitted with mode none
                $subCategoryId = filled($validated['procurement_expense_sub_category_id'] ?? null)
                    ? (int) $validated['procurement_expense_sub_category_id']
                    : null;
                if ($subCategoryId !== null) {
                    throw ValidationException::withMessages([
                        'procurement_expense_sub_category_id' => 'Locations are not permitted for this category.',
                    ]);
                }

                return $validated;
            }

            if ($editingExpense && $editingExpense->category === $categoryKey) {
                // Allow preserving historical unlisted category key on existing expense
                return $validated;
            }

            throw ValidationException::withMessages([
                'category' => 'Selected expense category is invalid.',
            ]);
        } else {
            // New expense: category must be active
            if ($editingExpense === null && ! $categoryModel->is_active) {
                throw ValidationException::withMessages([
                    'category' => "Category '{$categoryModel->name}' is inactive and unavailable for new expenses.",
                ]);
            }

            // Edit: if category changed, new category must be active
            if ($editingExpense !== null && $editingExpense->category !== $categoryKey && ! $categoryModel->is_active) {
                throw ValidationException::withMessages([
                    'category' => "Category '{$categoryModel->name}' is inactive and unavailable for new expenses.",
                ]);
            }

            $subCategoryId = filled($validated['procurement_expense_sub_category_id'] ?? null)
                ? (int) $validated['procurement_expense_sub_category_id']
                : null;

            if ($categoryModel->sub_category_mode === ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE) {
                if ($subCategoryId !== null) {
                    throw ValidationException::withMessages([
                        'procurement_expense_sub_category_id' => "Locations are not permitted for category '{$categoryModel->name}'.",
                    ]);
                }
            } elseif ($categoryModel->sub_category_mode === ProcurementExpenseCategory::SUB_CATEGORY_MODE_REQUIRED) {
                if ($subCategoryId === null) {
                    throw ValidationException::withMessages([
                        'procurement_expense_sub_category_id' => "A location / route is required for category '{$categoryModel->name}'.",
                    ]);
                }
            }

            if ($subCategoryId !== null) {
                $locationModel = ProcurementExpenseSubCategory::find($subCategoryId);

                if (! $locationModel) {
                    throw ValidationException::withMessages([
                        'procurement_expense_sub_category_id' => 'Selected location / route does not exist.',
                    ]);
                }

                if ($locationModel->procurement_expense_category_id !== $categoryModel->id) {
                    throw ValidationException::withMessages([
                        'procurement_expense_sub_category_id' => "Location '{$locationModel->name}' does not belong to category '{$categoryModel->name}'.",
                    ]);
                }

                $isUnchangedHistorical = ($editingExpense !== null && $editingExpense->procurement_expense_sub_category_id === $locationModel->id);
                if (! $locationModel->is_active && ! $isUnchangedHistorical) {
                    throw ValidationException::withMessages([
                        'procurement_expense_sub_category_id' => "Location '{$locationModel->name}' is inactive and cannot be selected.",
                    ]);
                }
            }
        }

        return $validated;
    }

    private function ensureExpenseOwner(Request $request, ProcurementExpense $expense): void
    {
        abort_unless((int) $expense->user_id === (int) $request->user()->id, 404);
    }

    private function ensurePurchaser(Request $request): void
    {
        if (
            ! $request->user()->hasRole('purchaser')
            && ! $request->user()->hasRole('admin')
            && ! $request->user()->hasRole('purchase')
        ) {
            abort(403, 'Unauthorized access.');
        }
    }
}
