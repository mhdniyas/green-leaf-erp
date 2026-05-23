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
            'contact' => ['required', 'string', 'max:255'],
            'payment_terms' => ['required', 'string', 'in:COD,Net 7,Net 15,Net 30'],
        ];
    }
}
