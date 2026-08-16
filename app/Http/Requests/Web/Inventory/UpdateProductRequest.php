<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.product.update');
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : $product;

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'default_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'sku' => ['required', 'string', 'max:100', "unique:products,sku,{$productId}", 'regex:/^[A-Za-z0-9\-_]+$/'],
            'unit' => ['required', 'string', 'in:'.implode(',', ProductUnit::AVAILABLE_UNITS)],
            'units' => ['nullable', 'array'],
            'units.*.unit' => ['required_with:units', 'string', 'in:'.implode(',', ProductUnit::AVAILABLE_UNITS)],
            'units.*.label' => ['nullable', 'string', 'max:50'],
            'units.*.conversion_to_base' => ['nullable', 'numeric', 'min:0.0001'],
            'units.*.is_base' => ['sometimes', 'boolean'],
            'units.*.is_orderable' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'buffer_qty' => ['nullable', 'numeric', 'min:0'],
            'carryover_enabled' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'show_in_purchaser_order' => ['sometimes', 'boolean'],
            'image_data' => ['nullable', 'string'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->validateUnitRows($validator);
        });
    }

    private function validateUnitRows($validator): void
    {
        $rows = collect($this->input('units', []))
            ->filter(fn ($row): bool => is_array($row) && filled($row['unit'] ?? null))
            ->values();

        if ($rows->isEmpty()) {
            return;
        }

        $labels = $rows->map(fn (array $row): string => mb_strtolower(trim((string) ($row['label'] ?? $row['unit'] ?? ''))));
        if ($labels->duplicates()->isNotEmpty()) {
            $validator->errors()->add('units', 'Each measure label can only be added once.');
        }

        $units = $rows->map(fn (array $row): string => strtolower((string) $row['unit']));
        $baseUnit = strtolower($this->string('unit')->toString());
        if (! $units->contains($baseUnit)) {
            $validator->errors()->add('units', 'The base unit must be included in order units.');
        }

        foreach ($rows as $index => $row) {
            $unit = strtolower((string) $row['unit']);

            if ($unit === $baseUnit && abs((float) ($row['conversion_to_base'] ?? 0) - 1.0) > 0.0001) {
                $validator->errors()->add("units.{$index}.conversion_to_base", 'The base unit conversion must be 1.');
            }

            if ($unit !== $baseUnit && $unit !== 'piece' && ! filled($row['conversion_to_base'] ?? null)) {
                $validator->errors()->add("units.{$index}.conversion_to_base", 'KG conversion is required for this unit.');
            }
        }
    }
}
