<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopOwnerAssignment;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\HR\EmployeeAdvanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManagerPaymentEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $shopUser;

    private Shop $shop;

    private Employee $employee;

    private EmployeeAdvanceService $advanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00', 'Asia/Kolkata'));

        $this->shop = Shop::factory()->create([
            'code' => 'SH-ENG',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->shopUser = User::factory()->create();
        $this->shopUser->assignRole('shop');

        ShopOwnerAssignment::create([
            'user_id' => $this->shopUser->id,
            'shop_id' => $this->shop->id,
        ]);

        $category = EmployeeCategory::factory()->create(['staff_area' => 'shop']);

        EmployeeAdvanceRule::create([
            'minimum_present_days' => 5,
            'advance_percent' => 50,
            'is_active' => true,
        ]);

        // Global active categories for cashbook
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

        // Monthly salary: 30000. In 30-day Sept, daily rate = 1000.
        $this->employee = Employee::factory()->create([
            'name' => 'Edward Elric',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => $category->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 30000,
            'employment_status' => 'active',
        ]);

        ShopEmployeeAssignment::create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'assigned_from' => '2026-09-01',
            'assigned_to' => null,
        ]);

        // 10 present days => earned 10 * 1000 = 10000.
        // Advance ceiling = 50% * 10000 = 5000.
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

        $this->advanceService = app(EmployeeAdvanceService::class);
    }

    public function test_valid_advance_within_limit_creates_approved_request_payment_and_cashbook_line(): void
    {
        $uuid = (string) Str::uuid();

        $advance = $this->advanceService->requestOrPayAdvance(
            $this->employee,
            $this->shop,
            2000.0,
            'petty_cash',
            Carbon::parse('2026-09-15'),
            $this->shopUser,
            'Emergency groceries',
            $uuid,
        );

        $this->assertSame('approved', $advance->status);
        $this->assertEquals(2000.0, (float) $advance->approved_amount);
        $this->assertNotNull($advance->shop_staff_payment_id);

        $payment = ShopStaffPayment::find($advance->shop_staff_payment_id);
        $this->assertNotNull($payment);
        $this->assertSame('advance', $payment->payment_type);
        $this->assertSame('petty_cash', $payment->fund_source);
        $this->assertEquals(2000.0, (float) $payment->amount);

        // Verify Cashbook line mapping
        $cashbookLine = ShopAccountingEntryLine::where('source_type', ShopStaffPayment::class)
            ->where('source_id', $payment->id)
            ->first();
        $this->assertNotNull($cashbookLine);
        $this->assertSame(ShopAccountingEntryLine::FundingPetty, $cashbookLine->funding_source);
        $this->assertEquals(2000.0, (float) $cashbookLine->amount);
    }

    public function test_advance_exactly_at_limit_is_auto_approved(): void
    {
        // Limit is 5000.0 (50% of 10000 earned)
        $advance = $this->advanceService->requestOrPayAdvance(
            $this->employee,
            $this->shop,
            5000.0,
            'sales_income',
            Carbon::parse('2026-09-15'),
            $this->shopUser,
            'Max normal advance',
        );

        $this->assertSame('approved', $advance->status);
        $this->assertEquals(5000.0, (float) $advance->approved_amount);
        $this->assertNotNull($advance->shop_staff_payment_id);

        $cashbookLine = ShopAccountingEntryLine::where('source_type', ShopStaffPayment::class)
            ->where('source_id', $advance->shop_staff_payment_id)
            ->first();
        $this->assertNotNull($cashbookLine);
        $this->assertSame(ShopAccountingEntryLine::FundingSales, $cashbookLine->funding_source);
    }

    public function test_advance_one_paise_above_limit_creates_pending_request_only(): void
    {
        // Limit is 5000.00. Request 5000.01.
        $advance = $this->advanceService->requestOrPayAdvance(
            $this->employee,
            $this->shop,
            5000.01,
            'sales_income',
            Carbon::parse('2026-09-15'),
            $this->shopUser,
            'Over-limit advance request',
        );

        $this->assertSame('pending', $advance->status);
        $this->assertNull($advance->approved_amount);
        $this->assertNull($advance->shop_staff_payment_id);

        // Must NOT create payment or cashbook entry
        $this->assertDatabaseCount('shop_staff_payments', 0);
        $this->assertDatabaseCount('shop_accounting_entry_lines', 0);
    }

    public function test_valid_salary_payment_within_remaining_salary_creates_payment_and_cashbook_line(): void
    {
        // Salary payments are only allowed on the last day of the month
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00', 'Asia/Kolkata'));

        // Earned: 10000. Remaining: 10000.
        $payment = $this->advanceService->recordShopSalaryPayment(
            $this->employee,
            $this->shop,
            4000.0,
            'sales_income',
            Carbon::parse('2026-09-30'),
            $this->shopUser,
            'End-of-month salary draw',
        );

        $this->assertSame('salary', $payment->payment_type);
        $this->assertEquals(4000.0, (float) $payment->amount);
        $this->assertSame('paid', $payment->status);

        $cashbookLine = ShopAccountingEntryLine::where('source_type', ShopStaffPayment::class)
            ->where('source_id', $payment->id)
            ->first();
        $this->assertNotNull($cashbookLine);
        $this->assertSame(ShopAccountingEntryLine::FundingSales, $cashbookLine->funding_source);
    }

    public function test_salary_payment_before_last_day_of_month_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('last day of the month');

        // 2026-09-15 is not the last day — must be rejected
        $this->advanceService->recordShopSalaryPayment(
            $this->employee,
            $this->shop,
            4000.0,
            'sales_income',
            Carbon::parse('2026-09-15'),
            $this->shopUser,
        );
    }

    public function test_salary_payment_above_remaining_salary_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00', 'Asia/Kolkata'));

        // Earned: ~30000 (30 days). Request 30000.01.
        $this->expectException(ValidationException::class);

        $this->advanceService->recordShopSalaryPayment(
            $this->employee,
            $this->shop,
            30000.01,
            'sales_income',
            Carbon::parse('2026-09-30'),
            $this->shopUser,
        );
    }

    public function test_advance_on_last_day_of_month_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('last day of the month');

        // 2026-09-30 is the last day — advances must be rejected
        $this->advanceService->requestOrPayAdvance(
            $this->employee,
            $this->shop,
            2000.0,
            'petty_cash',
            Carbon::parse('2026-09-30'),
            $this->shopUser,
        );
    }

    public function test_closed_accounting_period_rolls_back_entire_payment_and_request(): void
    {
        ShopAccountingPeriodClosure::create([
            'shop_id' => $this->shop->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'closed_by' => $this->shopUser->id,
            'closed_at' => now(),
        ]);

        try {
            $this->advanceService->requestOrPayAdvance(
                $this->employee,
                $this->shop,
                1000.0,
                'sales_income',
                Carbon::parse('2026-09-15'),
                $this->shopUser,
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('paid_on', $e->errors());
        }

        // Atomicity assertion: No request, no payment, no cashbook line
        $this->assertDatabaseCount('employee_advance_requests', 0);
        $this->assertDatabaseCount('shop_staff_payments', 0);
        $this->assertDatabaseCount('shop_accounting_entry_lines', 0);
    }

    public function test_inactive_staff_salary_category_rolls_back_entire_salary_payment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00', 'Asia/Kolkata'));
        ShopAccountingCategory::where('name', 'Staff Salaries')->update(['is_active' => false]);

        try {
            $this->advanceService->recordShopSalaryPayment(
                $this->employee,
                $this->shop,
                1000.0,
                'sales_income',
                Carbon::parse('2026-09-30'),
                $this->shopUser,
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('category', $e->errors());
        }

        $this->assertDatabaseCount('shop_staff_payments', 0);
        $this->assertDatabaseCount('shop_accounting_entry_lines', 0);
    }
}
