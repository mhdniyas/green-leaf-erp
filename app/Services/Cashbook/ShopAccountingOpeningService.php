<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopAccountingOpening;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ShopAccountingOpeningService
{
    public const DEFAULT_ACCOUNTING_START_DATE = '2026-09-01';

    /**
     * Get the active accounting opening record effective for a shop on or before the given date.
     */
    public function getOpeningForDate(int $shopId, string|Carbon $date): ?ShopAccountingOpening
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return ShopAccountingOpening::query()
            ->where('shop_id', $shopId)
            ->whereDate('accounting_start_date', '<=', $dateStr)
            ->orderByDesc('accounting_start_date')
            ->first();
    }

    /**
     * Get the earliest accounting start date configured for a shop.
     * Returns null if no explicit opening record is configured for the shop.
     */
    public function getAccountingStartDate(int $shopId): ?string
    {
        $opening = ShopAccountingOpening::query()
            ->where('shop_id', $shopId)
            ->orderBy('accounting_start_date')
            ->first();

        return $opening?->accounting_start_date?->toDateString();
    }

    /**
     * Determine whether the given date is in the historical pre-opening period for the shop.
     */
    public function isPreOpening(int $shopId, string|Carbon $date): bool
    {
        $startDate = $this->getAccountingStartDate($shopId);
        if ($startDate === null) {
            return false;
        }

        $dateStr = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return $dateStr < $startDate;
    }

    /**
     * Determine whether an entire month string (Y-m) is in the historical pre-opening period.
     */
    public function isPreOpeningMonth(int $shopId, string $month): bool
    {
        $startDate = $this->getAccountingStartDate($shopId);
        if ($startDate === null) {
            return false;
        }

        $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        return $monthEnd < $startDate;
    }

    /**
     * Create or update an accounting opening record idempotently.
     *
     * @param  array{
     *     opening_shop_company_balance?: float|int|string,
     *     opening_balance_direction?: string,
     *     opening_allocation_pending?: float|int|string,
     *     opening_petty_balance?: float|int|string,
     *     notes?: ?string
     * }  $attributes
     */
    public function setOpening(
        Shop|int $shop,
        string $startDate = self::DEFAULT_ACCOUNTING_START_DATE,
        array $attributes = [],
        ?int $userId = null
    ): ShopAccountingOpening {
        $shopId = $shop instanceof Shop ? (int) $shop->id : $shop;
        $dateStr = Carbon::parse($startDate)->toDateString();

        return DB::transaction(function () use ($shopId, $dateStr, $attributes, $userId): ShopAccountingOpening {
            $opening = ShopAccountingOpening::query()
                ->where('shop_id', $shopId)
                ->whereDate('accounting_start_date', $dateStr)
                ->lockForUpdate()
                ->first();

            $data = [
                'opening_shop_company_balance' => (float) ($attributes['opening_shop_company_balance'] ?? 0.0),
                'opening_balance_direction' => (string) ($attributes['opening_balance_direction'] ?? 'settled'),
                'opening_allocation_pending' => (float) ($attributes['opening_allocation_pending'] ?? 0.0),
                'opening_petty_balance' => (float) ($attributes['opening_petty_balance'] ?? 0.0),
                'notes' => $attributes['notes'] ?? 'September 2026 Opening — Clean accounting cutoff',
                'created_by' => $userId,
            ];

            if ($opening instanceof ShopAccountingOpening) {
                $opening->update($data);

                return $opening->fresh();
            }

            return ShopAccountingOpening::query()->create(array_merge([
                'shop_id' => $shopId,
                'accounting_start_date' => $dateStr,
            ], $data));
        });
    }
}
