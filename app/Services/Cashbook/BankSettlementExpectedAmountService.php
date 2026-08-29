<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopBankSettlementAdjustment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BankSettlementExpectedAmountService
{
    /**
     * Resolve expected bank settlement amount for a single shop collection.
     *
     * @return array{
     *     base_amount: float,
     *     plus_adjustments: float,
     *     minus_adjustments: float,
     *     adjustment_total: float,
     *     expected_amount: float,
     *     adjustments: array<int, array{id: int, label: string, direction: string, amount: float}>
     * }
     */
    public function resolve(
        int $shopId,
        string $businessDate,
        int $entryTypeId,
        float $baseAmount
    ): array {
        $formattedDate = Carbon::parse($businessDate)->toDateString();
        $base = round($baseAmount, 2);

        $adjustments = ShopBankSettlementAdjustment::query()
            ->with('rule')
            ->where('shop_id', $shopId)
            ->whereDate('business_date', $formattedDate)
            ->where('entry_type_id', $entryTypeId)
            ->whereHas('rule', fn ($q) => $q->where('enabled', true))
            ->get();

        $plusAdjustments = 0.0;
        $minusAdjustments = 0.0;
        $items = [];

        foreach ($adjustments as $adj) {
            $amt = max(0.0, round((float) $adj->amount, 2));
            if ($adj->direction === 'plus') {
                $plusAdjustments += $amt;
            } else {
                $minusAdjustments += $amt;
            }

            $items[] = [
                'id' => (int) $adj->id,
                'label' => (string) $adj->label,
                'direction' => (string) $adj->direction,
                'amount' => $amt,
            ];
        }

        $plusAdjustments = round($plusAdjustments, 2);
        $minusAdjustments = round($minusAdjustments, 2);
        $adjustmentTotal = round($plusAdjustments - $minusAdjustments, 2);
        $expectedAmount = round($base + $adjustmentTotal, 2);

        return [
            'base_amount' => $base,
            'plus_adjustments' => $plusAdjustments,
            'minus_adjustments' => $minusAdjustments,
            'adjustment_total' => $adjustmentTotal,
            'expected_amount' => $expectedAmount,
            'adjustments' => $items,
        ];
    }

    /**
     * Resolve expected bank settlement amounts for a batch of collection records in bulk.
     * Prevents N+1 query overhead.
     *
     * @param  iterable<mixed>  $items  Each item should have shop_id, business_date, entry_type_id, and base_amount (or amount)
     * @return Collection<string, array{
     *     base_amount: float,
     *     plus_adjustments: float,
     *     minus_adjustments: float,
     *     adjustment_total: float,
     *     expected_amount: float
     * }> Keyed by "shopId_businessDate_entryTypeId"
     */
    public function resolveBulk(iterable $items): Collection
    {
        $itemsCollection = collect($items);
        if ($itemsCollection->isEmpty()) {
            return collect();
        }

        $shopIds = [];
        $dates = [];
        $entryTypeIds = [];

        foreach ($itemsCollection as $item) {
            $sId = (int) (is_array($item) ? ($item['shop_id'] ?? 0) : ($item->shop_id ?? 0));
            $bDate = (string) (is_array($item) ? ($item['business_date'] ?? '') : ($item->business_date?->toDateString() ?? (string) ($item->business_date ?? '')));
            $eId = (int) (is_array($item) ? ($item['entry_type_id'] ?? 0) : ($item->entry_type_id ?? 0));

            if ($sId > 0 && $bDate !== '' && $eId > 0) {
                $shopIds[$sId] = $sId;
                $formattedDate = Carbon::parse($bDate)->toDateString();
                $dates[$formattedDate] = $formattedDate;
                $entryTypeIds[$eId] = $eId;
            }
        }

        $groupedAdjustments = [];
        if (! empty($shopIds) && ! empty($dates) && ! empty($entryTypeIds)) {
            $adjustments = ShopBankSettlementAdjustment::query()
                ->whereIn('shop_id', array_values($shopIds))
                ->whereIn('entry_type_id', array_values($entryTypeIds))
                ->where(function ($query) use ($dates): void {
                    foreach ($dates as $d) {
                        $query->orWhereDate('business_date', $d);
                    }
                })
                ->whereHas('rule', fn ($q) => $q->where('enabled', true))
                ->get();

            foreach ($adjustments as $adj) {
                $rawDate = (string) ($adj->getRawOriginal('business_date') ?: $adj->business_date);
                $bDate = Carbon::parse($rawDate)->toDateString();
                $key = "{$adj->shop_id}_{$bDate}_{$adj->entry_type_id}";
                if (! isset($groupedAdjustments[$key])) {
                    $groupedAdjustments[$key] = ['plus' => 0.0, 'minus' => 0.0];
                }
                $amt = max(0.0, round((float) $adj->amount, 2));
                if ($adj->direction === 'plus') {
                    $groupedAdjustments[$key]['plus'] += $amt;
                } else {
                    $groupedAdjustments[$key]['minus'] += $amt;
                }
            }
        }

        $results = collect();

        foreach ($itemsCollection as $item) {
            $sId = (int) (is_array($item) ? ($item['shop_id'] ?? 0) : ($item->shop_id ?? 0));
            $rawDate = is_array($item) ? ($item['business_date'] ?? '') : ($item->business_date?->toDateString() ?? (string) ($item->business_date ?? ''));
            $bDate = $rawDate !== '' ? Carbon::parse($rawDate)->toDateString() : '';
            $eId = (int) (is_array($item) ? ($item['entry_type_id'] ?? 0) : ($item->entry_type_id ?? 0));
            $base = round((float) (is_array($item) ? ($item['base_amount'] ?? ($item['amount'] ?? 0.0)) : ($item->base_amount ?? ($item->amount ?? 0.0))), 2);

            $key = "{$sId}_{$bDate}_{$eId}";
            $adj = $groupedAdjustments[$key] ?? ['plus' => 0.0, 'minus' => 0.0];

            $plus = round((float) $adj['plus'], 2);
            $minus = round((float) $adj['minus'], 2);
            $adjTotal = round($plus - $minus, 2);
            $expected = round($base + $adjTotal, 2);

            $results->put($key, [
                'base_amount' => $base,
                'plus_adjustments' => $plus,
                'minus_adjustments' => $minus,
                'adjustment_total' => $adjTotal,
                'expected_amount' => $expected,
            ]);
        }

        return $results;
    }
}
