<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class ReviewDeliveryDiscrepancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('purchase')
            || $this->user()?->hasRole('admin')
            || $this->user()?->can('purchasing.order.approve');
    }

    public function rules(): array
    {
        return [
            'review_note' => ['nullable', 'string', 'max:1000'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'approved_delivered_qty' => ['nullable', 'array'],
            'approved_delivered_qty.*' => ['nullable', 'numeric', 'min:0'],
            'item_review_notes' => ['nullable', 'array'],
            'item_review_notes.*' => ['nullable', 'string', 'max:500'],
            'item_inventory_actions' => ['nullable', 'array'],
            'item_inventory_actions.*' => ['nullable', 'string', 'in:none,add_back,deduct_extra'],
            'delivery_discrepancy_types' => ['nullable', 'array'],
            'delivery_discrepancy_types.*' => ['nullable', 'string', 'in:none,wastage,excess,other'],
            'delivery_discrepancy_notes' => ['nullable', 'array'],
            'delivery_discrepancy_notes.*' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
