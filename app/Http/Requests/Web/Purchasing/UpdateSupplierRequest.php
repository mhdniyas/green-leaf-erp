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
            'contact' => ['required', 'string', 'max:255'],
            'payment_terms' => ['required', 'string', 'in:COD,Net 7,Net 15,Net 30'],
            'quality_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
