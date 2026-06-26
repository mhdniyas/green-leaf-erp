<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use Illuminate\Support\Carbon;

class PurchaserBusinessDayService
{
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

        return $moment->copy()->format('H:i') >= '21:30';
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
        return Carbon::parse($date)->startOfDay()->setTime(21, 30);
    }

    public function isWarningWindowOpen(Carbon|string $date, ?Carbon $moment = null): bool
    {
        $moment ??= now();

        return $moment->gte($this->warningStartsAt($date));
    }
}
