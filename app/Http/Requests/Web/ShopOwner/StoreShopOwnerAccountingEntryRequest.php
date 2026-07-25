<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\ShopOwner;

use App\Models\ShopAccountingCategory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreShopOwnerAccountingEntryRequest extends FormRequest
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
        return $this->user() !== null
            && $this->user()->hasRole('shop')
            && $this->user()->shop_id !== null;
    }

    public function rules(): array
    {
        return [
            'business_date' => ['required', Rule::date()->todayOrBefore()],
            'submission_action' => ['required', 'string', Rule::in(['save_draft', 'submit'])],
            'create_adjustment' => ['nullable', 'boolean'],
            'opening_cash' => ['nullable', 'numeric'],
            'closing_cash' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'shop_reply_note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'],
            'lines.*.shop_accounting_category_id' => ['required', 'integer', 'exists:shop_accounting_categories,id'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function passedValidation(): void
    {
        $categoryIds = collect($this->input('lines', []))
            ->pluck('shop_accounting_category_id')
            ->filter()
            ->map(fn ($categoryId): int => (int) $categoryId)
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            return;
        }

        $authorizedCategoryCount = ShopAccountingCategory::query()
            ->whereIn('id', $categoryIds->toArray())
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('shop_id')
                    ->orWhere('shop_id', $this->user()?->shop_id);
            })
            ->count();

        if ($authorizedCategoryCount !== $categoryIds->count()) {
            throw new AuthorizationException('Unauthorized accounting category.');
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $lines = $this->validatedLines();
                $submissionAction = (string) $this->input('submission_action');
                $isAdjustment = $this->boolean('create_adjustment');

                if ($submissionAction === 'submit' && $lines === []) {
                    $validator->errors()->add('lines', 'Add at least one credit, debit, or non-cash line before submitting.');
                }

                if ($lines === []) {
                    return;
                }

                $categories = ShopAccountingCategory::query()
                    ->whereIn('id', collect($lines)->pluck('shop_accounting_category_id')->filter()->toArray())
                    ->get()
                    ->keyBy('id');

                foreach ($lines as $index => $line) {
                    $category = $categories->get((int) ($line['shop_accounting_category_id'] ?? 0));

                    if (! $category instanceof ShopAccountingCategory) {
                        continue;
                    }

                    if (str($category->name)->lower()->startsWith('other') && blank($line['description'] ?? null)) {
                        $validator->errors()->add("lines.$index.description", 'Notes are required when using Other.');
                    }

                    if ($isAdjustment && blank($line['description'] ?? null)) {
                        $validator->errors()->add("lines.$index.description", 'Notes are required for adjustment entries.');
                    }
                }
            },
        ];
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
