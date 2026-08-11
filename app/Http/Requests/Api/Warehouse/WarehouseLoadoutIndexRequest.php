<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Warehouse;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseLoadoutIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $warehouse = $this->route('warehouse');

        return $warehouse instanceof Warehouse
            && $this->user()?->canAccessWarehouse($warehouse) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'source' => ['nullable', Rule::in(['all', 'shop', 'direct'])],
        ];
    }
}
