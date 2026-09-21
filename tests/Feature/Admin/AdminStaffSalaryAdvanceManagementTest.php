<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Cashbook\FundingSource;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Employee;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\StaffPaymentCashbookProjectionService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffSalaryAdvanceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopOwner;

    private Shop $shopA;

    private Shop $shopB;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shopOwner = User::factory()->create();
        $this->shopOwner->assignRole('shop');

        $this->shopA = Shop::query()->create([
            'name' => 'City Center Shop',
            'code' => 'CITY_CENTER',
            'warehouse_tag' => 'CC',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->shopB = Shop::query()->create([
            'name' => 'Metro Branch',
            'code' => 'METRO_BRANCH',
            'warehouse_tag' => 'MB',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->shopOwner->ownedShopAssignments()->create(['shop_id' => $this->shopA->id]);

        $this->employee = Employee::factory()->create([
            'name' => 'Nikhil Nair',
            'employee_code' => 'EMP-NIKHIL',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'default_shop_id' => $this->shopA->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 30000,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_admin_can_record_salary_payment_which_creates_linked_cashbook_entry(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.staff.employee-payments.store', $this->employee->employee_code), [
            'amount' => 4500.00,
            'paid_on' => '2026-09-15',
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'shop_id' => $this->shopA->id,
            'notes' => 'Admin advance salary',
        ]);

        $response->assertRedirect(route('admin.staff.show', $this->employee->employee_code));

        $payment = ShopStaffPayment::query()
            ->where('employee_id', $this->employee->id)
            ->where('shop_id', $this->shopA->id)
            ->first();

        $this->assertNotNull($payment);
        $this->assertEquals(4500.00, (float) $payment->amount);
        $this->assertEquals('2026-09-15', $payment->paid_on->toDateString());
        $this->assertEquals('salary', $payment->payment_type);
        $this->assertEquals('sales', $payment->fund_source);
        $this->assertEquals('Admin advance salary', $payment->notes);

        // Check linked Cashbook transaction
        $tx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shopA->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(4500.00, (float) $tx->amount);
        $this->assertEquals('2026-09-15', $tx->business_date->toDateString());
        $this->assertEquals(FundingSource::Sales->value, $tx->funding_source);
    }

    public function test_admin_created_payment_appears_in_shop_staff_history(): void
    {
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopA->id,
            'employee_id' => $this->employee->id,
            'amount' => 5200.00,
            'fund_source' => 'sales',
            'payment_type' => 'advance',
            'paid_on' => '2026-09-14',
            'paid_by' => $this->admin->id,
            'status' => 'paid',
            'notes' => 'Admin approved advance',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);

        $shopHistoryResponse = $this->actingAs($this->shopOwner)->get(route('shop-owner.staff.index', [
            'shop' => $this->shopA->code,
            'tab' => 'history',
        ]));

        $shopHistoryResponse->assertOk();
        $shopHistoryResponse->assertSee('5,200.00');
        $shopHistoryResponse->assertSee('Admin approved advance');
    }

    public function test_shop_created_advance_appears_in_admin_hr_history(): void
    {
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopA->id,
            'employee_id' => $this->employee->id,
            'amount' => 2500.00,
            'fund_source' => 'sales',
            'payment_type' => 'advance',
            'paid_on' => '2026-09-12',
            'paid_by' => $this->shopOwner->id,
            'status' => 'paid',
            'notes' => 'Shop payout for festival',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->shopOwner->id);

        $adminShowResponse = $this->actingAs($this->admin)->get(route('admin.staff.show', $this->employee->employee_code));

        $adminShowResponse->assertOk();
        $adminShowResponse->assertSee('2,500.00');
        $adminShowResponse->assertSee('Shop payout for festival');
    }

    public function test_admin_can_edit_historical_payment_and_updates_cashbook(): void
    {
        $historicalDate = today()->subDays(5)->toDateString();
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopA->id,
            'employee_id' => $this->employee->id,
            'amount' => 2000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => $historicalDate,
            'paid_by' => $this->shopOwner->id,
            'status' => 'paid',
            'notes' => 'Old note',
        ]);

        $initialTx = app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->shopOwner->id);
        $this->assertNotNull($initialTx);

        $newHistoricalDate = today()->subDays(3)->toDateString();
        $response = $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 3200.00,
            'paid_on' => $newHistoricalDate,
            'payment_type' => 'advance',
            'fund_source' => 'petty_cash',
            'shop_id' => $this->shopB->id,
            'notes' => 'Admin shifted date, amount and shop',
        ]);

        $response->assertRedirect(route('admin.staff.show', $this->employee->employee_code));

        $fresh = $payment->fresh();
        $this->assertEquals(3200.00, (float) $fresh->amount);
        $this->assertEquals($newHistoricalDate, $fresh->paid_on->toDateString());
        $this->assertEquals('advance', $fresh->payment_type);
        $this->assertEquals('petty_cash', $fresh->fund_source);
        $this->assertEquals($this->shopB->id, $fresh->shop_id);
        $this->assertEquals('Admin shifted date, amount and shop', $fresh->notes);

        // Verify linked Cashbook transaction updated
        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals($this->shopB->id, $tx->shop_id);
        $this->assertEquals(3200.00, (float) $tx->amount);
        $this->assertEquals($newHistoricalDate, $tx->business_date->toDateString());
        $this->assertEquals(FundingSource::Petty->value, $tx->funding_source);
    }

    public function test_admin_can_delete_historical_payment_and_removes_linked_cashbook_entry(): void
    {
        $historicalDate = today()->subDays(10)->toDateString();
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopA->id,
            'employee_id' => $this->employee->id,
            'amount' => 4000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => $historicalDate,
            'paid_by' => $this->shopOwner->id,
            'status' => 'paid',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->shopOwner->id);

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($tx);

        $response = $this->actingAs($this->admin)->delete(route('admin.staff.shop-staff-payments.destroy', $payment));

        $response->assertRedirect(route('admin.staff.show', $this->employee->employee_code));

        $this->assertNull(ShopStaffPayment::query()->find($payment->id));
        $this->assertNull(ShopLedgerTransaction::query()->find($tx->id));
    }

    public function test_no_duplicate_cashbook_transactions_after_repeated_admin_sync(): void
    {
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopA->id,
            'employee_id' => $this->employee->id,
            'amount' => 1500.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => '2026-09-10',
            'paid_by' => $this->admin->id,
            'status' => 'paid',
        ]);

        $service = app(StaffPaymentCashbookProjectionService::class);
        $service->syncPayment($payment, $this->admin->id);
        $service->syncPayment($payment, $this->admin->id);
        $service->syncPayment($payment, $this->admin->id);

        $count = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shopA->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->count();

        $this->assertEquals(1, $count);
    }
}
