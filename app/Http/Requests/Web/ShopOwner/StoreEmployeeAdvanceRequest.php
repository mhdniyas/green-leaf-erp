<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\ShopOwner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeAdvanceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('fund_source') || empty($this->input('fund_source'))) {
            $this->merge([
                'fund_source' => 'petty_cash',
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasRole('shop') && $this->user()?->can('hr.attendance.mark-owned-shop');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'requested_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'fund_source' => ['required', 'string', Rule::in(['petty_cash', 'sales_income'])],
            'request_note' => ['nullable', 'string', 'max:1000'],
            'request_uuid' => ['required', 'string', 'uuid'],
        ];
    }
}
