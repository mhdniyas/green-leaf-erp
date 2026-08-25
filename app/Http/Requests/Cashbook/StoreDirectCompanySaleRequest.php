<?php

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDirectCompanySaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->isMainAdmin();
    }

    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date_format:Y-m-d'],
            'customer_name' => ['nullable', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
            'request_uuid' => ['required', 'uuid'],
            'payment_method' => ['required', Rule::in(['cash', 'bank'])],
            'company_account_uuid' => ['nullable', 'uuid', 'exists:cashbook_company_accounts,public_uuid', 'required_if:payment_method,bank'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_uuid' => ['required', 'uuid', 'distinct'],
            'items.*.unit' => ['required', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_rate' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
