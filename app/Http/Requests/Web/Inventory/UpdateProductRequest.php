<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    private const Units = ['kg', 'box', 'bunch', 'piece', 'bag', 'packet', 'crate', 'tray'];

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
            'unit' => ['required', 'string', 'in:'.implode(',', self::Units)],
            'units' => ['nullable', 'array'],
            'units.*.unit' => ['required_with:units', 'string', 'in:'.implode(',', self::Units)],
            'units.*.label' => ['nullable', 'string', 'max:50'],
            'units.*.conversion_to_base' => ['required_with:units', 'numeric', 'min:0.0001'],
            'units.*.is_base' => ['sometimes', 'boolean'],
            'units.*.is_orderable' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'buffer_qty' => ['nullable', 'numeric', 'min:0'],
            'carryover_enabled' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
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

        $units = $rows->map(fn (array $row): string => strtolower((string) $row['unit']));
        if ($units->duplicates()->isNotEmpty()) {
            $validator->errors()->add('units', 'Each unit can only be added once.');
        }

        $baseUnit = strtolower($this->string('unit')->toString());
        if (! $units->contains($baseUnit)) {
            $validator->errors()->add('units', 'The base unit must be included in order units.');
        }

        foreach ($rows as $index => $row) {
            if (strtolower((string) $row['unit']) === $baseUnit && abs((float) ($row['conversion_to_base'] ?? 0) - 1.0) > 0.0001) {
                $validator->errors()->add("units.{$index}.conversion_to_base", 'The base unit conversion must be 1.');
            }
        }
    }
}
