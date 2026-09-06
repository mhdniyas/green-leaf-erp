<?php

declare(strict_types=1);

namespace Tests\Unit\HR;

use App\DTOs\HR\SalaryAvailabilityData;
use App\Services\HR\SalaryAvailabilityService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SalaryAvailabilityArithmeticTest extends TestCase
{
    private SalaryAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SalaryAvailabilityService;
    }

    public function test_month_length_divisors_calculate_daily_rates_correctly(): void
    {
        $monthlySalary = 15000.0;

        // Feb 28-day
        $rateFeb28 = $monthlySalary / 28;
        $this->assertEqualsWithDelta(535.7142857, $rateFeb28, 0.0001);

        // Feb 29-day leap year
        $rateFeb29 = $monthlySalary / 29;
        $this->assertEqualsWithDelta(517.241379, $rateFeb29, 0.0001);

        // Sept 30-day
        $rateSept30 = $monthlySalary / 30;
        $this->assertEquals(500.0, $rateSept30);

        // Jan 31-day
        $rateJan31 = $monthlySalary / 31;
        $this->assertEqualsWithDelta(483.8709677, $rateJan31, 0.0001);
    }

    public function test_5_day_benchmark_arithmetic(): void
    {
        $monthlySalary = 15000.0;
        $calendarDays = 30;
        $dailyRate = $monthlySalary / $calendarDays; // 500.0
        $payableUnits = 5.0;
        $earnedSalary = round($dailyRate * $payableUnits, 2); // 2500.0
        $advanceCeiling = round($earnedSalary * 0.50, 2); // 1250.0

        $this->assertEquals(500.0, $dailyRate);
        $this->assertEquals(2500.0, $earnedSalary);
        $this->assertEquals(1250.0, $advanceCeiling);
    }

    public function test_evaluate_decision_valid_and_excess_advance(): void
    {
        $data = $this->createMockAvailability(
            employeeWideAvailableAdvance: 1250.0,
            employeeWideRemainingSalary: 2500.0,
            shopAvailableAdvance: 1250.0,
            shopRemainingSalary: 2500.0,
        );

        // Valid advance
        $validDecision = $this->service->evaluateDecision($data, 'advance', 1000.0);
        $this->assertTrue($validDecision->isAllowed());
        $this->assertEquals(1000.0, $validDecision->allowedAmount);

        // Advance equal to available limit
        $exactDecision = $this->service->evaluateDecision($data, 'advance', 1250.0);
        $this->assertTrue($exactDecision->isAllowed());

        // Excess advance requires HR
        $excessDecision = $this->service->evaluateDecision($data, 'advance', 1500.0);
        $this->assertTrue($excessDecision->requiresHr());
        $this->assertFalse($excessDecision->isAllowed());
        $this->assertEquals(1250.0, $excessDecision->allowedAmount);
    }

    public function test_evaluate_decision_valid_and_excess_salary(): void
    {
        $data = $this->createMockAvailability(
            employeeWideAvailableAdvance: 1250.0,
            employeeWideRemainingSalary: 2500.0,
            shopAvailableAdvance: 1250.0,
            shopRemainingSalary: 2500.0,
        );

        // Valid salary
        $validDecision = $this->service->evaluateDecision($data, 'salary', 2000.0);
        $this->assertTrue($validDecision->isAllowed());

        // Salary exceeding remaining amount is invalid
        $excessDecision = $this->service->evaluateDecision($data, 'salary', 3000.0);
        $this->assertTrue($excessDecision->isInvalid());
        $this->assertFalse($excessDecision->isAllowed());
    }

    public function test_evaluate_decision_rejects_non_finite_and_invalid_inputs(): void
    {
        $data = $this->createMockAvailability(
            employeeWideAvailableAdvance: 1250.0,
            employeeWideRemainingSalary: 2500.0,
            shopAvailableAdvance: 1250.0,
            shopRemainingSalary: 2500.0,
        );

        $nanDecision = $this->service->evaluateDecision($data, 'advance', NAN);
        $this->assertTrue($nanDecision->isInvalid());

        $infDecision = $this->service->evaluateDecision($data, 'advance', INF);
        $this->assertTrue($infDecision->isInvalid());

        $zeroDecision = $this->service->evaluateDecision($data, 'advance', 0.0);
        $this->assertTrue($zeroDecision->isInvalid());

        $negDecision = $this->service->evaluateDecision($data, 'advance', -50.0);
        $this->assertTrue($negDecision->isInvalid());

        $invalidTypeDecision = $this->service->evaluateDecision($data, 'bonus', 500.0);
        $this->assertTrue($invalidTypeDecision->isInvalid());
    }

    public function test_evaluate_decision_requires_hr_on_unknown_quality_or_conflicts(): void
    {
        $dataUnknown = $this->createMockAvailability(
            employeeWideAvailableAdvance: 1250.0,
            employeeWideRemainingSalary: 2500.0,
            shopAvailableAdvance: 1250.0,
            shopRemainingSalary: 2500.0,
            dataQualityStatus: 'unknown',
            dataQualityReasons: ['Opening recovery balance is unverified.'],
        );

        $decision = $this->service->evaluateDecision($dataUnknown, 'advance', 500.0);
        $this->assertTrue($decision->requiresHr());
        $this->assertFalse($decision->isAllowed());

        $dataConflict = $this->createMockAvailability(
            employeeWideAvailableAdvance: 1250.0,
            employeeWideRemainingSalary: 2500.0,
            shopAvailableAdvance: 1250.0,
            shopRemainingSalary: 2500.0,
            hasConflictingLinks: true,
        );

        $conflictDecision = $this->service->evaluateDecision($dataConflict, 'salary', 500.0);
        $this->assertTrue($conflictDecision->requiresHr());
    }

    private function createMockAvailability(
        ?float $employeeWideAvailableAdvance,
        ?float $employeeWideRemainingSalary,
        ?float $shopAvailableAdvance,
        ?float $shopRemainingSalary,
        string $dataQualityStatus = 'verified',
        bool $hasConflictingLinks = false,
        array $dataQualityReasons = [],
    ): SalaryAvailabilityData {
        $month = CarbonImmutable::parse('2026-09-01');
        $calcDate = CarbonImmutable::parse('2026-09-05');

        return new SalaryAvailabilityData(
            employeeId: 1,
            shopId: 10,
            payrollMonth: $month,
            calculationDate: $calcDate,
            salaryType: 'monthly',
            monthlySalary: 15000.0,
            dailyWage: 0.0,
            dailyRate: 500.0,
            calendarDays: 30,
            presentDays: 5.0,
            halfDays: 0.0,
            paidLeaveDays: 0.0,
            unpaidLeaveDays: 0.0,
            absentDays: 0.0,
            payableUnits: 5.0,
            employeeWideEarned: 2500.0,
            employeeWideAdvancesPaid: 0.0,
            employeeWideSalaryPaid: 0.0,
            employeeWideOpeningRecovery: 0.0,
            employeeWideSignedAdvanceAvailability: 1250.0,
            employeeWideSignedSalaryRemaining: 2500.0,
            employeeWideAvailableAdvance: $employeeWideAvailableAdvance,
            employeeWideRemainingSalary: $employeeWideRemainingSalary,
            shopEarned: 2500.0,
            shopAdvancesPaid: 0.0,
            shopSalaryPaid: 0.0,
            shopAllocatedRecovery: 0.0,
            shopSignedAdvanceAvailability: 1250.0,
            shopSignedSalaryRemaining: 2500.0,
            shopAvailableAdvance: $shopAvailableAdvance,
            shopRemainingSalary: $shopRemainingSalary,
            dataQualityStatus: $dataQualityStatus,
            hasConflictingLinks: $hasConflictingLinks,
            dataQualityReasons: $dataQualityReasons,
        );
    }
}
