<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\ProductUnit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyPurchaserPriceSyncService
{
    public function __construct(
        private readonly VendorPriceService $vendorPriceService,
    ) {}

    /**
     * Synchronize actual weighted purchase prices into daily_price_approvals snapshot for a given business date.
     *
     * @param  int|null  $productId  If null, syncs all products with actual purchases or approvals on that date.
     * @return int Number of approvals updated
     */
    public function syncForBusinessDate(CarbonInterface|string $businessDate, ?int $productId = null): int
    {
        $dateStr = Carbon::parse($businessDate)->toDateString();

        $purchasesQuery = DB::table('purchaser_cart_items')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->whereDate('purchaser_carts.business_date', $dateStr)
            ->where('purchaser_cart_items.quantity', '>', 0)
            ->where('purchaser_cart_items.unit_price', '>', 0);

        if ($productId !== null) {
            $purchasesQuery->where('purchaser_cart_items.product_id', $productId);
        }

        $purchasedProducts = $purchasesQuery
            ->selectRaw('purchaser_cart_items.product_id, (1.0 * SUM(purchaser_cart_items.quantity * purchaser_cart_items.unit_price)) / NULLIF(SUM(purchaser_cart_items.quantity), 0) as weighted_price, SUM(purchaser_cart_items.quantity) as total_qty')
            ->groupBy('purchaser_cart_items.product_id')
            ->get()
            ->keyBy('product_id');

        $updatedCount = 0;

        foreach ($purchasedProducts as $pId => $row) {
            $prodId = (int) $pId;
            $weightedPrice = round((float) $row->weighted_price, 4);

            if ($weightedPrice <= 0.0) {
                continue;
            }

            $approval = DailyPriceApproval::query()
                ->where('product_id', $prodId)
                ->whereDate('business_date', $dateStr)
                ->first();

            if ($approval instanceof DailyPriceApproval) {
                if (abs((float) $approval->purchase_price - $weightedPrice) > 0.0001) {
                    $approval->purchase_price = $weightedPrice;
                    $approval->save();
                    $updatedCount++;
                }
            } else {
                $product = Product::query()->find($prodId);
                if (! $product instanceof Product) {
                    continue;
                }

                $previousApproval = DailyPriceApproval::query()
                    ->where('product_id', $prodId)
                    ->whereDate('business_date', '<', $dateStr)
                    ->orderByDesc('business_date')
                    ->first();

                DailyPriceApproval::query()->create([
                    'product_id' => $prodId,
                    'business_date' => $dateStr,
                    'purchase_price' => $weightedPrice,
                    'price_unit' => ProductUnit::normalizeUnit((string) ($previousApproval?->price_unit ?: $product->unit ?: 'kg')),
                    'price_a' => (float) ($previousApproval?->price_a ?? $product->base_price),
                    'price_b' => (float) ($previousApproval?->price_b ?? $previousApproval?->price_a ?? $product->base_price),
                    'price_c' => (float) ($previousApproval?->price_c ?? $previousApproval?->price_a ?? $product->base_price),
                    'status' => 'approved',
                    'approved_by' => null,
                    'approved_at' => now(),
                ]);

                $updatedCount++;
            }

            $this->vendorPriceService->syncPrice($prodId, $weightedPrice);
        }

        if ($productId !== null && ! $purchasedProducts->has($productId)) {
            $approval = DailyPriceApproval::query()
                ->where('product_id', $productId)
                ->whereDate('business_date', $dateStr)
                ->first();

            if ($approval instanceof DailyPriceApproval) {
                $previousApproval = DailyPriceApproval::query()
                    ->where('product_id', $productId)
                    ->whereDate('business_date', '<', $dateStr)
                    ->orderByDesc('business_date')
                    ->first();

                $carriedPrice = (float) ($previousApproval?->purchase_price ?? 0);
                if ($carriedPrice > 0 && abs((float) $approval->purchase_price - $carriedPrice) > 0.0001) {
                    $approval->purchase_price = $carriedPrice;
                    $approval->save();
                    $updatedCount++;
                }
            }
        }

        return $updatedCount;
    }

    /**
     * Synchronize a range of business dates sequentially from startDate to endDate.
     */
    public function syncRange(CarbonInterface|string $startDate, CarbonInterface|string $endDate): int
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $totalUpdated = 0;

        $current = $start->copy();
        while ($current->lte($end)) {
            $totalUpdated += $this->syncForBusinessDate($current);
            $current->addDay();
        }

        return $totalUpdated;
    }
}
