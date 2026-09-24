<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport\DTO;

final class SectionReportDefinition
{
    /**
     * @param  array<int, int>  $productIds
     * @param  array<string, mixed>  $saleMappings
     * @param  array<string, mixed>  $purchaseMappings
     * @param  array<string, mixed>  $expenseMappings
     */
    public function __construct(
        public readonly string $id,
        public readonly string $key,
        public readonly string $name,
        public readonly string $type,
        public readonly array $productIds = [],
        public readonly ?string $monthlyReportGroup = null,
        public readonly array $saleMappings = [],
        public readonly array $purchaseMappings = [],
        public readonly array $expenseMappings = [],
        public readonly bool $enabled = true,
        public readonly int $sortOrder = 0,
        public readonly ?string $description = null,
    ) {}

    public function isTrading(): bool
    {
        return $this->type === 'trading';
    }

    public function isExpenseOnly(): bool
    {
        return $this->type === 'expense';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'type' => $this->type,
            'product_ids' => $this->productIds,
            'monthly_report_group' => $this->monthlyReportGroup,
            'enabled' => $this->enabled,
            'sort_order' => $this->sortOrder,
            'description' => $this->description,
        ];
    }
}
