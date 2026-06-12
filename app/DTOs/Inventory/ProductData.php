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
        public bool $isActive,
        public ?string $imageData = null,
        public bool $removeImage = false,
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
            isActive: $request->boolean('is_active', true),
            imageData: $request->input('image_data') ?: null,
            removeImage: $request->boolean('remove_image'),
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
            'is_active' => $this->isActive,
        ];
    }
}
