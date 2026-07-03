<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\ShopOwner;

use App\Models\ShopEmployeeAssignment;
use Illuminate\Foundation\Http\FormRequest;

class StoreShopEmployeeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ShopEmployeeAssignment::class) || $this->user()->hasRole('shop');
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ];
    }
}
