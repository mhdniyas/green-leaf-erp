<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class FundShopPettyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && ($this->user()->isMainAdmin() || $this->user()->hasRole('admin'));
    }

    public function rules(): array
    {
        return [
            'shop_uuid' => ['nullable', 'string'],
            'company_account_id' => ['required_without:company_account_uuid', 'nullable', 'integer', 'exists:cashbook_company_accounts,id'],
            'company_account_uuid' => ['required_without:company_account_id', 'nullable', 'string', 'exists:cashbook_company_accounts,public_uuid'],
            'request_uuid' => ['nullable', 'string'],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
