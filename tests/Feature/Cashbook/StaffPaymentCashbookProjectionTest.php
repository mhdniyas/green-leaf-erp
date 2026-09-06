<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeAttendance;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\StaffPaymentCashbookProjectionService;
use App\Services\HR\EmployeeAdvanceService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffPaymentCashbookProjectionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('shop');

        $this->shop = Shop::query()->create([
            'name' => 'Ashirwad Veg Shop',
            'code' => 'AV_ASHIRWAD',
            'warehouse_tag' => 'AV',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->owner->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);

        $this->employee = Employee::factory()->create([
            'name' => 'Ramesh Kumar',
            'employee_code' => 'EMP-RAMESH',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'default_shop_id' => $this->shop->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 15000,
            'joined_on' => '2026-01-01',
        ]);

        ShopEmployeeAssignment::query()->create([
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'assigned_from' => '2026-01-01',
            'assigned_to' => null,
            'status' => 'active',
        ]);

        EmployeeAdvanceRule::query()->create([
            'rule_name' => 'Default Rule',
            'advance_percent' => 50,
            'minimum_present_days' => 1,
            'is_active' => true,
        ]);

        for ($d = 1; $d <= 30; $d++) {
            EmployeeAttendance::query()->create([
                'employee_id' => $this->employee->id,
                'shop_id' => $this->shop->id,
                'attendance_date' => sprintf('2026-09-%02d', $d),
                'status' => 'present',
                'source' => 'manual',
                'day_count' => 1.0,
            ]);
        }

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_salary_payment_via_sales_cash_projects_to_cashbook(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');

        $advanceService = app(EmployeeAdvanceService::class);
        $payment = $advanceService->recordShopSalaryPayment(
            $this->employee,
            $this->shop,
            5000.0,
            'sales_cash',
            Carbon::parse('2026-09-30'),
            $this->owner,
            'September salary'
        );

        $this->assertInstanceOf(ShopStaffPayment::class, $payment);

        $transaction = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals(5000.0, (float) $transaction->amount);
        $this->assertEquals('sales', $transaction->funding_source);
        $this->assertEquals('expense', $transaction->direction);
        $this->assertEquals(-5000.0, (float) $transaction->settlement_delta);
        $this->assertEquals(0.0, (float) $transaction->petty_delta);
        $this->assertEquals(-5000.0, (float) $transaction->pl_delta);
        $this->assertEquals('2026-09-30', $transaction->business_date->toDateString());
    }

    public function test_advance_payment_via_petty_cash_projects_to_cashbook(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $advanceService = app(EmployeeAdvanceService::class);
        $advanceRequest = $advanceService->requestOrPayAdvance(
            $this->employee,
            $this->shop,
            2000.0,
            'petty_cash',
            Carbon::parse('2026-09-15'),
            $this->owner,
            'Festival advance'
        );

        $payment = $advanceRequest->shopStaffPayment;
        $this->assertInstanceOf(ShopStaffPayment::class, $payment);

        $transaction = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals(2000.0, (float) $transaction->amount);
        $this->assertEquals('petty', $transaction->funding_source);
        $this->assertEquals('expense', $transaction->direction);
        $this->assertEquals(0.0, (float) $transaction->settlement_delta);
        $this->assertEquals(-2000.0, (float) $transaction->petty_delta);
        $this->assertEquals(-2000.0, (float) $transaction->pl_delta);
        $this->assertEquals('2026-09-15', $transaction->business_date->toDateString());
    }

    public function test_salary_projection_is_idempotent(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');

        $advanceService = app(EmployeeAdvanceService::class);
        $payment = $advanceService->recordShopSalaryPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'sales_cash',
            Carbon::parse('2026-09-30'),
            $this->owner,
            'Salary'
        );

        $projectionService = app(StaffPaymentCashbookProjectionService::class);
        $projectionService->syncPayment($payment, $this->owner->id);
        $projectionService->syncPayment($payment, $this->owner->id);

        $count = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_shop_owner_cashbook_page_automatically_displays_and_calculates_salary_payment(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');

        $advanceService = app(EmployeeAdvanceService::class);
        $payment = $advanceService->recordShopSalaryPayment(
            $this->employee,
            $this->shop,
            4000.0,
            'sales_cash',
            Carbon::parse('2026-09-30'),
            $this->owner,
            'September Salary Ramesh'
        );

        $response = $this->actingAs($this->owner)->get(route('shop-owner.cashbook.show', [
            'date' => '2026-09-30',
        ]));

        $response->assertOk();

        $apiResponse = $this->actingAs($this->owner)->getJson(route('shop-owner.cashbook.api.shop-data', [
            'business_date' => '2026-09-30',
        ]));

        $apiResponse->assertOk();
        $this->assertEquals(4000.0, (float) $apiResponse->json('transactions.0.amount'));
        $this->assertEquals('September Salary Ramesh', $apiResponse->json('transactions.0.notes'));
        $this->assertEquals('sales', $apiResponse->json('transactions.0.funding_source'));
    }
}
