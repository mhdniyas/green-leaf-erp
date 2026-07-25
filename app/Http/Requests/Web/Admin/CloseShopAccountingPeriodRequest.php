<?php

namespace App\Http\Requests\Web\Admin;

use App\Support\AccountingAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CloseShopAccountingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AccountingAccess::canManageOwnedShops($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
