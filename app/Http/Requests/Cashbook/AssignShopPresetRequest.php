<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AssignShopPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isMainAdmin();
    }

    public function rules(): array
    {
        return [
            'shop_id' => 'required|integer|exists:shop_ledger_profiles,shop_id',
            'preset_id' => 'nullable|integer|exists:cashbook_config_presets,id',
        ];
    }
}
