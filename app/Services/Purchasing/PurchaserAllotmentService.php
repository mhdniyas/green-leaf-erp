<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Product;
use App\Models\ProductPurchaserAllotment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaserAllotmentService
{
    /**
     * Get the active purchaser for a given product on a specific business date.
     */
    public function getPurchaserForProductOnDate(int $productId, string|\DateTimeInterface $businessDate): ?User
    {
        return $this->getAllotmentOnDate($productId, $businessDate)?->purchaser;
    }

    /**
     * Get the allotment record active for a product on a specific business date.
     */
    public function getAllotmentOnDate(int $productId, string|\DateTimeInterface $businessDate): ?ProductPurchaserAllotment
    {
        $targetDate = is_string($businessDate) ? $businessDate : $businessDate->format('Y-m-d');

        /** @var ProductPurchaserAllotment|null $allotment */
        $allotment = ProductPurchaserAllotment::query()
            ->with(['purchaser'])
            ->where('product_id', $productId)
            ->where('effective_from', '<=', $targetDate)
            ->where(function (Builder $q) use ($targetDate): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $targetDate);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        return $allotment;
    }

    /**
     * Get the current active purchaser for a product today.
     */
    public function getCurrentPurchaserForProduct(int $productId): ?User
    {
        return $this->getPurchaserForProductOnDate($productId, now()->toDateString());
    }

    /**
     * Assign a new purchaser allotment for a product effective from a given date.
     * Automatically closes any existing active allotment on (effectiveFrom - 1 day).
     *
     * @throws ValidationException
     */
    public function assignPurchaser(
        int $productId,
        int $purchaserUserId,
        string|\DateTimeInterface $effectiveFrom,
        ?int $actorUserId = null
    ): ProductPurchaserAllotment {
        $effectiveFromDate = is_string($effectiveFrom) ? Carbon::parse($effectiveFrom) : Carbon::instance($effectiveFrom);
        $effectiveFromStr = $effectiveFromDate->toDateString();

        return DB::transaction(function () use ($productId, $purchaserUserId, $effectiveFromStr, $effectiveFromDate, $actorUserId): ProductPurchaserAllotment {
            $product = Product::query()->findOrFail($productId);
            $purchaser = User::query()->findOrFail($purchaserUserId);

            // Check if there are any existing allotments starting on or after the new effectiveFrom date
            $overlappingFutureAllotment = ProductPurchaserAllotment::query()
                ->where('product_id', $productId)
                ->where('effective_from', '>=', $effectiveFromStr)
                ->orderBy('effective_from')
                ->first();

            if ($overlappingFutureAllotment !== null) {
                throw ValidationException::withMessages([
                    'effective_from' => "Cannot assign allotment starting on {$effectiveFromStr} because an allotment starting on {$overlappingFutureAllotment->effective_from->format('Y-m-d')} already exists for product {$product->name}.",
                ]);
            }

            // Find currently active open allotment (effective_to is null or >= effectiveFromStr)
            $existingActiveAllotment = ProductPurchaserAllotment::query()
                ->where('product_id', $productId)
                ->where('effective_from', '<', $effectiveFromStr)
                ->where(function (Builder $q) use ($effectiveFromStr): void {
                    $q->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', $effectiveFromStr);
                })
                ->orderByDesc('effective_from')
                ->lockForUpdate()
                ->first();

            if ($existingActiveAllotment !== null) {
                $closingDate = $effectiveFromDate->copy()->subDay()->toDateString();
                if ($existingActiveAllotment->effective_from->toDateString() > $closingDate) {
                    throw ValidationException::withMessages([
                        'effective_from' => "Effective date {$effectiveFromStr} conflicts with existing allotment starting on {$existingActiveAllotment->effective_from->format('Y-m-d')}.",
                    ]);
                }

                $existingActiveAllotment->update([
                    'effective_to' => $closingDate,
                    'updated_by' => $actorUserId,
                ]);
            }

            /** @var ProductPurchaserAllotment $newAllotment */
            $newAllotment = ProductPurchaserAllotment::query()->create([
                'product_id' => $productId,
                'purchaser_user_id' => $purchaserUserId,
                'effective_from' => $effectiveFromStr,
                'effective_to' => null,
                'created_by' => $actorUserId,
                'updated_by' => null,
            ]);

            return $newAllotment->fresh(['product', 'purchaser', 'createdBy']) ?? $newAllotment;
        });
    }

    /**
     * Get complete allotment history for a product ordered newest first.
     *
     * @return Collection<int, ProductPurchaserAllotment>
     */
    public function getAllotmentHistory(int $productId): Collection
    {
        return ProductPurchaserAllotment::query()
            ->with(['purchaser', 'createdBy', 'updatedBy'])
            ->where('product_id', $productId)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Paginated overview of products with current purchaser allotment for admin UI.
     */
    public function getOverviewPaginated(
        ?string $search = null,
        ?int $categoryId = null,
        ?int $purchaserId = null,
        int $perPage = 25
    ): LengthAwarePaginator {
        $today = now()->toDateString();

        $query = Product::query()
            ->with(['category', 'currentPurchaserAllotment.purchaser'])
            ->when(filled($search), function (Builder $q) use ($search): void {
                $term = '%'.trim((string) $search).'%';
                $q->where(function (Builder $sub) use ($term): void {
                    $sub->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term);
                });
            })
            ->when($categoryId !== null && $categoryId > 0, fn (Builder $q) => $q->where('category_id', $categoryId))
            ->when($purchaserId !== null && $purchaserId > 0, function (Builder $q) use ($today, $purchaserId): void {
                $q->whereHas('purchaserAllotments', function (Builder $aq) use ($today, $purchaserId): void {
                    $aq->where('purchaser_user_id', $purchaserId)
                        ->where('effective_from', '<=', $today)
                        ->where(function (Builder $sub) use ($today): void {
                            $sub->whereNull('effective_to')
                                ->orWhere('effective_to', '>=', $today);
                        });
                });
            })
            ->orderBy('name');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Bulk assign multiple products to a purchaser effective from a date.
     * Reuses assignPurchaser for each product.
     * Skips safely if already assigned to the same purchaser on that date without error.
     *
     * @param  array<int>  $productIds
     * @return array{assigned_count: int, skipped_count: int, total_count: int}
     */
    public function bulkAssignPurchaser(
        array $productIds,
        int $purchaserUserId,
        string|\DateTimeInterface $effectiveFrom,
        ?int $actorUserId = null
    ): array {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $effectiveFromDate = is_string($effectiveFrom) ? Carbon::parse($effectiveFrom) : Carbon::instance($effectiveFrom);
        $effectiveFromStr = $effectiveFromDate->toDateString();

        return DB::transaction(function () use ($productIds, $purchaserUserId, $effectiveFromStr, $actorUserId): array {
            $assignedCount = 0;
            $skippedCount = 0;

            foreach ($productIds as $productId) {
                // Check current active allotment on effectiveFromStr
                $existingAllotment = $this->getAllotmentOnDate($productId, $effectiveFromStr);

                if (
                    $existingAllotment !== null
                    && (int) $existingAllotment->purchaser_user_id === $purchaserUserId
                    && $existingAllotment->effective_from->toDateString() <= $effectiveFromStr
                ) {
                    $skippedCount++;

                    continue;
                }

                $this->assignPurchaser($productId, $purchaserUserId, $effectiveFromStr, $actorUserId);
                $assignedCount++;
            }

            return [
                'assigned_count' => $assignedCount,
                'skipped_count' => $skippedCount,
                'total_count' => count($productIds),
            ];
        });
    }

    /**
     * Preview bulk assignment metrics for multiple products.
     *
     * @param  array<int>  $productIds
     * @return array{
     *     total_selected: int,
     *     already_assigned_count: int,
     *     changing_purchaser_count: int,
     *     previously_unassigned_count: int,
     *     effective_from: string,
     *     purchaser: User|null
     * }
     */
    public function previewBulkAssign(
        array $productIds,
        int $purchaserUserId,
        string|\DateTimeInterface $effectiveFrom
    ): array {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $effectiveFromDate = is_string($effectiveFrom) ? Carbon::parse($effectiveFrom) : Carbon::instance($effectiveFrom);
        $effectiveFromStr = $effectiveFromDate->toDateString();

        $purchaser = User::query()->find($purchaserUserId);

        $alreadyAssigned = 0;
        $changing = 0;
        $unassigned = 0;

        foreach ($productIds as $productId) {
            $existing = $this->getAllotmentOnDate($productId, $effectiveFromStr);

            if ($existing === null) {
                $unassigned++;
            } elseif ((int) $existing->purchaser_user_id === $purchaserUserId) {
                $alreadyAssigned++;
            } else {
                $changing++;
            }
        }

        return [
            'total_selected' => count($productIds),
            'already_assigned_count' => $alreadyAssigned,
            'changing_purchaser_count' => $changing,
            'previously_unassigned_count' => $unassigned,
            'effective_from' => $effectiveFromStr,
            'purchaser' => $purchaser,
        ];
    }
}
