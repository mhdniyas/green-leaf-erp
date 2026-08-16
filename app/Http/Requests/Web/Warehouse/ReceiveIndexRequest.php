<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveIndexRequest extends FormRequest
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
            'date' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'receive_search' => ['nullable', 'string', 'max:120'],
            'receive_source' => ['nullable', Rule::in(['all', 'vendor', 'direct', 'batch'])],
            'receive_category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
