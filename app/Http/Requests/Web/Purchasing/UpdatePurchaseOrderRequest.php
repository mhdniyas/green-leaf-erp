<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $po = $this->route('order') ?? $this->route('purchase_order');
        if ($po instanceof PurchaseOrder) {
            return $this->user()->can('update', $po);
        }

        return $this->user()->can('purchasing.order.create');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'order_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.0001'],
        ];
    }
}
