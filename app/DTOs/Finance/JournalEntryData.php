<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

use Illuminate\Http\Request;

final readonly class JournalEntryData
{
    /**
     * @param  array<int, array{account_id: int, type: string, amount: float}>  $lines
     */
    public function __construct(
        public string $entryDate,
        public ?string $reference,
        public ?string $description,
        public array $lines,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            entryDate: $request->string('entry_date')->toString(),
            reference: $request->filled('reference') ? $request->string('reference')->toString() : null,
            description: $request->filled('description') ? $request->string('description')->toString() : null,
            lines: $request->input('lines', []),
        );
    }

    public function toArray(): array
    {
        return [
            'entry_date' => $this->entryDate,
            'reference' => $this->reference,
            'description' => $this->description,
        ];
    }
}
