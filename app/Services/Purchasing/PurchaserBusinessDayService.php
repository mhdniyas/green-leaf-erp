<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\BusinessSetting;
use Illuminate\Support\Carbon;

class PurchaserBusinessDayService
{
    private const CUTOFF_SETTING_KEY = 'business_day_cutoff_time';

    private const PURCHASER_ENTRY_CUTOFF_SETTING_KEY = 'purchaser_entry_cutoff_time';

    private const AUTO_APPROVE_SHOP_ORDERS_KEY = 'auto_approve_shop_orders';

    public const AUTO_APPROVE_MANAGER_NOTE = 'Automatically approved by purchase setting.';

    private ?string $cachedCutoffTime = null;

    private ?string $cachedPurchaserEntryCutoffTime = null;

    private ?bool $cachedAutoApproveShopOrders = null;

    public function operationalDate(?Carbon $moment = null): Carbon
    {
        $moment ??= now();

        $businessDate = $moment->copy()->startOfDay();

        if ($this->hasRolledOver($moment)) {
            return $businessDate->addDay();
        }

        return $businessDate;
    }

    public function currentCalendarDate(?Carbon $moment = null): Carbon
    {
        return ($moment ??= now())->copy()->startOfDay();
    }

    public function hasRolledOver(?Carbon $moment = null): bool
    {
        $moment ??= now();

        return $moment->gte($this->rolloverStartsAt($moment));
    }

    public function maxSelectableDate(?Carbon $moment = null): Carbon
    {
        return $this->operationalDate($moment);
    }

    public function isSelectableDate(Carbon|string $date, ?Carbon $moment = null): bool
    {
        return Carbon::parse($date)->startOfDay()->lte($this->maxSelectableDate($moment));
    }

    public function warningStartsAt(Carbon|string $date): Carbon
    {
        return Carbon::parse($date)->startOfDay()->setTime(16, 0);
    }

    public function rolloverStartsAt(Carbon|string $date): Carbon
    {
        [$hour, $minute, $second] = $this->cutoffTimeParts();

        return Carbon::parse($date)->startOfDay()->setTime($hour, $minute, $second);
    }

    public function isWarningWindowOpen(Carbon|string $date, ?Carbon $moment = null): bool
    {
        $moment ??= now();

        return $moment->gte($this->warningStartsAt($date));
    }

    public function cutoffTime(): string
    {
        if ($this->cachedCutoffTime !== null) {
            return $this->cachedCutoffTime;
        }

        return $this->cachedCutoffTime = BusinessSetting::query()
            ->where('key', self::CUTOFF_SETTING_KEY)
            ->value('value')
            ?? (string) config('business-day.cutoff_time', '21:30:00');
    }

    public function cutoffInputValue(): string
    {
        return Carbon::createFromFormat('H:i:s', $this->normalizedCutoffTime())
            ->format('H:i');
    }

    public function purchaserEntryCutoffInputValue(): string
    {
        return Carbon::createFromFormat('H:i:s', $this->normalizedPurchaserEntryCutoffTime())
            ->format('H:i');
    }

    public function cutoffLabel(): string
    {
        return Carbon::createFromFormat('H:i:s', $this->normalizedCutoffTime())
            ->format('g:i A');
    }

    public function purchaserEntryCutoffLabel(): string
    {
        return Carbon::createFromFormat('H:i:s', $this->normalizedPurchaserEntryCutoffTime())
            ->format('g:i A');
    }

    public function updateCutoffTime(string $time): void
    {
        $normalizedTime = strlen($time) === 5 ? "{$time}:00" : $time;

        BusinessSetting::query()->updateOrCreate(
            ['key' => self::CUTOFF_SETTING_KEY],
            ['value' => $normalizedTime],
        );

        $this->cachedCutoffTime = $normalizedTime;
    }

    public function updatePurchaserEntryCutoffTime(string $time): void
    {
        $normalizedTime = strlen($time) === 5 ? "{$time}:00" : $time;

        BusinessSetting::query()->updateOrCreate(
            ['key' => self::PURCHASER_ENTRY_CUTOFF_SETTING_KEY],
            ['value' => $normalizedTime],
        );

        $this->cachedPurchaserEntryCutoffTime = $normalizedTime;
    }

    public function purchaserEntryCutoffStartsAt(Carbon|string $date): Carbon
    {
        [$hour, $minute, $second] = $this->purchaserEntryCutoffTimeParts();

        return Carbon::parse($date)->startOfDay()->setTime($hour, $minute, $second);
    }

    public function isPurchaserEntryOpenForDate(Carbon|string $date, ?Carbon $moment = null): bool
    {
        $moment ??= now();
        $date = Carbon::parse($date)->startOfDay();

        if (! $date->isSameDay($this->operationalDate($moment))) {
            return false;
        }

        return $moment->lt($this->purchaserEntryCutoffStartsAt($date));
    }

    public function autoApproveShopOrders(): bool
    {
        if ($this->cachedAutoApproveShopOrders !== null) {
            return $this->cachedAutoApproveShopOrders;
        }

        return $this->cachedAutoApproveShopOrders = filter_var(
            BusinessSetting::query()
                ->where('key', self::AUTO_APPROVE_SHOP_ORDERS_KEY)
                ->value('value') ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function updateAutoApproveShopOrders(bool $enabled): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => self::AUTO_APPROVE_SHOP_ORDERS_KEY],
            ['value' => $enabled ? '1' : '0'],
        );

        $this->cachedAutoApproveShopOrders = $enabled;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function cutoffTimeParts(): array
    {
        return array_map(
            static fn (string $segment): int => (int) $segment,
            explode(':', $this->normalizedCutoffTime())
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function purchaserEntryCutoffTimeParts(): array
    {
        return array_map(
            static fn (string $segment): int => (int) $segment,
            explode(':', $this->normalizedPurchaserEntryCutoffTime())
        );
    }

    private function normalizedCutoffTime(): string
    {
        $cutoffTime = $this->cutoffTime();

        return strlen($cutoffTime) === 5 ? "{$cutoffTime}:00" : $cutoffTime;
    }

    private function purchaserEntryCutoffTime(): string
    {
        if ($this->cachedPurchaserEntryCutoffTime !== null) {
            return $this->cachedPurchaserEntryCutoffTime;
        }

        return $this->cachedPurchaserEntryCutoffTime = BusinessSetting::query()
            ->where('key', self::PURCHASER_ENTRY_CUTOFF_SETTING_KEY)
            ->value('value')
            ?? $this->cutoffTime();
    }

    private function normalizedPurchaserEntryCutoffTime(): string
    {
        $cutoffTime = $this->purchaserEntryCutoffTime();

        return strlen($cutoffTime) === 5 ? "{$cutoffTime}:00" : $cutoffTime;
    }
}
