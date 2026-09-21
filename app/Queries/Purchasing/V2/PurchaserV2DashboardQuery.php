<?php

declare(strict_types=1);

namespace App\Queries\Purchasing\V2;

use App\Models\User;
use App\Services\Purchasing\PurchaserReadCacheService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaserV2DashboardQuery
{
    public function __construct(
        private readonly PurchaserReadCacheService $readCache,
    ) {}

    /**
     * Compute dashboard KPI aggregates.
     *
     * @return array{
     *     intended_count: int,
     *     fulfilled_count: int,
     *     pending_count: int,
     *     draft_carts_count: int,
     *     today_purchased_amount: float,
     *     total_purchased_products_count: int,
     *     total_approved_qty: float,
     *     total_purchased_qty: float,
     *     date: string,
     *     grade: string
     * }
     */
    public function getSummary(Carbon $date, string $grade, User $user, bool $useCache = true): array
    {
        $resolver = fn (): array => $this->executeQueries($date, $grade, $user);

        if (! $useCache) {
            return $resolver();
        }

        return $this->readCache->remember(
            scopes: ['orders', 'carts'],
            dataset: 'v2_dashboard_summary',
            ttlSeconds: 30,
            callback: $resolver,
            userId: (int) $user->id,
            businessDate: $date,
            grade: $grade,
        );
    }

    /**
     * Execute bounded aggregate SQL queries (≤ 3 queries total on cache miss).
     *
     * @return array{
     *     intended_count: int,
     *     fulfilled_count: int,
     *     pending_count: int,
     *     draft_carts_count: int,
     *     today_purchased_amount: float,
     *     total_purchased_products_count: int,
     *     total_approved_qty: float,
     *     total_purchased_qty: float,
     *     date: string,
     *     grade: string
     * }
     */
    public function executeQueries(Carbon $date, string $grade, User $user): array
    {
        $dateStr = $date->toDateString();
        $assignedCategoryIds = $user->hasAssignedCategoryFilter() ? $user->assignedCategoryIds() : [];
        $isCategoryFiltered = count($assignedCategoryIds) > 0;

        // Query 1: Approved intended demand grouped by product_id
        $intendedQuery = DB::table('shop_order_items as soi')
            ->join('shop_orders as so', 'so.id', '=', 'soi.shop_order_id')
            ->where(function ($q) use ($dateStr): void {
                $q->where('so.business_date', $dateStr)
                    ->orWhere('so.business_date', 'like', "{$dateStr}%");
            })
            ->where('so.state', 'approved')
            ->where('soi.product_grade', $grade)
            ->where('soi.approved_qty', '>', 0);

        if ($isCategoryFiltered) {
            $intendedQuery->join('products as p', 'p.id', '=', 'soi.product_id')
                ->whereIn('p.category_id', $assignedCategoryIds);
        }

        /** @var Collection<int, float> $intendedProducts */
        $intendedProducts = $intendedQuery
            ->select('soi.product_id', DB::raw('SUM(soi.approved_qty) as total_approved'))
            ->groupBy('soi.product_id')
            ->pluck('total_approved', 'soi.product_id');

        // Query 2: Submitted cart purchases grouped by product_id
        $purchasedQuery = DB::table('purchaser_cart_items as pci')
            ->join('purchaser_carts as pc', 'pc.id', '=', 'pci.purchaser_cart_id')
            ->where(function ($q) use ($dateStr): void {
                $q->where('pc.business_date', $dateStr)
                    ->orWhere('pc.business_date', 'like', "{$dateStr}%");
            })
            ->where('pc.purchase_grade', $grade)
            ->where('pc.status', 'submitted');

        if ($isCategoryFiltered) {
            $purchasedQuery->join('products as p', 'p.id', '=', 'pci.product_id')
                ->whereIn('p.category_id', $assignedCategoryIds);
        }

        /** @var Collection<int, float> $purchasedProducts */
        $purchasedProducts = $purchasedQuery
            ->select('pci.product_id', DB::raw('SUM(pci.quantity) as total_bought'))
            ->groupBy('pci.product_id')
            ->pluck('total_bought', 'pci.product_id');

        // Query 3: Cart status counts and submitted spend
        $cartStats = DB::table('purchaser_carts')
            ->where(function ($q) use ($dateStr): void {
                $q->where('business_date', $dateStr)
                    ->orWhere('business_date', 'like', "{$dateStr}%");
            })
            ->where('purchase_grade', $grade)
            ->whereIn('status', ['draft', 'submitted'])
            ->select([
                DB::raw("COUNT(CASE WHEN status = 'draft' AND user_id = {$user->id} THEN 1 END) as draft_count"),
                DB::raw("COALESCE(SUM(CASE WHEN status = 'submitted' THEN COALESCE(paid_amount, 0) END), 0) as submitted_paid"),
            ])
            ->first();

        // Calculate KPI totals
        $fulfilledCount = 0;
        $pendingCount = 0;
        $totalApprovedQty = 0.0;
        $totalPurchasedQty = 0.0;

        foreach ($intendedProducts as $productId => $approvedQty) {
            $approved = (float) $approvedQty;
            $totalApprovedQty += $approved;
            $bought = (float) ($purchasedProducts->get($productId) ?? 0.0);

            if ($bought >= $approved) {
                $fulfilledCount++;
            } else {
                $pendingCount++;
            }
        }

        foreach ($purchasedProducts as $boughtQty) {
            $totalPurchasedQty += (float) $boughtQty;
        }

        $draftCartsCount = (int) ($cartStats->draft_count ?? 0);
        $todayPurchasedAmount = (float) ($cartStats->submitted_paid ?? 0.0);

        return [
            'intended_count' => $intendedProducts->count(),
            'fulfilled_count' => $fulfilledCount,
            'pending_count' => $pendingCount,
            'draft_carts_count' => $draftCartsCount,
            'today_purchased_amount' => $todayPurchasedAmount,
            'total_purchased_products_count' => $purchasedProducts->count(),
            'total_approved_qty' => round($totalApprovedQty, 2),
            'total_purchased_qty' => round($totalPurchasedQty, 2),
            'date' => $dateStr,
            'grade' => $grade,
        ];
    }
}
