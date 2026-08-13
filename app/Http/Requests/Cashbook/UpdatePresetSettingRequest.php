<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePresetSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user instanceof User && $user->isMainAdmin();
    }

    public function rules(): array
    {
        return [
            'setting_id'               => 'required|integer|exists:cashbook_preset_entry_settings,id',
            'enabled'                  => 'sometimes|boolean',
            'include_in_sales'         => 'sometimes|boolean',
            'include_in_income'        => 'sometimes|boolean',
            'include_in_expense'       => 'sometimes|boolean',
            'include_in_pl'            => 'sometimes|boolean',
            'settlement_behavior'      => 'nullable|string|in:none,increase,decrease',
            'petty_behavior'           => 'nullable|string|in:none,increase,decrease',
            'company_pending_behavior' => 'nullable|string|in:none,increase,decrease',
            'default_funding_source'   => 'nullable|string|max:50',
            'allowed_funding_sources'  => 'nullable|array',
        ];
    }
}
