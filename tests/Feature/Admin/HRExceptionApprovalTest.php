<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\PayrollPayment;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\HR\EmployeeAdvanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HRExceptionApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private Employee $employee;

    private EmployeeAdvanceService $advanceService;

    private CompanyAccount $cashAccount;

    private CompanyAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00', 'Asia/Kolkata'));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'code' => 'SH-EXP',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $category = EmployeeCategory::factory()->create(['staff_area' => 'shop']);

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

        ShopAccountingCategory::create([
            'name' => 'Staff Advances',
            'purpose' => 'staff_advance',
            'type' => 'expense',
            'cash_effect' => true,
            'shop_id' => null,
            'is_active' => true,
        ]);

        $this->employee = Employee::factory()->create([
            'name' => 'Roy Mustang',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => $category->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 60000,
            'employment_status' => 'active',
        ]);

        ShopEmployeeAssignment::create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'assigned_from' => '2026-09-01',
            'assigned_to' => null,
        ]);

        // 10 present days => earned 20000. 50% ceiling = 10000.
        for ($d = 1; $d <= 10; $d++) {
            EmployeeAttendance::create([
                'employee_id' => $this->employee->id,
                'shop_id' => $this->shop->id,
                'attendance_date' => sprintf('2026-09-%02d', $d),
                'status' => 'present',
                'source' => 'manual',
                'day_count' => 1.0,
            ]);
        }

        $this->cashAccount = CompanyAccount::create([
            'name' => 'HQ Petty Cash',
            'account_type' => 'cash',
            'opening_balance' => 50000,
            'current_balance' => 50000,
            'enabled' => true,
        ]);

        $this->bankAccount = CompanyAccount::create([
            'name' => 'HQ HDFC Bank',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'account_number' => '1234567890',
            'opening_balance' => 500000,
            'current_balance' => 500000,
            'enabled' => true,
        ]);

        $this->advanceService = app(EmployeeAdvanceService::class);
    }

    private function createPendingRequest(float $amount = 15000.0): EmployeeAdvanceRequest
    {
        // 15000 > 10000 ceiling, so it creates a pending request
        return $this->advanceService->requestOrPayAdvance(
            $this->employee,
            $this->shop,
            $amount,
            'sales_income',
            Carbon::parse('2026-09-15'),
            $this->admin,
            'Medical emergency request',
        );
    }

    public function test_approve_with_shop_sales_cash(): void
    {
        $pending = $this->createPendingRequest(12000.0);
        $this->assertSame('pending', $pending->status);

        $reviewed = $this->advanceService->review(
            $pending,
            'approve',
            12000.0,
            $this->admin,
            'Approved by director',
            'sales_income',
            null,
        );

        $this->assertSame('approved', $reviewed->status);
        $this->assertEquals(12000.0, (float) $reviewed->approved_amount);
        $this->assertSame('sales_income', $reviewed->fund_source);
        $this->assertNotNull($reviewed->shop_staff_payment_id);
        $this->assertNull($reviewed->payroll_payment_id);
        $this->assertIsArray($reviewed->review_snapshot);

        // Shop payment created
        $payment = ShopStaffPayment::find($reviewed->shop_staff_payment_id);
        $this->assertNotNull($payment);
        $this->assertEquals(12000.0, (float) $payment->amount);

        // Cashbook line created with funding_source = sales
        $cashbookLine = ShopAccountingEntryLine::where('source_type', ShopStaffPayment::class)
            ->where('source_id', $payment->id)
            ->first();
        $this->assertNotNull($cashbookLine);
        $this->assertSame('sales', $cashbookLine->funding_source);
    }

    public function test_approve_with_company_cash_creates_payroll_payment_and_journal_with_no_shop_cashbook(): void
    {
        $pending = $this->createPendingRequest(15000.0);

        $reviewed = $this->advanceService->review(
            $pending,
            'approve',
            15000.0,
            $this->admin,
            'Approved via central cash',
            'company_cash',
            $this->cashAccount->id,
        );

        $this->assertSame('approved', $reviewed->status);
        $this->assertSame('company_cash', $reviewed->approved_fund_source);
        $this->assertNull($reviewed->shop_staff_payment_id);
        $this->assertNotNull($reviewed->payroll_payment_id);

        $payment = PayrollPayment::find($reviewed->payroll_payment_id);
        $this->assertNotNull($payment);
        $this->assertSame('advance', $payment->payment_type);
        $this->assertEquals(15000.0, (float) $payment->amount);
        $this->assertNotNull($payment->journal_entry_id);

        // Verify zero shop cashbook entries created
        $this->assertDatabaseCount('shop_staff_payments', 0);
        $this->assertDatabaseCount('shop_accounting_entry_lines', 0);
    }

    public function test_approve_with_company_bank(): void
    {
        $pending = $this->createPendingRequest(14000.0);

        $reviewed = $this->advanceService->review(
            $pending,
            'approve',
            14000.0,
            $this->admin,
            'Bank transfer approved',
            'company_bank',
            $this->bankAccount->id,
        );

        $this->assertSame('approved', $reviewed->status);
        $this->assertSame('company_bank', $reviewed->approved_fund_source);
        $this->assertNotNull($reviewed->payroll_payment_id);
        $this->assertNull($reviewed->shop_staff_payment_id);

        $payment = PayrollPayment::find($reviewed->payroll_payment_id);
        $this->assertNotNull($payment);
        $this->assertSame('bank', $payment->payment_method);
        $this->assertEquals(14000.0, (float) $payment->amount);
    }

    public function test_reduce_requested_amount_on_approval(): void
    {
        $pending = $this->createPendingRequest(15000.0);

        $reviewed = $this->advanceService->review(
            $pending,
            'approve',
            10000.0,
            $this->admin,
            'Partial advance allowed',
            'sales_income',
        );

        $this->assertSame('approved', $reviewed->status);
        $this->assertEquals(10000.0, (float) $reviewed->approved_amount);
        $this->assertEquals(15000.0, (float) $reviewed->requested_amount);
    }

    public function test_reject_with_note(): void
    {
        $pending = $this->createPendingRequest(15000.0);

        $reviewed = $this->advanceService->review(
            $pending,
            'reject',
            0.0,
            $this->admin,
            'Policy prohibits over-limit draw at this time',
        );

        $this->assertSame('rejected', $reviewed->status);
        $this->assertNull($reviewed->approved_amount);
        $this->assertSame('Policy prohibits over-limit draw at this time', $reviewed->review_note);
        $this->assertNull($reviewed->shop_staff_payment_id);
        $this->assertNull($reviewed->payroll_payment_id);
        $this->assertDatabaseCount('shop_staff_payments', 0);
        $this->assertDatabaseCount('payroll_payments', 0);
    }

    public function test_reject_without_note_fails(): void
    {
        $pending = $this->createPendingRequest(15000.0);

        $this->expectException(ValidationException::class);

        $this->advanceService->review(
            $pending,
            'reject',
            0.0,
            $this->admin,
            '', // Empty note
        );
    }

    public function test_disabled_company_account_fails(): void
    {
        $disabledAccount = CompanyAccount::create([
            'name' => 'Old Inactive Account',
            'account_type' => 'bank',
            'opening_balance' => 0,
            'current_balance' => 0,
            'enabled' => false,
        ]);

        $pending = $this->createPendingRequest(15000.0);

        $this->expectException(ValidationException::class);

        $this->advanceService->review(
            $pending,
            'approve',
            15000.0,
            $this->admin,
            'Attempt with disabled account',
            'company_bank',
            $disabledAccount->id,
        );
    }

    public function test_source_account_mismatch_fails(): void
    {
        // company_cash with a bank account
        $pending = $this->createPendingRequest(15000.0);

        $this->expectException(ValidationException::class);

        $this->advanceService->review(
            $pending,
            'approve',
            15000.0,
            $this->admin,
            'Mismatch test',
            'company_cash',
            $this->bankAccount->id,
        );
    }

    public function test_double_approval_fails(): void
    {
        $pending = $this->createPendingRequest(12000.0);

        $this->advanceService->review(
            $pending,
            'approve',
            12000.0,
            $this->admin,
            'First approval',
            'sales_income',
        );

        $this->expectException(ValidationException::class);

        // Attempt second approval on same request
        $this->advanceService->review(
            $pending,
            'approve',
            12000.0,
            $this->admin,
            'Second approval attempt',
            'sales_income',
        );
    }
}
