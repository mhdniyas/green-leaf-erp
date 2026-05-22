<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

use App\Enums\Inventory\ProductGrade;
use App\Models\StockBatch;
use Illuminate\Http\Request;

/**
 * Carries the graded quantities entered during the sorting workflow.
 *
 * @phpstan-type GradeQty array{grade: ProductGrade, quantity: float}
 */
final readonly class SortingData
{
    /**
     * @param  array<int, GradeQty>  $grades
     */
    public function __construct(
        public int $batchId,
        public array $grades,
        public ?string $notes,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var StockBatch $batch */
        $batch = $request->route('batch');

        $grades = collect($request->input('grades', []))
            ->map(fn (array $g) => [
                'grade' => ProductGrade::from($g['grade']),
                'quantity' => (float) ($g['quantity'] ?? 0),
            ])
            ->filter(fn (array $g) => $g['quantity'] > 0)
            ->values()
            ->all();

        return new self(
            batchId: $batch->id,
            grades: $grades,
            notes: $request->string('notes')->toString() ?: null,
        );
    }

    public function totalSortedKg(): float
    {
        return (float) collect($this->grades)->sum('quantity');
    }

    public function damageQuantity(): float
    {
        return (float) collect($this->grades)
            ->filter(fn (array $g) => $g['grade'] === ProductGrade::Damage)
            ->sum('quantity');
    }
}
