<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class FundShopPettyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->isMainAdmin();
    }

    public function rules(): array
    {
        return [
            'shop_uuid' => ['required', 'uuid', 'exists:shops,public_uuid'],
            'company_account_uuid' => ['required', 'uuid', 'exists:cashbook_company_accounts,public_uuid'],
            'request_uuid' => ['required', 'uuid'],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
