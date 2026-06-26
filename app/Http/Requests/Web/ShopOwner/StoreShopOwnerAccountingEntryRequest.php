<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\ShopOwner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'business_date' => ['required', 'date'],
            'submission_action' => ['required', 'string', Rule::in(['save_draft', 'submit'])],
            'opening_cash' => ['nullable', 'numeric', 'min:0'],
            'closing_cash' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'shop_reply_note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.shop_accounting_category_id' => ['required', 'integer', 'exists:shop_accounting_categories,id'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
