<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\Inventory\ProductGrade;
use App\Repositories\Inventory\StockMovementRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PurchaserWarehouseBalanceService
{
    public function __construct(
        private readonly StockMovementRepository $stockMovementRepository,
    ) {}

    /**
     * Get authoritative current stock levels grouped by product_id and grade.
     *
     * @param  array<int, int>|null  $productIds
     * @return Collection<int, Collection<int, object>>
     */
    public function getStockByProductAndGrade(Carbon|string $date, ?int $warehouseId = null, ?array $productIds = null): Collection
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return $this->stockMovementRepository
            ->currentStockByProductAndGrade(date: $dateString, warehouseId: $warehouseId, productIds: $productIds)
            ->groupBy('product_id');
    }

    /**
     * Attach authoritative warehouse balances to a collection of purchaser product summaries.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    public function attachWarehouseBalances(
        Collection $items,
        Carbon|string $date,
        ?int $warehouseId = null,
        string $purchaseGrade = 'ALL'
    ): Collection {
        if ($items->isEmpty()) {
            return $items;
        }

        $productIds = $items->pluck('product_id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
        $stockByProduct = $this->getStockByProductAndGrade($date, $warehouseId, $productIds);
        $normalizedPurchaseGrade = strtoupper(trim($purchaseGrade));

        return $items->map(function (array $item) use ($stockByProduct, $normalizedPurchaseGrade): array {
            $productId = (int) ($item['product_id'] ?? 0);
            /** @var Collection<int, object> $productRows */
            $productRows = $stockByProduct->get($productId, collect());

            // Available/sellable stock rows (exclude Damage)
            $sellableRows = $productRows->filter(function ($row): bool {
                $gradeVal = ProductGrade::tryResolve($row->grade)?->value ?? $row->grade;

                return $gradeVal !== ProductGrade::Damage->value;
            });

            // Map stock by grade: 'A', 'B', 'C', 'U'
            $stockByGrade = $sellableRows->groupBy(function ($row): string {
                return (string) (ProductGrade::tryResolve($row->grade)?->value ?? $row->grade);
            })->map(fn ($rows): float => (float) $rows->sum('current_stock'));

            $totalProductStock = max(0.0, (float) $sellableRows->sum('current_stock'));
            $gradeAStock = max(0.0, (float) ($stockByGrade->get(ProductGrade::GradeA->value) ?? 0.0));
            $gradeBStock = max(0.0, (float) ($stockByGrade->get(ProductGrade::GradeB->value) ?? 0.0));
            $gradeCStock = max(0.0, (float) ($stockByGrade->get(ProductGrade::GradeC->value) ?? 0.0));
            $unsortedStock = max(0.0, (float) (($stockByGrade->get(ProductGrade::Unsorted->value) ?? 0.0) + ($stockByGrade->get('Unsorted') ?? 0.0)));

            $hasNonAGrades = ! empty($item['has_non_a_grades']);

            // Determine card warehouse balance based on purchaseGrade and multi-grade state
            $balance = match ($normalizedPurchaseGrade) {
                'ALL' => $totalProductStock,
                'B' => $gradeBStock,
                'C' => $gradeCStock,
                'A' => $hasNonAGrades ? $totalProductStock : ($gradeAStock + $unsortedStock),
                default => $totalProductStock,
            };

            $roundedBalance = round($balance, 1);
            $item['warehouse_balance'] = $roundedBalance;
            $item['warehouse_balance_formatted'] = number_format($roundedBalance, 1).' kg';
            $item['unsorted_warehouse_balance'] = round($unsortedStock, 1);

            // Populate grade-level warehouse balances if grade_breakdown exists
            if (! empty($item['grade_breakdown']) && is_array($item['grade_breakdown'])) {
                $item['grade_breakdown'] = array_map(function (array $gb) use ($stockByGrade): array {
                    $grade = (string) ($gb['grade'] ?? 'A');
                    $gStock = max(0.0, (float) ($stockByGrade->get($grade) ?? 0.0));
                    $roundedGStock = round($gStock, 1);
                    $gb['warehouse_balance'] = $roundedGStock;
                    $gb['warehouse_balance_formatted'] = number_format($roundedGStock, 1).' kg';

                    return $gb;
                }, $item['grade_breakdown']);
            }

            return $item;
        });
    }

    /**
     * Get authoritative stock for a single product and optional grade.
     */
    public function getProductWarehouseBalance(
        int $productId,
        Carbon|string $date,
        ?int $warehouseId = null,
        ?string $grade = null
    ): float {
        $stockByProduct = $this->getStockByProductAndGrade($date, $warehouseId, [$productId]);
        $productRows = $stockByProduct->get($productId, collect());

        $sellableRows = $productRows->filter(function ($row): bool {
            $gradeVal = ProductGrade::tryResolve($row->grade)?->value ?? $row->grade;

            return $gradeVal !== ProductGrade::Damage->value;
        });

        if ($grade !== null && strtoupper($grade) !== 'ALL') {
            $targetGrade = strtoupper(trim($grade));
            $resolvedGrade = ProductGrade::tryResolve($targetGrade)?->value ?? $targetGrade;

            return round(max(0.0, (float) $sellableRows->filter(function ($row) use ($resolvedGrade): bool {
                $rowGrade = ProductGrade::tryResolve($row->grade)?->value ?? $row->grade;

                return $rowGrade === $resolvedGrade;
            })->sum('current_stock')), 1);
        }

        return round(max(0.0, (float) $sellableRows->sum('current_stock')), 1);
    }
}
