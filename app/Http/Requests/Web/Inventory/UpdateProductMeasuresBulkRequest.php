<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use App\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductMeasuresBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.product.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array'],
            'products.*.public_uuid' => ['required', 'string', 'exists:products,public_uuid'],
            'products.*.base_unit' => ['required', 'string', Rule::in(ProductUnit::AVAILABLE_UNITS)],
            'products.*.units' => ['nullable', 'array'],
            'products.*.units.*' => ['nullable', 'numeric', 'min:0.0001'],
        ];
    }

    public function validatedProducts(): array
    {
        return collect($this->validated('products', []))
            ->filter(fn (array $row): bool => filled($row['public_uuid'] ?? null))
            ->map(function (array $row): array {
                $baseUnit = strtolower((string) $row['base_unit']);
                $units = collect($row['units'] ?? [])
                    ->filter(fn ($value, string $unit): bool => in_array($unit, ProductUnit::AVAILABLE_UNITS, true) && filled($value))
                    ->map(fn ($value): float => round((float) $value, 4))
                    ->all();

                $units[$baseUnit] = 1.0;

                return [
                    'public_uuid' => (string) $row['public_uuid'],
                    'base_unit' => $baseUnit,
                    'units' => $units,
                ];
            })
            ->values()
            ->all();
    }
}
