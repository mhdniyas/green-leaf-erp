<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\PayrollPayment;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopCredit;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\ShopOwnerAssignment;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\HR\PayrollService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopStaffMoneyFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_hr_can_assign_employee_to_shop_and_close_previous_assignment(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = $this->shopEmployee();
        $oldShop = $this->ownedShop(['name' => 'Old Shop']);
        $newShop = $this->ownedShop(['name' => 'New Shop']);

        ShopEmployeeAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shop_id' => $oldShop->id,
            'assigned_by' => $admin->id,
            'effective_from' => '2026-07-01',
            'status' => 'active',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.staff.shop-assignments.store'), [
                'employee_id' => $employee->id,
                'shop_id' => $newShop->id,
                'effective_from' => '2026-07-17',
                'notes' => 'Moved for weekend coverage',
            ])
            ->assertRedirect(route('admin.staff.employees.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shop_employee_assignments', [
            'employee_id' => $employee->id,
            'shop_id' => $oldShop->id,
            'status' => 'inactive',
            'effective_to' => '2026-07-16',
        ]);
        $newAssignment = ShopEmployeeAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('shop_id', $newShop->id)
            ->firstOrFail();

        $this->assertSame('active', $newAssignment->status);
        $this->assertSame('2026-07-17', $newAssignment->effective_from?->toDateString());
        $this->assertSame($newShop->id, $employee->fresh()->default_shop_id);

        $this
            ->actingAs($admin)
            ->get(route('admin.staff.employees.index'))
            ->assertOk();
    }

    public function test_shop_owner_can_move_sales_to_petty_cash_and_pay_salary_from_petty_cash(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-17 10:00:00'));
        $this->seed(RolePermissionSeeder::class);

        $shop = $this->ownedShop();
        $shopOwner = $this->shopOwner($shop);
        $employee = $this->shopEmployee([
            'salary_type' => 'daily_wage',
            'daily_wage' => 1000,
            'monthly_salary' => 0,
        ]);
        ShopEmployeeAssignment::factory()->create([
            'shop_id' => $shop->id,
            'employee_id' => $employee->id,
            'assigned_by' => $shopOwner->id,
            'effective_from' => '2026-07-01',
            'status' => 'active',
        ]);
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'attendance_date' => '2026-07-17',
            'status' => 'present',
            'marked_by' => $shopOwner->id,
            'source' => 'owner',
        ]);

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.accounting.sales-to-petty-cash.store'), [
                'business_date' => '2026-07-17',
                'amount' => 1000,
                'description' => 'Cash kept from sales',
            ])
            ->assertRedirect(route('shop-owner.accounting.index', ['tab' => 'cashbook', 'date' => '2026-07-17']))
            ->assertSessionHas('success');

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.staff.salary-payments.store'), [
                'shop_id' => $shop->id,
                'employee_id' => $employee->id,
                'paid_on' => '2026-07-17',
                'amount' => 300,
                'fund_source' => 'petty_cash',
                'notes' => 'Daily wage part payment',
            ])
            ->assertRedirect(route('shop-owner.staff.index', ['shop' => $shop->code]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shop_credits', [
            'shop_id' => $shop->id,
            'is_petty_cash' => true,
            'amount' => 1000,
            'description' => 'Cash kept from sales',
        ]);
        $this->assertDatabaseHas('shop_staff_payments', [
            'shop_id' => $shop->id,
            'employee_id' => $employee->id,
            'amount' => 300,
            'fund_source' => 'petty_cash',
            'payment_type' => 'salary',
        ]);
        $this->assertDatabaseMissing('payroll_payments', [
            'shop_id' => $shop->id,
            'employee_id' => $employee->id,
            'amount' => 300,
        ]);

        $row = app(OwnedShopAccountingService::class)
            ->pettyCashRows($shop, Carbon::parse('2026-07-17'), Carbon::parse('2026-07-17'))
            ->first();

        $this->assertSame(1000.00, $row['admin_cash']);
        $this->assertSame(300.00, $row['payroll_expense']);
        $this->assertSame(700.00, $row['balance']);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $shop->code]))
            ->assertOk();

        Carbon::setTestNow();
    }

    public function test_advance_inside_rule_is_paid_and_above_rule_waits_for_admin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-21 10:00:00'));
        $this->seed(RolePermissionSeeder::class);

        EmployeeAdvanceRule::factory()->create([
            'minimum_present_days' => 20,
            'advance_percent' => 50,
            'is_active' => true,
        ]);
        $shop = $this->ownedShop();
        $shopOwner = $this->shopOwner($shop);
        $employee = $this->shopEmployee([
            'salary_type' => 'daily_wage',
            'daily_wage' => 1000,
            'monthly_salary' => 0,
        ]);
        ShopEmployeeAssignment::factory()->create([
            'shop_id' => $shop->id,
            'employee_id' => $employee->id,
            'assigned_by' => $shopOwner->id,
            'effective_from' => '2026-07-01',
            'status' => 'active',
        ]);

        foreach (range(1, 20) as $day) {
            EmployeeAttendance::factory()->create([
                'employee_id' => $employee->id,
                'shop_id' => $shop->id,
                'attendance_date' => Carbon::parse('2026-07-'.$day)->toDateString(),
                'status' => 'present',
                'marked_by' => $shopOwner->id,
                'source' => 'owner',
            ]);
        }

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.staff.advance-requests.store'), [
                'shop_id' => $shop->id,
                'employee_id' => $employee->id,
                'requested_on' => '2026-07-21',
                'amount' => 10000,
                'fund_source' => 'petty_cash',
                'request_note' => 'Allowed advance',
            ])
            ->assertRedirect(route('shop-owner.staff.index', ['shop' => $shop->code]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('employee_advance_requests', [
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'requested_amount' => 10000,
            'eligible_amount' => 10000,
            'status' => 'approved',
        ]);
        $this->assertSame(10000.00, (float) ShopStaffPayment::query()->where('payment_type', 'advance')->firstOrFail()->amount);
        $this->assertDatabaseCount('payroll_payments', 0);

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.staff.advance-requests.store'), [
                'shop_id' => $shop->id,
                'employee_id' => $employee->id,
                'requested_on' => '2026-07-21',
                'amount' => 12000,
                'fund_source' => 'petty_cash',
                'request_note' => 'Needs more',
            ])
            ->assertRedirect(route('shop-owner.staff.index', ['shop' => $shop->code]))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('employee_advance_requests', [
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'requested_amount' => 12000,
            'eligible_amount' => 10000,
            'status' => 'pending',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this
            ->actingAs($admin)
            ->get(route('admin.staff.payments.index', ['payroll_month' => '2026-07']))
            ->assertOk();

        Carbon::setTestNow();
    }

    public function test_office_salary_payment_and_contract_payment_post_journals(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = $this->shopEmployee(['monthly_salary' => 30000]);
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-17',
            'status' => 'present',
            'marked_by' => $admin->id,
            'source' => 'admin',
        ]);
        $payrollRun = app(PayrollService::class)->generate(
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
            (int) $admin->id,
        );
        $payrollRunItem = $payrollRun->items()->where('employee_id', $employee->id)->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.staff.payments.store'), [
                'payroll_run_item_id' => $payrollRunItem->id,
                'payment_type' => 'partial',
                'payment_method' => 'cash',
                'paid_on' => '2026-07-17',
                'amount' => 500,
                'notes' => 'Office cash salary',
            ])
            ->assertRedirect(route('admin.staff.payments.index', ['payroll_month' => '2026-07']))
            ->assertSessionHas('success');

        $officePayment = PayrollPayment::query()->firstOrFail();
        $this->assertNotNull($officePayment->journal_entry_id);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $officePayment->journal_entry_id,
            'source_type' => PayrollPayment::class,
            'source_event' => 'payment',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.staff.contract-worker-payments.store'), [
                'worker_name' => 'Contract Worker',
                'work_type' => 'Repair',
                'worked_on' => '2026-07-17',
                'paid_on' => '2026-07-17',
                'amount' => 750,
                'payment_method' => 'cash',
                'notes' => 'Repair work',
            ])
            ->assertRedirect(route('admin.staff.payments.index', ['payroll_month' => '2026-07']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('journal_entries', [
            'reference' => 'CONTRACT-PAY-1',
            'description' => 'Contract work payment to Contract Worker',
        ]);
    }

    public function test_owned_shop_bills_page_shows_daily_movement_graph(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->ownedShop(['code' => 'SHOP_CASIO']);
        $employee = $this->shopEmployee();
        $order = ShopOrder::query()->create([
            'shop_id' => $shop->id,
            'order_number' => 'SO-TEST-GRAPH',
            'state' => 'approved',
            'business_date' => '2026-07-17',
            'created_by' => $admin->id,
        ]);

        ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260717-SHOP_CASIO',
            'business_date' => '2026-07-17',
            'final_total' => 2500,
            'paid_amount' => 1800,
            'balance_amount' => 700,
        ]);
        ShopCredit::factory()->create([
            'shop_id' => $shop->id,
            'created_by' => $admin->id,
            'business_date' => '2026-07-17',
            'is_petty_cash' => true,
            'type' => 'in',
            'amount' => 500,
        ]);
        ShopStaffPayment::factory()->create([
            'payroll_run_id' => null,
            'payroll_run_item_id' => null,
            'shop_id' => $shop->id,
            'employee_id' => $employee->id,
            'paid_by' => $admin->id,
            'paid_on' => '2026-07-17',
            'amount' => 300,
            'payment_type' => 'salary',
            'fund_source' => 'petty_cash',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.owned-shops.show', [
                'shop' => $shop->code,
                'tab' => 'bills',
                'date' => '2026-07-17',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertSeeText('Sales Revenue')
            ->assertSeeText('Cash Flow Forecast')
            ->assertSeeText('Cost Segmentation')
            ->assertSeeText('Staff')
            ->assertSeeText('Rs. 300')
            ->assertSeeText('Current balance');
    }

    public function test_owned_shop_cashbook_shows_approval_on_top_cash_popup_and_sidebar_badge(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->ownedShop(['code' => 'SHOP_CASIO']);
        $category = ShopAccountingCategory::query()->create([
            'type' => 'income',
            'cash_effect' => true,
            'name' => 'Sales income',
            'is_active' => true,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-17',
            'status' => 'submitted',
            'created_by' => $admin->id,
            'submitted_by' => $admin->id,
            'submitted_at' => now(),
        ]);
        $entry->lines()->create([
            'shop_accounting_category_id' => $category->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 250,
            'description' => 'Daily income',
        ]);

        ShopInvoicePaymentRequest::factory()->count(7)->create(['status' => 'pending']);

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.owned-shops.show', [
                'shop' => $shop->code,
                'tab' => 'cashbook',
                'date' => '2026-07-17',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertSeeTextInOrder(['Admin Approval', 'Income', 'Expense', 'Net'])
            ->assertSeeText('Shop Cash Movement')
            ->assertSeeText('Add shop cash')
            ->assertDontSeeText('Add Shop Cash Movement')
            ->assertSee('rounded-full bg-red-600', false)
            ->assertSeeText('8');
    }

    public function test_approved_shop_entry_moves_to_approved_tab_and_is_read_only_for_shop_owner(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->ownedShop(['code' => 'SHOP_APPROVED']);
        $shopOwner = $this->shopOwner($shop);
        $category = ShopAccountingCategory::query()->create([
            'type' => 'income',
            'cash_effect' => true,
            'name' => 'Sales income',
            'is_active' => true,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-17',
            'status' => 'submitted',
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
        ]);
        $entry->lines()->create([
            'shop_accounting_category_id' => $category->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 1200,
            'description' => 'Approved sales',
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]), [
                'decision' => 'approve',
                'admin_note' => 'Approved and posted',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', [
                'shop' => $shop->code,
                'tab' => 'cashbook',
                'approval_tab' => 'approved',
                'date' => '2026-07-17',
            ]));

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.owned-shops.show', [
                'shop' => $shop->code,
                'tab' => 'cashbook',
                'approval_tab' => 'approved',
                'date' => '2026-07-17',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertSeeText('Approved')
            ->assertSeeText('This entry is now read-only')
            ->assertDontSeeText('Approve All Items');

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', [
                'tab' => 'cashbook',
                'ledger_status' => 'approved',
                'date' => '2026-07-17',
            ]))
            ->assertOk()
            ->assertSeeText('This day is already approved.')
            ->assertSeeText('Approved entries are read-only')
            ->assertSeeText('Approved sales')
            ->assertDontSeeText('Submit Updated Ledger Day');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ownedShop(array $attributes = []): Shop
    {
        return Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            ...$attributes,
        ]);
    }

    private function shopOwner(Shop $shop): User
    {
        $user = User::factory()->create(['shop_id' => $shop->id]);
        $user->assignRole('shop');
        ShopOwnerAssignment::factory()->create([
            'user_id' => $user->id,
            'shop_id' => $shop->id,
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function shopEmployee(array $attributes = []): Employee
    {
        $category = EmployeeCategory::factory()->create([
            'staff_area' => 'shop',
            'present_day_weight' => 1,
            'half_day_weight' => 0.5,
            'paid_leave_weight' => 1,
            'excess_leave_weight' => 0,
            'absent_day_weight' => 0,
        ]);

        return Employee::factory()->create([
            'employee_category_id' => $category->id,
            'staff_area' => 'shop',
            'employment_status' => 'active',
            ...$attributes,
        ]);
    }
}
