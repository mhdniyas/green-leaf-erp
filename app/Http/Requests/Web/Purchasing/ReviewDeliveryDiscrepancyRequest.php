<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'item_inventory_actions.*' => ['nullable', 'string', 'in:none,add_back,deduct_extra,return_to_warehouse,wastage,already_accounted'],
            'delivery_discrepancy_types' => ['nullable', 'array'],
            'delivery_discrepancy_types.*' => ['nullable', 'string', 'in:none,wastage,excess,other,wastage_damage,loadout_mistake,delivery_mistake'],
            'delivery_discrepancy_notes' => ['nullable', 'array'],
            'delivery_discrepancy_notes.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $invoice = $this->route('invoice');
            $order = null;

            if ($invoice instanceof ShopInvoice) {
                $order = $invoice->order;
            } elseif (is_numeric($invoice) || is_string($invoice)) {
                $invoiceModel = ShopInvoice::query()->with('order.items.product')->find($invoice);
                $order = $invoiceModel?->order;
            }

            if (! $order && $this->route('order_number')) {
                $order = ShopOrder::query()
                    ->where('order_number', (string) $this->route('order_number'))
                    ->with('items.product')
                    ->first();
            }

            if (! $order) {
                return;
            }

            $order->loadMissing('items.product');

            $approvedDeliveredQuantities = $this->input('approved_delivered_qty', []);
            $reasons = $this->input('delivery_discrepancy_types', []);
            $resolutions = $this->input('item_inventory_actions', []);
            $notes = $this->input('delivery_discrepancy_notes', []);

            foreach ($order->items as $item) {
                $expectedQty = $item->loaded_qty !== null
                    ? round((float) $item->loaded_qty, 2)
                    : round((float) $item->approved_qty, 2);

                $deliveredQty = array_key_exists($item->id, $approvedDeliveredQuantities)
                    ? round((float) $approvedDeliveredQuantities[$item->id], 2)
                    : round((float) ($item->delivered_qty ?? $expectedQty), 2);

                $diff = round($deliveredQty - $expectedQty, 2);

                if (abs($diff) > 0.001) {
                    $itemReason = $reasons[$item->id] ?? null;
                    $itemResolution = $resolutions[$item->id] ?? null;
                    $itemNote = trim((string) ($notes[$item->id] ?? ''));
                    $productName = $item->product?->name ?? "Item #{$item->id}";

                    if (empty($itemReason) || $itemReason === 'none') {
                        $validator->errors()->add(
                            "delivery_discrepancy_types.{$item->id}",
                            "Reason is required for quantity difference on {$productName}."
                        );
                    }

                    if (empty($itemResolution) || $itemResolution === 'none') {
                        $validator->errors()->add(
                            "item_inventory_actions.{$item->id}",
                            "Resolution is required for quantity difference on {$productName}."
                        );
                    }

                    if ($diff < 0 && $itemResolution && ! in_array($itemResolution, ['return_to_warehouse', 'wastage', 'already_accounted', 'add_back'], true)) {
                        $validator->errors()->add(
                            "item_inventory_actions.{$item->id}",
                            "Invalid resolution for shortage on {$productName}."
                        );
                    }

                    if ($diff > 0 && $itemResolution && ! in_array($itemResolution, ['deduct_extra'], true)) {
                        $validator->errors()->add(
                            "item_inventory_actions.{$item->id}",
                            "Invalid resolution for excess on {$productName}. Must be 'Deduct Extra From Warehouse'."
                        );
                    }

                    if (in_array($itemReason, ['other', 'Other'], true) && $itemNote === '') {
                        $validator->errors()->add(
                            "delivery_discrepancy_notes.{$item->id}",
                            "Note is required when reason is 'Other' on {$productName}."
                        );
                    }

                    if (in_array($itemResolution, ['already_accounted', 'Already Accounted / No Stock Adjustment'], true) && $itemNote === '') {
                        $validator->errors()->add(
                            "delivery_discrepancy_notes.{$item->id}",
                            "Note is required when resolution is 'Already Accounted' on {$productName}."
                        );
                    }
                }
            }
        });
    }
}
