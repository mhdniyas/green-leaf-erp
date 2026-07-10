<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Support\AccountingAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreShopAccountingEntryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->filter(function ($line): bool {
                if (! is_array($line)) {
                    return false;
                }

                return filled($line['shop_accounting_category_id'] ?? null)
                    || filled($line['amount'] ?? null)
                    || filled($line['description'] ?? null);
            })
            ->values()
            ->all();

        $this->merge(['lines' => $lines]);
    }

    public function authorize(): bool
    {
        return AccountingAccess::canManageOwnedShops($this->user());
    }

    public function rules(): array
    {
        $shop = $this->route('shop');

        return [
            'business_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($shop): void {
                    if ($shop === null) {
                        return;
                    }

                    $businessDate = Carbon::parse((string) $value)->toDateString();

                    $exists = ShopAccountingEntry::query()
                        ->where('shop_id', $shop->id)
                        ->whereDate('business_date', $businessDate)
                        ->exists();

                    if ($exists) {
                        $fail('A daily accounting entry already exists for this business date.');
                    }
                },
            ],
            'status' => ['required', 'string', Rule::in(['draft', 'submitted', 'recheck_required', 'approved', 'finalized'])],
            'opening_cash' => ['nullable', 'numeric', 'min:0'],
            'closing_cash' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.shop_accounting_category_id' => ['required', 'integer', 'exists:shop_accounting_categories,id'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $lines = $this->validatedLines();
                if ($lines === []) {
                    return;
                }

                $categories = ShopAccountingCategory::query()
                    ->whereIn('id', collect($lines)->pluck('shop_accounting_category_id')->filter()->all())
                    ->get()
                    ->keyBy('id');

                foreach ($lines as $index => $line) {
                    $category = $categories->get((int) ($line['shop_accounting_category_id'] ?? 0));

                    if (! $category instanceof ShopAccountingCategory) {
                        continue;
                    }

                    if ($category->name === 'Other' && blank($line['description'] ?? null)) {
                        $validator->errors()->add("lines.$index.description", 'Notes are required when using Other.');
                    }
                }
            },
        ];
    }

    protected function passedValidation(): void
    {
        $shop = $this->route('shop');
        $categoryIds = collect($this->input('lines', []))
            ->pluck('shop_accounting_category_id')
            ->filter()
            ->map(fn ($categoryId): int => (int) $categoryId)
            ->unique()
            ->values();

        if ($shop === null || $categoryIds->isEmpty()) {
            return;
        }

        $authorizedCategoryCount = ShopAccountingCategory::query()
            ->whereIn('id', $categoryIds->all())
            ->where(function ($query) use ($shop): void {
                $query->whereNull('shop_id')
                    ->orWhere('shop_id', $shop->id);
            })
            ->count();

        if ($authorizedCategoryCount !== $categoryIds->count()) {
            throw new AuthorizationException('Unauthorized accounting category.');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validatedLines(): array
    {
        $lines = $this->input('lines', []);

        return is_array($lines) ? $lines : [];
    }
}
