<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffAdvancePaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopUser;

    private Shop $shop;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin-advance-test@example.com',
        ]);
        $this->admin->assignRole('admin');

        $this->shopUser = User::factory()->create();
        $this->shopUser->assignRole('shop');

        $this->shop = Shop::factory()->create([
            'name' => 'Demo Branch',
            'code' => 'DEMO',
            'status' => 'active',
        ]);

        ShopLedgerProfile::query()->create([
            'shop_id' => $this->shop->id,
            'uuid' => (string) Str::uuid(),
            'slug' => 'demo-branch',
            'code' => $this->shop->code,
            'name' => $this->shop->name,
            'enabled' => true,
            'settings' => [],
        ]);

        LedgerEntryType::query()->firstOrCreate(
            ['code' => 'salary'],
            [
                'name' => 'Staff Salary',
                'category' => 'expense',
                'active' => true,
                'default_direction' => 'expense',
            ]
        );

        $category = EmployeeCategory::query()->create([
            'name' => 'Shop Staff',
            'code' => 'SHOP_STAFF',
            'staff_area' => 'shop',
            'is_active' => true,
        ]);

        $this->employee = Employee::query()->create([
            'name' => 'Ali Khan',
            'employee_code' => 'EMP-101',
            'employee_category_id' => $category->id,
            'default_shop_id' => $this->shop->id,
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'status' => 'approved',
            'salary_type' => 'monthly',
            'monthly_salary' => 30000,
            'joining_date' => '2026-01-01',
        ]);

        EmployeeAdvanceRule::query()->create([
            'minimum_present_days' => 1,
            'advance_percent' => 50,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_advance_payments_page(): void
    {
        $advance = EmployeeAdvanceRequest::query()->create([
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'requested_on' => '2026-09-10',
            'payroll_month' => '2026-09-01',
            'requested_amount' => 5000,
            'status' => 'pending',
            'request_note' => 'Personal emergency',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.advance-payments.index', ['payroll_month' => '2026-09']));

        $response->assertOk();
        $response->assertSee('Advance Payments');
        $response->assertSee('Ali Khan');
        $response->assertSee('5,000.00');
        $response->assertSee('Personal emergency');
    }

    public function test_admin_can_edit_pending_advance_request_amount_and_date(): void
    {
        $advance = EmployeeAdvanceRequest::query()->create([
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'requested_on' => '2026-09-10',
            'payroll_month' => '2026-09-01',
            'requested_amount' => 5000,
            'status' => 'pending',
            'request_note' => 'Original note',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.staff.advance-requests.update', $advance), [
                'amount' => 6500.50,
                'requested_on' => '2026-09-15',
                'note' => 'Updated amount and date note',
            ]);

        $response->assertRedirect(route('admin.staff.advance-payments.index', ['payroll_month' => '2026-09']));
        $response->assertSessionHas('success');

        $advance->refresh();
        $this->assertEquals(6500.50, (float) $advance->requested_amount);
        $this->assertSame('2026-09-15', $advance->requested_on->toDateString());
        $this->assertSame('Updated amount and date note', $advance->request_note);
    }

    public function test_admin_can_edit_approved_advance_and_it_syncs_linked_shop_staff_payment(): void
    {
        $advance = EmployeeAdvanceRequest::query()->create([
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'requested_on' => '2026-09-10',
            'payroll_month' => '2026-09-01',
            'requested_amount' => 4000,
            'approved_amount' => 4000,
            'status' => 'approved',
            'fund_source' => 'petty_cash',
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now(),
        ]);

        $payment = ShopStaffPayment::query()->create([
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'employee_advance_request_id' => $advance->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-10',
            'amount' => 4000,
            'payment_type' => 'advance',
            'fund_source' => 'petty_cash',
            'status' => 'paid',
        ]);
        $advance->update(['shop_staff_payment_id' => $payment->id]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.staff.advance-requests.update', $advance), [
                'amount' => 5500,
                'requested_on' => '2026-09-18',
                'note' => 'Adjusted higher by admin',
            ]);

        $response->assertRedirect(route('admin.staff.advance-payments.index', ['payroll_month' => '2026-09']));
        $response->assertSessionHas('success');

        $advance->refresh();
        $payment->refresh();

        $this->assertEquals(5500.00, (float) $advance->requested_amount);
        $this->assertEquals(5500.00, (float) $advance->approved_amount);
        $this->assertSame('2026-09-18', $advance->requested_on->toDateString());
        $this->assertSame('Adjusted higher by admin', $advance->review_note);

        $this->assertEquals(5500.00, (float) $payment->amount);
        $this->assertSame('2026-09-18', $payment->paid_on->toDateString());
    }

    public function test_admin_can_delete_advance_request_and_cleans_up_linked_records(): void
    {
        $advance = EmployeeAdvanceRequest::query()->create([
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'requested_on' => '2026-09-10',
            'payroll_month' => '2026-09-01',
            'requested_amount' => 3000,
            'approved_amount' => 3000,
            'status' => 'approved',
            'fund_source' => 'petty_cash',
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now(),
        ]);

        $payment = ShopStaffPayment::query()->create([
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'employee_advance_request_id' => $advance->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-10',
            'amount' => 3000,
            'payment_type' => 'advance',
            'fund_source' => 'petty_cash',
            'status' => 'paid',
        ]);
        $advance->update(['shop_staff_payment_id' => $payment->id]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.staff.advance-requests.destroy', $advance));

        $response->assertRedirect(route('admin.staff.advance-payments.index', ['payroll_month' => '2026-09']));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('employee_advance_requests', ['id' => $advance->id]);
        $this->assertDatabaseMissing('shop_staff_payments', ['id' => $payment->id]);
    }

    public function test_unauthorized_user_cannot_update_or_delete_advance(): void
    {
        $advance = EmployeeAdvanceRequest::query()->create([
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'requested_on' => '2026-09-10',
            'payroll_month' => '2026-09-01',
            'requested_amount' => 5000,
            'status' => 'pending',
        ]);

        $updateResponse = $this->actingAs($this->shopUser)
            ->put(route('admin.staff.advance-requests.update', $advance), [
                'amount' => 6000,
                'requested_on' => '2026-09-12',
            ]);
        $updateResponse->assertForbidden();

        $deleteResponse = $this->actingAs($this->shopUser)
            ->delete(route('admin.staff.advance-requests.destroy', $advance));
        $deleteResponse->assertForbidden();

        $this->assertDatabaseHas('employee_advance_requests', ['id' => $advance->id]);
    }
}
