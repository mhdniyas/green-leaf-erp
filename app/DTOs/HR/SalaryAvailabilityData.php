<?php

declare(strict_types=1);

namespace App\DTOs\HR;

use Carbon\CarbonImmutable;

readonly class SalaryAvailabilityData
{
    /**
     * @param  array<string>  $dataQualityReasons
     */
    public function __construct(
        public int $employeeId,
        public ?int $shopId,
        public CarbonImmutable $payrollMonth,
        public CarbonImmutable $calculationDate,
        public string $salaryType,
        public float $monthlySalary,
        public float $dailyWage,
        public float $dailyRate,
        public int $calendarDays,
        public float $presentDays,
        public float $halfDays,
        public float $paidLeaveDays,
        public float $unpaidLeaveDays,
        public float $absentDays,
        public float $payableUnits,
        public float $employeeWideEarned,
        public float $employeeWideAdvancesPaid,
        public float $employeeWideSalaryPaid,
        public ?float $employeeWideOpeningRecovery,
        public ?float $employeeWideSignedAdvanceAvailability,
        public ?float $employeeWideSignedSalaryRemaining,
        public ?float $employeeWideAvailableAdvance,
        public ?float $employeeWideRemainingSalary,
        public ?float $shopEarned,
        public ?float $shopAdvancesPaid,
        public ?float $shopSalaryPaid,
        public ?float $shopAllocatedRecovery,
        public ?float $shopSignedAdvanceAvailability,
        public ?float $shopSignedSalaryRemaining,
        public ?float $shopAvailableAdvance,
        public ?float $shopRemainingSalary,
        public string $dataQualityStatus,
        public bool $hasConflictingLinks = false,
        public array $dataQualityReasons = [],
    ) {}
}
