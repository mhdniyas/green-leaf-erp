<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\JournalEntry;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\HR\PayrollService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CarriedCompanyAdvanceAccountingTest extends TestCase
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

        $this->admin = User::factory()->create(['email' => 'admin_payroll_adv@example.com']);
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
            'name' => 'Carried Advance Staff',
            'salary_type' => 'monthly',
            'monthly_salary' => 30000,
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'joined_on' => '2026-01-01',
        ]);

        $this->payrollService = app(PayrollService::class);
    }

    private function recordAttendance(Carbon $start, int $days): void
    {
        for ($d = 1; $d <= $days; $d++) {
            EmployeeAttendance::create([
                'employee_id' => $this->employee->id,
                'shop_id' => $this->shop->id,
                'attendance_date' => $start->copy()->day($d)->toDateString(),
                'status' => 'present',
                'source' => 'shop_quick',
                'verified_by_shop_id' => $this->shop->id,
            ]);
        }
    }

    public function test_same_month_partial_recovery(): void
    {
        // September: 30 days present => 30,000 earned
        $septStart = Carbon::parse('2026-09-01');
        $septEnd = Carbon::parse('2026-09-30');
        $this->recordAttendance($septStart, 30);

        $septRun = $this->payrollService->generate($septStart, $septEnd, $this->admin->id);
        $septItem = $septRun->items()->where('employee_id', $this->employee->id)->firstOrFail();

        // Company advance of 35,000
        PayrollPayment::create([
            'payroll_run_id' => $septRun->id,
            'payroll_run_item_id' => $septItem->id,
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'payment_type' => 'advance',
            'amount' => 35000,
            'payment_method' => 'bank_transfer',
            'paid_on' => '2026-09-10',
            'created_by' => $this->admin->id,
        ]);

        $finalizedSeptRun = $this->payrollService->finalize($septRun, $this->admin->id);
        $finalizedSeptItem = $finalizedSeptRun->items()->where('employee_id', $this->employee->id)->firstOrFail();

        // Cleared company advance capped at 30,000 salary
        $clearingJournal = JournalEntry::where('source_type', PayrollRun::class)
            ->where('source_id', $finalizedSeptRun->id)
            ->where('source_event', 'advance_clearing')
            ->firstOrFail();

        $clearingJournal->load('transactions.account');
        $debitLine = $clearingJournal->transactions->where('type', 'debit')->firstOrFail();
        $creditLine = $clearingJournal->transactions->where('type', 'credit')->firstOrFail();

        $this->assertEquals(30000.0, (float) $debitLine->amount);
        $this->assertEquals('2300', $debitLine->account->code);
        $this->assertEquals(30000.0, (float) $creditLine->amount);
        $this->assertEquals('1600', $creditLine->account->code);

        // Closing company recovery is 5,000, and closing total recovery is 5,000
        $this->assertEquals(5000.0, (float) $finalizedSeptItem->closing_company_recovery_amount);
        $this->assertEquals(5000.0, (float) $finalizedSeptItem->closing_recovery_amount);
        $this->assertEquals(0.0, (float) $finalizedSeptItem->opening_company_recovery_amount);
        $this->assertEquals(0.0, (float) $finalizedSeptItem->opening_recovery_amount);
    }

    public function test_next_month_carried_company_recovery_and_reconciliation_across_two_months(): void
    {
        // Month 1 (September): 35k advance, 30k earned -> 30k cleared, 5k carried
        $septStart = Carbon::parse('2026-09-01');
        $septEnd = Carbon::parse('2026-09-30');
        $this->recordAttendance($septStart, 30);

        $septRun = $this->payrollService->generate($septStart, $septEnd, $this->admin->id);
        $septItem = $septRun->items()->where('employee_id', $this->employee->id)->firstOrFail();

        PayrollPayment::create([
            'payroll_run_id' => $septRun->id,
            'payroll_run_item_id' => $septItem->id,
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'payment_type' => 'advance',
            'amount' => 35000,
            'payment_method' => 'bank_transfer',
            'paid_on' => '2026-09-10',
            'created_by' => $this->admin->id,
        ]);

        $finalizedSept = $this->payrollService->finalize($septRun, $this->admin->id);
        $septClearingJournal = JournalEntry::where('source_type', PayrollRun::class)
            ->where('source_id', $finalizedSept->id)
            ->where('source_event', 'advance_clearing')
            ->firstOrFail();

        $septDebit = $septClearingJournal->transactions->where('type', 'debit')->firstOrFail();
        $this->assertEquals(30000.0, (float) $septDebit->amount);

        // Month 2 (October): 10 days present in 31-day month => (10/31)*30000 = 9677.42 earned, or let's use exact 10 days of a 30-day salary structure
        $octStart = Carbon::parse('2026-10-01');
        $octEnd = Carbon::parse('2026-10-31');
        // Let's record 10 days attendance (earned = round(30000 * 10 / 31, 2) = 9677.42, which is > 5,000)
        $this->recordAttendance($octStart, 10);

        $octRun = $this->payrollService->generate($octStart, $octEnd, $this->admin->id);
        $finalizedOct = $this->payrollService->finalize($octRun, $this->admin->id);
        $octItem = $finalizedOct->items()->where('employee_id', $this->employee->id)->firstOrFail();

        // Verify opening recovery amounts carried from September
        $this->assertEquals(5000.0, (float) $octItem->opening_recovery_amount);
        $this->assertEquals(5000.0, (float) $octItem->opening_company_recovery_amount);

        // Carried company advance of 5,000 is fully cleared against October salary
        $octClearingJournal = JournalEntry::where('source_type', PayrollRun::class)
            ->where('source_id', $finalizedOct->id)
            ->where('source_event', 'advance_clearing')
            ->firstOrFail();

        $octClearingJournal->load('transactions.account');
        $octDebit = $octClearingJournal->transactions->where('type', 'debit')->firstOrFail();
        $octCredit = $octClearingJournal->transactions->where('type', 'credit')->firstOrFail();

        $this->assertEquals(5000.0, (float) $octDebit->amount);
        $this->assertEquals('2300', $octDebit->account->code);
        $this->assertEquals(5000.0, (float) $octCredit->amount);
        $this->assertEquals('1600', $octCredit->account->code);

        // Closing company recovery is now 0.00
        $this->assertEquals(0.0, (float) $octItem->closing_company_recovery_amount);
        $this->assertEquals(0.0, (float) $octItem->closing_recovery_amount);

        // Gate check: Across Month 1 and Month 2, total company advance = 35,000; total cleared in Account 1600 = 30,000 + 5,000 = 35,000!
        $totalClearedAcrossTwoMonths = (float) $septDebit->amount + (float) $octDebit->amount;
        $this->assertEquals(35000.0, $totalClearedAcrossTwoMonths);
    }

    public function test_recovery_larger_than_salary_carries_remaining_forward(): void
    {
        // Month 1: 50,000 advance, 20,000 earned (20 days)
        $septStart = Carbon::parse('2026-09-01');
        $septEnd = Carbon::parse('2026-09-30');
        $this->recordAttendance($septStart, 20); // 20 * 1000 = 20,000

        $septRun = $this->payrollService->generate($septStart, $septEnd, $this->admin->id);
        $septItem = $septRun->items()->where('employee_id', $this->employee->id)->firstOrFail();

        PayrollPayment::create([
            'payroll_run_id' => $septRun->id,
            'payroll_run_item_id' => $septItem->id,
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'payment_type' => 'advance',
            'amount' => 50000,
            'payment_method' => 'bank_transfer',
            'paid_on' => '2026-09-10',
            'created_by' => $this->admin->id,
        ]);

        $finalizedSept = $this->payrollService->finalize($septRun, $this->admin->id);
        $septItem = $finalizedSept->items()->where('employee_id', $this->employee->id)->firstOrFail();

        // 20k cleared, 30k carried
        $this->assertEquals(30000.0, (float) $septItem->closing_company_recovery_amount);
        $this->assertEquals(30000.0, (float) $septItem->closing_recovery_amount);

        // Month 2: 10,000 earned (10 days attendance out of 30), carried debt is 30,000 (larger than salary)
        $octStart = Carbon::parse('2026-10-01');
        $octEnd = Carbon::parse('2026-10-31');
        // Let's create an employee with 31,000 monthly salary so daily is exactly 1000
        $this->recordAttendance($octStart, 10);

        $octRun = $this->payrollService->generate($octStart, $octEnd, $this->admin->id);
        $finalizedOct = $this->payrollService->finalize($octRun, $this->admin->id);
        $octItem = $finalizedOct->items()->where('employee_id', $this->employee->id)->firstOrFail();

        $octSalary = (float) $octItem->final_amount;
        $this->assertLessThan(30000.0, $octSalary);

        // Clearing journal only clears up to October salary
        $octClearingJournal = JournalEntry::where('source_type', PayrollRun::class)
            ->where('source_id', $finalizedOct->id)
            ->where('source_event', 'advance_clearing')
            ->firstOrFail();

        $clearedInOct = (float) $octClearingJournal->transactions->where('type', 'debit')->first()->amount;
        $this->assertEquals($octSalary, $clearedInOct);

        // Remaining unapplied company component carried forward
        $expectedRemaining = round(30000.0 - $octSalary, 2);
        $this->assertEquals($expectedRemaining, (float) $octItem->closing_company_recovery_amount);
        $this->assertEquals($expectedRemaining, (float) $octItem->closing_recovery_amount);
    }

    public function test_mixed_shop_and_company_advances(): void
    {
        // 30 days attendance => 30,000 earned
        $start = Carbon::parse('2026-09-01');
        $end = Carbon::parse('2026-09-30');
        $this->recordAttendance($start, 30);

        $run = $this->payrollService->generate($start, $end, $this->admin->id);
        $item = $run->items()->where('employee_id', $this->employee->id)->firstOrFail();

        // Shop advance: 5,000
        ShopStaffPayment::create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'payroll_run_item_id' => $item->id,
            'payment_type' => 'advance',
            'amount' => 5000,
            'paid_on' => '2026-09-05',
            'funding_source' => 'sales_income',
            'created_by' => $this->admin->id,
        ]);

        // Company advance: 28,000
        PayrollPayment::create([
            'payroll_run_id' => $run->id,
            'payroll_run_item_id' => $item->id,
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'payment_type' => 'advance',
            'amount' => 28000,
            'payment_method' => 'bank_transfer',
            'paid_on' => '2026-09-10',
            'created_by' => $this->admin->id,
        ]);

        $finalizedRun = $this->payrollService->finalize($run, $this->admin->id);
        $finalizedItem = $finalizedRun->items()->where('employee_id', $this->employee->id)->firstOrFail();

        // Salary = 30,000.
        // Shop advance (5,000) is absorbed first -> 25,000 available for company advance.
        // Company advance (28,000) recovers 25,000.
        // Clear Employee Advances journal = 25,000.
        $clearingJournal = JournalEntry::where('source_type', PayrollRun::class)
            ->where('source_id', $finalizedRun->id)
            ->where('source_event', 'advance_clearing')
            ->firstOrFail();

        $clearedAmount = (float) $clearingJournal->transactions->where('type', 'debit')->first()->amount;
        $this->assertEquals(25000.0, $clearedAmount);

        // Closing company recovery = 28,000 - 25,000 = 3,000
        $this->assertEquals(3000.0, (float) $finalizedItem->closing_company_recovery_amount);
        // Total closing recovery = 33,000 - 30,000 = 3,000
        $this->assertEquals(3000.0, (float) $finalizedItem->closing_recovery_amount);
    }

    public function test_no_double_clearing_after_finalization_and_journals_balanced(): void
    {
        $start = Carbon::parse('2026-09-01');
        $end = Carbon::parse('2026-09-30');
        $this->recordAttendance($start, 30);

        $run = $this->payrollService->generate($start, $end, $this->admin->id);
        $item = $run->items()->where('employee_id', $this->employee->id)->firstOrFail();

        PayrollPayment::create([
            'payroll_run_id' => $run->id,
            'payroll_run_item_id' => $item->id,
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'payment_type' => 'advance',
            'amount' => 10000,
            'payment_method' => 'bank_transfer',
            'paid_on' => '2026-09-10',
            'created_by' => $this->admin->id,
        ]);

        $finalized = $this->payrollService->finalize($run, $this->admin->id);

        // Exactly one clearing journal exists
        $journals = JournalEntry::where('source_type', PayrollRun::class)
            ->where('source_id', $finalized->id)
            ->where('source_event', 'advance_clearing')
            ->get();
        $this->assertCount(1, $journals);

        // Journal is balanced
        $entry = $journals->firstOrFail();
        $entry->load('transactions');
        $totalDebit = (float) $entry->transactions->where('type', 'debit')->sum('amount');
        $totalCredit = (float) $entry->transactions->where('type', 'credit')->sum('amount');
        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertGreaterThan(0.0, $totalDebit);

        // Attempt repeat finalization throws RuntimeException
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already finalized');

        $this->payrollService->finalize($finalized, $this->admin->id);
    }

    public function test_migration_rollback_and_historical_null_compatibility(): void
    {
        $this->assertTrue(Schema::hasColumn('payroll_run_items', 'opening_company_recovery_amount'));
        $this->assertTrue(Schema::hasColumn('payroll_run_items', 'closing_company_recovery_amount'));

        // Instantiate migration and test down()
        $migration = require database_path('migrations/2026_09_05_223500_add_company_recovery_fields_to_payroll_run_items_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasColumn('payroll_run_items', 'opening_company_recovery_amount'));
        $this->assertFalse(Schema::hasColumn('payroll_run_items', 'closing_company_recovery_amount'));

        // Run up() again
        $migration->up();

        $this->assertTrue(Schema::hasColumn('payroll_run_items', 'opening_company_recovery_amount'));
        $this->assertTrue(Schema::hasColumn('payroll_run_items', 'closing_company_recovery_amount'));

        // Historical compatibility: item with positive closing recovery but NULL company recovery blocks finalization
        $augustRun = PayrollRun::factory()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'finalized',
        ]);

        PayrollRunItem::factory()->create([
            'payroll_run_id' => $augustRun->id,
            'employee_id' => $this->employee->id,
            'closing_recovery_amount' => 500.0,
            'closing_company_recovery_amount' => null, // Unknown composition
        ]);

        $septRun = $this->payrollService->generate(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30'),
            $this->admin->id,
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('unverified or unknown company recovery composition');

        $this->payrollService->finalize($septRun, $this->admin->id);
    }
}
