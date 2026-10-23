<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Purchasing;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceivedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['admin', 'purchase', 'purchaser', 'warehouse_receiver'])
            || $user->can('purchasing.grn.create')
            || $user->can('warehouse.receive.view');
    }

    public function rules(): array
    {
        return [
            'client_submission_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'destination_shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'received_at' => ['required', 'date'],
            'transport_cost' => ['sometimes', 'numeric', 'min:0'],
            'labour_cost' => ['sometimes', 'numeric', 'min:0'],
            'bill_status' => ['sometimes', 'string', 'in:bill_available,bill_pending'],
            'bill_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
            'purchase_grade' => ['sometimes', 'nullable', 'string', 'in:A,B,C,MIXED,U'],
            'grading_mode' => ['sometimes', 'nullable', 'string', 'in:fixed_purchase_grade,sort_required'],
            'items.*.grade' => ['sometimes', 'nullable', 'string', 'in:A,B,C,MIXED,U'],
            'items.*.grading_mode' => ['sometimes', 'nullable', 'string', 'in:fixed_purchase_grade,sort_required'],
            'items.*.discrepancy_type' => ['sometimes', 'nullable', 'string', 'in:none,wastage,other'],
            'items.*.discrepancy_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'bypass_advance_match' => ['sometimes', 'boolean'],
            'bypass_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'items.*.received_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'advance_matches' => ['sometimes', 'array'],
            'advance_matches.*.advance_goods_received_id' => ['required', 'integer', 'exists:goods_received,id'],
            'advance_matches.*.advance_goods_received_item_id' => ['nullable', 'integer', 'exists:goods_received_items,id'],
            'advance_matches.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'advance_matches.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'advance_matches.*.matched_qty' => ['required', 'numeric', 'min:0.001'],
            'advance_matches.*.unit' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $isAdvance = $this->input('receipt_type') === 'warehouse_advance'
                || ($this->input('purchase_order_id') === null && $this->input('bill_status') === 'bill_pending');

            $items = (array) $this->input('items', []);
            foreach ($items as $index => $item) {
                $rawGrade = isset($item['grade']) && filled($item['grade']) ? strtoupper((string) $item['grade']) : null;
                $gradingMode = isset($item['grading_mode']) && filled($item['grading_mode']) ? (string) $item['grading_mode'] : null;

                if ($gradingMode !== null) {
                    $resolvedGrade = in_array($rawGrade, ['MIXED', 'U'], true) || $rawGrade === null ? 'U' : $rawGrade;

                    if (in_array($resolvedGrade, ['A', 'B', 'C'], true) && $gradingMode !== 'fixed_purchase_grade') {
                        $validator->errors()->add("items.{$index}.grading_mode", "Grading mode for Grade {$resolvedGrade} must be fixed_purchase_grade.");
                    } elseif ($resolvedGrade === 'U' && $gradingMode !== 'sort_required') {
                        $validator->errors()->add("items.{$index}.grading_mode", 'Grading mode for Mixed / Unsegregated must be sort_required.');
                    }
                }

                if (empty($item['product_id']) || (empty($item['received_unit']) && empty($item['unit']))) {
                    continue;
                }

                $rawUnit = (string) ($item['received_unit'] ?? $item['unit']);
                $product = Product::find($item['product_id']);
                if (! $product) {
                    continue;
                }

                $normUnit = ProductUnit::normalizeUnit($rawUnit);
                $normBase = ProductUnit::normalizeUnit($product->unit);

                $allowed = [$normBase];
                if ($isAdvance) {
                    $allowed[] = 'kg';
                }

                $orderUnits = $product->relationLoaded('orderUnits') ? $product->orderUnits : $product->orderUnits()->get();
                foreach ($orderUnits as $ou) {
                    $allowed[] = ProductUnit::normalizeUnit($ou->unit);
                }

                if (! in_array($normUnit, $allowed, true)) {
                    $validator->errors()->add(
                        "items.{$index}.received_unit",
                        "The received unit '{$rawUnit}' is not allowed for product '{$product->name}'."
                    );
                }
            }

            if ($this->filled('grading_mode')) {
                $topGrade = $this->filled('purchase_grade') ? strtoupper((string) $this->input('purchase_grade')) : ($this->filled('grade') ? strtoupper((string) $this->input('grade')) : null);
                $resolvedTopGrade = in_array($topGrade, ['MIXED', 'U'], true) || $topGrade === null ? 'U' : $topGrade;
                $topMode = (string) $this->input('grading_mode');
                if (in_array($resolvedTopGrade, ['A', 'B', 'C'], true) && $topMode !== 'fixed_purchase_grade') {
                    $validator->errors()->add('grading_mode', "Grading mode for Grade {$resolvedTopGrade} must be fixed_purchase_grade.");
                } elseif ($resolvedTopGrade === 'U' && $topMode !== 'sort_required') {
                    $validator->errors()->add('grading_mode', 'Grading mode for Mixed / Unsegregated must be sort_required.');
                }
            }
        });
    }
}
