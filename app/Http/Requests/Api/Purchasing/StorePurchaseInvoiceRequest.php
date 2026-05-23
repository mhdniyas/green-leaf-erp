<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchasing.order.create') || $this->user()->can('accounting.entry.create');
    }

    public function rules(): array
    {
        return [
            'goods_received_id' => ['required', 'integer', 'exists:goods_received,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'status' => ['required', 'string', 'in:pending,approved,paid'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
