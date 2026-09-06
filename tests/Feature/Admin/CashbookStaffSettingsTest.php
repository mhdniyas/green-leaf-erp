<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\EmployeeAdvanceRule;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\Finance\OwnedShopAccountingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashbookStaffSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();

        $this->shop = Shop::query()->create([
            'name' => 'Grandcity Test Shop',
            'code' => 'GC_TEST',
            'warehouse_tag' => 'GC',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);
    }

    public function test_authorized_admin_can_view_and_update_staff_settings(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings'));
        $response->assertOk();
        $response->assertSeeText('Staff Salary & Advances');
        $response->assertSeeText('Salary Category Name');
        $response->assertSeeText('Advance Category Name');

        $updateResponse = $this->actingAs($this->admin)->post(route('admin.cashbook.settings.staff'), [
            'salary_category_name' => 'Base Salary Expense',
            'salary_category_active' => '1',
            'advance_category_name' => 'Employee Advance Payout',
            'advance_category_active' => '1',
            'default_fund_source' => 'sales_income',
            'advance_percent' => 50,
            'minimum_present_days' => 0,
        ]);

        $updateResponse->assertRedirect(route('admin.cashbook.settings'));
        $updateResponse->assertSessionHas('success');

        $this->assertDatabaseHas('shop_accounting_categories', [
            'shop_id' => null,
            'purpose' => 'staff_salary',
            'name' => 'Base Salary Expense',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('shop_accounting_categories', [
            'shop_id' => null,
            'purpose' => 'staff_advance',
            'name' => 'Employee Advance Payout',
            'is_active' => 1,
        ]);

        $rule = EmployeeAdvanceRule::activeRule();
        $this->assertEquals(50.0, (float) $rule->advance_percent);
        $this->assertEquals(0, $rule->minimum_present_days);
        $this->assertFalse($rule->default_from_petty_cash);
    }

    public function test_unauthorized_users_are_forbidden(): void
    {
        $this->get(route('admin.cashbook.settings'))->assertRedirect(route('login'));

        $this->actingAs($this->regularUser)
            ->get(route('admin.cashbook.settings'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->regularUser)
            ->post(route('admin.cashbook.settings.staff'), [
                'salary_category_name' => 'Hack Salary',
                'salary_category_active' => '1',
                'advance_category_name' => 'Hack Advance',
                'advance_category_active' => '1',
                'default_fund_source' => 'sales_income',
                'advance_percent' => 50,
                'minimum_present_days' => 0,
            ])
            ->assertForbidden();
    }

    public function test_global_category_applies_to_eligible_shops(): void
    {
        $this->actingAs($this->admin)->post(route('admin.cashbook.settings.staff'), [
            'salary_category_name' => 'Global Staff Salary',
            'salary_category_active' => '1',
            'advance_category_name' => 'Global Staff Advance',
            'advance_category_active' => '1',
            'default_fund_source' => 'petty_cash',
            'advance_percent' => 50,
            'minimum_present_days' => 0,
        ]);

        $employee = Employee::factory()->create();
        $payrollRun = PayrollRun::query()->create([
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);
        $payrollRunItem = PayrollRunItem::query()->create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $employee->id,
            'employee_category_id' => $employee->employee_category_id,
            'gross_salary' => 10000,
            'net_salary' => 10000,
            'final_payable_salary' => 10000,
            'rule_snapshot' => [],
        ]);

        $payment = ShopStaffPayment::query()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollRunItem->id,
            'employee_id' => $employee->id,
            'shop_id' => $this->shop->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-05',
            'amount' => 1000,
            'payment_type' => 'advance',
            'fund_source' => 'petty_cash',
            'status' => 'paid',
        ]);

        /** @var OwnedShopAccountingService $accountingService */
        $accountingService = app(OwnedShopAccountingService::class);
        $line = $accountingService->postShopStaffPaymentToCashbook($payment, (int) $this->admin->id);

        $this->assertEquals('Global Staff Advance', $line->category->name);
        $this->assertNull($line->category->shop_id);
    }

    public function test_existing_shop_override_wins(): void
    {
        // Set global categories
        $this->actingAs($this->admin)->post(route('admin.cashbook.settings.staff'), [
            'salary_category_name' => 'Global Salary',
            'salary_category_active' => '1',
            'advance_category_name' => 'Global Advance',
            'advance_category_active' => '1',
            'default_fund_source' => 'petty_cash',
            'advance_percent' => 50,
            'minimum_present_days' => 0,
        ]);

        // Create shop override for advance
        $this->actingAs($this->admin)->post(route('admin.cashbook.settings.staff'), [
            'shop_id' => $this->shop->id,
            'salary_category_name' => 'Shop Specific Salary',
            'salary_category_active' => '1',
            'advance_category_name' => 'Shop Specific Advance',
            'advance_category_active' => '1',
            'default_fund_source' => 'petty_cash',
            'advance_percent' => 50,
            'minimum_present_days' => 0,
        ]);

        $employee = Employee::factory()->create();
        $payrollRun = PayrollRun::query()->create([
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);
        $payrollRunItem = PayrollRunItem::query()->create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $employee->id,
            'employee_category_id' => $employee->employee_category_id,
            'gross_salary' => 10000,
            'net_salary' => 10000,
            'final_payable_salary' => 10000,
            'rule_snapshot' => [],
        ]);

        $payment = ShopStaffPayment::query()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollRunItem->id,
            'employee_id' => $employee->id,
            'shop_id' => $this->shop->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-05',
            'amount' => 1000,
            'payment_type' => 'advance',
            'fund_source' => 'petty_cash',
            'status' => 'paid',
        ]);

        /** @var OwnedShopAccountingService $accountingService */
        $accountingService = app(OwnedShopAccountingService::class);
        $line = $accountingService->postShopStaffPaymentToCashbook($payment, (int) $this->admin->id);

        $this->assertEquals('Shop Specific Advance', $line->category->name);
        $this->assertEquals($this->shop->id, $line->category->shop_id);
    }

    public function test_inactive_category_blocks_payment_with_clear_error(): void
    {
        // Deactivate global advance category
        ShopAccountingCategory::query()->updateOrCreate(
            ['shop_id' => null, 'purpose' => 'staff_advance'],
            ['name' => 'Disabled Advance', 'type' => 'expense', 'cash_effect' => true, 'is_active' => false]
        );

        $employee = Employee::factory()->create();
        $payrollRun = PayrollRun::query()->create([
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);
        $payrollRunItem = PayrollRunItem::query()->create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $employee->id,
            'employee_category_id' => $employee->employee_category_id,
            'gross_salary' => 10000,
            'net_salary' => 10000,
            'final_payable_salary' => 10000,
            'rule_snapshot' => [],
        ]);

        $payment = ShopStaffPayment::query()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollRunItem->id,
            'employee_id' => $employee->id,
            'shop_id' => $this->shop->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-05',
            'amount' => 1000,
            'payment_type' => 'advance',
            'fund_source' => 'petty_cash',
            'status' => 'paid',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("The salary advance category 'Disabled Advance' is inactive. Please activate it before recording payments.");

        /** @var OwnedShopAccountingService $accountingService */
        $accountingService = app(OwnedShopAccountingService::class);
        $accountingService->postShopStaffPaymentToCashbook($payment, (int) $this->admin->id);
    }

    public function test_historical_entries_remain_unchanged_and_settings_creates_no_cashbook_entry(): void
    {
        $oldCategory = ShopAccountingCategory::query()->create([
            'shop_id' => null,
            'purpose' => 'staff_advance',
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Original Advance Category',
            'is_active' => true,
        ]);

        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'created_by' => $this->admin->id,
        ]);

        $line = ShopAccountingEntryLine::query()->create([
            'shop_accounting_entry_id' => $entry->id,
            'shop_accounting_category_id' => $oldCategory->id,
            'type' => 'expense',
            'funding_source' => 'petty',
            'amount' => 500,
            'source_type' => ShopStaffPayment::class,
            'source_id' => 99999,
            'source_event' => 'staff_advance_paid',
        ]);

        $initialEntriesCount = ShopAccountingEntry::query()->count();
        $initialLinesCount = ShopAccountingEntryLine::query()->count();

        // Update settings
        $this->actingAs($this->admin)->post(route('admin.cashbook.settings.staff'), [
            'salary_category_name' => 'Renamed Salary',
            'salary_category_active' => '1',
            'advance_category_name' => 'Renamed Advance',
            'advance_category_active' => '1',
            'default_fund_source' => 'petty_cash',
            'advance_percent' => 50,
            'minimum_present_days' => 0,
        ]);

        // Historical line must keep its original category and data
        $line->refresh();
        $this->assertEquals($oldCategory->id, $line->shop_accounting_category_id);
        $this->assertEquals('petty', $line->funding_source);
        $this->assertEquals(500.0, (float) $line->amount);

        // Submitting settings created ZERO new cashbook entries or lines
        $this->assertEquals($initialEntriesCount, ShopAccountingEntry::query()->count());
        $this->assertEquals($initialLinesCount, ShopAccountingEntryLine::query()->count());
    }
}
