<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventorySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') === true;
    }

    public function rules(): array
    {
        return [
            'sort_all_as_grade_a' => ['required', 'boolean'],
        ];
    }
}
