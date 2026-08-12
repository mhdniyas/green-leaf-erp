<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSalesShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.customer.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('shops', 'code')],
            'warehouse_tag' => ['nullable', 'string', 'max:12', Rule::unique('shops', 'warehouse_tag')],
            'shop_price_group_id' => ['nullable', 'integer', 'exists:shop_price_groups,id'],
            'allow_grade_b_purchase' => ['sometimes', 'boolean'],
            'destination_type' => ['required', 'string', 'in:client,direct'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->string('destination_type')->toString() === 'client'
                    && ! $this->filled('client_id')
                    && ! $this->filled('client_name')
                ) {
                    $validator->errors()->add('client_id', 'Select a client or enter a new client name.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(str_replace([' ', '-'], '_', trim((string) $this->input('code')))),
            'warehouse_tag' => filled($this->input('warehouse_tag')) ? strtoupper(trim((string) $this->input('warehouse_tag'))) : null,
        ]);
    }
}
