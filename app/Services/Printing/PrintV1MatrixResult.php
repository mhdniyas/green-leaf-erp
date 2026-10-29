<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\Shop;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

class PrintV1MatrixResult
{
    /**
     * @param  Collection<int, Shop>  $filteredShops
     * @param  array<string, array<int, float>>  $matrix
     * @param  array<string, array<string, mixed>>  $productMeta
     * @param  array<int, string>  $selectedGrades
     * @param  array<string, mixed>  $settings
     * @param  Collection<int, Shop>  $skippedEmptyShops
     * @param  Collection<int, Shop>  $candidateShops
     */
    public function __construct(
        public readonly Collection $filteredShops,
        public readonly array $matrix,
        public readonly array $productMeta,
        public readonly string $date,
        public readonly ?Warehouse $selectedWarehouse,
        public readonly array $selectedGrades,
        public readonly array $settings = [],
        public readonly Collection $skippedEmptyShops = new Collection,
        public readonly Collection $candidateShops = new Collection,
    ) {}

    public function productCount(): int
    {
        return count($this->matrix);
    }

    public function shopCount(): int
    {
        return $this->filteredShops->count();
    }

    public function skippedShopCount(): int
    {
        return $this->skippedEmptyShops->count();
    }

    public function hasOrders(): bool
    {
        return ! empty($this->matrix) && $this->filteredShops->isNotEmpty();
    }

    public function isSingleGrade(): bool
    {
        return count($this->selectedGrades) === 1;
    }

    /**
     * Get formatted grade heading for printing headers (e.g. 'GRADE A', 'GRADE A + B', 'GRADE MIXED').
     */
    public function gradeHeading(): string
    {
        if (empty($this->selectedGrades)) {
            return 'GRADE ALL';
        }

        $canonicalOrder = ['A' => 1, 'B' => 2, 'C' => 3, 'U' => 4, 'DMG' => 5];
        $grades = $this->selectedGrades;
        usort($grades, function (string $a, string $b) use ($canonicalOrder): int {
            $posA = $canonicalOrder[strtoupper(trim($a))] ?? 99;
            $posB = $canonicalOrder[strtoupper(trim($b))] ?? 99;

            return $posA <=> $posB;
        });

        $labels = array_map(function (string $g): string {
            return match (strtoupper(trim($g))) {
                'A', 'B', 'C' => strtoupper(trim($g)),
                'U', 'MIXED' => 'MIXED',
                'DMG', 'DAMAGE' => 'DAMAGE',
                default => strtoupper(trim($g)),
            };
        }, $grades);

        return 'GRADE '.implode(' + ', $labels);
    }
}
