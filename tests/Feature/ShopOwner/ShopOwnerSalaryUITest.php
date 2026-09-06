<?php

declare(strict_types=1);

namespace Tests\Feature\ShopOwner;

use App\Models\Employee;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopOwnerSalaryUITest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00', 'Asia/Kolkata'));
    }

    public function test_shop_staff_page_displays_calculated_availability_and_restricts_company_source(): void
    {
        $shop = Shop::factory()->create(['code' => 'SH01', 'name' => 'Downtown Shop']);

        /** @var User $shopUser */
        $shopUser = User::factory()->create();
        $shopUser->assignRole('shop');

        ShopOwnerAssignment::create([
            'user_id' => $shopUser->id,
            'shop_id' => $shop->id,
        ]);

        $category = EmployeeCategory::factory()->create([
            'code' => 'sales-staff',
            'staff_area' => 'shop',
        ]);

        EmployeeAdvanceRule::create([
            'minimum_present_days' => 5,
            'advance_percent' => 50,
            'is_active' => true,
        ]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'name' => 'Alice Walker',
            'user_id' => null,
            'default_shop_id' => $shop->id,
            'employee_category_id' => $category->id,
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 30000,
        ]);

        ShopEmployeeAssignment::create([
            'shop_id' => $shop->id,
            'employee_id' => $employee->id,
            'assigned_from' => '2026-09-01',
            'assigned_to' => null,
        ]);

        for ($d = 1; $d <= 10; $d++) {
            EmployeeAttendance::create([
                'employee_id' => $employee->id,
                'shop_id' => $shop->id,
                'attendance_date' => sprintf('2026-09-%02d', $d),
                'status' => 'present',
                'source' => 'manual',
                'day_count' => 1.0,
            ]);
        }

        $response = $this->actingAs($shopUser)
            ->get(route('shop-owner.staff.index', [
                'shop' => $shop->code,
                'tab' => 'advance',
                'date' => '2026-09-15',
            ]));

        $response->assertOk();
        $response->assertSee('Alice Walker');
        $response->assertSee('Sales Cash');
        $response->assertSee('Petty Cash');
        $response->assertDontSee('Company Bank Account');
        $response->assertDontSee('Company Central Cash');
        $response->assertSee('data-advance-summary', false);
    }

    public function test_cross_shop_employee_cannot_be_paid_by_unauthorized_shop(): void
    {
        $shopA = Shop::factory()->create(['code' => 'SHA']);
        $shopB = Shop::factory()->create(['code' => 'SHB']);

        /** @var User $shopUser */
        $shopUser = User::factory()->create();
        $shopUser->assignRole('shop');

        ShopOwnerAssignment::create([
            'user_id' => $shopUser->id,
            'shop_id' => $shopA->id,
        ]);

        $category = EmployeeCategory::factory()->create(['staff_area' => 'shop']);

        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create([
            'name' => 'Bob Smith',
            'default_shop_id' => $shopB->id,
            'employee_category_id' => $category->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 20000,
        ]);

        ShopEmployeeAssignment::create([
            'shop_id' => $shopB->id,
            'employee_id' => $otherEmployee->id,
            'assigned_from' => '2026-09-01',
            'assigned_to' => null,
        ]);

        $salaryResponse = $this->actingAs($shopUser)
            ->post(route('shop-owner.staff.salary-payments.store'), [
                'shop_id' => $shopA->id,
                'employee_id' => $otherEmployee->id,
                'amount' => 500,
                'fund_source' => 'sales_income',
                'paid_on' => '2026-09-30',
                'request_uuid' => (string) Str::uuid(),
            ]);

        $salaryResponse->assertSessionHasErrors(['employee_id']);

        $advanceResponse = $this->actingAs($shopUser)
            ->post(route('shop-owner.staff.advance-requests.store'), [
                'shop_id' => $shopA->id,
                'employee_id' => $otherEmployee->id,
                'amount' => 500,
                'fund_source' => 'sales_income',
                'requested_on' => '2026-09-15',
                'request_uuid' => (string) Str::uuid(),
            ]);

        $advanceResponse->assertSessionHasErrors(['employee_id']);
    }

    public function test_salary_payment_submission_is_idempotent_with_request_uuid(): void
    {
        $shop = Shop::factory()->create([
            'code' => 'SH02',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        /** @var User $shopUser */
        $shopUser = User::factory()->create();
        $shopUser->assignRole('shop');

        ShopOwnerAssignment::create([
            'user_id' => $shopUser->id,
            'shop_id' => $shop->id,
        ]);

        $category = EmployeeCategory::factory()->create(['staff_area' => 'shop']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'name' => 'Charlie Day',
            'default_shop_id' => $shop->id,
            'employee_category_id' => $category->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 30000,
        ]);

        ShopEmployeeAssignment::create([
            'shop_id' => $shop->id,
            'employee_id' => $employee->id,
            'assigned_from' => '2026-09-01',
            'assigned_to' => null,
        ]);

        for ($d = 1; $d <= 10; $d++) {
            EmployeeAttendance::create([
                'employee_id' => $employee->id,
                'shop_id' => $shop->id,
                'attendance_date' => sprintf('2026-09-%02d', $d),
                'status' => 'present',
                'source' => 'manual',
                'day_count' => 1.0,
            ]);
        }

        $uuid = (string) Str::uuid();

        $firstResponse = $this->actingAs($shopUser)
            ->post(route('shop-owner.staff.salary-payments.store'), [
                'shop_id' => $shop->id,
                'employee_id' => $employee->id,
                'amount' => 1000,
                'fund_source' => 'sales_income',
                'paid_on' => '2026-09-30',
                'request_uuid' => $uuid,
            ]);

        $firstResponse->assertRedirect();
        $this->assertDatabaseCount('shop_staff_payments', 1);

        $secondResponse = $this->actingAs($shopUser)
            ->post(route('shop-owner.staff.salary-payments.store'), [
                'shop_id' => $shop->id,
                'employee_id' => $employee->id,
                'amount' => 1000,
                'fund_source' => 'sales_income',
                'paid_on' => '2026-09-15',
                'request_uuid' => $uuid,
            ]);

        $secondResponse->assertRedirect();
        $this->assertDatabaseCount('shop_staff_payments', 1);
    }

    public function test_advance_request_submission_is_idempotent_with_request_uuid(): void
    {
        $shop = Shop::factory()->create([
            'code' => 'SH03',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        /** @var User $shopUser */
        $shopUser = User::factory()->create();
        $shopUser->assignRole('shop');

        ShopOwnerAssignment::create([
            'user_id' => $shopUser->id,
            'shop_id' => $shop->id,
        ]);

        $category = EmployeeCategory::factory()->create(['staff_area' => 'shop']);

        EmployeeAdvanceRule::create([
            'minimum_present_days' => 5,
            'advance_percent' => 50,
            'is_active' => true,
        ]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'name' => 'Diana Prince',
            'default_shop_id' => $shop->id,
            'employee_category_id' => $category->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 30000,
        ]);

        ShopEmployeeAssignment::create([
            'shop_id' => $shop->id,
            'employee_id' => $employee->id,
            'assigned_from' => '2026-09-01',
            'assigned_to' => null,
        ]);

        for ($d = 1; $d <= 10; $d++) {
            EmployeeAttendance::create([
                'employee_id' => $employee->id,
                'shop_id' => $shop->id,
                'attendance_date' => sprintf('2026-09-%02d', $d),
                'status' => 'present',
                'source' => 'manual',
                'day_count' => 1.0,
            ]);
        }

        $uuid = (string) Str::uuid();

        $firstResponse = $this->actingAs($shopUser)
            ->post(route('shop-owner.staff.advance-requests.store'), [
                'shop_id' => $shop->id,
                'employee_id' => $employee->id,
                'amount' => 2000,
                'fund_source' => 'sales_income',
                'requested_on' => '2026-09-15',
                'request_uuid' => $uuid,
            ]);

        $firstResponse->assertRedirect();
        $this->assertDatabaseCount('employee_advance_requests', 1);

        $secondResponse = $this->actingAs($shopUser)
            ->post(route('shop-owner.staff.advance-requests.store'), [
                'shop_id' => $shop->id,
                'employee_id' => $employee->id,
                'amount' => 2000,
                'fund_source' => 'sales_income',
                'requested_on' => '2026-09-15',
                'request_uuid' => $uuid,
            ]);

        $secondResponse->assertRedirect();
        $this->assertDatabaseCount('employee_advance_requests', 1);
    }
}
