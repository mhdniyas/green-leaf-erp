<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaserBusinessDayServiceTest extends TestCase
{
    public function test_operational_date_stays_on_current_day_before_rollover(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24 21:29:00'));

        $service = app(PurchaserBusinessDayService::class);

        $this->assertSame('2026-06-24', $service->operationalDate()->toDateString());
        $this->assertFalse($service->hasRolledOver());
    }

    public function test_operational_date_switches_to_next_day_at_rollover(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24 21:30:00'));

        $service = app(PurchaserBusinessDayService::class);

        $this->assertSame('2026-06-25', $service->operationalDate()->toDateString());
        $this->assertTrue($service->hasRolledOver());
    }

    public function test_selectable_date_is_limited_to_operational_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24 22:00:00'));

        $service = app(PurchaserBusinessDayService::class);

        $this->assertTrue($service->isSelectableDate('2026-06-25'));
        $this->assertFalse($service->isSelectableDate('2026-06-26'));
    }
}
