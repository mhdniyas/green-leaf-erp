<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin\ShopCreditNotes;

use App\Models\Shop;
use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\User;
use App\Services\ShopInvoices\ShopCreditNoteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveShopCreditNoteDraftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && (
            $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->hasRole('super_admin')
            || $user->hasRole('accounts')
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Support JSON string payload if submitted from Alpine/JS via payload_json
        if ($this->filled('payload_json')) {
            $decoded = json_decode((string) $this->input('payload_json'), true);
            if (is_array($decoded)) {
                $this->merge($decoded);
            }
        }

        // If editing an existing draft and shop_id was omitted, resolve from route model
        $routeModel = $this->route('creditNote');
        if (! $this->filled('shop_id') && $routeModel && isset($routeModel->shop_id)) {
            $this->merge(['shop_id' => (int) $routeModel->shop_id]);
        }

        // Default credit_note_date to existing draft date on update, or today on create
        if (! $this->filled('credit_note_date')) {
            $defaultDate = ($routeModel instanceof ShopCreditNote && $routeModel->credit_note_date)
                ? $routeModel->credit_note_date->toDateString()
                : today()->toDateString();
            $this->merge(['credit_note_date' => $defaultDate]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'credit_note_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'expected_lock_version' => ['nullable', 'integer'],
            'expected_updated_at' => ['nullable', 'string'],

            'items' => ['nullable', 'array'],
            'items.*.source_shop_invoice_id' => ['required', 'integer', 'exists:shop_invoices,id'],
            'items.*.shop_invoice_item_id' => ['required', 'integer', 'exists:shop_invoice_items,id'],
            'items.*.credit_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.credit_rate' => ['required', 'numeric', 'min:0'],
            'items.*.line_credit_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.credit_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason_type' => ['nullable', 'string'],
            'items.*.inventory_action' => ['nullable', 'string'],
            'items.*.expected_returned_grade' => ['nullable', 'string', 'in:A,B,C,a,b,c'],
            'items.*.damage_reason' => ['nullable', 'string'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],

            'discounts' => ['nullable', 'array'],
            'discounts.*.line_credit_amount' => ['nullable', 'numeric', 'min:0.01'],
            'discounts.*.discount_amount' => ['nullable', 'numeric', 'min:0.01'],
            'discounts.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            'discounts.*.notes' => ['nullable', 'string', 'max:500'],
            'discounts.*.description' => ['nullable', 'string', 'max:500'],
            'discounts.*.source_shop_invoice_id' => ['nullable', 'integer', 'exists:shop_invoices,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $items = (array) $this->input('items', []);
            $discounts = (array) $this->input('discounts', []);

            $hasValidProductLine = false;
            foreach ($items as $item) {
                if (is_array($item)) {
                    $qty = (float) ($item['credit_quantity'] ?? $item['adjusted_quantity'] ?? 0.0);
                    $rate = (float) ($item['credit_rate'] ?? $item['adjusted_unit_price'] ?? 0.0);
                    $amt = (float) ($item['line_credit_amount'] ?? $item['credit_amount'] ?? ($qty * $rate));
                    if ($qty > 0.0001 || $amt > 0.0001) {
                        $hasValidProductLine = true;
                        break;
                    }
                }
            }

            $hasValidDiscount = false;
            foreach ($discounts as $disc) {
                if (is_array($disc)) {
                    $amt = (float) ($disc['line_credit_amount'] ?? $disc['discount_amount'] ?? $disc['amount'] ?? 0.0);
                    if ($amt > 0.0001) {
                        $hasValidDiscount = true;
                        break;
                    }
                }
            }

            if (! $hasValidProductLine && ! $hasValidDiscount) {
                $v->errors()->add(
                    'items',
                    'At least one product adjustment line or commercial discount must be specified with a positive amount.'
                );
            }

            // Cross-shop validation check: all source invoices must belong to the selected shop
            $shopId = (int) $this->input('shop_id');
            if ($shopId > 0) {
                $invoiceIds = [];
                foreach ($items as $item) {
                    if (is_array($item) && ! empty($item['source_shop_invoice_id'])) {
                        $invoiceIds[] = (int) $item['source_shop_invoice_id'];
                    }
                }
                foreach ($discounts as $disc) {
                    if (is_array($disc) && ! empty($disc['source_shop_invoice_id'])) {
                        $invoiceIds[] = (int) $disc['source_shop_invoice_id'];
                    }
                }

                $invoiceIds = array_unique(array_filter($invoiceIds));
                if (! empty($invoiceIds)) {
                    $invalidInvoices = ShopInvoice::query()
                        ->whereIn('id', $invoiceIds)
                        ->where('shop_id', '!=', $shopId)
                        ->pluck('invoice_number', 'id');

                    if ($invalidInvoices->isNotEmpty()) {
                        foreach ($invalidInvoices as $invNum) {
                            $v->errors()->add(
                                'shop_id',
                                "Cross-shop invoice blocked: Source invoice '{$invNum}' does not belong to the selected shop."
                            );
                        }
                    }
                }
            }

            // Business date override authorization and accounting-period closure validation
            $submittedDate = (string) $this->input('credit_note_date', '');
            if ($submittedDate !== '' && ! $v->errors()->has('credit_note_date')) {
                $routeModel = $this->route('creditNote');
                $defaultDate = ($routeModel instanceof ShopCreditNote && $routeModel->credit_note_date)
                    ? $routeModel->credit_note_date->toDateString()
                    : today()->toDateString();

                if ($submittedDate !== $defaultDate && ! app(ShopCreditNoteService::class)->canOverrideBusinessDate($this->user())) {
                    $v->errors()->add(
                        'credit_note_date',
                        'Only authorized administrators can override the default Credit Note business date.'
                    );
                } elseif ($shopId > 0) {
                    $periodClosed = ShopAccountingPeriodClosure::query()
                        ->where('shop_id', $shopId)
                        ->whereDate('period_start', '<=', $submittedDate)
                        ->whereDate('period_end', '>=', $submittedDate)
                        ->exists();

                    if ($periodClosed) {
                        $v->errors()->add(
                            'credit_note_date',
                            $routeModel instanceof ShopCreditNote
                                ? 'Cannot update credit note to a closed accounting period.'
                                : 'Cannot create credit note in a closed accounting period.'
                        );
                    }
                }
            }
        });
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shop_id.required' => 'Please select a shop.',
            'shop_id.exists' => 'The selected shop does not exist.',
            'credit_note_date.required' => 'Credit note date is required.',
            'items.*.credit_quantity.min' => 'Credit quantity cannot be negative.',
            'items.*.credit_rate.min' => 'Credit rate cannot be negative.',
        ];
    }
}
