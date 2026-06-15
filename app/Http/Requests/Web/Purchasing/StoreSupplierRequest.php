<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchasing.supplier.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255', 'unique:suppliers,name'],
            'type' => ['required', 'string', 'in:Farmer,Market Agent,Importer,Co-operative'],
            'category' => ['required', 'string', 'in:own_purchase,b2b,market'],
            'is_default_purchase' => ['nullable', 'boolean'],
            'contact' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:50'],
            'payment_terms' => ['required', 'string', 'in:COD,Net 7,Net 15,Net 30'],
            'preferred_payment_method' => ['nullable', 'string', 'max:100'],
            'credit_approved' => ['nullable', 'boolean'],
            'credit_terms' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_default_purchase' => $this->boolean('is_default_purchase'),
            'credit_approved' => $this->boolean('credit_approved'),
        ]);
    }
}
