<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user instanceof User && $user->isMainAdmin();
    }

    public function rules(): array
    {
        return [
            'setting_id'             => 'required|integer|exists:shop_ledger_entry_settings,id',
            'default_funding_source' => 'required|string',
            'include_in_sales'       => 'required|boolean',
            'include_in_expense'     => 'required|boolean',
            'include_in_pl'          => 'required|boolean',
            'generates_secondary'    => 'required|boolean',
        ];
    }
}
