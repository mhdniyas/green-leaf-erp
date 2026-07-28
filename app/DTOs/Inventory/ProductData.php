<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

use Illuminate\Http\Request;

final readonly class ProductData
{
    public function __construct(
        public int $categoryId,
        public ?int $defaultWarehouseId,
        public string $name,
        public string $sku,
        public string $unit,
        public ?string $description,
        public float $bufferQty,
        public bool $carryoverEnabled,
        public bool $isActive,
        public ?string $imageData = null,
        public bool $removeImage = false,
        public array $units = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            categoryId: (int) $request->input('category_id'),
            defaultWarehouseId: $request->input('default_warehouse_id') ? (int) $request->input('default_warehouse_id') : null,
            name: $request->string('name')->toString(),
            sku: strtoupper($request->string('sku')->toString()),
            unit: $request->string('unit', 'kg')->toString(),
            description: $request->string('description')->toString() ?: null,
            bufferQty: round((float) $request->input('buffer_qty', 0), 2),
            carryoverEnabled: $request->boolean('carryover_enabled'),
            isActive: $request->boolean('is_active', true),
            imageData: $request->input('image_data') ?: null,
            removeImage: $request->boolean('remove_image'),
            units: self::unitsFromRequest($request),
        );
    }

    public function toArray(): array
    {
        return [
            'category_id' => $this->categoryId,
            'default_warehouse_id' => $this->defaultWarehouseId,
            'name' => $this->name,
            'sku' => $this->sku,
            'unit' => $this->unit,
            'description' => $this->description,
            'buffer_qty' => $this->bufferQty,
            'carryover_enabled' => $this->carryoverEnabled,
            'is_active' => $this->isActive,
        ];
    }

    private static function unitsFromRequest(Request $request): array
    {
        $baseUnit = strtolower($request->string('unit', 'kg')->toString());
        $rows = collect($request->input('units', []))
            ->filter(fn ($row): bool => is_array($row) && filled($row['unit'] ?? null))
            ->map(function (array $row, int $index) use ($baseUnit): array {
                $unit = strtolower(trim((string) $row['unit']));
                $isBase = (bool) ($row['is_base'] ?? false) || $unit === $baseUnit;
                $label = trim((string) ($row['label'] ?? strtoupper($unit))) ?: strtoupper($unit);

                return [
                    'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
                    'unit' => $unit,
                    'label' => $label,
                    'public_uuid' => filled($row['public_uuid'] ?? null) ? (string) $row['public_uuid'] : null,
                    'conversion_to_base' => $isBase ? 1.0 : (filled($row['conversion_to_base'] ?? null) ? round((float) $row['conversion_to_base'], 4) : null),
                    'is_base' => $isBase,
                    'is_orderable' => (bool) ($row['is_orderable'] ?? true),
                    'sort_order' => $index,
                ];
            })
            ->unique(fn (array $row): string => mb_strtolower($row['label']))
            ->values();

        if (! $rows->contains(fn (array $row): bool => $row['unit'] === $baseUnit)) {
            $rows->prepend([
                'unit' => $baseUnit,
                'label' => strtoupper($baseUnit),
                'conversion_to_base' => 1.0,
                'is_base' => true,
                'is_orderable' => true,
                'sort_order' => 0,
            ]);
        }

        return $rows
            ->map(function (array $row, int $index) use ($baseUnit): array {
                $row['is_base'] = $row['unit'] === $baseUnit;
                $row['conversion_to_base'] = $row['is_base']
                    ? 1.0
                    : ($row['conversion_to_base'] !== null ? max(0.0001, (float) $row['conversion_to_base']) : null);
                $row['sort_order'] = $index;

                return $row;
            })
            ->values()
            ->all();
    }
}
