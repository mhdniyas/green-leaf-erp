<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchasing.supplier.update');
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier')?->id ?? $this->route('supplier');

        return [
            'name' => ['required', 'string', 'min:2', 'max:255', 'unique:suppliers,name,'.$supplierId],
            'type' => ['required', 'string', 'in:Farmer,Market Agent,Importer,Co-operative'],
            'category' => ['required', 'string', 'in:own_purchase,b2b,market'],
            'is_default_purchase' => ['nullable', 'boolean'],
            'contact' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:50'],
            'payment_terms' => ['required', 'string', 'in:COD,Net 7,Net 15,Net 30'],
            'preferred_payment_method' => ['nullable', 'string', 'max:100'],
            'bank_details' => ['nullable', 'string', 'max:1000'],
            'vendor_bank_details' => ['nullable', 'string', 'max:1000'],
            'credit_approved' => ['nullable', 'boolean'],
            'credit_terms' => ['nullable', 'string', 'max:100'],
            'quality_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
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
