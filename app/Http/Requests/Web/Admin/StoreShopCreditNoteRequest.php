<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopInvoice;
use App\Models\User;
use App\Services\ShopInvoices\ShopCreditNoteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreShopCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && (
            $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->hasRole('super_admin')
        );
    }

    /**
     * Prepare the data for validation.
     * Resolves the server-side session selection token when invoice_id is not passed directly.
     */
    protected function prepareForValidation(): void
    {
        $selections = (array) $this->session()->get('credit_note_selections', []);
        $now = now()->timestamp;

        if ($this->filled('selection_token')) {
            $token = (string) $this->input('selection_token');

            if (isset($selections[$token]['invoice_id'], $selections[$token]['created_at'])) {
                if (($now - (int) $selections[$token]['created_at']) < 7200) {
                    $this->merge([
                        'invoice_id' => (int) $selections[$token]['invoice_id'],
                    ]);

                    return;
                }
            }

            // Explicit token provided but invalid or expired: force failure
            $this->merge(['invoice_id' => null]);

            return;
        }

        // If no selection_token provided, verify if a single active session selection exists
        if (! $this->filled('invoice_id') && count($selections) === 1) {
            $single = reset($selections);
            if (is_array($single) && isset($single['invoice_id'], $single['created_at']) && ($now - (int) $single['created_at']) < 7200) {
                $this->merge([
                    'invoice_id' => (int) $single['invoice_id'],
                ]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'selection_token' => ['nullable', 'string', 'max:100'],
            'invoice_id' => ['required', 'integer', 'exists:shop_invoices,id'],
            'credit_note_date' => ['required', 'date_format:Y-m-d'],
            'reason_type' => ['required', Rule::enum(ShopCreditNoteReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'action' => ['required', 'string', Rule::in(['draft', 'finalize'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.shop_invoice_item_id' => ['required', 'integer', 'exists:shop_invoice_items,id'],
            'items.*.revised_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.revised_unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.inventory_action' => ['required', Rule::enum(ShopCreditNoteInventoryAction::class)],
            'items.*.damage_reason' => ['nullable', 'string', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_id.required' => 'The selected invoice session has expired or is invalid. Please select the invoice again.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $invoiceId = (int) $this->input('invoice_id');
                $invoice = ShopInvoice::with('items')->find($invoiceId);

                if (! $invoice instanceof ShopInvoice) {
                    $validator->errors()->add('invoice_id', 'The selected shop invoice was not found.');

                    return;
                }

                if ($invoice->status === 'cancelled') {
                    $validator->errors()->add('invoice_id', 'Cancelled invoices cannot receive credit notes.');

                    return;
                }

                $creditNoteDate = (string) $this->input('credit_note_date');
                $periodClosed = ShopAccountingPeriodClosure::query()
                    ->where('shop_id', $invoice->shop_id)
                    ->whereDate('period_start', '<=', $creditNoteDate)
                    ->whereDate('period_end', '>=', $creditNoteDate)
                    ->exists();

                if ($periodClosed) {
                    $validator->errors()->add('credit_note_date', 'Cannot create or finalize a credit note in a closed accounting period.');
                }

                $items = (array) $this->input('items', []);
                $creditNoteService = app(ShopCreditNoteService::class);
                $hasAnyAdjustment = false;

                foreach ($items as $index => $itemData) {
                    $invAction = (string) ($itemData['inventory_action'] ?? 'none');

                    if ($invAction === 'deduct_from_stock') {
                        $validator->errors()->add(
                            "items.{$index}.inventory_action",
                            "Inventory action 'Deduct Extra from Warehouse' is not supported in this release. Please select 'Financial adjustment only', 'Return to warehouse', or 'Move to damage'."
                        );
                    }

                    $invoiceItemId = (int) ($itemData['shop_invoice_item_id'] ?? 0);
                    $invoiceItem = $invoice->items->firstWhere('id', $invoiceItemId);

                    if (! $invoiceItem) {
                        $validator->errors()->add("items.{$index}.shop_invoice_item_id", "Invoice item #{$invoiceItemId} does not belong to invoice {$invoice->invoice_number}.");

                        continue;
                    }

                    $cumulative = $creditNoteService->cumulativeAdjustmentsForInvoiceItem($invoiceItem);
                    $currentEffectiveQty = (float) $cumulative['active']['effective_quantity'];
                    $origPrice = (float) $invoiceItem->unit_price;

                    $revisedQty = isset($itemData['revised_quantity']) && $itemData['revised_quantity'] !== null && $itemData['revised_quantity'] !== ''
                        ? (float) $itemData['revised_quantity']
                        : $currentEffectiveQty;

                    $revisedPrice = isset($itemData['revised_unit_price']) && $itemData['revised_unit_price'] !== null && $itemData['revised_unit_price'] !== ''
                        ? (float) $itemData['revised_unit_price']
                        : $origPrice;

                    $qtyChanged = abs($revisedQty - $currentEffectiveQty) > 0.0001;
                    $priceChanged = abs($revisedPrice - $origPrice) > 0.001;

                    if ($qtyChanged || $priceChanged) {
                        $hasAnyAdjustment = true;
                    }

                    if ($invAction === 'return_to_stock') {
                        if ($revisedQty >= $currentEffectiveQty - 0.0001) {
                            $validator->errors()->add(
                                "items.{$index}.revised_quantity",
                                "To return items to warehouse stock, revised quantity must be less than the current effective quantity ({$currentEffectiveQty})."
                            );
                        }
                    }

                    if ($invAction === 'write_off_wastage') {
                        if ($revisedQty >= $currentEffectiveQty - 0.0001) {
                            $validator->errors()->add(
                                "items.{$index}.revised_quantity",
                                "To move items to damage, revised quantity must be less than the current effective quantity ({$currentEffectiveQty})."
                            );
                        }

                        $damageReason = $itemData['damage_reason'] ?? null;
                        if (empty($damageReason) && empty($itemData['notes']) && $this->input('reason_type') !== 'damage_wastage') {
                            $validator->errors()->add(
                                "items.{$index}.damage_reason",
                                'A damage reason is required when moving items to damage.'
                            );
                        }
                    }
                }

                if (! $hasAnyAdjustment) {
                    $validator->errors()->add('items', 'No adjustments detected. Please correct the quantity or unit price for at least one product row.');
                }
            },
        ];
    }
}
