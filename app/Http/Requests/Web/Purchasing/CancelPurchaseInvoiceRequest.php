<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use App\Models\PurchaseInvoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelPurchaseInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var PurchaseInvoice|null $invoice */
        $invoice = $this->route('invoice');

        return $invoice instanceof PurchaseInvoice
            && ($this->user()?->can('cancel', $invoice) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', Rule::in([
                'Test invoice',
                'Duplicate invoice',
                'Wrong supplier',
                'Wrong amount/items',
                'Entered by mistake',
                'Other',
            ])],
            'cancellation_note' => ['nullable', 'string', 'max:1000', 'required_if:cancellation_reason,Other'],
        ];
    }
}
