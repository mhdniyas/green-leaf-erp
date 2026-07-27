<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\JournalEntry;
use App\Models\PayrollPayment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
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
            ->assertRedirect(route('admin.staff.assignments.index'))
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

    public function test_hr_assignment_page_shows_shop_owner_daily_details_and_assignment_action(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->ownedShop([
            'code' => 'OWN-101',
            'name' => 'Central Owned Shop',
        ]);
        $owner = $this->shopOwner($shop);
        $employee = $this->shopEmployee([
            'name' => 'Assigned Staff',
            'employee_code' => 'EMP-100',
        ]);
        $availableEmployee = $this->shopEmployee([
            'name' => 'Available Staff',
            'employee_code' => 'EMP-101',
        ]);

        ShopEmployeeAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'assigned_by' => $admin->id,
            'effective_from' => '2026-07-18',
            'status' => 'active',
        ]);
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'attendance_date' => '2026-07-18',
            'status' => 'present',
            'source' => 'admin',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.staff.assignments.index', ['date' => '2026-07-18']))
            ->assertOk()
            ->assertSee('Assign Employees')
            ->assertSee('Central Owned Shop')
            ->assertSee($owner->name)
            ->assertSee('Assigned Staff')
            ->assertSee('Available Staff')
            ->assertSee('1 assigned')
            ->assertSee('Assign to Central Owned Shop')
            ->assertSee('data-employee-dropdown', false)
            ->assertSee('Search employee, code, shop')
            ->assertSee('Shop Employees')
            ->assertSee('Office')
            ->assertSee('value="shop"', false)
            ->assertSee('checked', false);

        $this->assertTrue($availableEmployee->exists);
    }

    public function test_hr_cannot_assign_employee_to_non_owned_staff_shop(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = $this->shopEmployee();
        $regularShop = Shop::factory()->create([
            'accounting_enabled' => false,
            'accounting_mode' => 'standard',
            'name' => 'Regular Retail Shop',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.staff.shop-assignments.store'), [
                'employee_id' => $employee->id,
                'shop_id' => $regularShop->id,
                'effective_from' => '2026-07-18',
                'notes' => 'Invalid target',
            ])
            ->assertSessionHasErrors('shop_id');

        $this->assertDatabaseMissing('shop_employee_assignments', [
            'employee_id' => $employee->id,
            'shop_id' => $regularShop->id,
        ]);
    }

    public function test_owned_shop_index_shows_latest_closing_balance_instead_of_daily_balance(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->ownedShop(['name' => 'Ashirwad']);

        ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-16',
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 999,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-17',
            'status' => 'draft',
            'opening_cash' => 999,
            'closing_cash' => -121223,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.owned-shops.index'))
            ->assertOk()
            ->assertSeeText('Closing Balance')
            ->assertSeeText('- Rs. 121,223.00')
            ->assertSeeText('17 Jul 2026')
            ->assertDontSeeText('Daily Balance');
    }

    public function test_shop_owner_can_submit_daily_receipt_and_pay_salary_from_shop_cash(): void
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

        ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-16',
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 250,
            'created_by' => $shopOwner->id,
        ]);

        $cashSales = ShopAccountingCategory::query()->create([
            'type' => 'income',
            'cash_effect' => true,
            'name' => 'Cash Sales',
            'is_active' => true,
        ]);

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.accounting.entries.store'), [
                'business_date' => '2026-07-17',
                'submission_action' => 'submit',
                'notes' => 'Cash kept in shop balance.',
                'lines' => [
                    [
                        'shop_accounting_category_id' => $cashSales->id,
                        'amount' => 1000,
                        'description' => 'Cash sales retained in closing balance',
                    ],
                ],
            ])
            ->assertRedirect(route('shop-owner.accounting.index', ['tab' => 'cashbook', 'ledger_status' => 'submitted', 'date' => '2026-07-17']))
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
            ->assertRedirect(route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'salary']))
            ->assertSessionHas('success');

        $entry = ShopAccountingEntry::query()
            ->with('lines.category')
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '2026-07-17')
            ->firstOrFail();

        $this->assertSame('250.00', $entry->opening_cash);
        $this->assertSame('950.00', $entry->closing_cash);
        $this->assertSame('submitted', $entry->status);
        $staffSalaryLine = $entry->lines->first(
            fn (ShopAccountingEntryLine $line): bool => $line->source_type === ShopStaffPayment::class
        );
        $this->assertNotNull($staffSalaryLine);
        $this->assertSame('Staff Salary', $staffSalaryLine->category?->name);
        $this->assertSame('300.00', $staffSalaryLine->amount);
        $this->assertSame('staff_salary', $staffSalaryLine->source_event);
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

        $summary = app(OwnedShopAccountingService::class)->receiptSummary($entry);

        $this->assertSame(250.00, $summary['opening_balance']);
        $this->assertSame(1000.00, $summary['cash_credit']);
        $this->assertSame(300.00, $summary['cash_debit']);
        $this->assertSame(950.00, $summary['entered_closing']);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $shop->code]))
            ->assertOk()
            ->assertSee('Attendance')
            ->assertSee('Advance')
            ->assertSee('Salary')
            ->assertSee('Leave')
            ->assertSee('History');

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
            ->assertRedirect(route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'advance']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('employee_advance_requests', [
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'requested_amount' => 10000,
            'eligible_amount' => 10000,
            'status' => 'approved',
        ]);
        $autoAdvancePayment = ShopStaffPayment::query()->where('payment_type', 'advance')->firstOrFail();
        $this->assertSame(10000.00, (float) $autoAdvancePayment->amount);
        $this->assertDatabaseHas('shop_accounting_entry_lines', [
            'source_type' => ShopStaffPayment::class,
            'source_id' => $autoAdvancePayment->id,
            'source_event' => 'staff_advance',
            'amount' => 10000,
        ]);
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
            ->assertRedirect(route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'advance']))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('employee_advance_requests', [
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'requested_amount' => 12000,
            'eligible_amount' => 10000,
            'status' => 'pending',
        ]);
        $this->assertSame(10000.00, (float) EmployeeAdvanceRequest::query()->where('status', 'pending')->firstOrFail()->rule_snapshot['already_advanced_amount']);
        $this->assertSame(1, ShopAccountingEntryLine::query()
            ->where('source_type', ShopStaffPayment::class)
            ->where('source_event', 'staff_advance')
            ->count());

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this
            ->actingAs($admin)
            ->get(route('admin.staff.advance-payments.index', ['payroll_month' => '2026-07']))
            ->assertOk()
            ->assertSee('Advance Payments')
            ->assertSee('Needs more');

        $pendingAdvance = EmployeeAdvanceRequest::query()->where('status', 'pending')->firstOrFail();

        $this
            ->actingAs($admin)
            ->patch(route('admin.staff.advance-requests.review', $pendingAdvance), [
                'decision' => 'approve',
                'approved_amount' => 5000,
                'review_note' => 'Approved partial extra advance',
            ])
            ->assertRedirect(route('admin.staff.advance-payments.index', ['payroll_month' => '2026-07']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('employee_advance_requests', [
            'id' => $pendingAdvance->id,
            'status' => 'approved',
            'approved_amount' => 5000,
        ]);
        $this->assertSame(15000.00, (float) ShopStaffPayment::query()->where('payment_type', 'advance')->sum('amount'));
        $this->assertSame(2, ShopAccountingEntryLine::query()
            ->where('source_type', ShopStaffPayment::class)
            ->where('source_event', 'staff_advance')
            ->count());
        $this->assertSame(-15000.00, app(OwnedShopAccountingService::class)->closingBalanceForDate($shop, Carbon::parse('2026-07-21')));

        $payrollRunItem = app(PayrollService::class)
            ->ensurePayrollRunItem($employee, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'), $admin->id)
            ->fresh(['payments', 'shopStaffPayments']);
        $this->assertSame(5000.00, $payrollRunItem->remainingAmount());

        Carbon::setTestNow();
    }

    public function test_linked_shop_owner_can_check_in_without_staff_assignment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-21 10:00:00'));
        $this->seed(RolePermissionSeeder::class);

        $shop = $this->ownedShop();
        $shopOwner = $this->shopOwner($shop);
        $ownerEmployee = $shopOwner->employee()->first() ?? $this->shopEmployee([
            'name' => 'Owner Employee',
            'user_id' => $shopOwner->id,
            'staff_area' => 'office',
        ]);

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $ownerEmployee->id,
                'attendance_date' => '2026-07-21',
                'shop_id' => $shop->id,
                'status' => 'present',
            ])
            ->assertRedirect(route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'attendance']))
            ->assertSessionHas('success');

        $this->assertTrue(EmployeeAttendance::query()
            ->where('employee_id', $ownerEmployee->id)
            ->where('shop_id', $shop->id)
            ->whereDate('attendance_date', '2026-07-21')
            ->where('status', 'present')
            ->where('source', 'owner')
            ->exists());

        Carbon::setTestNow(Carbon::parse('2026-07-21 11:15:00'));

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $ownerEmployee->id,
                'attendance_date' => '2026-07-21',
                'shop_id' => $shop->id,
                'status' => 'half_day',
                'notes' => 'Updated to half day',
            ])
            ->assertRedirect(route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'attendance']));

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'attendance', 'date' => '2026-07-21']))
            ->assertOk()
            ->assertSeeText('Checked in 10:00 AM')
            ->assertSeeText('Latest mark 11:15 AM')
            ->assertSeeText('Changed 11:15 AM')
            ->assertSeeText('Update Check-In');

        Carbon::setTestNow();
    }

    public function test_shop_owner_staff_attendance_can_be_updated_with_json_response(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-25 09:35:00'));
        $this->seed(RolePermissionSeeder::class);

        $shop = $this->ownedShop();
        $shopOwner = $this->shopOwner($shop);
        $employee = $this->shopEmployee(['name' => 'Quick Staff']);
        ShopEmployeeAssignment::factory()->create([
            'shop_id' => $shop->id,
            'employee_id' => $employee->id,
            'assigned_by' => $shopOwner->id,
            'effective_from' => '2026-07-01',
            'status' => 'active',
        ]);

        $this
            ->actingAs($shopOwner)
            ->postJson(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $employee->id,
                'attendance_date' => '2026-07-25',
                'shop_id' => $shop->id,
                'status' => 'present',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Attendance updated for today.')
            ->assertJsonPath('attendance.employee_id', $employee->id)
            ->assertJsonPath('attendance.status', 'present')
            ->assertJsonPath('attendance.status_label', 'checked in')
            ->assertJsonPath('attendance.button_label', 'Update Check-In');

        $this->assertTrue(EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->where('shop_id', $shop->id)
            ->whereDate('attendance_date', '2026-07-25')
            ->where('status', 'present')
            ->where('source', 'owner')
            ->exists());

        Carbon::setTestNow();
    }

    public function test_unmarked_shop_staff_attendance_defaults_to_full_day_selection(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-25 09:35:00'));
        $this->seed(RolePermissionSeeder::class);

        $shop = $this->ownedShop();
        $shopOwner = $this->shopOwner($shop);
        $employee = $this->shopEmployee(['name' => 'Default Full Day Staff']);
        ShopEmployeeAssignment::factory()->create([
            'shop_id' => $shop->id,
            'employee_id' => $employee->id,
            'assigned_by' => $shopOwner->id,
            'effective_from' => '2026-07-01',
            'status' => 'active',
        ]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'attendance', 'date' => '2026-07-25']))
            ->assertOk()
            ->assertSeeText('Default Full Day Staff')
            ->assertSee('<option value="present" selected>Full Day</option>', false);

        Carbon::setTestNow();
    }

    public function test_unlinked_shop_owner_staff_page_shows_hr_linking_message(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-21 10:00:00'));
        $this->seed(RolePermissionSeeder::class);

        $shop = $this->ownedShop();
        $shopOwner = $this->shopOwner($shop);
        Employee::query()->where('user_id', $shopOwner->id)->update(['user_id' => null]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('HR needs to link your user account to an employee profile before owner check-in is available.');

        Carbon::setTestNow();
    }

    public function test_owned_shop_advance_tab_includes_previous_shop_staff_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-21 10:00:00'));
        $this->seed(RolePermissionSeeder::class);

        EmployeeAdvanceRule::factory()->create([
            'minimum_present_days' => 1,
            'advance_percent' => 50,
            'is_active' => true,
        ]);
        $shop = $this->ownedShop();
        $otherShop = $this->ownedShop(['name' => 'Other Owned Shop']);
        $shopOwner = $this->shopOwner($shop);
        $previousEmployee = $this->shopEmployee(['name' => 'Previous Shop Staff']);
        $unrelatedEmployee = $this->shopEmployee(['name' => 'Unrelated Shop Staff']);

        ShopEmployeeAssignment::factory()->create([
            'shop_id' => $shop->id,
            'employee_id' => $previousEmployee->id,
            'assigned_by' => $shopOwner->id,
            'effective_from' => '2026-07-01',
            'effective_to' => '2026-07-10',
            'status' => 'inactive',
        ]);
        ShopEmployeeAssignment::factory()->create([
            'shop_id' => $otherShop->id,
            'employee_id' => $unrelatedEmployee->id,
            'assigned_by' => $shopOwner->id,
            'effective_from' => '2026-07-01',
            'status' => 'active',
        ]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'advance', 'date' => '2026-07-21']))
            ->assertOk()
            ->assertSee('Previous Shop Staff')
            ->assertDontSee('Unrelated Shop Staff');

        Carbon::setTestNow();
    }

    public function test_shop_staff_payment_on_approved_cashbook_posts_adjustment_expense(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-21 10:00:00'));
        $this->seed(RolePermissionSeeder::class);

        EmployeeAdvanceRule::factory()->create([
            'minimum_present_days' => 1,
            'advance_percent' => 100,
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
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'attendance_date' => '2026-07-21',
            'status' => 'present',
            'marked_by' => $shopOwner->id,
            'source' => 'owner',
        ]);
        $cashSales = ShopAccountingCategory::query()->create([
            'type' => 'income',
            'cash_effect' => true,
            'name' => 'Approved Day Sales',
            'is_active' => true,
        ]);
        $approvedEntry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-21',
            'entry_type' => ShopAccountingEntry::TypeDaily,
            'daily_entry_key' => ShopAccountingEntry::dailyEntryKey($shop->id, '2026-07-21'),
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 1000,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => now(),
        ]);
        $approvedEntry->lines()->create([
            'shop_accounting_category_id' => $cashSales->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 1000,
            'description' => 'Approved cash sales',
        ]);

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.staff.advance-requests.store'), [
                'shop_id' => $shop->id,
                'employee_id' => $employee->id,
                'requested_on' => '2026-07-21',
                'amount' => 500,
                'request_note' => 'Cash taken from shop counter',
            ])
            ->assertRedirect(route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'advance']))
            ->assertSessionHas('success');

        $this->assertTrue(ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '2026-07-21')
            ->where('entry_type', ShopAccountingEntry::TypeAdjustment)
            ->where('status', 'submitted')
            ->exists());
        $this->assertSame('approved', ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->where('entry_type', ShopAccountingEntry::TypeDaily)
            ->firstOrFail()
            ->status);
        $this->assertSame(500.00, app(OwnedShopAccountingService::class)->closingBalanceForDate($shop, Carbon::parse('2026-07-21')));

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

        $shop = $this->ownedShop(['code' => 'SHOP_HR_ADV']);
        $this
            ->actingAs($admin)
            ->post(route('admin.staff.shop-staff-payments.store'), [
                'payroll_run_item_id' => $payrollRunItem->id,
                'shop_id' => $shop->id,
                'payment_type' => 'advance',
                'paid_on' => '2026-07-17',
                'amount' => 100,
                'notes' => 'Employee took cash from shop',
            ])
            ->assertRedirect(route('admin.staff.payments.index', ['payroll_month' => '2026-07']))
            ->assertSessionHas('success');

        $shopPayment = ShopStaffPayment::query()
            ->where('shop_id', $shop->id)
            ->where('payment_type', 'advance')
            ->firstOrFail();
        $this->assertDatabaseHas('shop_accounting_entry_lines', [
            'source_type' => ShopStaffPayment::class,
            'source_id' => $shopPayment->id,
            'source_event' => 'staff_advance',
            'amount' => 100,
        ]);
        $this->assertSame(100.00, $payrollRunItem->fresh(['payments', 'shopStaffPayments'])->shopPaidAmount());
    }

    public function test_salary_payable_is_split_between_green_leaf_and_client_shop_attendance(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->ownedShop(['code' => 'SHOP_SPLIT_PAY']);
        $shopOwner = $this->shopOwner($shop);
        $employee = $this->shopEmployee([
            'monthly_salary' => 31000,
            'salary_type' => 'monthly',
        ]);
        ShopEmployeeAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'assigned_by' => $admin->id,
            'effective_from' => '2026-07-01',
            'status' => 'active',
        ]);
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-01',
            'status' => 'present',
            'shop_id' => null,
            'source' => 'admin',
            'marked_by' => $admin->id,
        ]);
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-02',
            'status' => 'present',
            'shop_id' => $shop->id,
            'source' => 'owner',
            'marked_by' => $shopOwner->id,
        ]);

        $payrollRun = app(PayrollService::class)->generate(
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
            $admin->id,
        );
        $payrollRunItem = $payrollRun->items()->where('employee_id', $employee->id)->firstOrFail()->fresh(['payments', 'shopStaffPayments']);

        $this->assertSame('1000.00', $payrollRun->fresh()->gross_amount);
        $this->assertSame(1000.00, $payrollRunItem->greenLeafPayableAmount());
        $this->assertSame(1000.00, $payrollRunItem->clientShopPayableAmount());
        $this->assertSame(1000.00, $payrollRunItem->remainingGreenLeafAmount());
        $this->assertSame(1000.00, app(PayrollService::class)->payableForAttendance(
            $employee,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
            $shop->id,
        )['amount']);

        $this
            ->actingAs($admin)
            ->post(route('admin.staff.payments.store'), [
                'payroll_run_item_id' => $payrollRunItem->id,
                'payment_type' => 'full',
                'payment_method' => 'cash',
                'paid_on' => '2026-07-03',
                'amount' => 1000,
            ])
            ->assertSessionHas('success');

        $this
            ->actingAs($admin)
            ->from(route('admin.staff.payments.index', ['payroll_month' => '2026-07']))
            ->post(route('admin.staff.payments.store'), [
                'payroll_run_item_id' => $payrollRunItem->id,
                'payment_type' => 'partial',
                'payment_method' => 'cash',
                'paid_on' => '2026-07-03',
                'amount' => 100,
            ])
            ->assertRedirect(route('admin.staff.payments.index', ['payroll_month' => '2026-07']))
            ->assertSessionHasErrors('amount');

        $refreshedItem = app(PayrollService::class)->ensurePayrollRunItem(
            $employee,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
            $shopOwner->id,
        );
        $this->assertSame(1000.00, $refreshedItem->fresh()->clientShopPayableAmount());
        $this->assertSame(0.00, (float) $refreshedItem->shopStaffPayments()->where('shop_id', $shop->id)->sum('amount'));

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.staff.salary-payments.store'), [
                'shop_id' => $shop->id,
                'employee_id' => $employee->id,
                'paid_on' => '2026-07-03',
                'amount' => 1000,
                'fund_source' => 'sales_income',
                'notes' => 'Shop day salary',
            ])
            ->assertSessionHas('success');

        $this
            ->actingAs($shopOwner)
            ->from(route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'salary']))
            ->post(route('shop-owner.staff.salary-payments.store'), [
                'shop_id' => $shop->id,
                'employee_id' => $employee->id,
                'paid_on' => '2026-07-03',
                'amount' => 100,
                'fund_source' => 'sales_income',
            ])
            ->assertRedirect(route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'salary']))
            ->assertSessionHasErrors('amount');

        $payrollRunItem = $payrollRunItem->fresh(['payments', 'shopStaffPayments']);
        $this->assertSame(1000.00, $payrollRunItem->officePaidAmount());
        $this->assertSame(1000.00, $payrollRunItem->shopPaidAmount());
        $this->assertSame(0.00, $payrollRunItem->remainingGreenLeafAmount());
        $this->assertSame(0.00, $payrollRunItem->remainingClientShopAmount($shop->id));
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
            ->assertSeeText('Daily Receipt Balance')
            ->assertSeeText('Cost Segmentation')
            ->assertSeeText('Staff')
            ->assertSeeText('Rs. 300')
            ->assertSeeText('Daily Balance');
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
            ->assertSeeText('Green Leaf Loan')
            ->assertSeeText('Add loan')
            ->assertSeeText('Loan given to client shop')
            ->assertSeeText('Green Leaf view: cash out. Client dashboard: loan balance increases.')
            ->assertSeeText('Advance Loan for Salary')
            ->assertDontSeeText('Cash Received from Shop - Income')
            ->assertDontSeeText('Daily Balance Movement')
            ->assertDontSeeText('Add Shop Cash Movement')
            ->assertDontSeeText('Add category')
            ->assertSeeText('Admin Approval')
            ->assertSeeText('7');
    }

    public function test_admin_shop_cash_credit_updates_stored_daily_closing_balance(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->ownedShop(['code' => 'SHOP_CASH_SYNC']);
        ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-16',
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 500,
            'created_by' => $admin->id,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-17',
            'status' => 'draft',
            'opening_cash' => 500,
            'closing_cash' => 500,
            'created_by' => $admin->id,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.accounting.owned-shops.credits.store', ['shop' => $shop]), [
                'amount' => 200000,
                'business_date' => '2026-07-17',
                'description' => 'Shop working cash for weekend',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', [
                'shop' => $shop->code,
                'tab' => 'cashbook',
                'date' => '2026-07-17',
            ]))
            ->assertSessionHas('success');

        $entry->refresh();

        $this->assertSame('500.00', $entry->opening_cash);
        $this->assertSame('200500.00', $entry->closing_cash);
        $this->assertDatabaseHas('shop_credits', [
            'shop_id' => $shop->id,
            'type' => 'in',
            'is_petty_cash' => true,
            'amount' => 200000,
            'description' => 'Shop working cash for weekend',
        ]);

        $newDayShop = $this->ownedShop(['code' => 'SHOP_CASH_SYNC_NEW']);

        $this
            ->actingAs($admin)
            ->post(route('admin.accounting.owned-shops.credits.store', ['shop' => $newDayShop]), [
                'amount' => 1000,
                'business_date' => '2026-07-17',
            ])
            ->assertSessionHas('success');

        $createdEntry = ShopAccountingEntry::query()
            ->where('shop_id', $newDayShop->id)
            ->whereDate('business_date', '2026-07-17')
            ->firstOrFail();

        $this->assertSame('draft', $createdEntry->status);
        $this->assertSame('0.00', $createdEntry->opening_cash);
        $this->assertSame('1000.00', $createdEntry->closing_cash);
    }

    public function test_admin_daily_ledger_update_is_submitted_for_approval(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->ownedShop(['code' => 'SHOP_LEDGER_ADMIN']);
        $category = ShopAccountingCategory::query()->create([
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Cash Purchase',
            'is_active' => true,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-17',
            'status' => 'draft',
            'opening_cash' => 0,
            'closing_cash' => -121223,
            'created_by' => $admin->id,
        ]);
        $entry->lines()->create([
            'shop_accounting_category_id' => $category->id,
            'type' => 'expense',
            'cash_effect' => true,
            'amount' => 121223,
            'description' => 'Large cash purchase',
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.owned-shops.entries.update', ['shop' => $shop, 'entry' => $entry]), [
                'business_date' => '2026-07-17',
                'status' => 'submitted',
                'opening_cash' => 0,
                'closing_cash' => -121223,
                'lines' => [
                    [
                        'shop_accounting_category_id' => $category->id,
                        'amount' => 121223,
                        'description' => 'Large cash purchase',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', [
                'shop' => $shop->code,
                'tab' => 'cashbook',
                'approval_tab' => 'pending',
                'date' => '2026-07-17',
            ]));

        $entry->refresh();

        $this->assertSame('submitted', $entry->status);
        $this->assertSame($admin->id, $entry->submitted_by);
        $this->assertNotNull($entry->submitted_at);

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.owned-shops.show', [
                'shop' => $shop->code,
                'tab' => 'cashbook',
                'approval_tab' => 'pending',
                'date' => '2026-07-17',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertSeeText('Review submitted shop entry')
            ->assertSeeText('Rs. 121,223.00')
            ->assertSeeText('Large cash purchase');
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
            ->assertSeeText('Approved sales')
            ->assertSeeText('Rs. 1,200.00')
            ->assertDontSeeText('Not entered')
            ->assertDontSeeText('Submit Updated Ledger Day');

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', [
                'tab' => 'create',
                'date' => '2026-07-17',
            ]))
            ->assertOk()
            ->assertSeeText('This day is already approved.')
            ->assertSeeText('Approved entries are read-only');
    }

    public function test_admin_pending_receipt_list_shows_inline_approve_for_adjustment_entry(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->ownedShop(['code' => 'SHOP_INLINE_APPROVE']);
        $shopOwner = $this->shopOwner($shop);
        $category = ShopAccountingCategory::query()->create([
            'type' => 'expense',
            'cash_effect' => true,
            'purpose' => 'staff_advance',
            'name' => 'Staff Advance',
            'is_active' => true,
        ]);

        ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-25',
            'entry_type' => ShopAccountingEntry::TypeDaily,
            'status' => 'draft',
            'created_by' => $shopOwner->id,
        ]);

        $adjustment = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-25',
            'entry_type' => ShopAccountingEntry::TypeAdjustment,
            'status' => 'submitted',
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
        ]);
        $adjustment->lines()->create([
            'shop_accounting_category_id' => $category->id,
            'type' => 'expense',
            'cash_effect' => true,
            'amount' => 4000,
            'description' => 'Staff advance paid from shop cashbook',
        ]);

        $reviewUrl = route('admin.accounting.owned-shops.entries.review', [
            'shop' => $shop,
            'entry' => $adjustment,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.owned-shops.show', [
                'shop' => $shop->code,
                'tab' => 'cashbook',
                'approval_tab' => 'pending',
                'date' => '2026-07-25',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertSeeText('Daily Shop Receipt workflow')
            ->assertSeeText('Expense Rs. 4,000.00')
            ->assertSee($reviewUrl, false)
            ->assertSee('name="decision" value="approve"', false)
            ->assertSeeText('Approve');

        $this
            ->actingAs($admin)
            ->patch($reviewUrl, [
                'decision' => 'approve',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', [
                'shop' => $shop->code,
                'tab' => 'cashbook',
                'approval_tab' => 'approved',
                'date' => '2026-07-25',
            ]));

        $this->assertSame('approved', $adjustment->fresh()->status);
    }

    public function test_approved_shop_receipt_tracks_cashbook_without_posting_journal(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->ownedShop(['code' => 'SHOP_JOURNAL_RULE', 'name' => 'Ashirwad']);
        $shopOwner = $this->shopOwner($shop);
        $category = ShopAccountingCategory::query()->create([
            'type' => 'income',
            'cash_effect' => true,
            'purpose' => 'sales_cash',
            'name' => 'Sales Income - Cash',
            'is_active' => true,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-18',
            'status' => 'submitted',
            'opening_cash' => 126555,
            'closing_cash' => 426555,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
        ]);
        $line = $entry->lines()->create([
            'shop_accounting_category_id' => $category->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 300000,
            'description' => 'Cash sales for the day',
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]), [
                'decision' => 'approve',
                'admin_note' => 'Approved cash sales',
            ])
            ->assertSessionHas('success');

        $this->assertSame('approved', $entry->fresh()->status);
        $this->assertSame('approved', $line->fresh()->review_status);
        $this->assertSame(0, JournalEntry::query()
            ->where('source_type', ShopAccountingEntryLine::class)
            ->where('source_id', $line->id)
            ->where('source_event', 'shop_accounting_line_approved')
            ->count());

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]), [
                'decision' => 'approve',
                'admin_note' => 'Approved again',
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, JournalEntry::query()
            ->where('source_type', ShopAccountingEntryLine::class)
            ->where('source_id', $line->id)
            ->where('source_event', 'shop_accounting_line_approved')
            ->count());
    }

    public function test_client_shop_and_regular_shop_finance_use_invoice_payment_requests(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $ownedShop = $this->ownedShop(['code' => 'SHOP_FIN_PAY']);
        $shopOwner = $this->shopOwner($ownedShop);
        $salesCategory = ShopAccountingCategory::query()->create([
            'type' => 'income',
            'cash_effect' => true,
            'purpose' => 'sales_cash',
            'name' => 'Sales Income - Cash',
            'is_active' => true,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $ownedShop->id,
            'business_date' => '2026-07-18',
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 5000,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => now(),
        ]);
        $entry->lines()->create([
            'shop_accounting_category_id' => $salesCategory->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 5000,
            'description' => 'Cash sales',
            'review_status' => 'approved',
        ]);
        $order = ShopOrder::query()->create([
            'shop_id' => $ownedShop->id,
            'state' => 'approved',
            'business_date' => '2026-07-18',
            'created_by' => $shopOwner->id,
        ]);
        $invoice = ShopInvoice::query()->create([
            'shop_id' => $ownedShop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-FIN-PAY-001',
            'business_date' => '2026-07-18',
            'status' => 'generated',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'subtotal' => 4000,
            'final_total' => 4000,
            'paid_amount' => 500,
            'balance_amount' => 3500,
            'generated_by' => $shopOwner->id,
        ]);
        $pendingOrder = ShopOrder::query()->create([
            'shop_id' => $ownedShop->id,
            'state' => 'approved',
            'business_date' => '2026-07-17',
            'created_by' => $shopOwner->id,
        ]);
        ShopInvoice::query()->create([
            'shop_id' => $ownedShop->id,
            'shop_order_id' => $pendingOrder->id,
            'invoice_number' => 'SINV-FIN-PENDING',
            'business_date' => '2026-07-17',
            'status' => 'delivery_review',
            'delivery_status' => 'received_with_discrepancy',
            'payment_status' => 'unpaid',
            'subtotal' => 600,
            'final_total' => 600,
            'paid_amount' => 0,
            'balance_amount' => 600,
            'generated_by' => $shopOwner->id,
        ]);

        $this->assertSame(1000.00, app(OwnedShopAccountingService::class)->closingBalanceForDate($ownedShop, Carbon::parse('2026-07-18')));

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.finance.index', ['tab' => 'payments']))
            ->assertOk()
            ->assertSeeText('Payments')
            ->assertSeeText('Green Leaf Invoice Payments')
            ->assertSeeText('Submit bill payment for approval')
            ->assertSeeText('Rs. 4,100.00')
            ->assertSeeText('SINV-FIN-PENDING')
            ->assertSeeText('SINV-FIN-PAY-001');

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.accounting.payment-requests.store'), [
                'invoice_id' => $invoice->id,
                'amount_mode' => 'custom',
                'amount' => 700,
                'shop_note' => 'Cash paid to office',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shop_invoice_payment_requests', [
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $ownedShop->id,
            'requested_amount' => 700,
            'status' => 'pending',
            'shop_note' => 'Cash paid to office',
        ]);
        $this->assertSame(0, ShopCredit::query()
            ->where('shop_id', $ownedShop->id)
            ->where('type', 'out')
            ->count());
        $this->assertSame(1000.00, app(OwnedShopAccountingService::class)->closingBalanceForDate($ownedShop, Carbon::parse('2026-07-18')));
        $this->assertSame(0, JournalEntry::query()->where('source_type', ShopInvoice::class)->count());

        $regularShop = Shop::factory()->create([
            'accounting_enabled' => false,
            'accounting_mode' => 'regular',
        ]);
        $regularShopOwner = User::factory()->create(['shop_id' => $regularShop->id]);
        $regularShopOwner->assignRole('shop');
        $regularInvoice = ShopInvoice::factory()->create([
            'shop_id' => $regularShop->id,
            'business_date' => '2026-07-18',
            'subtotal' => 900,
            'final_total' => 900,
            'paid_amount' => 0,
            'balance_amount' => 900,
            'payment_status' => 'unpaid',
        ]);

        $this
            ->actingAs($regularShopOwner)
            ->get(route('shop-owner.finance.index', ['tab' => 'payments']))
            ->assertOk()
            ->assertSeeText('Submit bill payment for approval')
            ->assertSeeText($regularInvoice->invoice_number);

        $this
            ->actingAs($regularShopOwner)
            ->post(route('shop-owner.accounting.payment-requests.store'), [
                'invoice_id' => $regularInvoice->id,
                'amount_mode' => 'custom',
                'amount' => 200,
                'shop_note' => 'Cash paid at office',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shop_invoice_payment_requests', [
            'shop_invoice_id' => $regularInvoice->id,
            'shop_id' => $regularShop->id,
            'requested_amount' => 200,
            'status' => 'pending',
            'shop_note' => 'Cash paid at office',
        ]);
        $this->assertSame(0, JournalEntry::query()->where('source_type', ShopInvoice::class)->where('source_id', $regularInvoice->id)->count());

        $paymentRequest = ShopInvoicePaymentRequest::query()
            ->where('shop_invoice_id', $regularInvoice->id)
            ->firstOrFail();

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.shop-invoice-payment-requests.review', $paymentRequest), [
                'decision' => 'approve',
                'admin_note' => 'Verified cash received',
            ])
            ->assertSessionHas('success');

        $regularInvoice->refresh();

        $this->assertSame('paid', $regularInvoice->payment_status);
        $this->assertSame('200.00', $regularInvoice->paid_amount);
        $this->assertSame('0.00', $regularInvoice->balance_amount);
        $this->assertSame(1, JournalEntry::query()->where('source_type', ShopInvoice::class)->where('source_id', $regularInvoice->id)->count());
    }

    public function test_admin_client_shop_daily_bill_payment_posts_shop_invoice_journal(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->ownedShop(['code' => 'SHOP_NO_IN']);
        $shopOwner = $this->shopOwner($shop);
        $product = Product::factory()->create(['base_price' => 750]);
        $invoice = $this->regularShopInvoiceWithOneLine($shop, $shopOwner, $product, 'SINV-OWNED-NO-IN', '2026-07-18', 750);

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.owned-shops.daily-bills.payment', ['shop' => $shop, 'invoice' => $invoice]), [
                'paid_amount' => 750,
                'payment_note' => 'Owned shop bill approved.',
            ])
            ->assertSessionHas('success');

        $invoice->refresh();

        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame('750.00', $invoice->paid_amount);
        $this->assertSame('0.00', $invoice->balance_amount);
        $this->assertSame(1, JournalEntry::query()
            ->where('source_type', ShopInvoice::class)
            ->where('source_id', $invoice->id)
            ->count());
    }

    public function test_regular_shop_overpayment_allocates_multiple_invoices_and_keeps_credit_after_accounting_approval(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $shop = Shop::factory()->create([
            'accounting_enabled' => false,
            'accounting_mode' => 'regular',
        ]);
        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');
        $product = Product::factory()->create(['base_price' => 100]);

        $firstInvoice = $this->regularShopInvoiceWithOneLine($shop, $shopOwner, $product, 'SINV-OVERPAY-001', '2026-07-16', 300);
        $secondInvoice = $this->regularShopInvoiceWithOneLine($shop, $shopOwner, $product, 'SINV-OVERPAY-002', '2026-07-17', 500);

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.accounting.payment-requests.store'), [
                'amount_mode' => 'custom',
                'amount' => 1000,
                'shop_note' => 'Paid extra for next bill',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $paymentRequest = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $this->assertSame('1000.00', $paymentRequest->requested_amount);
        $this->assertSame(0, JournalEntry::query()->where('source_type', ShopInvoice::class)->where('source_id', $firstInvoice->id)->count());

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.shop-invoice-payment-requests.review', $paymentRequest), [
                'decision' => 'approve',
                'admin_note' => 'Cash verified',
            ])
            ->assertSessionHas('success');

        $paymentRequest->refresh()->load('allocations');
        $firstInvoice->refresh();
        $secondInvoice->refresh();

        $this->assertSame('paid', $firstInvoice->payment_status);
        $this->assertSame('300.00', $firstInvoice->paid_amount);
        $this->assertSame('0.00', $firstInvoice->balance_amount);
        $this->assertSame('paid', $secondInvoice->payment_status);
        $this->assertSame('500.00', $secondInvoice->paid_amount);
        $this->assertSame('0.00', $secondInvoice->balance_amount);
        $this->assertSame('800.00', $paymentRequest->applied_amount);
        $this->assertSame('200.00', $paymentRequest->credit_amount);
        $this->assertSame(200.00, $paymentRequest->remainingCreditAmount());
        $this->assertCount(2, $paymentRequest->allocations);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => ShopInvoice::class,
            'source_id' => $firstInvoice->id,
            'source_event' => 'shop-payment-request:'.$paymentRequest->id,
        ]);
        $this->assertDatabaseHas('journal_transactions', [
            'amount' => '1000.00',
            'type' => 'debit',
        ]);
    }

    public function test_admin_can_manage_owned_shop_categories_but_cannot_delete_used_category(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->ownedShop(['code' => 'SHOP_CRUD_CAT']);
        $shopOwner = $this->shopOwner($shop);

        $this
            ->actingAs($admin)
            ->post(route('admin.accounting.owned-shops.categories.store', $shop), [
                'scope' => 'shop',
                'type' => 'expense',
                'purpose' => 'custom',
                'name' => 'Packing Expense',
                'cash_effect' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.categories.index', ['shop' => $shop->code]))
            ->assertSessionHas('success');

        $category = ShopAccountingCategory::query()
            ->where('shop_id', $shop->id)
            ->where('name', 'Packing Expense')
            ->firstOrFail();

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.owned-shops.categories.update', ['shop' => $shop, 'category' => $category]), [
                'scope' => 'shop',
                'type' => 'expense',
                'purpose' => 'staff_salary',
                'name' => 'Staff Food',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.categories.index', ['shop' => $shop->code]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shop_accounting_categories', [
            'id' => $category->id,
            'name' => 'Staff Food',
            'purpose' => 'staff_salary',
            'cash_effect' => false,
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.accounting.owned-shops.categories.destroy', ['shop' => $shop, 'category' => $category]))
            ->assertRedirect(route('admin.accounting.owned-shops.categories.index', ['shop' => $shop->code]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shop_accounting_categories', [
            'id' => $category->id,
        ]);

        $usedCategory = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'income',
            'cash_effect' => true,
            'purpose' => 'custom',
            'name' => 'Used Sales Category',
            'is_active' => true,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-18',
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 100,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        $entry->lines()->create([
            'shop_accounting_category_id' => $usedCategory->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 100,
            'description' => 'Already used',
            'review_status' => 'approved',
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.accounting.owned-shops.categories.destroy', ['shop' => $shop, 'category' => $usedCategory]))
            ->assertRedirect()
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('shop_accounting_categories', [
            'id' => $usedCategory->id,
        ]);
    }

    public function test_cashbook_cards_use_day_level_totals_when_multiple_entries_and_company_payments_exist(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = $this->ownedShop(['code' => 'SHOP_DAY_TOTAL']);
        $shopOwner = $this->shopOwner($shop);
        $incomeCategory = ShopAccountingCategory::query()->create([
            'type' => 'income',
            'cash_effect' => true,
            'purpose' => 'sales_cash',
            'name' => 'Sales Income - Cash',
            'is_active' => true,
        ]);
        $expenseCategory = ShopAccountingCategory::query()->create([
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Cash Purchase',
            'is_active' => true,
        ]);
        $firstEntry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-18',
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 9055,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => now(),
        ]);
        $firstEntry->lines()->create([
            'shop_accounting_category_id' => $incomeCategory->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 51555,
            'description' => 'Cash sales morning',
            'review_status' => 'approved',
        ]);
        $firstEntry->lines()->create([
            'shop_accounting_category_id' => $expenseCategory->id,
            'type' => 'expense',
            'cash_effect' => true,
            'amount' => 9500,
            'description' => 'Manual cash purchase',
            'review_status' => 'approved',
        ]);
        $secondEntry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-18',
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => -21889,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => now(),
        ]);
        $secondEntry->lines()->create([
            'shop_accounting_category_id' => $incomeCategory->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 11111,
            'description' => 'Cash sales evening',
            'review_status' => 'approved',
        ]);
        ShopCredit::query()->create([
            'shop_id' => $shop->id,
            'type' => 'in',
            'is_petty_cash' => true,
            'amount' => 5000,
            'description' => 'Shop working cash given to shop',
            'created_by' => $shopOwner->id,
            'business_date' => '2026-07-18',
            'status' => 'approved',
        ]);
        ShopCredit::query()->create([
            'shop_id' => $shop->id,
            'type' => 'out',
            'is_petty_cash' => true,
            'amount' => 30000,
            'description' => 'Cash paid to company',
            'created_by' => $shopOwner->id,
            'business_date' => '2026-07-18',
            'status' => 'approved',
        ]);
        ShopCredit::query()->create([
            'shop_id' => $shop->id,
            'type' => 'out',
            'is_petty_cash' => true,
            'amount' => 8000,
            'description' => 'Cash paid to company',
            'created_by' => $shopOwner->id,
            'business_date' => '2026-07-18',
            'status' => 'approved',
        ]);

        $this->assertSame(20166.00, app(OwnedShopAccountingService::class)->closingBalanceForDate($shop, Carbon::parse('2026-07-18')));

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', [
                'tab' => 'cashbook',
                'ledger_status' => 'approved',
                'date' => '2026-07-18',
            ]))
            ->assertOk()
            ->assertSeeText('Cash Given')
            ->assertSeeText('Paid Company')
            ->assertSeeText('Rs. 62,666.00')
            ->assertSeeText('Rs. 5,000.00')
            ->assertSeeText('Rs. 38,000.00')
            ->assertSeeText('Rs. 9,500.00')
            ->assertSeeText('Rs. 20,166.00')
            ->assertDontSeeText('Rs. -21,889.00');
    }

    public function test_shop_owner_accounting_history_shows_all_money_transactions_and_combined_report(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = $this->ownedShop(['code' => 'SHOP_HISTORY']);
        $shopOwner = $this->shopOwner($shop);
        $order = ShopOrder::query()->create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => '2026-07-18',
            'created_by' => $shopOwner->id,
        ]);
        $invoice = ShopInvoice::query()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-HISTORY-001',
            'business_date' => '2026-07-18',
            'status' => 'generated',
            'delivery_status' => 'received_full',
            'payment_status' => 'partially_paid',
            'subtotal' => 3000,
            'final_total' => 3000,
            'paid_amount' => 1200,
            'balance_amount' => 1800,
            'generated_by' => $shopOwner->id,
        ]);
        ShopInvoicePaymentRequest::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $shop->id,
            'requested_by' => $shopOwner->id,
            'request_type' => 'custom',
            'requested_amount' => 1200,
            'approved_amount' => 1200,
            'status' => 'approved',
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => '2026-07-18 10:00:00',
        ]);
        ShopCredit::query()->create([
            'shop_id' => $shop->id,
            'type' => 'in',
            'is_petty_cash' => true,
            'amount' => 500,
            'description' => 'Loan given to client shop',
            'created_by' => $shopOwner->id,
            'business_date' => '2026-07-18',
        ]);
        $incomeCategory = ShopAccountingCategory::query()->create([
            'type' => 'income',
            'cash_effect' => true,
            'purpose' => 'sales_cash',
            'name' => 'Sales Income - Cash',
            'is_active' => true,
        ]);
        $expenseCategory = ShopAccountingCategory::query()->create([
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Cash Purchase',
            'is_active' => true,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-18',
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 1250,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => now(),
        ]);
        $entry->lines()->create([
            'shop_accounting_category_id' => $incomeCategory->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 1000,
            'description' => 'Cash sales',
            'review_status' => 'approved',
        ]);
        $entry->lines()->create([
            'shop_accounting_category_id' => $expenseCategory->id,
            'type' => 'expense',
            'cash_effect' => true,
            'amount' => 250,
            'description' => 'Vegetable cash purchase',
            'review_status' => 'approved',
        ]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.history', ['tab' => 'bills']))
            ->assertOk()
            ->assertSeeText('Shop Money Report')
            ->assertSeeText('Cash bills and cashbook together')
            ->assertSeeText('Money in and out to shop')
            ->assertSeeText('Cash Bills')
            ->assertSeeText('Rs. 3,000.00')
            ->assertSeeText('Bill Paid')
            ->assertSeeText('Rs. 1,200.00')
            ->assertSeeText('Shop Cash In')
            ->assertSeeText('Rs. 500.00')
            ->assertSeeText('Cashbook In')
            ->assertSeeText('Rs. 1,000.00')
            ->assertSeeText('Cashbook Out')
            ->assertSeeText('Rs. 250.00')
            ->assertSeeText('Cash Bill')
            ->assertSeeText('SINV-HISTORY-001')
            ->assertSeeText('Bill Payment')
            ->assertSeeText('Loan Given')
            ->assertSeeText('Sales Income - Cash')
            ->assertSeeText('Cash Purchase');
    }

    public function test_shop_owner_daily_report_shows_daily_opening_closing_and_net_difference(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01 09:00:00'));
        $this->seed(RolePermissionSeeder::class);

        $shop = $this->ownedShop(['code' => 'SHOP_DAILY_REPORT']);
        $shopOwner = $this->shopOwner($shop);
        ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-13',
            'status' => 'approved',
            'opening_cash' => 1000,
            'closing_cash' => 1450,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => now(),
        ]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.daily-report', ['month' => '2026-07', 'daily_page' => 2]))
            ->assertOk()
            ->assertSeeText('Daily Report')
            ->assertSeeText('Today')
            ->assertSeeText('13 Jul 2026')
            ->assertSeeText('13')
            ->assertSeeText('Opening Balance')
            ->assertSeeText('Closing Balance')
            ->assertSeeText('Net Difference')
            ->assertSeeText('Rs. 1,000.00')
            ->assertSeeText('Rs. 1,450.00')
            ->assertSeeText('+ Rs. 450.00');
    }

    public function test_shop_owner_daily_report_defaults_to_today_page_for_current_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 09:00:00'));
        $this->seed(RolePermissionSeeder::class);

        $shop = $this->ownedShop(['code' => 'SHOP_DAILY_TODAY']);
        $shopOwner = $this->shopOwner($shop);
        ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-18',
            'status' => 'approved',
            'opening_cash' => 400,
            'closing_cash' => 700,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => now(),
        ]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.daily-report', ['month' => '2026-07']))
            ->assertOk()
            ->assertSeeText('18 Jul 2026')
            ->assertSeeText('18')
            ->assertSeeText('Rs. 400.00')
            ->assertSeeText('Rs. 700.00')
            ->assertSeeText('+ Rs. 300.00');
    }

    public function test_cashbook_and_daily_report_are_only_visible_and_accessible_for_owned_shops(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $regularShop = Shop::factory()->create([
            'accounting_enabled' => false,
            'accounting_mode' => 'regular',
        ]);
        $regularShopOwner = User::factory()->create(['shop_id' => $regularShop->id]);
        $regularShopOwner->assignRole('shop');

        $this
            ->actingAs($regularShopOwner)
            ->get(route('shop-owner.accounting.index', ['tab' => 'bills']))
            ->assertOk()
            ->assertSeeText('Bills')
            ->assertDontSeeText('Cashbook')
            ->assertDontSeeText('Daily Report');

        $this
            ->actingAs($regularShopOwner)
            ->get(route('shop-owner.accounting.index', ['tab' => 'cashbook']))
            ->assertNotFound();

        $this
            ->actingAs($regularShopOwner)
            ->get(route('shop-owner.accounting.daily-report'))
            ->assertNotFound();

        $ownedShop = $this->ownedShop(['code' => 'SHOP_OWNED_NAV']);
        $ownedShopOwner = $this->shopOwner($ownedShop);

        $this
            ->actingAs($ownedShopOwner)
            ->get(route('shop-owner.accounting.index', ['tab' => 'bills']))
            ->assertOk()
            ->assertSeeText('Cashbook')
            ->assertSeeText('Daily Report');
    }

    public function test_shop_owner_can_add_additional_entry_after_day_is_approved(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = $this->ownedShop(['code' => 'SHOP_APPROVED_EXTRA']);
        $shopOwner = $this->shopOwner($shop);
        $incomeCategory = ShopAccountingCategory::query()->create([
            'type' => 'income',
            'cash_effect' => true,
            'name' => 'Sales income',
            'is_active' => true,
        ]);
        $expenseCategory = ShopAccountingCategory::query()->create([
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Cash Purchase',
            'is_active' => true,
        ]);
        $approvedEntry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-17',
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 1200,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => now(),
        ]);
        $approvedEntry->lines()->create([
            'shop_accounting_category_id' => $incomeCategory->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 1200,
            'description' => 'Approved sales',
            'review_status' => 'approved',
        ]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', [
                'tab' => 'cashbook',
                'ledger_status' => 'approved',
                'date' => '2026-07-17',
            ]))
            ->assertOk()
            ->assertSee('Create cashbook entry', false)
            ->assertDontSeeText('Add New Entry')
            ->assertDontSeeText('Add additional income or expense');

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', [
                'tab' => 'create',
                'date' => '2026-07-17',
            ]))
            ->assertOk()
            ->assertSeeText('Add Adjustment')
            ->assertSeeText('Add adjustment income or expense')
            ->assertSeeText('Entry Type')
            ->assertSeeText('Income')
            ->assertSeeText('Expense');

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.accounting.entries.store'), [
                'business_date' => '2026-07-17',
                'submission_action' => 'submit',
                'create_adjustment' => '1',
                'notes' => 'Missed cash purchase after approval',
                'lines' => [
                    [
                        'shop_accounting_category_id' => $expenseCategory->id,
                        'amount' => 200,
                        'description' => 'Extra cash purchase',
                    ],
                ],
            ])
            ->assertRedirect(route('shop-owner.accounting.index', [
                'tab' => 'cashbook',
                'ledger_status' => 'submitted',
                'date' => '2026-07-17',
            ]))
            ->assertSessionHas('success');

        $this->assertSame('approved', $approvedEntry->fresh()->status);
        $this->assertSame(2, ShopAccountingEntry::query()->where('shop_id', $shop->id)->count());

        $additionalEntry = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '2026-07-17')
            ->where('status', 'submitted')
            ->firstOrFail();

        $this->assertSame('submitted', $additionalEntry->status);
        $this->assertSame(ShopAccountingEntry::TypeAdjustment, $additionalEntry->entry_type);
        $this->assertNull($additionalEntry->daily_entry_key);
        $this->assertSame('Extra cash purchase', $additionalEntry->lines()->first()?->description);

        $this
            ->actingAs($shopOwner)
            ->from(route('shop-owner.accounting.index', [
                'tab' => 'create',
                'date' => '2026-07-17',
            ]))
            ->post(route('shop-owner.accounting.entries.store'), [
                'business_date' => '2026-07-17',
                'submission_action' => 'submit',
                'create_adjustment' => '1',
                'notes' => 'Duplicate cash purchase',
                'lines' => [
                    [
                        'shop_accounting_category_id' => $expenseCategory->id,
                        'amount' => 200,
                        'description' => 'Extra cash purchase',
                    ],
                ],
            ])
            ->assertRedirect(route('shop-owner.accounting.index', [
                'tab' => 'create',
                'date' => '2026-07-17',
            ]))
            ->assertSessionHasErrors('lines');

        $this->assertSame(2, ShopAccountingEntry::query()->where('shop_id', $shop->id)->count());
    }

    public function test_owned_shop_approved_old_delivery_bill_recalculates_future_opening_balance(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = $this->ownedShop(['code' => 'SHOP_OLD_BILL']);
        $shopOwner = $this->shopOwner($shop);
        $salesCategory = ShopAccountingCategory::query()->create([
            'type' => 'income',
            'cash_effect' => true,
            'purpose' => 'sales_cash',
            'name' => 'Sales Income - Cash',
            'is_active' => true,
        ]);
        $todayEntry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-21',
            'status' => 'draft',
            'opening_cash' => 0,
            'closing_cash' => 100,
            'created_by' => $shopOwner->id,
        ]);
        $todayEntry->lines()->create([
            'shop_accounting_category_id' => $salesCategory->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 100,
            'description' => 'Cash sales',
        ]);
        $order = ShopOrder::query()->create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => '2026-07-20',
            'created_by' => $shopOwner->id,
        ]);
        $invoice = ShopInvoice::query()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-OLD-BILL-001',
            'business_date' => '2026-07-20',
            'status' => 'delivery_review',
            'delivery_status' => 'awaiting_review',
            'payment_status' => 'unpaid',
            'subtotal' => 338,
            'shortage_total' => 0,
            'discount_total' => 0,
            'final_total' => 338,
            'paid_amount' => 0,
            'balance_amount' => 338,
            'generated_by' => $shopOwner->id,
        ]);
        $service = app(OwnedShopAccountingService::class);

        $this->assertSame(0.0, $service->previousClosingBalance($shop, Carbon::parse('2026-07-21')));

        $invoice->update([
            'status' => 'payment_pending',
            'delivery_status' => 'received_full',
        ]);
        $service->syncStoredClosingBalancesFromDate($shop, Carbon::parse('2026-07-20'), $shopOwner->id, Carbon::parse('2026-07-21'));

        $yesterdayEntry = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '2026-07-20')
            ->firstOrFail();
        $todayEntry->refresh();

        $this->assertSame('0.00', $yesterdayEntry->opening_cash);
        $this->assertSame('-338.00', $yesterdayEntry->closing_cash);
        $this->assertSame('-338.00', $todayEntry->opening_cash);
        $this->assertSame('-238.00', $todayEntry->closing_cash);
        $this->assertSame(338.0, $service->receiptSummaryForDate($shop, Carbon::parse('2026-07-20'))['cash_debit']);
        $this->assertSame(-338.0, $service->receiptSummaryForDate($shop, Carbon::parse('2026-07-21'))['opening_balance']);
    }

    public function test_admin_can_update_client_shop_settings_from_index(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $client = Client::query()->firstOrCreate(
            ['code' => 'AISHWARYA_VEG'],
            ['name' => 'Aishwarya Veg', 'status' => 'active'],
        );
        $newClient = Client::query()->create([
            'name' => 'New Client',
            'code' => 'NEW_CLIENT',
            'status' => 'active',
        ]);
        $shop = $this->ownedShop([
            'name' => 'Casio',
            'client_id' => $client->id,
            'reserve_amount' => 100,
            'default_petty_cash_amount' => 50,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.owned-shops.index'))
            ->assertOk()
            ->assertSeeText('Edit')
            ->assertSeeText('Remove');

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.owned-shops.update', ['shop' => $shop->code]), [
                'client_id' => $newClient->id,
                'reserve_amount' => 250,
                'default_petty_cash_amount' => 75,
                'business_date' => '2026-07-27',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.index'));

        $shop->refresh();

        $this->assertSame($newClient->id, $shop->client_id);
        $this->assertSame('250.00', $shop->reserve_amount);
        $this->assertSame('75.00', $shop->default_petty_cash_amount);
        $this->assertDatabaseHas('shop_credits', [
            'shop_id' => $shop->id,
            'type' => 'in',
            'amount' => '150.00',
            'business_date' => '2026-07-27 00:00:00',
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_remove_shop_from_client_accounting_without_deleting_shop(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $client = Client::query()->firstOrCreate(
            ['code' => 'AISHWARYA_VEG'],
            ['name' => 'Aishwarya Veg', 'status' => 'active'],
        );
        $shop = $this->ownedShop([
            'name' => 'Casio',
            'client_id' => $client->id,
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.accounting.owned-shops.destroy', ['shop' => $shop->code]))
            ->assertRedirect(route('admin.accounting.owned-shops.index'));

        $shop->refresh();

        $this->assertFalse($shop->accounting_enabled);
        $this->assertSame('regular', $shop->accounting_mode);
        $this->assertNull($shop->client_id);
        $this->assertModelExists($shop);
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

    private function regularShopInvoiceWithOneLine(Shop $shop, User $shopOwner, Product $product, string $invoiceNumber, string $businessDate, float $amount): ShopInvoice
    {
        $order = ShopOrder::query()->create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'delivery_status' => 'delivered',
            'payment_status' => 'unpaid',
            'business_date' => $businessDate,
            'created_by' => $shopOwner->id,
            'is_delivered' => true,
        ]);

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'status' => 'payment_pending',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'subtotal' => $amount,
            'shortage_total' => 0,
            'discount_total' => 0,
            'final_total' => $amount,
            'paid_amount' => 0,
            'balance_amount' => $amount,
            'generated_by' => $shopOwner->id,
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => $product->unit,
            'approved_qty' => 1,
            'delivered_qty' => 1,
            'shortage_qty' => 0,
            'unit_price' => $amount,
            'line_subtotal' => $amount,
            'shortage_amount' => 0,
            'final_line_total' => $amount,
        ]);

        return $invoice;
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
