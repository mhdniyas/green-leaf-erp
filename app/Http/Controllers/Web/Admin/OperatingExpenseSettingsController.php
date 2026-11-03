<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcurementExpense;
use App\Models\ProcurementExpenseCategory;
use App\Models\ProcurementExpenseSubCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperatingExpenseSettingsController extends Controller
{
    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $keyInput = (string) $request->input('key', '');
        $nameInput = (string) $request->input('name', '');

        $derivedKey = filled($keyInput)
            ? Str::slug($keyInput, '_')
            : Str::slug($nameInput, '_');

        $request->merge(['key' => $derivedKey]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:procurement_expense_categories,key'],
            'sub_category_mode' => ['required', 'string', Rule::in([
                ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
                ProcurementExpenseCategory::SUB_CATEGORY_MODE_OPTIONAL,
                ProcurementExpenseCategory::SUB_CATEGORY_MODE_REQUIRED,
            ])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validated['sub_category_mode'] === ProcurementExpenseCategory::SUB_CATEGORY_MODE_REQUIRED) {
            throw ValidationException::withMessages([
                'sub_category_mode' => 'Cannot set location mode to Required because this category has no active locations yet. Add locations first.',
            ]);
        }

        $isActive = isset($validated['is_active']) ? (bool) $validated['is_active'] : false;

        $category = DB::transaction(function () use ($validated, $isActive): ProcurementExpenseCategory {
            return ProcurementExpenseCategory::create([
                'key' => $validated['key'],
                'name' => trim((string) $validated['name']),
                'sub_category_mode' => $validated['sub_category_mode'],
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => $isActive,
            ]);
        });

        return redirect()
            ->route('admin.company-settings.edit', [
                'tab' => 'operating-expenses',
                'category_id' => $category->id,
            ])
            ->with('success', "Operating expense category '{$category->name}' created successfully.");
    }

    public function updateCategory(Request $request, ProcurementExpenseCategory $category): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($request->filled('key') && $request->input('key') !== $category->key) {
            throw ValidationException::withMessages([
                'key' => 'Category key is immutable and cannot be changed after creation.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sub_category_mode' => ['required', 'string', Rule::in([
                ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
                ProcurementExpenseCategory::SUB_CATEGORY_MODE_OPTIONAL,
                ProcurementExpenseCategory::SUB_CATEGORY_MODE_REQUIRED,
            ])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validated['sub_category_mode'] === ProcurementExpenseCategory::SUB_CATEGORY_MODE_REQUIRED) {
            $activeLocationsCount = $category->activeSubCategories()->count();
            if ($activeLocationsCount === 0) {
                throw ValidationException::withMessages([
                    'sub_category_mode' => 'Cannot set location mode to Required because this category has no active locations. Add at least one active location first.',
                ]);
            }
        }

        DB::transaction(function () use ($category, $validated): void {
            $category->update([
                'name' => trim((string) $validated['name']),
                'sub_category_mode' => $validated['sub_category_mode'],
                'sort_order' => (int) ($validated['sort_order'] ?? $category->sort_order),
                'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : $category->is_active,
            ]);
        });

        return redirect()
            ->route('admin.company-settings.edit', [
                'tab' => 'operating-expenses',
                'category_id' => $category->id,
            ])
            ->with('success', "Category '{$category->name}' updated successfully.");
    }

    public function toggleCategoryStatus(Request $request, ProcurementExpenseCategory $category): RedirectResponse
    {
        $this->authorizeAdmin($request);

        DB::transaction(function () use ($category): void {
            $category->update(['is_active' => ! $category->is_active]);
        });

        $statusLabel = $category->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->route('admin.company-settings.edit', [
                'tab' => 'operating-expenses',
                'category_id' => $category->id,
            ])
            ->with('success', "Category '{$category->name}' {$statusLabel} successfully.");
    }

    public function destroyCategory(Request $request, ProcurementExpenseCategory $category): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $hasExpenses = ProcurementExpense::query()->where('category', $category->key)->exists();
        if ($hasExpenses) {
            throw ValidationException::withMessages([
                'category' => "Cannot delete category '{$category->name}' because it is referenced by existing expenses.",
            ]);
        }

        $hasSubCategories = $category->subCategories()->exists();
        if ($hasSubCategories) {
            throw ValidationException::withMessages([
                'category' => "Cannot delete category '{$category->name}' because it contains locations. Delete or reassign locations first.",
            ]);
        }

        $categoryName = $category->name;
        DB::transaction(function () use ($category): void {
            $category->delete();
        });

        return redirect()
            ->route('admin.company-settings.edit', ['tab' => 'operating-expenses'])
            ->with('success', "Category '{$categoryName}' deleted successfully.");
    }

    public function storeSubCategory(Request $request, ProcurementExpenseCategory $category): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('procurement_expense_sub_categories', 'name')
                    ->where('procurement_expense_category_id', $category->id),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => "A location with name ':input' already exists in category '{$category->name}'.",
        ]);

        DB::transaction(function () use ($category, $validated): void {
            ProcurementExpenseSubCategory::create([
                'procurement_expense_category_id' => $category->id,
                'name' => trim((string) $validated['name']),
                'code' => filled($validated['code'] ?? null) ? trim((string) $validated['code']) : null,
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : true,
            ]);
        });

        return redirect()
            ->route('admin.company-settings.edit', [
                'tab' => 'operating-expenses',
                'category_id' => $category->id,
            ])
            ->with('success', "Location '{$validated['name']}' added to {$category->name}.");
    }

    public function updateSubCategory(Request $request, ProcurementExpenseSubCategory $subCategory): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('procurement_expense_sub_categories', 'name')
                    ->where('procurement_expense_category_id', $subCategory->procurement_expense_category_id)
                    ->ignore($subCategory->id),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => "A location with name ':input' already exists in this category.",
        ]);

        $willBeActive = isset($validated['is_active']) ? (bool) $validated['is_active'] : $subCategory->is_active;

        if ($subCategory->is_active && ! $willBeActive) {
            $category = $subCategory->category;
            if ($category && $category->sub_category_mode === ProcurementExpenseCategory::SUB_CATEGORY_MODE_REQUIRED) {
                $otherActiveCount = $category->activeSubCategories()
                    ->where('id', '!=', $subCategory->id)
                    ->count();

                if ($otherActiveCount === 0) {
                    throw ValidationException::withMessages([
                        'is_active' => "Cannot deactivate the final active location for category '{$category->name}' while location mode is Required. Change the category mode first.",
                    ]);
                }
            }
        }

        DB::transaction(function () use ($subCategory, $validated, $willBeActive): void {
            $subCategory->update([
                'name' => trim((string) $validated['name']),
                'code' => filled($validated['code'] ?? null) ? trim((string) $validated['code']) : null,
                'sort_order' => (int) ($validated['sort_order'] ?? $subCategory->sort_order),
                'is_active' => $willBeActive,
            ]);
        });

        return redirect()
            ->route('admin.company-settings.edit', [
                'tab' => 'operating-expenses',
                'category_id' => $subCategory->procurement_expense_category_id,
            ])
            ->with('success', "Location '{$subCategory->name}' updated successfully.");
    }

    public function toggleSubCategoryStatus(Request $request, ProcurementExpenseSubCategory $subCategory): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $category = $subCategory->category;
        if ($subCategory->is_active && $category && $category->sub_category_mode === ProcurementExpenseCategory::SUB_CATEGORY_MODE_REQUIRED) {
            $otherActiveCount = $category->activeSubCategories()
                ->where('id', '!=', $subCategory->id)
                ->count();

            if ($otherActiveCount === 0) {
                throw ValidationException::withMessages([
                    'status' => "Cannot deactivate the final active location for category '{$category->name}' while location mode is Required. Change the category mode first.",
                ]);
            }
        }

        DB::transaction(function () use ($subCategory): void {
            $subCategory->update(['is_active' => ! $subCategory->is_active]);
        });

        $statusLabel = $subCategory->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->route('admin.company-settings.edit', [
                'tab' => 'operating-expenses',
                'category_id' => $subCategory->procurement_expense_category_id,
            ])
            ->with('success', "Location '{$subCategory->name}' {$statusLabel} successfully.");
    }

    public function destroySubCategory(Request $request, ProcurementExpenseSubCategory $subCategory): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $hasExpenses = ProcurementExpense::query()
            ->where('procurement_expense_sub_category_id', $subCategory->id)
            ->exists();

        if ($hasExpenses) {
            throw ValidationException::withMessages([
                'location' => "Cannot delete location '{$subCategory->name}' because it is referenced by existing expenses.",
            ]);
        }

        $category = $subCategory->category;
        if ($category && $category->sub_category_mode === ProcurementExpenseCategory::SUB_CATEGORY_MODE_REQUIRED && $subCategory->is_active) {
            $otherActiveCount = $category->activeSubCategories()
                ->where('id', '!=', $subCategory->id)
                ->count();

            if ($otherActiveCount === 0) {
                throw ValidationException::withMessages([
                    'location' => "Cannot delete the final active location for category '{$category->name}' while location mode is Required. Change the category mode first.",
                ]);
            }
        }

        $categoryId = $subCategory->procurement_expense_category_id;
        $locationName = $subCategory->name;

        DB::transaction(function () use ($subCategory): void {
            $subCategory->delete();
        });

        return redirect()
            ->route('admin.company-settings.edit', [
                'tab' => 'operating-expenses',
                'category_id' => $categoryId,
            ])
            ->with('success', "Location '{$locationName}' deleted successfully.");
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
