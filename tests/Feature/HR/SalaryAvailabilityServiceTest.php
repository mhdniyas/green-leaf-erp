<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Services\HR\SalaryAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalaryAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private SalaryAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(SalaryAvailabilityService::class);
    }

    public function test_5_day_benchmark_example_returns_correct_availability(): void
    {
        $employee = Employee::factory()->create([
            'salary_type' => 'monthly',
            'monthly_salary' => 15000.00,
        ]);
        $shop = Shop::factory()->create();

        $month = CarbonImmutable::parse('2026-09-01');
        $calcDate = CarbonImmutable::parse('2026-09-05');

        for ($i = 1; $i <= 5; $i++) {
            EmployeeAttendance::factory()->create([
                'employee_id' => $employee->id,
                'shop_id' => $shop->id,
                'attendance_date' => sprintf('2026-09-%02d', $i),
                'status' => 'present',
            ]);
        }

        $data = $this->service->calculate($employee, $month, $calcDate, $shop->id, openingRecovery: 0.0);

        $this->assertEquals(500.0, $data->dailyRate);
        $this->assertEquals(5.0, $data->presentDays);
        $this->assertEquals(2500.0, $data->employeeWideEarned);
        $this->assertEquals(2500.0, $data->shopEarned);
        $this->assertEquals(1250.0, $data->employeeWideAvailableAdvance);
        $this->assertEquals(1250.0, $data->shopAvailableAdvance);
        $this->assertEquals(2500.0, $data->shopRemainingSalary);
        $this->assertEquals('verified', $data->dataQualityStatus);
    }

    public function test_daily_wage_employee_calculation(): void
    {
        $employee = Employee::factory()->create([
            'salary_type' => 'daily_wage',
            'daily_wage' => 600.00,
        ]);
        $shop = Shop::factory()->create();

        $month = CarbonImmutable::parse('2026-09-01');
        $calcDate = CarbonImmutable::parse('2026-09-05');

        for ($i = 1; $i <= 4; $i++) {
            EmployeeAttendance::factory()->create([
                'employee_id' => $employee->id,
                'shop_id' => $shop->id,
                'attendance_date' => sprintf('2026-09-%02d', $i),
                'status' => 'present',
            ]);
        }

        $data = $this->service->calculate($employee, $month, $calcDate, $shop->id, openingRecovery: 0.0);

        $this->assertEquals(600.0, $data->dailyRate);
        $this->assertEquals(4.0, $data->presentDays);
        $this->assertEquals(2400.0, $data->employeeWideEarned);
        $this->assertEquals(1200.0, $data->employeeWideAvailableAdvance);
    }

    public function test_paid_leave_cap_is_shared_across_shops_in_date_order(): void
    {
        $category = EmployeeCategory::factory()->create(['monthly_paid_leave_limit' => 2]);
        $employee = Employee::factory()->create(['employee_category_id' => $category->id, 'salary_type' => 'monthly', 'monthly_salary' => 15000]);
        $shops = Shop::factory()->count(2)->create();
        $month = CarbonImmutable::parse('2026-09-01');
        EmployeeLeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => LeaveType::factory()->create(['is_paid' => true])->id,
            'start_date' => $month, 'end_date' => $month->addDays(3), 'status' => 'approved',
        ]);
        for ($day = 0; $day < 4; $day++) {
            EmployeeAttendance::factory()->create([
                'employee_id' => $employee->id, 'shop_id' => $shops[$day % 2]->id,
                'attendance_date' => $month->addDays($day), 'status' => 'leave',
            ]);
        }
        $shopEarned = 0.0;
        foreach ($shops as $shop) {
            $single = $this->service->calculate($employee, $month, $month->addDays(3), $shop->id, 0.0);
            $batch = $this->service->calculateForShop(Employee::whereKey($employee->id)->get(), $month, $month->addDays(3), $shop->id, [$employee->id => 0.0]);
            $this->assertEquals($single, $batch->get($employee->id));
            $this->assertEquals(500.0, $single->shopEarned);
            $this->assertEquals(1000.0, $single->employeeWideEarned);
            $shopEarned += $single->shopEarned;
        }
        $this->assertEquals(1000.0, $shopEarned);
    }

    public function test_conflicting_month_sources_require_hr_for_both_payment_models(): void
    {
        $month = CarbonImmutable::parse('2026-09-01');
        $run = PayrollRun::factory()->create(['period_start' => $month, 'period_end' => $month->endOfMonth()]);
        $otherRun = PayrollRun::factory()->create(['period_start' => $month->subMonth(), 'period_end' => $month->subMonth()->endOfMonth()]);
        foreach ([ShopStaffPayment::class, PayrollPayment::class] as $model) {
            foreach (['run', 'request'] as $conflictingSource) {
                $employee = Employee::factory()->create();
                $shop = Shop::factory()->create();
                $item = PayrollRunItem::factory()->create(['employee_id' => $employee->id, 'payroll_run_id' => $run->id]);
                $request = EmployeeAdvanceRequest::factory()->create([
                    'employee_id' => $employee->id, 'shop_id' => $shop->id,
                    'payroll_month' => $conflictingSource === 'request' ? $month->subMonth() : $month,
                ]);
                $model::factory()->create([
                    'employee_id' => $employee->id, 'shop_id' => $shop->id,
                    'payroll_run_id' => $conflictingSource === 'run' ? $otherRun->id : $run->id,
                    'payroll_run_item_id' => $item->id, 'employee_advance_request_id' => $request->id,
                    'payment_type' => 'advance', 'amount' => 100,
                ]);
                $single = $this->service->calculate($employee, $month, $month->addDays(4), $shop->id, 0.0);
                $batch = $this->service->calculateForShop(Employee::whereKey($employee->id)->get(), $month, $month->addDays(4), $shop->id, [$employee->id => 0.0]);
                $this->assertEquals($single, $batch->get($employee->id));
                $this->assertTrue($single->hasConflictingLinks);
                $this->assertSame('unknown', $single->dataQualityStatus);
                $this->assertTrue($this->service->evaluateDecision($single, 'advance', 1.0)->requiresHr());
            }
        }
    }

    public function test_unlinked_payments_require_hr_regardless_of_payment_date(): void
    {
        $month = CarbonImmutable::parse('2026-09-01');
        foreach ([ShopStaffPayment::class] as $model) {
            foreach (['2026-08-10', '2026-09-10', '2026-10-10'] as $paidOn) {
                $employee = Employee::factory()->create();
                $shop = Shop::factory()->create();
                $model::factory()->create([
                    'employee_id' => $employee->id, 'shop_id' => $shop->id,
                    'payroll_run_id' => null, 'payroll_run_item_id' => null,
                    'employee_advance_request_id' => null, 'paid_on' => $paidOn,
                    'payment_type' => 'advance', 'amount' => 100,
                ]);
                $single = $this->service->calculate($employee, $month, $month->addDays(4), $shop->id, 0.0);
                $batch = $this->service->calculateForShop(Employee::whereKey($employee->id)->get(), $month, $month->addDays(4), $shop->id, [$employee->id => 0.0]);
                $this->assertEquals($single, $batch->get($employee->id));
                $this->assertSame('unknown', $single->dataQualityStatus);
                $this->assertTrue($this->service->evaluateDecision($single, 'advance', 1.0)->requiresHr());
                $this->assertTrue($this->service->evaluateDecision($single, 'salary', 1.0)->requiresHr());
            }
        }
    }

    public function test_attendance_weights_and_leave_limit_capping(): void
    {
        $category = EmployeeCategory::factory()->create([
            'present_day_weight' => 1.0,
            'half_day_weight' => 0.5,
            'paid_leave_weight' => 1.0,
            'excess_leave_weight' => 0.0,
            'absent_day_weight' => 0.0,
            'monthly_paid_leave_limit' => 2,
        ]);

        $employee = Employee::factory()->create([
            'employee_category_id' => $category->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 30000.00,
        ]);
        $shop = Shop::factory()->create();

        $month = CarbonImmutable::parse('2026-09-01');
        $calcDate = CarbonImmutable::parse('2026-09-10');

        $paidLeaveType = LeaveType::factory()->create(['is_paid' => true]);

        // 3 days leave requested (exceeding monthly limit of 2)
        EmployeeLeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $paidLeaveType->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'status' => 'approved',
        ]);

        // 3 days marked as leave in attendance
        for ($i = 1; $i <= 3; $i++) {
            EmployeeAttendance::factory()->create([
                'employee_id' => $employee->id,
                'shop_id' => $shop->id,
                'attendance_date' => sprintf('2026-09-%02d', $i),
                'status' => 'leave',
            ]);
        }

        // 4 days present, 2 days half day
        for ($i = 4; $i <= 7; $i++) {
            EmployeeAttendance::factory()->create([
                'employee_id' => $employee->id,
                'shop_id' => $shop->id,
                'attendance_date' => sprintf('2026-09-%02d', $i),
                'status' => 'present',
            ]);
        }
        for ($i = 8; $i <= 9; $i++) {
            EmployeeAttendance::factory()->create([
                'employee_id' => $employee->id,
                'shop_id' => $shop->id,
                'attendance_date' => sprintf('2026-09-%02d', $i),
                'status' => 'half_day',
            ]);
        }

        $data = $this->service->calculate($employee, $month, $calcDate, $shop->id, openingRecovery: 0.0);

        // Daily rate: 30000 / 30 = 1000
        // Paid leaves: 2 days (capped from 3), Unpaid leaves: 1 day
        // Present: 4 days (4.0), Half: 2 days (1.0), Paid leave: 2 days (2.0) -> total payable units = 7.0
        $this->assertEquals(4.0, $data->presentDays);
        $this->assertEquals(2.0, $data->halfDays);
        $this->assertEquals(2.0, $data->paidLeaveDays);
        $this->assertEquals(1.0, $data->unpaidLeaveDays);
        $this->assertEquals(7.0, $data->payableUnits);
        $this->assertEquals(7000.0, $data->employeeWideEarned);
        $this->assertEquals(3500.0, $data->employeeWideAvailableAdvance);
    }

    public function test_company_payments_assigned_to_shop_reduce_shop_and_global_totals(): void
    {
        $employee = Employee::factory()->create([
            'salary_type' => 'monthly',
            'monthly_salary' => 15000.00,
        ]);
        $shop = Shop::factory()->create();

        $month = CarbonImmutable::parse('2026-09-01');
        $calcDate = CarbonImmutable::parse('2026-09-10');

        for ($i = 1; $i <= 10; $i++) {
            EmployeeAttendance::factory()->create([
                'employee_id' => $employee->id,
                'shop_id' => $shop->id,
                'attendance_date' => sprintf('2026-09-%02d', $i),
                'status' => 'present',
            ]);
        }

        $payrollRun = PayrollRun::factory()->create([
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
        ]);
        $payrollRunItem = PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $employee->id,
        ]);

        // Company advance assigned to $shop
        PayrollPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollRunItem->id,
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'payment_type' => 'advance',
            'amount' => 1000.00,
        ]);

        $data = $this->service->calculate($employee, $month, $calcDate, $shop->id, openingRecovery: 0.0);

        // Earned: 10 * 500 = 5000. Ceiling = 2500.
        // Advances paid = 1000 (both employee-wide and shop-specific).
        $this->assertEquals(1000.0, $data->employeeWideAdvancesPaid);
        $this->assertEquals(1000.0, $data->shopAdvancesPaid);
        $this->assertEquals(1500.0, $data->employeeWideAvailableAdvance);
        $this->assertEquals(1500.0, $data->shopAvailableAdvance);
        $this->assertEquals(4000.0, $data->shopRemainingSalary);
    }

    public function test_conflicting_links_flag_data_quality_unknown(): void
    {
        $employee = Employee::factory()->create();
        $shop = Shop::factory()->create();
        $month = CarbonImmutable::parse('2026-09-01');
        $calcDate = CarbonImmutable::parse('2026-09-05');

        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'attendance_date' => '2026-09-01',
            'status' => 'present',
        ]);

        $payrollRun = PayrollRun::factory()->create([
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
        ]);
        $payrollRunItem = PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $employee->id,
        ]);

        // Advance request linked to BOTH shop payment and company payment
        $shopPayment = ShopStaffPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollRunItem->id,
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'amount' => 500.0,
            'status' => 'paid',
        ]);
        $payrollPayment = PayrollPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollRunItem->id,
            'employee_id' => $employee->id,
            'amount' => 500.0,
        ]);

        EmployeeAdvanceRequest::factory()->create([
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'payroll_month' => '2026-09-01',
            'shop_staff_payment_id' => $shopPayment->id,
            'payroll_payment_id' => $payrollPayment->id,
            'status' => 'approved',
        ]);

        $data = $this->service->calculate($employee, $month, $calcDate, $shop->id, openingRecovery: 0.0);

        $this->assertTrue($data->hasConflictingLinks);
        $this->assertEquals('unknown', $data->dataQualityStatus);

        $decision = $this->service->evaluateDecision($data, 'advance', 100.0);
        $this->assertTrue($decision->requiresHr());
    }

    public function test_single_and_batch_calculations_produce_identical_results(): void
    {
        $shop = Shop::factory()->create();
        $employees = Employee::factory()->count(3)->create([
            'salary_type' => 'monthly',
            'monthly_salary' => 15000.00,
        ]);

        $month = CarbonImmutable::parse('2026-09-01');
        $calcDate = CarbonImmutable::parse('2026-09-05');

        foreach ($employees as $index => $emp) {
            for ($d = 1; $d <= ($index + 2); $d++) {
                EmployeeAttendance::factory()->create([
                    'employee_id' => $emp->id,
                    'shop_id' => $shop->id,
                    'attendance_date' => sprintf('2026-09-%02d', $d),
                    'status' => 'present',
                ]);
            }
        }

        // Run batch calculation
        $batchResults = $this->service->calculateForShop(
            employees: $employees,
            payrollMonth: $month,
            calculationDate: $calcDate,
            shopId: $shop->id,
            openingRecoveries: [$employees[0]->id => 0.0, $employees[1]->id => 100.0, $employees[2]->id => 0.0],
            shopAllocatedRecoveries: [$employees[0]->id => 0.0, $employees[1]->id => 100.0, $employees[2]->id => 0.0],
        );

        // Compare each with single calculation
        foreach ($employees as $emp) {
            $recovery = in_array($emp->id, [$employees[1]->id], true) ? 100.0 : 0.0;
            $single = $this->service->calculate(
                employee: $emp,
                payrollMonth: $month,
                calculationDate: $calcDate,
                shopId: $shop->id,
                openingRecovery: $recovery,
                shopAllocatedRecovery: $recovery,
            );

            $batch = $batchResults->get($emp->id);

            $this->assertNotNull($batch);
            $this->assertEquals($single, $batch);
            $this->assertEquals($single->dailyRate, $batch->dailyRate);
            $this->assertEquals($single->presentDays, $batch->presentDays);
            $this->assertEquals($single->payableUnits, $batch->payableUnits);
            $this->assertEquals($single->employeeWideEarned, $batch->employeeWideEarned);
            $this->assertEquals($single->shopEarned, $batch->shopEarned);
            $this->assertEquals($single->employeeWideAvailableAdvance, $batch->employeeWideAvailableAdvance);
            $this->assertEquals($single->shopAvailableAdvance, $batch->shopAvailableAdvance);
            $this->assertEquals($single->shopRemainingSalary, $batch->shopRemainingSalary);
            $this->assertEquals($single->dataQualityStatus, $batch->dataQualityStatus);
            $this->assertEquals($single->hasConflictingLinks, $batch->hasConflictingLinks);
        }
    }

    public function test_batch_calculation_query_budget_is_constant(): void
    {
        $shop = Shop::factory()->create();
        $employees = Employee::factory()->count(20)->create([
            'salary_type' => 'monthly',
            'monthly_salary' => 15000.00,
        ]);

        $month = CarbonImmutable::parse('2026-09-01');
        $calcDate = CarbonImmutable::parse('2026-09-05');

        $run = PayrollRun::factory()->create(['period_start' => $month, 'period_end' => $month->endOfMonth()]);
        $leaveType = LeaveType::factory()->create(['is_paid' => true]);

        foreach ($employees as $emp) {
            $item = PayrollRunItem::factory()->create(['employee_id' => $emp->id, 'payroll_run_id' => $run->id]);
            $request = EmployeeAdvanceRequest::factory()->create([
                'employee_id' => $emp->id, 'shop_id' => $shop->id, 'payroll_month' => $month,
            ]);
            EmployeeLeaveRequest::factory()->create([
                'employee_id' => $emp->id, 'leave_type_id' => $leaveType->id,
                'start_date' => $month->addDay(), 'end_date' => $month->addDay(), 'status' => 'approved',
            ]);
            foreach ([ShopStaffPayment::class, PayrollPayment::class] as $model) {
                foreach ([null, $item->id] as $itemId) {
                    $model::factory()->create([
                        'employee_id' => $emp->id, 'shop_id' => $shop->id, 'payroll_run_id' => $run->id,
                        'payroll_run_item_id' => $model === PayrollPayment::class ? $item->id : $itemId,
                        'employee_advance_request_id' => $itemId === null ? null : $request->id,
                        'payment_type' => $itemId === null ? 'salary' : 'advance', 'amount' => 25,
                        'paid_on' => '2026-10-01',
                    ]);
                }
            }
            EmployeeAttendance::factory()->create([
                'employee_id' => $emp->id,
                'shop_id' => $shop->id,
                'attendance_date' => '2026-09-01',
                'status' => 'present',
            ]);
        }

        $queryCounts = [];
        foreach ([1, 20] as $size) {
            $freshEmployees = Employee::whereIn('id', $employees->take($size)->modelKeys())->get();
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $results = $this->service->calculateForShop($freshEmployees, $month, $calcDate, $shop->id, $freshEmployees->mapWithKeys(fn (Employee $employee): array => [$employee->id => 0.0])->all());
                $queries = DB::getQueryLog();
                $queryCounts[] = count($queries);
            } finally {
                DB::disableQueryLog();
            }
            $this->assertCount($size, $results);
            foreach ($queries as $query) {
                $this->assertStringStartsWith('select ', strtolower($query['query']));
            }
            foreach ($freshEmployees as $employee) {
                $single = $this->service->calculate($employee, $month, $calcDate, $shop->id, 0.0);
                $this->assertEquals($single, $results->get($employee->id));
                $this->assertSame('verified', $single->dataQualityStatus);
                $this->assertEquals(50.0, $single->employeeWideSalaryPaid);
                $this->assertEquals(50.0, $single->employeeWideAdvancesPaid);
            }
        }
        $this->assertSame($queryCounts[0], $queryCounts[1]);
        $this->assertLessThanOrEqual(15, $queryCounts[1]);
    }

    public function test_zero_database_writes_during_calculation(): void
    {
        $employee = Employee::factory()->create([
            'salary_type' => 'monthly',
            'monthly_salary' => 15000.00,
        ]);
        $shop = Shop::factory()->create();

        $month = CarbonImmutable::parse('2026-09-01');
        $calcDate = CarbonImmutable::parse('2026-09-05');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $data = $this->service->calculate($employee, $month, $calcDate, $shop->id, openingRecovery: 0.0);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        foreach ($queries as $query) {
            $sql = strtoupper($query['query']);
            $this->assertStringNotContainsString('INSERT INTO', $sql);
            $this->assertStringNotContainsString('UPDATE ', $sql);
            $this->assertStringNotContainsString('DELETE FROM', $sql);
        }

        $this->assertNotNull($data);
    }
}
