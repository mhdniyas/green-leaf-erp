<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Cashbook\FundingSource;
use App\Enums\Cashbook\SalaryHrTransactionType;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopSalaryBridgeSetting;
use App\Models\Employee;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\StaffPaymentCashbookProjectionService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class StaffSalaryPaymentCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private Employee $employee;

    private ShopLedgerEntrySetting $salarySetting;

    private ShopLedgerEntrySetting $advanceSetting;

    private ShopCashbookRelation $settlementRelation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->shop = Shop::query()->create([
            'name' => 'Casio Fresh Shop',
            'code' => 'CASIO_FRESH',
            'warehouse_tag' => 'AV',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->employee = Employee::factory()->create([
            'name' => 'Faisal Mohammed',
            'employee_code' => 'EMP-FAISAL',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'default_shop_id' => $this->shop->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 25000,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $salaryType = LedgerEntryType::query()->where('code', 'salary')->firstOrFail();
        $advanceType = LedgerEntryType::query()->where('code', 'staff_advance')->first() ?? $salaryType;

        $this->salarySetting = ShopLedgerEntrySetting::query()->firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryType->id,
        ], [
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
        ]);

        $this->advanceSetting = ShopLedgerEntrySetting::query()->firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $advanceType->id,
        ], [
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
        ]);

        $this->settlementRelation = ShopCashbookRelation::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Salary Company Settlement',
            'relationship_type' => 'company_settlement',
            'source_ledger_type' => 'salary',
            'settlement_timing' => 'daily_cutoff',
            'enabled' => true,
        ]);

        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shop->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $this->salarySetting->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash', 'petty', 'company_payable'],
            'company_payable_settlement_id' => $this->settlementRelation->id,
            'is_enabled' => true,
        ]);

        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shop->id,
            'transaction_type' => SalaryHrTransactionType::SalaryAdvance->value,
            'shop_ledger_entry_setting_id' => $this->advanceSetting->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash', 'petty', 'company_payable'],
            'company_payable_settlement_id' => $this->settlementRelation->id,
            'is_enabled' => true,
        ]);
    }

    public function test_same_source_amount_change_updates_same_payment_and_projection(): void
    {
        $today = '2026-09-21';
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 5000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => $today,
            'paid_by' => $this->admin->id,
            'status' => 'paid',
        ]);

        $initialTx = app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);
        $this->assertNotNull($initialTx);

        $initialPaymentCount = ShopStaffPayment::count();
        $initialRunItemCount = PayrollRunItem::count();

        $response = $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 4000.00,
            'paid_on' => $today,
            'payment_type' => 'salary',
            'fund_source' => 'sales_cash',
            'notes' => 'Corrected amount to 4000',
        ]);

        $response->assertRedirect();

        // 1. Same ShopStaffPayment row updated
        $this->assertEquals($initialPaymentCount, ShopStaffPayment::count());
        $fresh = $payment->fresh();
        $this->assertEquals(4000.00, (float) $fresh->amount);

        // 2. Same Cashbook projection identity
        $txs = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->get();

        $this->assertCount(1, $txs);
        $tx = $txs->first();
        $this->assertEquals($initialTx->id, $tx->id);
        $this->assertEquals(4000.00, (float) $tx->amount);
        $this->assertEquals(-4000.00, (float) $tx->settlement_delta);

        // 3. No duplicate salary obligations / payroll run items
        $this->assertEquals($initialRunItemCount, PayrollRunItem::count());

        // 4. Snapshot recomputed
        $snapshot = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $this->shop->id)
            ->whereDate('business_date', $today)
            ->first();
        $this->assertNotNull($snapshot);
    }

    public function test_sales_cash_to_petty_reclassification(): void
    {
        $today = '2026-09-21';
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 3000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => $today,
            'paid_by' => $this->admin->id,
            'status' => 'paid',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);

        $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 3000.00,
            'paid_on' => $today,
            'payment_type' => 'salary',
            'fund_source' => 'petty',
            'notes' => 'Reclassified to Petty',
        ])->assertRedirect();

        $fresh = $payment->fresh();
        $this->assertEquals('petty', $fresh->fund_source);

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $this->assertEquals(FundingSource::Petty->value, $tx->funding_source);
        $this->assertEquals(-3000.00, (float) $tx->petty_delta);
        $this->assertEquals(0.00, (float) $tx->settlement_delta);
    }

    public function test_petty_to_sales_cash_reclassification(): void
    {
        $today = '2026-09-21';
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 3000.00,
            'fund_source' => 'petty',
            'payment_type' => 'salary',
            'paid_on' => $today,
            'paid_by' => $this->admin->id,
            'status' => 'paid',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);

        $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 3000.00,
            'paid_on' => $today,
            'payment_type' => 'salary',
            'fund_source' => 'sales_cash',
            'notes' => 'Reclassified back to Sales Cash',
        ])->assertRedirect();

        $fresh = $payment->fresh();
        $this->assertEquals('sales', $fresh->fund_source);

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $this->assertEquals(FundingSource::Sales->value, $tx->funding_source);
        $this->assertEquals(0.00, (float) $tx->petty_delta);
        $this->assertEquals(-3000.00, (float) $tx->settlement_delta);
    }

    public function test_sales_cash_to_company_payable_reclassification(): void
    {
        $today = '2026-09-21';
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 5000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => $today,
            'paid_by' => $this->admin->id,
            'status' => 'paid',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);

        $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 5000.00,
            'paid_on' => $today,
            'payment_type' => 'salary',
            'fund_source' => 'company_payable',
            'notes' => 'Paid from Company Payable',
        ])->assertRedirect();

        $fresh = $payment->fresh();
        $this->assertEquals('company', $fresh->fund_source);

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $this->assertEquals(FundingSource::Company->value, $tx->funding_source);
        $this->assertEquals(0.00, (float) $tx->settlement_delta);
        $this->assertEquals(0.00, (float) $tx->petty_delta);
        $this->assertEquals(0.00, (float) $tx->company_pending_delta);
    }

    public function test_company_payable_to_sales_cash_reclassification(): void
    {
        $today = '2026-09-21';
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 5000.00,
            'fund_source' => 'company',
            'payment_type' => 'salary',
            'paid_on' => $today,
            'paid_by' => $this->admin->id,
            'status' => 'paid',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);

        $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 5000.00,
            'paid_on' => $today,
            'payment_type' => 'salary',
            'fund_source' => 'sales_cash',
            'notes' => 'Reclassified from Company to Sales Cash',
        ])->assertRedirect();

        $fresh = $payment->fresh();
        $this->assertEquals('sales', $fresh->fund_source);

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $this->assertEquals(FundingSource::Sales->value, $tx->funding_source);
        $this->assertEquals(-5000.00, (float) $tx->settlement_delta);
        $this->assertEquals(0.00, (float) $tx->company_pending_delta);
    }

    public function test_company_payable_settlement_isolation_between_shops(): void
    {
        $otherShop = Shop::query()->create([
            'name' => 'Second Shop',
            'code' => 'SECOND_SHOP',
            'warehouse_tag' => 'SS',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $otherSettlement = ShopCashbookRelation::query()->create([
            'shop_id' => $otherShop->id,
            'name' => 'Shop B Settlement',
            'relationship_type' => 'company_settlement',
            'source_ledger_type' => 'salary',
            'settlement_timing' => 'daily_cutoff',
            'enabled' => true,
        ]);

        $today = '2026-09-21';
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 4500.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => $today,
            'paid_by' => $this->admin->id,
            'status' => 'paid',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);

        $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 4500.00,
            'paid_on' => $today,
            'payment_type' => 'salary',
            'fund_source' => 'company_payable',
        ])->assertRedirect();

        // Shop A ledger transaction updated
        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();
        $this->assertEquals($this->shop->id, $tx->shop_id);

        // Shop B has 0 ledger transactions
        $this->assertEquals(0, ShopLedgerTransaction::query()->where('shop_id', $otherShop->id)->count());
    }

    public function test_date_correction_recalculates_both_dates(): void
    {
        $oldDate = '2026-09-13';
        $newDate = '2026-09-18';

        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 5000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => $oldDate,
            'paid_by' => $this->admin->id,
            'status' => 'paid',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);

        $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 5000.00,
            'paid_on' => $newDate,
            'payment_type' => 'salary',
            'fund_source' => 'sales_cash',
            'notes' => 'Corrected date',
        ])->assertRedirect();

        $fresh = $payment->fresh();
        $this->assertEquals($newDate, $fresh->paid_on->toDateString());

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();
        $this->assertEquals($newDate, $tx->business_date->toDateString());

        // Snapshots exist and are recalculated for both dates
        $oldSnapshot = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $this->shop->id)
            ->whereDate('business_date', $oldDate)
            ->first();
        $newSnapshot = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $this->shop->id)
            ->whereDate('business_date', $newDate)
            ->first();

        $this->assertNotNull($oldSnapshot);
        $this->assertNotNull($newSnapshot);
    }

    public function test_category_immutability_is_preserved_when_reclassifying_source(): void
    {
        $today = '2026-09-21';
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 5000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => $today,
            'paid_by' => $this->admin->id,
            'status' => 'paid',
        ]);

        $tx = app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);
        $originalEntryTypeId = $tx->entry_type_id;

        // Now create a new entry type and change the Salary bridge to point to the new entry type
        $newSalaryType = LedgerEntryType::query()->create([
            'code' => 'new_payroll_expense',
            'name' => 'New Payroll Expense',
            'category' => 'expense',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $newSalarySetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $newSalaryType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
        ]);

        ShopSalaryBridgeSetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('transaction_type', SalaryHrTransactionType::Salary->value)
            ->update(['shop_ledger_entry_setting_id' => $newSalarySetting->id]);

        // Reclassify payment source from Sales to Petty
        $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 5000.00,
            'paid_on' => $today,
            'payment_type' => 'salary',
            'fund_source' => 'petty',
        ])->assertRedirect();

        $freshTx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        // Must PRESERVE the original category!
        $this->assertEquals($originalEntryTypeId, $freshTx->entry_type_id);
        $this->assertNotEquals($newSalaryType->id, $freshTx->entry_type_id);
        $this->assertEquals(FundingSource::Petty->value, $freshTx->funding_source);
    }

    public function test_invalid_source_rejected_without_database_mutations(): void
    {
        $today = '2026-09-21';
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 5000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => $today,
            'paid_by' => $this->admin->id,
            'status' => 'paid',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);

        $response = $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 5000.00,
            'paid_on' => $today,
            'payment_type' => 'salary',
            'fund_source' => 'invalid_random_source',
        ]);

        $response->assertSessionHasErrors(['fund_source']);

        $fresh = $payment->fresh();
        $this->assertEquals('sales', $fresh->fund_source);
    }

    public function test_missing_company_payable_configuration_rejected(): void
    {
        // Disable company_payable on bridge
        ShopSalaryBridgeSetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('transaction_type', SalaryHrTransactionType::Salary->value)
            ->update([
                'allowed_payment_modes' => ['sales_cash', 'petty'],
                'company_payable_settlement_id' => null,
            ]);

        // Disable any existing relation
        ShopCashbookRelation::query()->where('shop_id', $this->shop->id)->update(['enabled' => false]);

        $today = '2026-09-21';
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 5000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => $today,
            'paid_by' => $this->admin->id,
            'status' => 'paid',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);

        $response = $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 5000.00,
            'paid_on' => $today,
            'payment_type' => 'salary',
            'fund_source' => 'company_payable',
        ]);

        $response->assertSessionHasErrors(['fund_source']);

        $fresh = $payment->fresh();
        $this->assertEquals('sales', $fresh->fund_source);
    }

    public function test_saving_identical_correction_twice_is_idempotent(): void
    {
        $today = '2026-09-21';
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 5000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => $today,
            'paid_by' => $this->admin->id,
            'status' => 'paid',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);

        // First update
        $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 4500.00,
            'paid_on' => $today,
            'payment_type' => 'salary',
            'fund_source' => 'petty',
        ])->assertRedirect();

        // Second identical update
        $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 4500.00,
            'paid_on' => $today,
            'payment_type' => 'salary',
            'fund_source' => 'petty',
        ])->assertRedirect();

        $txCount = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->count();

        $this->assertEquals(1, $txCount);
        $this->assertEquals(1, ShopStaffPayment::count());
    }

    public function test_audit_log_records_before_and_after_states_and_actor(): void
    {
        $today = '2026-09-21';
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 5000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => $today,
            'paid_by' => $this->admin->id,
            'status' => 'paid',
            'notes' => 'Original payment note',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->admin->id);

        $this->actingAs($this->admin)->put(route('admin.staff.shop-staff-payments.update', $payment), [
            'amount' => 4200.00,
            'paid_on' => $today,
            'payment_type' => 'salary',
            'fund_source' => 'company_payable',
            'notes' => 'Corrected note',
        ])->assertRedirect();

        $activity = Activity::query()
            ->where('log_name', 'cashbook_salary_payment_correction')
            ->where('subject_type', ShopStaffPayment::class)
            ->where('subject_id', $payment->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->admin->id, $activity->causer_id);

        $properties = $activity->properties->toArray();
        $this->assertEquals($this->shop->id, $properties['shop_id']);
        $this->assertEquals($this->employee->id, $properties['employee_id']);
        $this->assertEquals($payment->id, $properties['payment_id']);

        // Assert before state
        $this->assertEquals(5000.00, (float) $properties['before']['amount']);
        $this->assertEquals('sales', $properties['before']['fund_source']);
        $this->assertEquals('Original payment note', $properties['before']['notes']);

        // Assert after state
        $this->assertEquals(4200.00, (float) $properties['after']['amount']);
        $this->assertEquals('company', $properties['after']['fund_source']);
        $this->assertEquals('Corrected note', $properties['after']['notes']);
        $this->assertEquals($this->settlementRelation->id, $properties['after']['settlement_relation_id']);
    }
}
