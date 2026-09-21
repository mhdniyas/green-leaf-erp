<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Enums\Cashbook\SalaryHrTransactionType;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopSalaryBridgeSetting;
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

        $salaryType = LedgerEntryType::query()->where('code', 'salary')->firstOrFail();
        $salarySetting = ShopLedgerEntrySetting::query()->firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryType->id,
        ], [
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
        ]);

        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shop->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $salarySetting->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash', 'petty'],
            'is_enabled' => true,
        ]);

        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shop->id,
            'transaction_type' => SalaryHrTransactionType::SalaryAdvance->value,
            'shop_ledger_entry_setting_id' => $salarySetting->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash', 'petty'],
            'is_enabled' => true,
        ]);
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

    public function test_delete_salary_payment_removes_cashbook_transaction_and_recalculates_balance(): void
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
            'Salary to delete'
        );

        $tx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($tx);

        $advanceService->deleteShopStaffPayment($payment, $this->owner);

        $this->assertNull(ShopStaffPayment::query()->find($payment->id));

        $txAfter = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNull($txAfter);
    }

    public function test_two_payments_on_different_dates_remain_independent(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');

        $advanceService = app(EmployeeAdvanceService::class);
        $projectionService = app(StaffPaymentCashbookProjectionService::class);

        $payment1 = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 3000.00,
            'fund_source' => 'sales',
            'payment_type' => 'advance',
            'paid_on' => '2026-09-13',
            'paid_by' => $this->owner->id,
            'status' => 'paid',
            'notes' => 'Advance 13th',
        ]);
        $projectionService->syncPayment($payment1, $this->owner->id);

        $payment2 = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 3000.00,
            'fund_source' => 'sales',
            'payment_type' => 'advance',
            'paid_on' => '2026-09-18',
            'paid_by' => $this->owner->id,
            'status' => 'paid',
            'notes' => 'Advance 18th',
        ]);
        $projectionService->syncPayment($payment2, $this->owner->id);

        $tx1 = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment1->id)
            ->first();

        $tx2 = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment2->id)
            ->first();

        $this->assertNotNull($tx1);
        $this->assertNotNull($tx2);
        $this->assertNotEquals($tx1->id, $tx2->id);
        $this->assertEquals('2026-09-13', $tx1->business_date->toDateString());
        $this->assertEquals('2026-09-18', $tx2->business_date->toDateString());

        // Deleting payment1 leaves payment2 intact
        $advanceService->deleteShopStaffPayment($payment1, $this->owner);

        $this->assertNull(ShopLedgerTransaction::query()->find($tx1->id));
        $this->assertNotNull(ShopLedgerTransaction::query()->find($tx2->id));
    }

    public function test_manual_salary_cashbook_expense_remains_untouched_during_sync(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');

        $salaryEntryType = LedgerEntryType::query()->where('code', 'salary')->first();

        // Create a manual Cashbook entry (reference_type IS NULL)
        $manualTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryEntryType->id,
            'amount' => 5000.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'business_date' => '2026-09-15',
            'status' => 'posted',
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'Manual Cashbook Salary Expense',
            'entered_by' => $this->owner->id,
        ]);

        $projectionService = app(StaffPaymentCashbookProjectionService::class);
        $results = $projectionService->syncAndAuditShopPayments((int) $this->shop->id, null, '2026-09', (int) $this->owner->id);

        $this->assertEmpty($results['orphans']);
        $this->assertNotNull(ShopLedgerTransaction::query()->find($manualTx->id));
    }

    public function test_sync_cashbook_twice_produces_no_duplicates(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');

        $advanceService = app(EmployeeAdvanceService::class);
        $advanceService->recordShopSalaryPayment(
            $this->employee,
            $this->shop,
            2500.0,
            'sales_cash',
            Carbon::parse('2026-09-30'),
            $this->owner,
            'Salary 30th'
        );

        $projectionService = app(StaffPaymentCashbookProjectionService::class);

        $firstRun = $projectionService->syncAndAuditShopPayments((int) $this->shop->id, null, '2026-09', (int) $this->owner->id);
        $this->assertCount(0, $firstRun['created']); // Already created at payment creation
        $this->assertCount(1, $firstRun['matching']);

        $secondRun = $projectionService->syncAndAuditShopPayments((int) $this->shop->id, null, '2026-09', (int) $this->owner->id);
        $this->assertCount(0, $secondRun['created']);
        $this->assertCount(0, $secondRun['updated']);
        $this->assertCount(1, $secondRun['matching']);
        $this->assertCount(0, $secondRun['orphans']);

        $txCount = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->count();
        $this->assertEquals(1, $txCount);
    }

    public function test_historical_orphan_is_cleaned_by_sync_cashbook(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');

        $salaryEntryType = LedgerEntryType::query()->where('code', 'salary')->first();

        // Create an orphan transaction pointing to non-existent ShopStaffPayment ID 99999
        $orphanTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryEntryType->id,
            'amount' => 3000.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'business_date' => '2026-09-18',
            'status' => 'posted',
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => 99999,
            'notes' => 'Staff Advance: Deleted Employee',
            'entered_by' => $this->owner->id,
        ]);

        $projectionService = app(StaffPaymentCashbookProjectionService::class);
        $results = $projectionService->syncAndAuditShopPayments((int) $this->shop->id, null, '2026-09', (int) $this->owner->id);

        $this->assertCount(1, $results['orphans']);
        $this->assertEquals($orphanTx->id, $results['orphans'][0]['transaction_id']);

        // Verify transaction is deleted from DB
        $this->assertNull(ShopLedgerTransaction::query()->find($orphanTx->id));
    }

    public function test_dry_run_reconciliation_reports_orphan_without_deleting_it(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');

        $salaryEntryType = LedgerEntryType::query()->where('code', 'salary')->first();

        $orphanTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryEntryType->id,
            'amount' => 3000.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'business_date' => '2026-09-18',
            'status' => 'posted',
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => 88888,
            'notes' => 'Staff Advance: Ghost',
            'entered_by' => $this->owner->id,
        ]);

        $projectionService = app(StaffPaymentCashbookProjectionService::class);
        $summary = $projectionService->reconcile(from: '2026-09-01', to: '2026-09-30', apply: false, userId: (int) $this->owner->id);

        $this->assertGreaterThanOrEqual(1, $summary['voided']);
        $this->assertNotNull(ShopLedgerTransaction::query()->find($orphanTx->id));
    }

    public function test_apply_reconciliation_cleans_orphan(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');

        $salaryEntryType = LedgerEntryType::query()->where('code', 'salary')->first();

        $orphanTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryEntryType->id,
            'amount' => 3000.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'business_date' => '2026-09-18',
            'status' => 'posted',
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => 77777,
            'notes' => 'Staff Advance: Ghost Apply',
            'entered_by' => $this->owner->id,
        ]);

        $projectionService = app(StaffPaymentCashbookProjectionService::class);
        $summary = $projectionService->reconcile(from: '2026-09-01', to: '2026-09-30', apply: true, userId: (int) $this->owner->id);

        $this->assertGreaterThanOrEqual(1, $summary['voided']);
        $this->assertNull(ShopLedgerTransaction::query()->find($orphanTx->id));
    }
}
