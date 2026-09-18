<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\GoodsReceived;
use App\Models\PurchaserCart;
use App\Models\Purchasing\PurchaserBusinessDaySubmission;
use Illuminate\Support\Carbon;

class PurchaserBusinessDayCalendarService
{
    public function __construct(
        private readonly PurchaserBusinessDayReconciliationService $reconciliationService,
        private readonly PurchaserBusinessDayService $businessDayService,
    ) {}

    /**
     * Get monthly calendar summary for a purchaser.
     * Consumes Phase 2 live reconciliation + Phase 3 submission snapshot data.
     *
     * @return array<string, mixed>
     */
    public function getMonthlyCalendar(
        int $purchaserUserId,
        ?string $yearMonth = null,
        ?int $warehouseId = null,
        ?string $selectedDate = null
    ): array {
        if (blank($yearMonth) || ! is_string($yearMonth)) {
            $yearMonth = $this->businessDayService->operationalDate()->format('Y-m');
        }

        try {
            $startOfMonth = Carbon::parse($yearMonth.'-01')->startOfMonth();
        } catch (\Throwable) {
            $startOfMonth = $this->businessDayService->operationalDate()->startOfMonth();
            $yearMonth = $startOfMonth->format('Y-m');
        }
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $todayStr = $this->businessDayService->operationalDate()->format('Y-m-d');
        $selectedDateStr = filled($selectedDate) ? $selectedDate : $todayStr;

        // 1. Bulk load submissions for purchaser in this month
        $submissions = PurchaserBusinessDaySubmission::query()
            ->where('purchaser_user_id', $purchaserUserId)
            ->whereBetween('business_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->get()
            ->keyBy(fn (PurchaserBusinessDaySubmission $s): string => $s->business_date->format('Y-m-d'));

        // 2. Bulk load active GRN dates for purchaser in this month
        $grnDates = GoodsReceived::query()
            ->whereBetween('received_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->where(function ($q) use ($purchaserUserId): void {
                $q->where('received_by', $purchaserUserId)
                    ->orWhereHas('items', function ($iq) use ($purchaserUserId): void {
                        $iq->whereHas('product.purchaserAllotments', function ($aq) use ($purchaserUserId): void {
                            $aq->where('purchaser_user_id', $purchaserUserId);
                        });
                    });
            })
            ->pluck('received_date')
            ->map(fn ($d): string => Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->flip();

        // 3. Bulk load purchaser cart dates in this month
        $cartDates = PurchaserCart::query()
            ->where('user_id', $purchaserUserId)
            ->whereBetween('business_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->pluck('business_date')
            ->map(fn ($d): string => Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->flip();

        $days = [];
        $submittedCount = 0;
        $completeCount = 0;
        $pendingCount = 0;
        $notSubmittedCount = 0;

        $currentDay = $startOfMonth->copy();
        while ($currentDay->lte($endOfMonth)) {
            $dateStr = $currentDay->toDateString();
            $submission = $submissions->get($dateStr);
            $hasOperations = $grnDates->has($dateStr) || $cartDates->has($dateStr);

            if ($submission !== null) {
                $submittedCount++;
                $subCoverage = (float) $submission->submission_coverage_percentage;

                // Live Phase 2 calculation to check if bills were added later
                $liveRec = $this->reconciliationService->calculateReconciliation($purchaserUserId, $dateStr, $warehouseId);
                $liveCoverage = (float) $liveRec['coverage_percentage'];

                if ($subCoverage >= 99.99) {
                    $state = 'submitted_complete';
                    $label = 'Complete';
                    $coverageDisplay = '100%';
                    $completeCount++;
                } elseif ($liveCoverage >= 99.99) {
                    $state = 'submitted_completed_later';
                    $label = 'Complete Later';
                    $coverageDisplay = number_format($subCoverage, 1).'% → 100%';
                    $completeCount++;
                } else {
                    $state = 'submitted_pending';
                    $label = 'Pending Bills';
                    $coverageDisplay = number_format($subCoverage, 1).'%';
                    $pendingCount++;
                }

                $days[] = [
                    'date' => $dateStr,
                    'day_number' => $currentDay->day,
                    'day_name' => $currentDay->format('D'),
                    'is_today' => $dateStr === $todayStr,
                    'is_selected' => $dateStr === $selectedDateStr,
                    'state' => $state,
                    'label' => $label,
                    'coverage_display' => $coverageDisplay,
                    'submitted_coverage' => $subCoverage,
                    'live_coverage' => $liveCoverage,
                    'has_submission' => true,
                    'has_operations' => true,
                    'has_mixed_units' => (bool) $submission->has_mixed_units,
                ];
            } else {
                if (! $hasOperations) {
                    $days[] = [
                        'date' => $dateStr,
                        'day_number' => $currentDay->day,
                        'day_name' => $currentDay->format('D'),
                        'is_today' => $dateStr === $todayStr,
                        'is_selected' => $dateStr === $selectedDateStr,
                        'state' => 'no_operations',
                        'label' => 'No Operations',
                        'coverage_display' => '-',
                        'submitted_coverage' => null,
                        'live_coverage' => null,
                        'has_submission' => false,
                        'has_operations' => false,
                        'has_mixed_units' => false,
                    ];
                } else {
                    $notSubmittedCount++;
                    $liveRec = $this->reconciliationService->calculateReconciliation($purchaserUserId, $dateStr, $warehouseId);
                    $hasUnitMismatch = (bool) ($liveRec['has_unit_mismatch'] ?? false);
                    $liveCoverage = (float) $liveRec['coverage_percentage'];

                    if ($hasUnitMismatch) {
                        $state = 'unit_mismatch';
                        $label = 'Unit Issue';
                        $coverageDisplay = 'Issue';
                    } else {
                        $state = 'not_submitted';
                        $label = 'Not Submitted';
                        $coverageDisplay = number_format($liveCoverage, 1).'%';
                    }

                    $days[] = [
                        'date' => $dateStr,
                        'day_number' => $currentDay->day,
                        'day_name' => $currentDay->format('D'),
                        'is_today' => $dateStr === $todayStr,
                        'is_selected' => $dateStr === $selectedDateStr,
                        'state' => $state,
                        'label' => $label,
                        'coverage_display' => $coverageDisplay,
                        'submitted_coverage' => null,
                        'live_coverage' => $liveCoverage,
                        'has_submission' => false,
                        'has_operations' => true,
                        'has_mixed_units' => (bool) ($liveRec['has_mixed_units'] ?? false),
                    ];
                }
            }

            $currentDay->addDay();
        }

        $prevMonth = $startOfMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $startOfMonth->copy()->addMonth()->format('Y-m');

        return [
            'year_month' => $yearMonth,
            'month_label' => $startOfMonth->format('F Y'),
            'prev_month' => $prevMonth,
            'next_month' => $nextMonth,
            'submitted_count' => $submittedCount,
            'complete_count' => $completeCount,
            'pending_count' => $pendingCount,
            'not_submitted_count' => $notSubmittedCount,
            'days' => $days,
            'start_day_of_week' => $startOfMonth->dayOfWeekIso, // 1 (Mon) to 7 (Sun)
        ];
    }
}
