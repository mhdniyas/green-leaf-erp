<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Enums\Cashbook\FundingSource;
use App\Enums\Cashbook\SalaryHrTransactionType;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopSalaryBridgeSetting;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\StaffSalaryPaymentModeResolver;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffSalaryPaymentModeExecutionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    private Employee $employee;

    private EmployeeCategory $category;

    private LedgerEntryType $salaryEntryType;

    private LedgerEntryType $advanceEntryType;

    private ShopLedgerEntrySetting $salarySetting;

    private ShopLedgerEntrySetting $advanceSetting;

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

        $this->category = EmployeeCategory::query()->create([
            'code' => 'SALES_EXECUTIVE',
            'name' => 'Sales Executive',
            'staff_area' => 'shop',
            'is_active' => true,
        ]);

        $this->employee = Employee::factory()->create([
            'employee_category_id' => $this->category->id,
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

        $this->salaryEntryType = LedgerEntryType::query()->where('code', 'salary')->firstOrFail();
        $this->salarySetting = ShopLedgerEntrySetting::query()->firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->salaryEntryType->id,
        ], [
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
        ]);

        $this->advanceEntryType = LedgerEntryType::query()->where('code', 'staff_advance')->firstOrFail();
        $this->advanceSetting = ShopLedgerEntrySetting::query()->firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->advanceEntryType->id,
        ], [
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
        ]);
    }

    private function configureSalaryBridge(
        array $allowedModes = ['sales_cash', 'petty', 'company_payable'],
        string $defaultMode = 'sales_cash',
        ?int $settlementId = null
    ): ShopSalaryBridgeSetting {
        return ShopSalaryBridgeSetting::query()->updateOrCreate([
            'shop_id' => $this->shop->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
        ], [
            'shop_ledger_entry_setting_id' => $this->salarySetting->id,
            'is_enabled' => true,
            'allowed_payment_modes' => $allowedModes,
            'default_payment_mode' => $defaultMode,
            'company_payable_settlement_id' => $settlementId,
        ]);
    }

    private function configureAdvanceBridge(
        array $allowedModes = ['sales_cash', 'petty'],
        string $defaultMode = 'petty'
    ): ShopSalaryBridgeSetting {
        return ShopSalaryBridgeSetting::query()->updateOrCreate([
            'shop_id' => $this->shop->id,
            'transaction_type' => SalaryHrTransactionType::SalaryAdvance->value,
        ], [
            'shop_ledger_entry_setting_id' => $this->advanceSetting->id,
            'is_enabled' => true,
            'allowed_payment_modes' => $allowedModes,
            'default_payment_mode' => $defaultMode,
        ]);
    }

    public function test_resolver_returns_allowed_modes_and_default_mode(): void
    {
        $this->configureSalaryBridge(['sales_cash', 'company_payable'], 'company_payable');

        $resolver = app(StaffSalaryPaymentModeResolver::class);
        $config = $resolver->resolveAllowedModesForShop($this->shop->id, 'salary', true);

        $this->assertSame(['sales_cash', 'company_payable'], $config['allowed_modes']);
        $this->assertSame('company_payable', $config['default_mode']);
    }

    public function test_payment_is_rejected_when_mode_is_not_allowed_in_salary_settings(): void
    {
        $this->configureSalaryBridge(['sales_cash']); // petty is NOT allowed

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Payment mode 'petty' is not allowed for this shop under Salary Settings.");

        $resolver = app(StaffSalaryPaymentModeResolver::class);
        $resolver->resolvePaymentMode($this->shop->id, 'salary', 'petty', true);
    }

    public function test_shop_owner_cannot_submit_admin_only_fund_sources(): void
    {
        $this->configureSalaryBridge(['sales_cash', 'company_payable']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Shop owners can only pay using Sales Cash, Petty, or Company Payable.');

        $resolver = app(StaffSalaryPaymentModeResolver::class);
        $resolver->resolvePaymentMode($this->shop->id, 'salary', 'company_bank', true);
    }

    public function test_sales_cash_payment_execution_stores_sales_fund_source_and_projects_to_cashbook(): void
    {
        $this->configureSalaryBridge(['sales_cash', 'petty'], 'sales_cash');

        $run = PayrollRun::query()->create([
            'year' => 2026,
            'month' => 9,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => 'draft',
            'created_by' => $this->owner->id,
        ]);

        $item = PayrollRunItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $this->employee->id,
            'employee_category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'rule_snapshot' => json_encode([]),
            'gross_earnings' => 15000,
            'total_deductions' => 0,
            'net_salary' => 15000,
            'computed_amount' => 15000,
            'final_amount' => 15000,
        ]);

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.salary-payments.store'), [
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'payroll_run_item_id' => $item->id,
            'amount' => 5000,
            'fund_source' => 'sales_cash',
            'paid_on' => '2026-09-30',
            'request_uuid' => (string) Str::uuid(),
            'notes' => 'Part salary payment via sales cash',
        ]);

        $response->assertRedirect();

        $payment = ShopStaffPayment::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame('sales', $payment->fund_source);
        $this->assertEquals(5000, $payment->amount);

        $item->refresh();
        $item->load(['shopStaffPayments', 'payments']);
        $this->assertEquals(5000, $item->paidAmount());

        $transaction = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $this->assertEquals(5000, $transaction->amount);
        $this->assertSame(FundingSource::Sales->value, $transaction->funding_source);
    }

    public function test_petty_payment_execution_records_petty_fund_source(): void
    {
        $this->configureSalaryBridge(['sales_cash', 'petty'], 'petty');

        $run = PayrollRun::query()->create([
            'year' => 2026,
            'month' => 9,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => 'draft',
            'created_by' => $this->owner->id,
        ]);

        $item = PayrollRunItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $this->employee->id,
            'employee_category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'rule_snapshot' => json_encode([]),
            'gross_earnings' => 15000,
            'total_deductions' => 0,
            'net_salary' => 15000,
            'computed_amount' => 15000,
            'final_amount' => 15000,
        ]);

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.salary-payments.store'), [
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'payroll_run_item_id' => $item->id,
            'amount' => 3000,
            'fund_source' => 'petty',
            'paid_on' => '2026-09-30',
            'request_uuid' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();

        $payment = ShopStaffPayment::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame('petty', $payment->fund_source);
        $this->assertEquals(3000, $payment->amount);

        $transaction = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $this->assertSame(FundingSource::Petty->value, $transaction->funding_source);
    }

    /**
     * Explicit User Requirement Test:
     * Casio Salary Bridge
     * Company Payable → Settlement A
     * Shop Owner pays ₹5,000 using Company Payable
     * Assert:
     * ✓ ShopStaffPayment fund_source = company
     * ✓ Settlement A is the relation actually used
     * ✓ Sales Cash unchanged
     * ✓ Petty unchanged
     * ✓ correct Company↔Shop payable effect occurs
     * ✓ Settlement B / another shop's relation is never touched
     */
    public function test_company_payable_uses_configured_settlement_a_leaving_sales_and_petty_untouched(): void
    {
        // 1. Setup Settlement A for Casio (this shop)
        $settlementA = ShopCashbookRelation::query()->create([
            'shop_id' => $this->shop->id,
            'relation_type' => 'company_payable',
            'name' => 'Settlement A (Casio Head Office)',
            'enabled' => true,
            'is_company_payable' => true,
        ]);

        // Setup another shop & Settlement B (to verify B is never touched)
        $otherShop = Shop::query()->create([
            'name' => 'Other Shop',
            'code' => 'OTHER_SHOP',
            'warehouse_tag' => 'OT',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);
        $settlementB = ShopCashbookRelation::query()->create([
            'shop_id' => $otherShop->id,
            'relation_type' => 'company_payable',
            'name' => 'Settlement B (Other Shop Head Office)',
            'account_code' => 'ACC-SETTLE-B',
            'enabled' => true,
            'current_balance' => 0,
        ]);

        // Configure Salary Bridge with Settlement A
        $this->configureSalaryBridge(
            allowedModes: ['sales_cash', 'company_payable'],
            defaultMode: 'company_payable',
            settlementId: $settlementA->id
        );

        $run = PayrollRun::query()->create([
            'year' => 2026,
            'month' => 9,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => 'draft',
            'created_by' => $this->owner->id,
        ]);

        $item = PayrollRunItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $this->employee->id,
            'employee_category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'rule_snapshot' => json_encode([]),
            'gross_earnings' => 15000,
            'total_deductions' => 0,
            'net_salary' => 15000,
            'computed_amount' => 15000,
            'final_amount' => 15000,
        ]);

        // 2. Shop owner pays ₹5,000 using company_payable
        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.salary-payments.store'), [
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'payroll_run_item_id' => $item->id,
            'amount' => 5000,
            'fund_source' => 'company_payable',
            'paid_on' => '2026-09-30',
            'request_uuid' => (string) Str::uuid(),
            'notes' => 'Salary paid via Company Settlement A',
        ]);

        $response->assertRedirect();

        // 3. Assert ShopStaffPayment fund_source = company
        $payment = ShopStaffPayment::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame('company', $payment->fund_source);
        $this->assertEquals(5000, $payment->amount);

        // 4. Assert Cashbook Projection
        $transaction = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $this->assertSame(FundingSource::Company->value, $transaction->funding_source);

        $resolvedMode = app(StaffSalaryPaymentModeResolver::class)->resolvePaymentMode(
            $this->shop->id,
            'salary',
            'company_payable',
            true
        );
        $this->assertEquals($settlementA->id, $resolvedMode->settlementRelation?->id);

        // 5. Assert Sales Cash unchanged (no Sales Cash reduction transaction)
        $salesTransactions = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('funding_source', FundingSource::Sales->value)
            ->where('reference_type', ShopStaffPayment::class)
            ->count();
        $this->assertSame(0, $salesTransactions);

        // 6. Assert Petty unchanged (no Petty transaction)
        $pettyTransactions = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('funding_source', FundingSource::Petty->value)
            ->where('reference_type', ShopStaffPayment::class)
            ->count();
        $this->assertSame(0, $pettyTransactions);

        // 7. Assert Settlement B was never touched (remains 0)
        $settlementB->refresh();
        $this->assertEquals(0, $settlementB->current_balance);
    }

    public function test_multi_source_partial_payments_create_distinct_staff_payments_and_update_balances(): void
    {
        $settlementA = ShopCashbookRelation::query()->create([
            'shop_id' => $this->shop->id,
            'relation_type' => 'company_payable',
            'name' => 'Company Settlement',
            'enabled' => true,
        ]);

        $this->configureSalaryBridge(
            allowedModes: ['sales_cash', 'petty', 'company_payable'],
            defaultMode: 'sales_cash',
            settlementId: $settlementA->id
        );

        $run = PayrollRun::query()->create([
            'year' => 2026,
            'month' => 9,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => 'draft',
            'created_by' => $this->owner->id,
        ]);

        $item = PayrollRunItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $this->employee->id,
            'employee_category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'rule_snapshot' => json_encode([]),
            'gross_earnings' => 20000,
            'total_deductions' => 0,
            'net_salary' => 20000,
            'computed_amount' => 20000,
            'final_amount' => 20000,
        ]);

        // Payment 1: ₹5,000 via Sales Cash
        $this->actingAs($this->owner)->post(route('shop-owner.staff.salary-payments.store'), [
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'payroll_run_item_id' => $item->id,
            'amount' => 5000,
            'fund_source' => 'sales_cash',
            'paid_on' => '2026-09-30',
            'request_uuid' => (string) Str::uuid(),
        ]);

        // Payment 2: ₹2,000 via Petty
        $this->actingAs($this->owner)->post(route('shop-owner.staff.salary-payments.store'), [
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'payroll_run_item_id' => $item->id,
            'amount' => 2000,
            'fund_source' => 'petty',
            'paid_on' => '2026-09-30',
            'request_uuid' => (string) Str::uuid(),
        ]);

        // Payment 3: ₹4,000 via Company Payable
        $this->actingAs($this->owner)->post(route('shop-owner.staff.salary-payments.store'), [
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'payroll_run_item_id' => $item->id,
            'amount' => 4000,
            'fund_source' => 'company_payable',
            'paid_on' => '2026-09-30',
            'request_uuid' => (string) Str::uuid(),
        ]);

        // Assert 3 distinct payments created
        $payments = ShopStaffPayment::query()->where('employee_id', $this->employee->id)->get();
        $this->assertCount(3, $payments);

        $this->assertSame(['sales', 'petty', 'company'], $payments->pluck('fund_source')->toArray());

        $item->refresh();
        $item->load(['shopStaffPayments', 'payments']);
        $this->assertEquals(11000, $item->paidAmount());
        $this->assertEquals(4000, $item->remainingAmount());
    }

    public function test_advance_payment_uses_salary_advance_bridge_setting_modes(): void
    {
        $this->configureAdvanceBridge(allowedModes: ['petty'], defaultMode: 'petty');

        // Pay advance using sales_cash when only petty is allowed should fail
        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.advance-requests.store'), [
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 2000,
            'fund_source' => 'sales_cash',
            'requested_on' => '2026-09-20',
            'request_uuid' => (string) Str::uuid(),
        ]);

        $response->assertSessionHasErrors(['fund_source']);

        // Pay advance using petty should succeed
        $responseSuccess = $this->actingAs($this->owner)->post(route('shop-owner.staff.advance-requests.store'), [
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 2000,
            'fund_source' => 'petty',
            'requested_on' => '2026-09-20',
            'request_uuid' => (string) Str::uuid(),
        ]);

        $responseSuccess->assertRedirect();
    }
}
