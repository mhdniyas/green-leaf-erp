<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accounting.entry.create');
    }

    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date'],
            'account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(function ($query) {
                    $query->where('type', 'expense')->where('is_active', true);
                }),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'in:cash,bank'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
