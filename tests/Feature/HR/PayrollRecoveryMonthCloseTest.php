<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\JournalEntry;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\HR\PayrollService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PayrollRecoveryMonthCloseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private EmployeeCategory $category;

    private Employee $employee;

    private PayrollService $payrollService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-30 20:00:00', 'Asia/Kolkata'));

        $this->admin = User::factory()->create(['email' => 'admin_payroll@example.com']);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'name' => 'Flagship Store',
            'code' => 'FLAG',
        ]);

        $this->category = EmployeeCategory::create([
            'code' => 'SALES_EXECUTIVE',
            'name' => 'Sales Executive',
            'staff_area' => 'shop',
            'monthly_paid_leave_limit' => 2,
            'present_day_weight' => 1.0,
            'half_day_weight' => 0.5,
            'paid_leave_weight' => 1.0,
            'excess_leave_weight' => 0.0,
            'absent_day_weight' => 0.0,
            'is_active' => true,
        ]);

        $this->employee = Employee::factory()->create([
            'employee_category_id' => $this->category->id,
            'default_shop_id' => $this->shop->id,
            'name' => 'Payroll Test Employee',
            'salary_type' => 'monthly',
            'monthly_salary' => 30000,
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'joined_on' => '2026-01-01',
        ]);

        $this->payrollService = app(PayrollService::class);
    }

    private function recordFullMonthAttendance(Carbon $start, Carbon $end): void
    {
        $current = $start->copy();
        while ($current->lte($end)) {
            EmployeeAttendance::create([
                'employee_id' => $this->employee->id,
                'shop_id' => $this->shop->id,
                'attendance_date' => $current->toDateString(),
                'status' => 'present',
                'source' => 'shop_quick',
                'verified_by_shop_id' => $this->shop->id,
            ]);
            $current->addDay();
        }
    }

    public function test_advance_smaller_than_salary_reduces_remaining_and_clears_recovery(): void
    {
        $start = Carbon::parse('2026-09-01');
        $end = Carbon::parse('2026-09-30');
        $this->recordFullMonthAttendance($start, $end);

        $run = $this->payrollService->generate($start, $end, $this->admin->id);
        $item = $run->items()->where('employee_id', $this->employee->id)->firstOrFail();

        ShopStaffPayment::create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'payroll_run_item_id' => $item->id,
            'payment_type' => 'advance',
            'amount' => 10000,
            'paid_on' => '2026-09-15',
            'funding_source' => 'sales_income',
            'created_by' => $this->admin->id,
        ]);

        $finalizedRun = $this->payrollService->finalize($run, $this->admin->id);
        $finalizedItem = $finalizedRun->items()->where('employee_id', $this->employee->id)->firstOrFail();

        $this->assertSame('finalized', $finalizedRun->status);
        $this->assertEquals(0.0, (float) $finalizedItem->opening_recovery_amount);
        $this->assertEquals(0.0, (float) $finalizedItem->closing_recovery_amount);
        $this->assertEquals(20000.0, (float) $finalizedItem->signedSalaryRemaining());
        $this->assertEquals(20000.0, (float) $finalizedItem->rule_snapshot['signed_closing_balance']);
    }

    public function test_advance_equal_to_salary_leaves_zero_balance(): void
    {
        $start = Carbon::parse('2026-09-01');
        $end = Carbon::parse('2026-09-30');
        $this->recordFullMonthAttendance($start, $end);

        $run = $this->payrollService->generate($start, $end, $this->admin->id);
        $item = $run->items()->where('employee_id', $this->employee->id)->firstOrFail();

        ShopStaffPayment::create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'payroll_run_item_id' => $item->id,
            'payment_type' => 'advance',
            'amount' => 30000,
            'paid_on' => '2026-09-15',
            'funding_source' => 'sales_income',
            'created_by' => $this->admin->id,
        ]);

        $finalizedRun = $this->payrollService->finalize($run, $this->admin->id);
        $finalizedItem = $finalizedRun->items()->where('employee_id', $this->employee->id)->firstOrFail();

        $this->assertEquals(0.0, (float) $finalizedItem->closing_recovery_amount);
        $this->assertEquals(0.0, (float) $finalizedItem->signedSalaryRemaining());
        $this->assertEquals(0.0, (float) $finalizedItem->rule_snapshot['signed_closing_balance']);
    }

    public function test_advance_greater_than_salary_creates_closing_recovery(): void
    {
        $start = Carbon::parse('2026-09-01');
        $end = Carbon::parse('2026-09-30');
        $this->recordFullMonthAttendance($start, $end);

        $run = $this->payrollService->generate($start, $end, $this->admin->id);
        $item = $run->items()->where('employee_id', $this->employee->id)->firstOrFail();

        // Over-advanced by 5000 (35000 vs 30000 earned)
        ShopStaffPayment::create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'payroll_run_item_id' => $item->id,
            'payment_type' => 'advance',
            'amount' => 35000,
            'paid_on' => '2026-09-20',
            'funding_source' => 'sales_income',
            'created_by' => $this->admin->id,
        ]);

        $finalizedRun = $this->payrollService->finalize($run, $this->admin->id);
        $finalizedItem = $finalizedRun->items()->where('employee_id', $this->employee->id)->firstOrFail();

        $this->assertEquals(5000.0, (float) $finalizedItem->closing_recovery_amount);
        $this->assertEquals(-5000.0, (float) $finalizedItem->signedSalaryRemaining());
        $this->assertEquals(-5000.0, (float) $finalizedItem->rule_snapshot['signed_closing_balance']);
        $this->assertEquals(5000.0, (float) $finalizedItem->rule_snapshot['closing_recovery_amount']);
    }

    public function test_recovery_carries_over_to_next_month_finalization(): void
    {
        // Month 1: September 2026 with 5000 excess advance
        $septStart = Carbon::parse('2026-09-01');
        $septEnd = Carbon::parse('2026-09-30');
        $this->recordFullMonthAttendance($septStart, $septEnd);

        $septRun = $this->payrollService->generate($septStart, $septEnd, $this->admin->id);
        $septItem = $septRun->items()->where('employee_id', $this->employee->id)->firstOrFail();

        ShopStaffPayment::create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'payroll_run_item_id' => $septItem->id,
            'payment_type' => 'advance',
            'amount' => 35000,
            'paid_on' => '2026-09-20',
            'funding_source' => 'sales_income',
            'created_by' => $this->admin->id,
        ]);

        $this->payrollService->finalize($septRun, $this->admin->id);

        // Month 2: October 2026 with full attendance
        $octStart = Carbon::parse('2026-10-01');
        $octEnd = Carbon::parse('2026-10-31');
        $this->recordFullMonthAttendance($octStart, $octEnd);

        $octRun = $this->payrollService->generate($octStart, $octEnd, $this->admin->id);
        $finalizedOctRun = $this->payrollService->finalize($octRun, $this->admin->id);
        $octItem = $finalizedOctRun->items()->where('employee_id', $this->employee->id)->firstOrFail();

        // Opening recovery carried forward from September
        $this->assertEquals(5000.0, (float) $octItem->opening_recovery_amount);
        // Gross 30000 - 5000 opening recovery = 25000 signed remaining
        $this->assertEquals(25000.0, (float) $octItem->signedSalaryRemaining());
        $this->assertEquals(0.0, (float) $octItem->closing_recovery_amount);
    }

    public function test_company_advance_recovery_creates_clearing_journal_without_cash_movement(): void
    {
        $start = Carbon::parse('2026-09-01');
        $end = Carbon::parse('2026-09-30');
        $this->recordFullMonthAttendance($start, $end);

        $run = $this->payrollService->generate($start, $end, $this->admin->id);
        $item = $run->items()->where('employee_id', $this->employee->id)->firstOrFail();

        PayrollPayment::create([
            'payroll_run_id' => $run->id,
            'payroll_run_item_id' => $item->id,
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'payment_type' => 'advance',
            'amount' => 8000,
            'payment_method' => 'cash',
            'paid_on' => '2026-09-10',
            'created_by' => $this->admin->id,
        ]);

        $finalizedRun = $this->payrollService->finalize($run, $this->admin->id);

        $clearingJournal = JournalEntry::where('source_type', PayrollRun::class)
            ->where('source_id', $finalizedRun->id)
            ->where('source_event', 'advance_clearing')
            ->first();

        $this->assertNotNull($clearingJournal);
        $clearingJournal->load('transactions.account');
        $this->assertCount(2, $clearingJournal->transactions);

        $debitLine = $clearingJournal->transactions->where('type', 'debit')->first();
        $creditLine = $clearingJournal->transactions->where('type', 'credit')->first();

        // Debit: Salary Payable (2300)
        $this->assertEquals(8000.0, (float) $debitLine->amount);
        $this->assertEquals('2300', $debitLine->account->code);

        // Credit: Employee Advances (1600)
        $this->assertEquals(8000.0, (float) $creditLine->amount);
        $this->assertEquals('1600', $creditLine->account->code);
    }

    public function test_cannot_regenerate_finalized_payroll_run(): void
    {
        $start = Carbon::parse('2026-09-01');
        $end = Carbon::parse('2026-09-30');
        $this->recordFullMonthAttendance($start, $end);

        $run = $this->payrollService->generate($start, $end, $this->admin->id);
        $this->payrollService->finalize($run, $this->admin->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Finalized payroll runs cannot be regenerated.');

        $this->payrollService->generate($start, $end, $this->admin->id);
    }
}
