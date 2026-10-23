<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use App\Models\GoodsReceived;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceivedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', GoodsReceived::class);
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'received_at' => ['required', 'date'],
            'transport_cost' => ['sometimes', 'numeric', 'min:0'],
            'labour_cost' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'purchase_grade' => ['sometimes', 'nullable', 'string', 'in:A,B,C,MIXED,U'],
            'grading_mode' => ['sometimes', 'nullable', 'string', 'in:fixed_purchase_grade,sort_required'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
            'items.*.grade' => ['sometimes', 'nullable', 'string', 'in:A,B,C,MIXED,U'],
            'items.*.grading_mode' => ['sometimes', 'nullable', 'string', 'in:fixed_purchase_grade,sort_required'],
            'items.*.received_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'items.*.unit' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
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
