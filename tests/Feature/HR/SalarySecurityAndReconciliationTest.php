<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\PayrollPayment;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopOwnerAssignment;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\HR\EmployeeAdvanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalarySecurityAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopManagerA;

    private User $shopManagerB;

    private Shop $shopA;

    private Shop $shopB;

    private Employee $employeeA;

    private Employee $employeeB;

    private CompanyAccount $companyAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-15 11:00:00', 'Asia/Kolkata'));

        // Users
        $this->admin = User::factory()->create(['email' => 'admin_sec@example.com']);
        $this->admin->assignRole('admin');

        $this->shopManagerA = User::factory()->create(['email' => 'manager_a@example.com']);
        $this->shopManagerA->assignRole('shop');

        $this->shopManagerB = User::factory()->create(['email' => 'manager_b@example.com']);
        $this->shopManagerB->assignRole('shop');

        // Shops
        $this->shopA = Shop::factory()->create([
            'name' => 'Shop Alpha',
            'code' => 'ALPHA',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $this->shopB = Shop::factory()->create([
            'name' => 'Shop Beta',
            'code' => 'BETA',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopOwnerAssignment::create([
            'user_id' => $this->shopManagerA->id,
            'shop_id' => $this->shopA->id,
            'status' => 'active',
        ]);

        ShopOwnerAssignment::create([
            'user_id' => $this->shopManagerB->id,
            'shop_id' => $this->shopB->id,
            'status' => 'active',
        ]);

        // Category & Rules
        $category = EmployeeCategory::factory()->create([
            'staff_area' => 'shop',
            'monthly_paid_leave_limit' => 2,
            'present_day_weight' => 1.0,
            'half_day_weight' => 0.5,
            'paid_leave_weight' => 1.0,
            'excess_leave_weight' => 0.0,
            'absent_day_weight' => 0.0,
        ]);

        EmployeeAdvanceRule::create([
            'minimum_present_days' => 5,
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

        // Employees
        $this->employeeA = Employee::factory()->create([
            'employee_category_id' => $category->id,
            'default_shop_id' => $this->shopA->id,
            'name' => 'Alpha Worker',
            'monthly_salary' => 30000,
            'salary_type' => 'monthly',
            'verification_status' => 'approved',
            'employment_status' => 'active',
        ]);
        ShopEmployeeAssignment::create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shopA->id,
            'assigned_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $this->employeeB = Employee::factory()->create([
            'employee_category_id' => $category->id,
            'default_shop_id' => $this->shopB->id,
            'name' => 'Beta Worker',
            'monthly_salary' => 30000,
            'salary_type' => 'monthly',
            'verification_status' => 'approved',
            'employment_status' => 'active',
        ]);
        ShopEmployeeAssignment::create([
            'employee_id' => $this->employeeB->id,
            'shop_id' => $this->shopB->id,
            'assigned_from' => '2026-01-01',
            'is_active' => true,
        ]);

        // Company account
        $this->companyAccount = CompanyAccount::create([
            'code' => 'CA-MAIN',
            'name' => 'Main Head Office Cash',
            'account_type' => 'cash',
            'currency' => 'INR',
            'enabled' => true,
        ]);

        // 10 days of attendance for each
        for ($i = 1; $i <= 10; $i++) {
            EmployeeAttendance::create([
                'employee_id' => $this->employeeA->id,
                'shop_id' => $this->shopA->id,
                'attendance_date' => sprintf('2026-09-%02d', $i),
                'status' => 'present',
                'source' => 'shop_quick',
                'verified_by_shop_id' => $this->shopA->id,
            ]);
            EmployeeAttendance::create([
                'employee_id' => $this->employeeB->id,
                'shop_id' => $this->shopB->id,
                'attendance_date' => sprintf('2026-09-%02d', $i),
                'status' => 'present',
                'source' => 'shop_quick',
                'verified_by_shop_id' => $this->shopB->id,
            ]);
        }
    }

    public function test_manager_cannot_request_advance_for_employee_of_another_shop(): void
    {
        // Manager A tries to request advance for Employee B (belonging to Shop B)
        $response = $this->actingAs($this->shopManagerA)
            ->post(route('shop-owner.staff.advance-requests.store'), [
                'shop_id' => $this->shopA->id,
                'employee_id' => $this->employeeB->id,
                'amount' => 1000,
                'requested_on' => '2026-09-15',
                'fund_source' => 'sales_income',
                'request_uuid' => (string) Str::uuid(),
            ]);

        $response->assertSessionHasErrors(['employee_id']);
        $this->assertDatabaseMissing('employee_advance_requests', [
            'employee_id' => $this->employeeB->id,
        ]);
    }

    public function test_manager_cannot_record_salary_for_employee_of_another_shop(): void
    {
        $response = $this->actingAs($this->shopManagerA)
            ->post(route('shop-owner.staff.salary-payments.store'), [
                'shop_id' => $this->shopA->id,
                'employee_id' => $this->employeeB->id,
                'amount' => 2000,
                'paid_on' => '2026-09-15',
                'fund_source' => 'sales_income',
                'request_uuid' => (string) Str::uuid(),
            ]);

        $response->assertSessionHasErrors(['employee_id']);
        $this->assertDatabaseMissing('shop_staff_payments', [
            'employee_id' => $this->employeeB->id,
        ]);
    }

    public function test_manager_cannot_inject_unauthorized_funding_sources(): void
    {
        // Manager A attempts to specify company_cash as funding source
        $response = $this->actingAs($this->shopManagerA)
            ->post(route('shop-owner.staff.advance-requests.store'), [
                'shop_id' => $this->shopA->id,
                'employee_id' => $this->employeeA->id,
                'amount' => 1000,
                'requested_on' => '2026-09-15',
                'fund_source' => 'company_cash',
                'request_uuid' => (string) Str::uuid(),
            ]);

        $response->assertSessionHasErrors(['fund_source']);
        $this->assertDatabaseMissing('employee_advance_requests', [
            'employee_id' => $this->employeeA->id,
        ]);
    }

    public function test_non_hr_user_cannot_approve_advance_requests(): void
    {
        // Create a pending advance request for Employee A
        $request = EmployeeAdvanceRequest::create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shopA->id,
            'payroll_month' => '2026-09-01',
            'requested_amount' => 8000,
            'requested_on' => '2026-09-15',
            'status' => 'pending',
            'created_by' => $this->shopManagerA->id,
            'request_uuid' => (string) Str::uuid(),
        ]);

        // Manager B (non-admin / non-HR) attempts to approve the request
        $response = $this->actingAs($this->shopManagerB)
            ->patch(route('admin.staff.advance-requests.review', $request), [
                'action' => 'approve',
                'approved_amount' => 8000,
                'fund_source' => 'sales_income',
                'review_notes' => 'Unauthorized approval attempt',
            ]);

        $response->assertForbidden();

        $request->refresh();
        $this->assertSame('pending', $request->status);
    }

    public function test_duplicate_request_uuid_prevents_double_payout_and_duplicate_lines(): void
    {
        $uuid = (string) Str::uuid();

        // First direct payment
        $response1 = $this->actingAs($this->shopManagerA)
            ->post(route('shop-owner.staff.advance-requests.store'), [
                'shop_id' => $this->shopA->id,
                'employee_id' => $this->employeeA->id,
                'amount' => 2000,
                'requested_on' => '2026-09-15',
                'fund_source' => 'sales_income',
                'request_uuid' => $uuid,
            ]);
        $response1->assertRedirect();

        $this->assertDatabaseCount('shop_staff_payments', 1);
        $this->assertDatabaseCount('shop_accounting_entry_lines', 1);

        // Second payment with SAME UUID (e.g. replay attack or rapid double click)
        $response2 = $this->actingAs($this->shopManagerA)
            ->post(route('shop-owner.staff.advance-requests.store'), [
                'shop_id' => $this->shopA->id,
                'employee_id' => $this->employeeA->id,
                'amount' => 2000,
                'requested_on' => '2026-09-15',
                'fund_source' => 'sales_income',
                'request_uuid' => $uuid,
            ]);
        $response2->assertRedirect();

        // Payments and cashbook entries remain strictly 1
        $this->assertDatabaseCount('shop_staff_payments', 1);
        $this->assertDatabaseCount('shop_accounting_entry_lines', 1);
    }

    public function test_end_to_end_reconciliation_maintains_zero_discrepancy(): void
    {
        // 1. Direct Manager Shop Advance
        $advUuid = (string) Str::uuid();
        $this->actingAs($this->shopManagerA)
            ->post(route('shop-owner.staff.advance-requests.store'), [
                'shop_id' => $this->shopA->id,
                'employee_id' => $this->employeeA->id,
                'amount' => 2500,
                'requested_on' => '2026-09-15',
                'fund_source' => 'petty_cash',
                'request_uuid' => $advUuid,
            ]);

        $shopPayment = ShopStaffPayment::where('employee_id', $this->employeeA->id)->firstOrFail();
        $cashbookLine = ShopAccountingEntryLine::where('source_type', ShopStaffPayment::class)
            ->where('source_id', $shopPayment->id)
            ->firstOrFail();

        // Verify Shop Payment matches cashbook line exactly
        $this->assertEquals(2500.0, (float) $shopPayment->amount);
        $this->assertEquals(2500.0, (float) $cashbookLine->amount);
        $cashbookLine->load('entry');
        $this->assertSame($this->shopA->id, $cashbookLine->entry->shop_id);
        $this->assertSame('petty', $cashbookLine->funding_source);

        // 2. HR Company Payout
        $advanceService = app(EmployeeAdvanceService::class);
        $companyAdvRequest = EmployeeAdvanceRequest::create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shopA->id,
            'payroll_month' => '2026-09-01',
            'requested_amount' => 4000,
            'requested_on' => '2026-09-15',
            'status' => 'pending',
            'created_by' => $this->shopManagerA->id,
            'request_uuid' => (string) Str::uuid(),
        ]);

        $advanceService->review(
            $companyAdvRequest,
            'approve',
            4000.0,
            $this->admin,
            'Approved for company disbursement',
            'company_cash',
            $this->companyAccount->id,
        );

        // Verify company payment created 0 additional shop cashbook lines
        $this->assertDatabaseCount('shop_accounting_entry_lines', 1);

        $companyAdvRequest->refresh();
        $this->assertNotNull($companyAdvRequest->payroll_payment_id);
        $payrollPayment = PayrollPayment::findOrFail($companyAdvRequest->payroll_payment_id);
        $this->assertNotNull($payrollPayment->journal_entry_id);

        // Verify company journal entry is perfectly balanced
        $journalEntry = JournalEntry::findOrFail($payrollPayment->journal_entry_id);

        $totalDebits = (float) JournalTransaction::where('journal_entry_id', $journalEntry->id)
            ->where('type', 'debit')
            ->sum('amount');
        $totalCredits = (float) JournalTransaction::where('journal_entry_id', $journalEntry->id)
            ->where('type', 'credit')
            ->sum('amount');

        $this->assertEquals(4000.0, $totalDebits);
        $this->assertEquals(4000.0, $totalCredits);
        $this->assertEquals($totalDebits, $totalCredits);
    }
}
