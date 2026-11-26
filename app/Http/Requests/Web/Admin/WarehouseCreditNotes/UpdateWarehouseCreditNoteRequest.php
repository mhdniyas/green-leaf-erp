<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin\WarehouseCreditNotes;

use App\Enums\Warehouse\WarehouseCreditInventoryAction;
use App\Enums\Warehouse\WarehouseCreditReason;
use App\Models\User;
use App\Models\WarehouseCreditNote;
use App\Support\CashbookAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateWarehouseCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            return true;
        }

        if (! $user->hasRole('accounts') || ! $user->can(CashbookAccess::WarehouseCreditNotesManage)) {
            return false;
        }

        $creditNote = $this->route('creditNote');
        if ($creditNote instanceof WarehouseCreditNote && ! $user->canAccessWarehouse((int) $creditNote->warehouse_id)) {
            return false;
        }

        $targetWarehouseId = (int) $this->input('warehouse_id', 0);

        return $targetWarehouseId > 0 ? $user->canAccessWarehouse($targetWarehouseId) : true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'business_date' => ['required', 'date'],
            'movement_direction' => ['nullable', 'string', Rule::in(['in', 'out', 'mixed'])],
            'reason' => ['required', new Enum(WarehouseCreditReason::class)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'save_action' => ['nullable', 'string', Rule::in(['draft', 'finalize'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.grade' => ['required', 'string', Rule::in(['A', 'B', 'C', 'U'])],
            'items.*.inventory_action' => ['required', new Enum(WarehouseCreditInventoryAction::class)],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.valuation_rate' => ['nullable', 'numeric', 'gte:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $direction = $this->input('movement_direction');
                $reason = (string) $this->input('reason');

                if ($direction === 'in' && ! in_array($reason, ['excess_found', 'stock_return', 'other'], true)) {
                    $validator->errors()->add('reason', 'Selected reason is not valid for Stock IN.');
                }

                if ($direction === 'out' && ! in_array($reason, ['shortage', 'stock_return', 'damage_wastage', 'spoilage', 'other'], true)) {
                    $validator->errors()->add('reason', 'Selected reason is not valid for Stock OUT.');
                }
            },
        ];
    }
}
