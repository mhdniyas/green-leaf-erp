<?php

declare(strict_types=1);

namespace App\DTOs\Cashbook;

final class CashFlowTreeNode
{
    /**
     * @param  array<int, CashFlowTreeNode>  $children
     * @param  array<int, MoneyMovement>  $movements
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $subtitle = null,
        public ?string $badge = null,
        public string $entityType = 'general',
        public float $openingBalance = 0.0,
        public float $totalIn = 0.0,
        public float $totalOut = 0.0,
        public float $closingBalance = 0.0,
        public float $netChange = 0.0,
        public array $children = [],
        public array $movements = [],
        public array $metadata = [],
    ) {
        $this->openingBalance = round($this->openingBalance, 2);
        $this->totalIn = round($this->totalIn, 2);
        $this->totalOut = round($this->totalOut, 2);
        $this->closingBalance = round($this->closingBalance, 2);
        $this->netChange = round($this->netChange, 2);
    }

    public function addChild(self $child): self
    {
        $this->children[] = $child;

        return $this;
    }

    public function addMovement(MoneyMovement $movement): self
    {
        $this->movements[] = $movement;

        return $this;
    }

    public function hasChildren(): bool
    {
        return ! empty($this->children);
    }

    public function movementsCount(): int
    {
        return count($this->movements);
    }

    /**
     * Convert node recursively to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'badge' => $this->badge,
            'entity_type' => $this->entityType,
            'opening_balance' => $this->openingBalance,
            'total_in' => $this->totalIn,
            'total_out' => $this->totalOut,
            'closing_balance' => $this->closingBalance,
            'net_change' => $this->netChange,
            'movements_count' => $this->movementsCount(),
            'children' => array_map(fn (self $child): array => $child->toArray(), $this->children),
            'metadata' => $this->metadata,
        ];
    }
}
