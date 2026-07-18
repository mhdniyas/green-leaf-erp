<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.customer.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255', Rule::unique('customers', 'name')],
            'type' => ['required', 'string', 'in:Retailer,Wholesaler,Restaurant,Supermarket'],
            'contact' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'payment_terms' => ['required', 'string', 'in:COD,Net 7,Net 15,Net 30'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
