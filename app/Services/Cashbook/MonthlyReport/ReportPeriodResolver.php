<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

final class ReportPeriodResolver
{
    public const TIMEZONE = 'Asia/Kolkata';

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{
     *     period_mode: string,
     *     month: string,
     *     date: string,
     *     from: string,
     *     to: string,
     *     start_date: string,
     *     end_date: string,
     *     formatted_range: string,
     *     period_label: string,
     *     previous_period: array<string, string>,
     *     next_period: array<string, string>,
     *     dates: array<int, string>
     * }
     */
    public function resolve(array $inputs): array
    {
        $now = Carbon::now(self::TIMEZONE);
        $mode = (string) ($inputs['period_mode'] ?? 'month');
        if (! in_array($mode, ['month', 'day', 'custom'], true)) {
            $mode = 'month';
        }

        $month = (string) ($inputs['month'] ?? $now->format('Y-m'));
        $date = (string) ($inputs['date'] ?? $now->format('Y-m-d'));
        $from = (string) ($inputs['from'] ?? $now->copy()->startOfMonth()->format('Y-m-d'));
        $to = (string) ($inputs['to'] ?? $now->copy()->endOfMonth()->format('Y-m-d'));

        if ($mode === 'day') {
            try {
                $carbonDate = Carbon::createFromFormat('Y-m-d', $date, self::TIMEZONE) ?: $now;
            } catch (\Throwable) {
                $carbonDate = $now;
            }
            $startDate = $carbonDate->format('Y-m-d');
            $endDate = $startDate;
            $periodLabel = $carbonDate->format('d M Y');
            $formattedRange = $carbonDate->format('d M Y');

            $prevDate = $carbonDate->copy()->subDay()->format('Y-m-d');
            $nextDate = $carbonDate->copy()->addDay()->format('Y-m-d');

            $previousPeriod = ['period_mode' => 'day', 'date' => $prevDate];
            $nextPeriod = ['period_mode' => 'day', 'date' => $nextDate];
        } elseif ($mode === 'custom') {
            try {
                $carbonFrom = Carbon::createFromFormat('Y-m-d', $from, self::TIMEZONE) ?: $now->copy()->startOfMonth();
            } catch (\Throwable) {
                $carbonFrom = $now->copy()->startOfMonth();
            }

            try {
                $carbonTo = Carbon::createFromFormat('Y-m-d', $to, self::TIMEZONE) ?: $now->copy()->endOfMonth();
            } catch (\Throwable) {
                $carbonTo = $now->copy()->endOfMonth();
            }

            if ($carbonTo->lt($carbonFrom)) {
                $carbonTo = $carbonFrom->copy();
            }

            // Cap custom range at 366 days
            if ($carbonFrom->diffInDays($carbonTo) > 366) {
                $carbonTo = $carbonFrom->copy()->addDays(366);
            }

            $startDate = $carbonFrom->format('Y-m-d');
            $endDate = $carbonTo->format('Y-m-d');
            $from = $startDate;
            $to = $endDate;

            $diffDays = $carbonFrom->diffInDays($carbonTo) + 1;
            $periodLabel = $carbonFrom->format('d M Y').' – '.$carbonTo->format('d M Y');
            $formattedRange = $periodLabel;

            $prevFrom = $carbonFrom->copy()->subDays($diffDays)->format('Y-m-d');
            $prevTo = $carbonFrom->copy()->subDay()->format('Y-m-d');
            $nextFrom = $carbonTo->copy()->addDay()->format('Y-m-d');
            $nextTo = $carbonTo->copy()->addDays($diffDays)->format('Y-m-d');

            $previousPeriod = ['period_mode' => 'custom', 'from' => $prevFrom, 'to' => $prevTo];
            $nextPeriod = ['period_mode' => 'custom', 'from' => $nextFrom, 'to' => $nextTo];
        } else {
            // Month mode
            $mode = 'month';
            try {
                $carbonMonth = Carbon::createFromFormat('Y-m', $month, self::TIMEZONE) ?: $now;
            } catch (\Throwable) {
                $carbonMonth = $now;
            }
            $month = $carbonMonth->format('Y-m');
            $startDate = $carbonMonth->copy()->startOfMonth()->format('Y-m-d');
            $endDate = $carbonMonth->copy()->endOfMonth()->format('Y-m-d');
            $periodLabel = $carbonMonth->format('F Y');
            $formattedRange = $carbonMonth->copy()->startOfMonth()->format('d M Y').' – '.$carbonMonth->copy()->endOfMonth()->format('d M Y');

            $prevMonth = $carbonMonth->copy()->subMonth()->format('Y-m');
            $nextMonth = $carbonMonth->copy()->addMonth()->format('Y-m');

            $previousPeriod = ['period_mode' => 'month', 'month' => $prevMonth];
            $nextPeriod = ['period_mode' => 'month', 'month' => $nextMonth];
        }

        $dates = [];
        $periodRange = CarbonPeriod::create($startDate, $endDate);
        foreach ($periodRange as $d) {
            $dates[] = $d->format('Y-m-d');
        }

        return [
            'period_mode' => $mode,
            'month' => $month,
            'date' => $date,
            'from' => $from,
            'to' => $to,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'formatted_range' => $formattedRange,
            'period_label' => $periodLabel,
            'month_label' => $periodLabel,
            'previous_period' => $previousPeriod,
            'next_period' => $nextPeriod,
            'dates' => $dates,
        ];
    }
}
