<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CreatePresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isMainAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_default' => 'nullable|boolean',
            'copy_from_preset_id' => 'nullable|integer|exists:cashbook_config_presets,id',
        ];
    }
}
