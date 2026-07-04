<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Exports\PayrollMonthExport;
use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\EmployeeCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class StaffPayrollExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            EmployeeCategorySeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_payroll_index_filters_to_selected_month(): void
    {
        $juneRun = PayrollRun::factory()->create([
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
        ]);

        PayrollRun::factory()->create([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.payroll.index', [
            'payroll_month' => '2026-06',
        ]));

        $response->assertOk();
        $response->assertSee($juneRun->period_start->format('F Y'));
        $response->assertDontSee('July 2026');
        $response->assertSee('Export Excel');
        $response->assertSee('PDF View');
    }

    public function test_admin_can_export_selected_payroll_month_to_excel(): void
    {
        $this->createPayrollRunWithItem('2026-06-01', '2026-06-30');

        Excel::fake();

        $this->actingAs($this->admin)->get(route('admin.staff.payroll.export.excel', [
            'payroll_month' => '2026-06',
        ]));

        Excel::assertDownloaded('staff-payroll-2026-06.xlsx', function (PayrollMonthExport $export): bool {
            return $export instanceof PayrollMonthExport;
        });
    }

    public function test_admin_can_open_selected_payroll_month_pdf_view(): void
    {
        $this->createPayrollRunWithItem('2026-06-01', '2026-06-30');

        $response = $this->actingAs($this->admin)->get(route('admin.staff.payroll.export.pdf', [
            'payroll_month' => '2026-06',
        ]));

        $response->assertOk();
        $response->assertSee('Staff Payroll');
        $response->assertSee('June 2026');
        $response->assertSee('Net Amount');
    }

    private function createPayrollRunWithItem(string $periodStart, string $periodEnd): PayrollRun
    {
        $category = EmployeeCategory::query()->where('code', 'office')->firstOrFail();
        $employee = Employee::factory()->create([
            'employee_category_id' => $category->id,
            'monthly_salary' => 25000,
            'staff_area' => 'office',
        ]);

        $payrollRun = PayrollRun::factory()->create([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'generated_by' => $this->admin->id,
            'gross_amount' => 25000,
            'net_amount' => 25000,
        ]);

        $payrollRun->items()->create([
            'employee_id' => $employee->id,
            'employee_category_id' => $category->id,
            'base_salary' => 25000,
            'present_days' => 20,
            'half_days' => 2,
            'paid_leave_days' => 4,
            'unpaid_leave_days' => 0,
            'absent_days' => 4,
            'payable_units' => 25,
            'computed_amount' => 25000,
            'override_amount' => null,
            'final_amount' => 25000,
            'rule_snapshot' => [
                'present_day_weight' => 1,
                'half_day_weight' => 0.5,
                'monthly_paid_leave_limit' => 4,
            ],
        ]);

        return $payrollRun->fresh(['items.employee', 'items.category', 'generatedBy']);
    }
}
