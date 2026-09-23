<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaserPreviousPriceResolver
{
    /**
     * Resolve the last prior business day's quantity-weighted average actual purchase price
     * for a given list of product IDs and purchase grade.
     *
     * @param  iterable<int, int>  $productIds
     * @return array<int, float> map of product_id => weighted_average_price
     */
    public function getPreviousWeightedPrices(
        iterable $productIds,
        CarbonInterface|string $currentBusinessDate,
        string $purchaseGrade = 'A'
    ): array {
        $pIds = collect($productIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $resultMap = [];
        foreach ($pIds as $id) {
            $resultMap[$id] = 0.0;
        }

        if (empty($pIds)) {
            return $resultMap;
        }

        $dateStr = Carbon::parse($currentBusinessDate)->toDateString();
        $normalizedGrade = strtoupper(trim($purchaseGrade)) ?: 'A';

        // Subquery: find the latest business_date < currentBusinessDate for each product_id & grade
        $latestDates = DB::table('purchaser_cart_items as sub_pci')
            ->join('purchaser_carts as sub_pc', 'sub_pc.id', '=', 'sub_pci.purchaser_cart_id')
            ->leftJoin('purchase_invoices as sub_pi', 'sub_pi.purchaser_cart_id', '=', 'sub_pc.id')
            ->where(function ($q): void {
                $q->where(function ($q1): void {
                    $q1->whereNotNull('sub_pi.id')
                        ->whereNull('sub_pi.deleted_at')
                        ->where('sub_pi.status', '!=', 'cancelled');
                })->orWhere(function ($q2): void {
                    $q2->whereNull('sub_pi.id')
                        ->whereIn('sub_pc.status', ['submitted', 'approved', 'completed']);
                });
            })
            ->whereDate('sub_pc.business_date', '<', $dateStr)
            ->whereIn('sub_pci.product_id', $pIds)
            ->where(DB::raw("COALESCE(NULLIF(sub_pci.grade, ''), NULLIF(sub_pc.purchase_grade, ''), 'A')"), $normalizedGrade)
            ->where('sub_pci.quantity', '>', 0)
            ->where('sub_pci.unit_price', '>', 0)
            ->select('sub_pci.product_id', DB::raw('MAX(sub_pc.business_date) as max_date'))
            ->groupBy('sub_pci.product_id');

        // Main Query: Compute quantity-weighted average on that MAX(business_date) for each product_id
        $rows = DB::table('purchaser_cart_items as pci')
            ->join('purchaser_carts as pc', 'pc.id', '=', 'pci.purchaser_cart_id')
            ->leftJoin('purchase_invoices as pi', 'pi.purchaser_cart_id', '=', 'pc.id')
            ->joinSub($latestDates, 'latest', function ($join): void {
                $join->on('latest.product_id', '=', 'pci.product_id')
                    ->on('pc.business_date', '=', 'latest.max_date');
            })
            ->where(function ($q): void {
                $q->where(function ($q1): void {
                    $q1->whereNotNull('pi.id')
                        ->whereNull('pi.deleted_at')
                        ->where('pi.status', '!=', 'cancelled');
                })->orWhere(function ($q2): void {
                    $q2->whereNull('pi.id')
                        ->whereIn('pc.status', ['submitted', 'approved', 'completed']);
                });
            })
            ->where(DB::raw("COALESCE(NULLIF(pci.grade, ''), NULLIF(pc.purchase_grade, ''), 'A')"), $normalizedGrade)
            ->where('pci.quantity', '>', 0)
            ->where('pci.unit_price', '>', 0)
            ->select('pci.product_id', DB::raw('(1.0 * SUM(pci.quantity * pci.unit_price)) / NULLIF(SUM(pci.quantity), 0) as weighted_price'))
            ->groupBy('pci.product_id')
            ->get();

        foreach ($rows as $row) {
            $prodId = (int) $row->product_id;
            $weightedPrice = round((float) $row->weighted_price, 4);
            if ($weightedPrice > 0) {
                $resultMap[$prodId] = $weightedPrice;
            }
        }

        return $resultMap;
    }
}
