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
            'products.*.enabled_units' => ['nullable', 'array'],
            'products.*.enabled_units.*' => ['nullable', 'boolean'],
            'products.*.units' => ['nullable', 'array'],
            'products.*.units.*' => ['nullable', 'numeric', 'min:0.0001'],
            'products.*.box_variants' => ['nullable', 'string', 'max:255'],
            'products.*.visible_labels' => ['nullable', 'array'],
            'products.*.visible_labels.*' => ['nullable', 'boolean'],
            'save_row' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('products', []) as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $baseUnit = strtolower((string) ($row['base_unit'] ?? ''));
                $boxEnabled = (bool) data_get($row, 'enabled_units.box', false) || filled(data_get($row, 'units.box'));

                if ($boxEnabled && $baseUnit !== 'box' && ! filled(data_get($row, 'units.box'))) {
                    $validator->errors()->add("products.{$index}.units.box", 'KG per box is required when box is enabled.');
                }

                if (array_key_exists('visible_labels', $row)
                    && collect($row['visible_labels'])->filter(fn ($visible): bool => (bool) $visible)->isEmpty()) {
                    $validator->errors()->add("products.{$index}.visible_labels", 'At least one shop incharge unit must be visible.');
                }
            }
        });
    }

    public function validatedProducts(): array
    {
        $saveRow = $this->validated('save_row') ?? null;

        return collect($this->validated('products', []))
            ->when($saveRow !== null, fn ($rows) => $rows->only((int) $saveRow))
            ->filter(fn (array $row): bool => filled($row['public_uuid'] ?? null))
            ->map(function (array $row): array {
                $baseUnit = strtolower((string) $row['base_unit']);
                $enabledUnits = collect($row['enabled_units'] ?? [])
                    ->filter(fn ($enabled, string $unit): bool => in_array($unit, ProductUnit::AVAILABLE_UNITS, true) && (bool) $enabled)
                    ->keys()
                    ->all();
                $units = [];

                foreach (ProductUnit::AVAILABLE_UNITS as $unit) {
                    $value = data_get($row, "units.{$unit}");
                    $isEnabled = in_array($unit, $enabledUnits, true) || filled($value) || $unit === $baseUnit;

                    if (! $isEnabled) {
                        continue;
                    }

                    $units[$unit] = filled($value) ? round((float) $value, 4) : null;
                }

                $units[$baseUnit] = 1.0;
                $boxVariants = collect(explode(',', (string) ($row['box_variants'] ?? '')))
                    ->map(fn (string $value): string => trim($value))
                    ->filter(fn (string $value): bool => $value !== '' && is_numeric($value) && (float) $value > 0)
                    ->map(fn (string $value): float => round((float) $value, 4))
                    ->unique()
                    ->values()
                    ->all();
                $visibleLabels = collect($row['visible_labels'] ?? [])
                    ->filter(fn ($visible): bool => (bool) $visible)
                    ->keys()
                    ->map(fn (string $label): string => mb_strtolower(trim($label)))
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'public_uuid' => (string) $row['public_uuid'],
                    'base_unit' => $baseUnit,
                    'units' => $units,
                    'box_variants' => $boxVariants,
                    'visible_labels' => $visibleLabels,
                ];
            })
            ->values()
            ->all();
    }
}
