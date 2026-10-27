<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Enums\Inventory\ProductGrade;
use Illuminate\Foundation\Http\FormRequest;

class GradeStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['admin', 'warehouse_receiver', 'warehouse', 'purchaser']) ||
            $user->can('inventory.sorting.process') ||
            $user->can('warehouse.receive.sort') ||
            $user->can('inventory.stock.adjust')) {

            $warehouseId = $this->input('warehouse_id');
            if ($warehouseId && ! $user->hasRole('admin') && ! $user->hasAllWarehouseAccess()) {
                $assignedIds = $user->warehouses()->pluck('warehouses.id')->all();
                if (! empty($assignedIds) && ! in_array((int) $warehouseId, $assignedIds, true)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'source_grade' => ['required', 'string'],
            'target_grade' => ['required', 'string', 'different:source_grade'],
            'source_batch_id' => ['nullable', 'integer', 'exists:stock_batches,id'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],
            'client_submission_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $src = ProductGrade::tryResolve($this->input('source_grade'));
            $dst = ProductGrade::tryResolve($this->input('target_grade'));

            if (! $src) {
                $v->errors()->add('source_grade', 'Invalid source grade. Supported: Mixed (U), Grade A, Grade B, Grade C.');
            }

            if (! $dst) {
                $v->errors()->add('target_grade', 'Invalid destination grade. Supported: Mixed (U), Grade A, Grade B, Grade C.');
            }

            if ($src && $dst && $src === $dst) {
                $v->errors()->add('target_grade', "Source and destination cannot be the same grade ({$src->value}).");
            }
        });
    }
}
