<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Warehouse;

use App\Services\Warehouse\WarehouseSalesAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWarehouseSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if (! WarehouseSalesAccessService::userCanMakeSales($user)) {
            return false;
        }

        $warehouseId = $this->input('warehouse_id');
        if ($warehouseId !== null) {
            return WarehouseSalesAccessService::userCanSellFromWarehouse($user, (int) $warehouseId);
        }

        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'customer_type' => ['nullable', 'string', 'in:walkin,existing,new'],
            'customer_id' => ['nullable', 'integer', 'exists:warehouse_customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'business_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.grade' => ['nullable', 'string'],
            'items.*.grade_id' => ['nullable', 'integer'],
            'items.*.qty' => ['nullable', 'numeric', 'gt:0'],
            'items.*.entered_qty' => ['nullable', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'money_holder_type' => ['nullable', 'string', 'in:company,user'],
            'money_holder_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'company_account_id' => ['nullable', 'integer', 'exists:cashbook_company_accounts,id'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $moneyHolderType = (string) $this->input('money_holder_type');
                $moneyHolderUserId = $this->input('money_holder_user_id');

                if ($moneyHolderType === 'user' && ! $moneyHolderUserId) {
                    $validator->errors()->add('money_holder_user_id', 'User must be selected when money is held by user.');
                }
            },
        ];
    }
}
