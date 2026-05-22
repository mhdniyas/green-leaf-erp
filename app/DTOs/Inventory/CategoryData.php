<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

use App\Http\Requests\Api\Inventory\StoreCategoryRequest;
use App\Http\Requests\Api\Inventory\UpdateCategoryRequest;
use Illuminate\Http\Request;

final readonly class CategoryData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $isActive,
    ) {}

    public static function fromRequest(StoreCategoryRequest|UpdateCategoryRequest|Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            description: $request->string('description')->toString() ?: null,
            isActive: $request->boolean('is_active', true),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
