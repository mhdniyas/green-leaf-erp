<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use App\Services\HR\EmployeeAdvanceService;
use App\Services\HR\PayrollService;
use App\Services\HR\SalaryAvailabilityService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecoverySourceAndShopAllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopUser;

    private Shop $shopA;

    private Shop $shopB;

    private EmployeeCategory $category;

    private EmployeeAdvanceService $advanceService;

    private SalaryAvailabilityService $availabilityService;

    private PayrollService $payrollService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00', 'Asia/Kolkata'));

        $this->admin = User::factory()->create(['email' => 'admin_rec@example.com']);
        $this->admin->assignRole('admin');

        $this->shopA = Shop::factory()->create([
            'name' => 'Shop A',
            'code' => 'SH-A',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->shopB = Shop::factory()->create([
            'name' => 'Shop B',
            'code' => 'SH-B',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->shopUser = User::factory()->create();
        $this->shopUser->assignRole('shop');

        ShopOwnerAssignment::create([
            'user_id' => $this->shopUser->id,
            'shop_id' => $this->shopA->id,
        ]);

        $this->category = EmployeeCategory::factory()->create(['staff_area' => 'shop']);

        EmployeeAdvanceRule::create([
            'minimum_present_days' => 0,
            'advance_percent' => 50,
            'is_active' => true,
        ]);

        ShopAccountingCategory::create([
            'name' => 'Staff Salaries',
            'purpose' => 'staff_salary',
            'type' => 'expense',
            'cash_effect' => true,
            'shop_id' => null,
            'is_active' => true,
        ]);

        ShopAccountingCategory::create([
            'name' => 'Staff Advances',
            'purpose' => 'staff_advance',
            'type' => 'expense',
            'cash_effect' => true,
            'shop_id' => null,
            'is_active' => true,
        ]);

        $this->advanceService = app(EmployeeAdvanceService::class);
        $this->availabilityService = app(SalaryAvailabilityService::class);
        $this->payrollService = app(PayrollService::class);
    }

    private function createEmployee(string $name, float $monthlySalary = 30000): Employee
    {
        $employee = Employee::factory()->create([
            'name' => $name,
            'default_shop_id' => $this->shopA->id,
            'employee_category_id' => $this->category->id,
            'salary_type' => 'monthly',
            'monthly_salary' => $monthlySalary,
            'employment_status' => 'active',
        ]);

        ShopEmployeeAssignment::create([
            'shop_id' => $this->shopA->id,
            'employee_id' => $employee->id,
            'assigned_from' => '2026-09-01',
            'assigned_to' => null,
        ]);

        // 10 days of attendance in September => 10 * 1000 = 10,000 earned
        for ($d = 1; $d <= 10; $d++) {
            EmployeeAttendance::create([
                'employee_id' => $employee->id,
                'shop_id' => $this->shopA->id,
                'attendance_date' => sprintf('2026-09-%02d', $d),
                'status' => 'present',
                'source' => 'manual',
                'day_count' => 1.0,
            ]);
        }

        return $employee;
    }

    public function test_no_prior_payroll_allows_normal_payment(): void
    {
        $employee = $this->createEmployee('New Staff');
        $payrollMonth = CarbonImmutable::parse('2026-09-01');

        $recovery = $this->advanceService->resolveEmployeeRecoveryDebt($employee, $payrollMonth);
        $this->assertSame(0.0, $recovery);

        // Advance up to 50% ceiling (5,000 of 10,000) should be auto-approved
        $advance = $this->advanceService->requestOrPayAdvance(
            $employee,
            $this->shopA,
            2000.0,
            'petty_cash',
            Carbon::parse('2026-09-15'),
            $this->shopUser,
            'Advance with no prior payroll',
            (string) Str::uuid(),
        );

        $this->assertSame('approved', $advance->status);
        $this->assertEquals(2000.0, (float) $advance->approved_amount);

        // Salary payment within remaining earned (10,000 - 2,000 = 8,000) should succeed
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00', 'Asia/Kolkata'));
        $salary = $this->advanceService->recordShopSalaryPayment(
            $employee,
            $this->shopA,
            3000.0,
            'petty_cash',
            Carbon::parse('2026-09-30'),
            $this->shopUser,
            'Partial salary payment',
            (string) Str::uuid(),
        );

        $this->assertSame('paid', $salary->status);
        $this->assertEquals(3000.0, (float) $salary->amount);
    }

    public function test_prior_verified_zero_recovery_allows_normal_payment(): void
    {
        $employee = $this->createEmployee('Zero Recovery Staff');
        $payrollMonth = CarbonImmutable::parse('2026-09-01');

        $priorRun = PayrollRun::factory()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'finalized',
        ]);

        PayrollRunItem::factory()->create([
            'payroll_run_id' => $priorRun->id,
            'employee_id' => $employee->id,
            'opening_recovery_amount' => 0.0,
            'closing_recovery_amount' => 0.0,
        ]);

        $recovery = $this->advanceService->resolveEmployeeRecoveryDebt($employee, $payrollMonth);
        $this->assertSame(0.0, $recovery);

        $advance = $this->advanceService->requestOrPayAdvance(
            $employee,
            $this->shopA,
            2500.0,
            'petty_cash',
            Carbon::parse('2026-09-15'),
            $this->shopUser,
            'Advance with verified zero recovery',
            (string) Str::uuid(),
        );

        $this->assertSame('approved', $advance->status);

        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00', 'Asia/Kolkata'));
        $salary = $this->advanceService->recordShopSalaryPayment(
            $employee,
            $this->shopA,
            2000.0,
            'petty_cash',
            Carbon::parse('2026-09-30'),
            $this->shopUser,
            'Salary with verified zero recovery',
            (string) Str::uuid(),
        );

        $this->assertSame('paid', $salary->status);
    }

    public function test_prior_positive_recovery_causes_manager_advance_to_become_pending(): void
    {
        $employee = $this->createEmployee('Debt Staff');
        $payrollMonth = CarbonImmutable::parse('2026-09-01');

        $priorRun = PayrollRun::factory()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'finalized',
        ]);

        PayrollRunItem::factory()->create([
            'payroll_run_id' => $priorRun->id,
            'employee_id' => $employee->id,
            'opening_recovery_amount' => 0.0,
            'closing_recovery_amount' => 1500.0,
        ]);

        $recovery = $this->advanceService->resolveEmployeeRecoveryDebt($employee, $payrollMonth);
        $this->assertSame(1500.0, $recovery);

        // Advance must NOT be auto-approved; it must become pending because shop allocation is NULL
        $advance = $this->advanceService->requestOrPayAdvance(
            $employee,
            $this->shopA,
            1000.0,
            'petty_cash',
            Carbon::parse('2026-09-15'),
            $this->shopUser,
            'Advance under debt',
            (string) Str::uuid(),
        );

        $this->assertSame('pending', $advance->status);
        $this->assertNull($advance->shop_staff_payment_id);
    }

    public function test_prior_positive_recovery_rejects_manager_salary(): void
    {
        $employee = $this->createEmployee('Debt Staff 2');

        $priorRun = PayrollRun::factory()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'finalized',
        ]);

        PayrollRunItem::factory()->create([
            'payroll_run_id' => $priorRun->id,
            'employee_id' => $employee->id,
            'closing_recovery_amount' => 2000.0,
        ]);

        $this->expectException(ValidationException::class);

        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00', 'Asia/Kolkata'));
        $this->advanceService->recordShopSalaryPayment(
            $employee,
            $this->shopA,
            1000.0,
            'petty_cash',
            Carbon::parse('2026-09-30'),
            $this->shopUser,
            'Salary under debt',
            (string) Str::uuid(),
        );
    }

    public function test_prior_null_recovery_blocks_manager_payment(): void
    {
        $employee = $this->createEmployee('Historical Null Staff');
        $payrollMonth = CarbonImmutable::parse('2026-09-01');

        $priorRun = PayrollRun::factory()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'finalized',
        ]);

        PayrollRunItem::factory()->create([
            'payroll_run_id' => $priorRun->id,
            'employee_id' => $employee->id,
            'opening_recovery_amount' => null,
            'closing_recovery_amount' => null,
        ]);

        $recovery = $this->advanceService->resolveEmployeeRecoveryDebt($employee, $payrollMonth);
        $this->assertNull($recovery);

        // Advance request becomes pending (requires HR)
        $advance = $this->advanceService->requestOrPayAdvance(
            $employee,
            $this->shopA,
            1000.0,
            'petty_cash',
            Carbon::parse('2026-09-15'),
            $this->shopUser,
            'Advance with unknown recovery',
            (string) Str::uuid(),
        );
        $this->assertSame('pending', $advance->status);

        // Direct salary payment is blocked
        $this->expectException(ValidationException::class);
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00', 'Asia/Kolkata'));
        $this->advanceService->recordShopSalaryPayment(
            $employee,
            $this->shopA,
            1000.0,
            'petty_cash',
            Carbon::parse('2026-09-30'),
            $this->shopUser,
            'Salary with unknown recovery',
            (string) Str::uuid(),
        );
    }

    public function test_multi_shop_employee_debt_is_not_deducted_fully_from_every_shop(): void
    {
        $employee = Employee::factory()->create([
            'name' => 'Multi Shop Staff',
            'default_shop_id' => $this->shopA->id,
            'employee_category_id' => $this->category->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 30000,
            'employment_status' => 'active',
        ]);

        ShopEmployeeAssignment::create([
            'shop_id' => $this->shopA->id,
            'employee_id' => $employee->id,
            'assigned_from' => '2026-09-01',
        ]);

        ShopEmployeeAssignment::create([
            'shop_id' => $this->shopB->id,
            'employee_id' => $employee->id,
            'assigned_from' => '2026-09-01',
        ]);

        // 5 days at Shop A (5,000 earned) and 5 days at Shop B (5,000 earned)
        for ($d = 1; $d <= 5; $d++) {
            EmployeeAttendance::create([
                'employee_id' => $employee->id,
                'shop_id' => $this->shopA->id,
                'attendance_date' => sprintf('2026-09-%02d', $d),
                'status' => 'present',
                'source' => 'manual',
                'day_count' => 1.0,
            ]);
            EmployeeAttendance::create([
                'employee_id' => $employee->id,
                'shop_id' => $this->shopB->id,
                'attendance_date' => sprintf('2026-09-%02d', $d + 5),
                'status' => 'present',
                'source' => 'manual',
                'day_count' => 1.0,
            ]);
        }

        $payrollMonth = CarbonImmutable::parse('2026-09-01');
        $calcDate = CarbonImmutable::parse('2026-09-15');
        $openingRecovery = 4000.0;

        // Calculate for Shop A with unallocated shop recovery (null)
        $dataShopA = $this->availabilityService->calculate(
            $employee,
            $payrollMonth,
            $calcDate,
            (int) $this->shopA->id,
            openingRecovery: $openingRecovery,
            shopAllocatedRecovery: null,
        );

        // Calculate for Shop B with unallocated shop recovery (null)
        $dataShopB = $this->availabilityService->calculate(
            $employee,
            $payrollMonth,
            $calcDate,
            (int) $this->shopB->id,
            openingRecovery: $openingRecovery,
            shopAllocatedRecovery: null,
        );

        // Shop A earned is 5,000. Shop signed salary remaining should NOT deduct 4,000 debt twice!
        $this->assertEquals(5000.0, $dataShopA->shopSignedSalaryRemaining);
        $this->assertEquals(5000.0, $dataShopB->shopSignedSalaryRemaining);

        // Employee-wide earned = 10,000, minus 4,000 debt = 6,000 remaining
        $this->assertEquals(6000.0, $dataShopA->employeeWideSignedSalaryRemaining);

        // Both indicate unknown data quality because recovery allocation is unknown
        $this->assertSame('unknown', $dataShopA->dataQualityStatus);
        $this->assertContains('recovery_allocation_unknown', $dataShopA->dataQualityReasons);
        $this->assertSame('unknown', $dataShopB->dataQualityStatus);
        $this->assertContains('recovery_allocation_unknown', $dataShopB->dataQualityReasons);
    }

    public function test_hr_snapshot_preserves_positive_and_unknown_recovery(): void
    {
        $employeeDebt = $this->createEmployee('Snapshot Debt Staff');
        $employeeNull = $this->createEmployee('Snapshot Null Staff');

        $priorRun = PayrollRun::factory()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'finalized',
        ]);

        PayrollRunItem::factory()->create([
            'payroll_run_id' => $priorRun->id,
            'employee_id' => $employeeDebt->id,
            'closing_recovery_amount' => 1750.0,
        ]);

        PayrollRunItem::factory()->create([
            'payroll_run_id' => $priorRun->id,
            'employee_id' => $employeeNull->id,
            'closing_recovery_amount' => null,
        ]);

        // Create pending requests
        $reqDebt = $this->advanceService->requestOrPayAdvance(
            $employeeDebt,
            $this->shopA,
            1000.0,
            'petty_cash',
            Carbon::parse('2026-09-15'),
            $this->shopUser,
            'Debt advance',
            (string) Str::uuid(),
        );

        $reqNull = $this->advanceService->requestOrPayAdvance(
            $employeeNull,
            $this->shopA,
            1000.0,
            'petty_cash',
            Carbon::parse('2026-09-15'),
            $this->shopUser,
            'Null advance',
            (string) Str::uuid(),
        );

        // HR overrides and reviews both
        $reviewedDebt = $this->advanceService->review(
            $reqDebt,
            'approved',
            1000.0,
            $this->admin,
            'HR approved debt override',
            'shop_cash',
        );

        $reviewedNull = $this->advanceService->review(
            $reqNull,
            'approved',
            1000.0,
            $this->admin,
            'HR approved null override',
            'shop_cash',
        );

        $this->assertEquals(1750.0, $reviewedDebt->review_snapshot['opening_recovery']);
        $this->assertNull($reviewedNull->review_snapshot['opening_recovery']);
    }

    public function test_backfilled_payroll_runs_select_latest_period_not_highest_id(): void
    {
        $employee = $this->createEmployee('Backfill Staff');
        $payrollMonth = CarbonImmutable::parse('2026-09-01');

        // Run 1: August (period_end 2026-08-31), closing recovery = 1200.0
        $augustRun = PayrollRun::factory()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'finalized',
        ]);

        $augustItem = PayrollRunItem::factory()->create([
            'payroll_run_id' => $augustRun->id,
            'employee_id' => $employee->id,
            'closing_recovery_amount' => 1200.0,
        ]);

        // Run 2: July backfilled later with HIGHER database ID, closing recovery = 9999.0
        $julyRun = PayrollRun::factory()->create([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => 'finalized',
        ]);

        $julyItem = PayrollRunItem::factory()->create([
            'payroll_run_id' => $julyRun->id,
            'employee_id' => $employee->id,
            'closing_recovery_amount' => 9999.0,
        ]);

        // Verify July has higher ID than August
        $this->assertGreaterThan($augustItem->id, $julyItem->id);

        // Query for September should pick August (1200.0), NOT July (9999.0)
        $recovery = $this->advanceService->resolveEmployeeRecoveryDebt($employee, $payrollMonth);
        $this->assertSame(1200.0, $recovery);

        $recoveryMap = $this->advanceService->resolveEmployeesRecoveryDebt([$employee->id], $payrollMonth);
        $this->assertSame(1200.0, $recoveryMap[$employee->id]);
    }

    public function test_finalization_rejects_prior_null_recovery(): void
    {
        $employee = $this->createEmployee('Finalize Null Staff');

        // Prior finalized run with NULL closing recovery
        $augustRun = PayrollRun::factory()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'finalized',
        ]);

        PayrollRunItem::factory()->create([
            'payroll_run_id' => $augustRun->id,
            'employee_id' => $employee->id,
            'closing_recovery_amount' => null,
        ]);

        // Current unfinalized September run
        $septemberRun = $this->payrollService->generate(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30'),
            $this->admin->id,
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('unverified or unknown historical recovery balance');

        $this->payrollService->finalize($septemberRun, $this->admin->id);
    }

    public function test_finalization_succeeds_when_prior_recovery_is_numeric_or_no_prior_run(): void
    {
        $employeeWithZero = $this->createEmployee('Finalize Zero Staff');
        $employeeNew = $this->createEmployee('Finalize New Staff');

        $augustRun = PayrollRun::factory()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'finalized',
        ]);

        PayrollRunItem::factory()->create([
            'payroll_run_id' => $augustRun->id,
            'employee_id' => $employeeWithZero->id,
            'closing_recovery_amount' => 0.0,
        ]);

        $septemberRun = $this->payrollService->generate(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30'),
            $this->admin->id,
        );

        $finalized = $this->payrollService->finalize($septemberRun, $this->admin->id);
        $this->assertSame('finalized', $finalized->status);
    }

    public function test_batch_resolver_returns_chronologically_latest_item_with_tie_breaker(): void
    {
        $employee = $this->createEmployee('Tie Breaker Staff');
        $payrollMonth = CarbonImmutable::parse('2026-09-01');

        $runMay = PayrollRun::factory()->create([
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'finalized',
        ]);
        PayrollRunItem::factory()->create([
            'payroll_run_id' => $runMay->id,
            'employee_id' => $employee->id,
            'closing_recovery_amount' => 500.0,
        ]);

        $runJune = PayrollRun::factory()->create([
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'finalized',
        ]);
        PayrollRunItem::factory()->create([
            'payroll_run_id' => $runJune->id,
            'employee_id' => $employee->id,
            'closing_recovery_amount' => 600.0,
        ]);

        $runAugust = PayrollRun::factory()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'finalized',
        ]);
        PayrollRunItem::factory()->create([
            'payroll_run_id' => $runAugust->id,
            'employee_id' => $employee->id,
            'closing_recovery_amount' => 800.0,
        ]);

        $batch = $this->advanceService->resolveEmployeesRecoveryDebt([$employee->id], $payrollMonth);
        $this->assertSame(800.0, $batch[$employee->id]);
    }

    public function test_batch_resolver_remains_bounded_and_identical_to_single_resolver_with_many_months(): void
    {
        $emp1 = $this->createEmployee('Multi Month Staff 1');
        $emp2 = $this->createEmployee('Multi Month Staff 2');
        $emp3 = $this->createEmployee('Multi Month Staff 3 (No Prior)');

        $payrollMonth = CarbonImmutable::parse('2026-09-01');

        // Create 6 historical finalized payroll months for emp1 and emp2
        for ($m = 1; $m <= 6; $m++) {
            $run = PayrollRun::factory()->create([
                'period_start' => sprintf('2026-%02d-01', $m),
                'period_end' => Carbon::parse(sprintf('2026-%02d-01', $m))->endOfMonth()->toDateString(),
                'status' => 'finalized',
            ]);

            PayrollRunItem::factory()->create([
                'payroll_run_id' => $run->id,
                'employee_id' => $emp1->id,
                'closing_recovery_amount' => $m === 6 ? 1500.0 : ($m * 100.0),
            ]);

            PayrollRunItem::factory()->create([
                'payroll_run_id' => $run->id,
                'employee_id' => $emp2->id,
                'closing_recovery_amount' => $m === 6 ? null : 0.0, // June (latest) is NULL
            ]);
        }

        $allEmpIds = [$emp1->id, $emp2->id, $emp3->id];
        $batchResults = $this->advanceService->resolveEmployeesRecoveryDebt($allEmpIds, $payrollMonth);

        $this->assertCount(3, $batchResults);

        // Batch results must strictly match single resolution for every employee
        foreach ($allEmpIds as $id) {
            $emp = Employee::find($id);
            $singleResult = $this->advanceService->resolveEmployeeRecoveryDebt($emp, $payrollMonth);
            $this->assertSame($singleResult, $batchResults[$id]);
        }

        $this->assertSame(1500.0, $batchResults[$emp1->id]);
        $this->assertNull($batchResults[$emp2->id]);
        $this->assertSame(0.0, $batchResults[$emp3->id]);
    }
}
