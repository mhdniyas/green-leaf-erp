<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoadoutIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'source' => ['nullable', Rule::in(['all', 'shop', 'direct'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_ids' => ['nullable'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ];
    }

    /**
     * @return array<int, int>|null
     */
    public function categoryIds(): ?array
    {
        if ($this->has('category_ids')) {
            $rawIds = $this->get('category_ids');

            if (is_array($rawIds)) {
                return array_values(array_filter(array_map('intval', $rawIds)));
            }

            if (is_string($rawIds) && $rawIds !== '') {
                return array_values(array_filter(array_map('intval', explode(',', $rawIds))));
            }
        }

        $validated = $this->validated();

        return isset($validated['category_id']) ? [(int) $validated['category_id']] : null;
    }
}
