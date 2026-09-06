<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\PayrollRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewEmployeeAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PayrollRun::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'approved_amount' => ['nullable', 'required_if:decision,approve', 'numeric', 'min:0.01'],
            'fund_source' => ['nullable', 'required_if:decision,approve', 'string', Rule::in(['sales_income', 'petty_cash', 'company_cash', 'company_bank'])],
            'company_account_id' => [
                'nullable',
                'required_if:fund_source,company_cash,company_bank',
                'integer',
                'exists:cashbook_company_accounts,id',
            ],
            'review_note' => ['nullable', 'required_if:decision,reject', 'string', 'max:1000'],
        ];
    }
}
