<?php

declare(strict_types=1);

namespace App\DTOs\Cashbook;

final class MoneyMovement
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $date,
        public string $sourceType,
        public string|int|null $sourceId,
        public string $fromEntityType,
        public string|int|null $fromEntityId,
        public string $fromEntityName,
        public string $toEntityType,
        public string|int|null $toEntityId,
        public string $toEntityName,
        public float $amount,
        public string $movementType,
        public ?string $category = null,
        public ?string $referenceType = null,
        public string|int|null $referenceId = null,
        public ?string $referenceNumber = null,
        public ?string $notes = null,
        public array $metadata = [],
    ) {}

    public function isInternalTransfer(): bool
    {
        return $this->movementType === 'internal_bank_transfer'
            || ($this->fromEntityType === 'company_bank' && $this->toEntityType === 'company_bank');
    }

    /**
     * Convert DTO to array representation for JSON responses / drilldowns.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'from_entity_type' => $this->fromEntityType,
            'from_entity_id' => $this->fromEntityId,
            'from_entity_name' => $this->fromEntityName,
            'to_entity_type' => $this->toEntityType,
            'to_entity_id' => $this->toEntityId,
            'to_entity_name' => $this->toEntityName,
            'amount' => $this->amount,
            'movement_type' => $this->movementType,
            'category' => $this->category,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
            'reference_number' => $this->referenceNumber,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'is_internal_transfer' => $this->isInternalTransfer(),
        ];
    }
}
