<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyAccountingCashbookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->isMainAdmin();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:income,expense'],
            'company_accounting_category_id' => ['required', 'integer', 'exists:company_accounting_categories,id'],
            'company_account_uuid' => ['required', 'uuid', 'exists:cashbook_company_accounts,public_uuid'],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'request_uuid' => ['required', 'uuid'],
        ];
    }
}
