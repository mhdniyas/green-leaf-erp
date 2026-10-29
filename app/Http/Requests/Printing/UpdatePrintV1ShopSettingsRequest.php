<?php

declare(strict_types=1);

namespace App\Http\Requests\Printing;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrintV1ShopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sort.sheet.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'included_inactive_shop_ids' => 'nullable|array',
            'included_inactive_shop_ids.*' => 'integer|exists:shops,id',
            'excluded_active_shop_ids' => 'nullable|array',
            'excluded_active_shop_ids.*' => 'integer|exists:shops,id',
            'default_skip_empty_shops' => 'nullable|boolean',
        ];
    }
}
