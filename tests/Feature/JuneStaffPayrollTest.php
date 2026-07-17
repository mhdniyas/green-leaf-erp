<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\EmployeeCategorySeeder;
use Database\Seeders\JuneStaffAttendanceSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JuneStaffPayrollTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_june_staff_payroll_generates_expected_amounts_and_can_be_finalized(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            EmployeeCategorySeeder::class,
        ]);

        $admin = User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@greenleaf.com',
        ]);
        $admin->assignRole('admin');
        $admin->employee()->update([
            'employment_status' => 'inactive',
        ]);

        $this->seed(JuneStaffAttendanceSeeder::class);

        $this
            ->actingAs($admin)
            ->post(route('admin.staff.payroll.store'), [
                'payroll_month' => '2026-06',
            ])
            ->assertRedirect(route('admin.staff.payroll.index', ['payroll_month' => '2026-06']))
            ->assertSessionHas('success', 'Payroll draft generated for June 2026. Review and finalize when ready.');

        $payrollRun = PayrollRun::query()
            ->with(['items.employee'])
            ->whereDate('period_start', '2026-06-01')
            ->whereDate('period_end', '2026-06-30')
            ->firstOrFail();

        $this->assertSame('draft', $payrollRun->status);
        $this->assertSame(3, $payrollRun->items->count());
        $this->assertSame('63400.00', $payrollRun->gross_amount);
        $this->assertSame('63400.00', $payrollRun->net_amount);

        $this->assertPayrollLine(
            $payrollRun,
            'DEMO-DB-001',
            presentDays: '20.00',
            halfDays: '0.00',
            paidLeaveDays: '6.00',
            unpaidLeaveDays: '4.00',
            absentDays: '0.00',
            payableUnits: '26.00',
            finalAmount: '52000.00',
        );

        $this->assertPayrollLine(
            $payrollRun,
            'DEMO-OFF-ABS-001',
            presentDays: '0.00',
            halfDays: '0.00',
            paidLeaveDays: '0.00',
            unpaidLeaveDays: '0.00',
            absentDays: '30.00',
            payableUnits: '0.00',
            finalAmount: '0.00',
        );

        $this->assertPayrollLine(
            $payrollRun,
            'DEMO-SH-MIX-001',
            presentDays: '12.00',
            halfDays: '6.00',
            paidLeaveDays: '4.00',
            unpaidLeaveDays: '1.00',
            absentDays: '7.00',
            payableUnits: '19.00',
            finalAmount: '11400.00',
        );

        $this
            ->actingAs($admin)
            ->post(route('admin.staff.payroll.finalize', $payrollRun), [
                'payroll_month' => '2026-06',
            ])
            ->assertRedirect(route('admin.staff.payroll.index', ['payroll_month' => '2026-06']))
            ->assertSessionHas('success', 'Payroll finalized for June 2026.');

        $payrollRun->refresh();

        $this->assertSame('finalized', $payrollRun->status);
        $this->assertSame($admin->id, $payrollRun->finalized_by);
        $this->assertNotNull($payrollRun->finalized_at);
        $this->assertNotNull($payrollRun->journal_entry_id);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $payrollRun->journal_entry_id,
            'reference' => 'PAYROLL-20260601-20260630',
            'description' => 'Payroll finalized for June 2026',
        ]);
    }

    public function test_june_staff_attendance_seeder_gives_existing_demo_users_payable_salary(): void
    {
        $this->seed([
            EmployeeCategorySeeder::class,
            DemoUserSeeder::class,
            JuneStaffAttendanceSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@greenleaf.com')->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.staff.payroll.store'), [
                'payroll_month' => '2026-06',
            ])
            ->assertRedirect(route('admin.staff.payroll.index', ['payroll_month' => '2026-06']));

        $payrollRun = PayrollRun::query()
            ->with(['items.employee'])
            ->whereDate('period_start', '2026-06-01')
            ->whereDate('period_end', '2026-06-30')
            ->firstOrFail();

        $this->assertPayrollLine(
            $payrollRun,
            $admin->employee()->firstOrFail()->employee_code,
            presentDays: '30.00',
            halfDays: '0.00',
            paidLeaveDays: '0.00',
            unpaidLeaveDays: '0.00',
            absentDays: '0.00',
            payableUnits: '30.00',
            finalAmount: '24000.00',
        );

        $shopOwner = User::query()->where('email', 'shop-shop-casio@greenleaf.com')->firstOrFail();

        $this->assertPayrollLine(
            $payrollRun,
            $shopOwner->employee()->firstOrFail()->employee_code,
            presentDays: '30.00',
            halfDays: '0.00',
            paidLeaveDays: '0.00',
            unpaidLeaveDays: '0.00',
            absentDays: '0.00',
            payableUnits: '30.00',
            finalAmount: '18000.00',
        );
    }

    private function assertPayrollLine(
        PayrollRun $payrollRun,
        string $employeeCode,
        string $presentDays,
        string $halfDays,
        string $paidLeaveDays,
        string $unpaidLeaveDays,
        string $absentDays,
        string $payableUnits,
        string $finalAmount,
    ): void {
        $employee = Employee::query()->where('employee_code', $employeeCode)->firstOrFail();
        $item = $payrollRun->items->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($item, "Expected payroll item for {$employeeCode}.");
        $this->assertSame($presentDays, $item->present_days);
        $this->assertSame($halfDays, $item->half_days);
        $this->assertSame($paidLeaveDays, $item->paid_leave_days);
        $this->assertSame($unpaidLeaveDays, $item->unpaid_leave_days);
        $this->assertSame($absentDays, $item->absent_days);
        $this->assertSame($payableUnits, $item->payable_units);
        $this->assertSame($finalAmount, $item->final_amount);
    }
}
