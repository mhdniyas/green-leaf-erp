<?php

declare(strict_types=1);

namespace Tests\Feature\ShopOwner;

use App\Enums\Cashbook\SalaryHrTransactionType;
use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopSalaryBridgeSetting;
use App\Models\Employee;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\Cashbook\ShopCashbookSalarySectionService;
use App\Services\Cashbook\StaffPaymentCashbookProjectionService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCashbookSalarySectionTest extends TestCase
{
    use RefreshDatabase;

    private Shop $casio;

    private Shop $sana;

    private Employee $faisal;

    private Employee $shafeek;

    private ShopLedgerEntrySetting $casioSalarySetting;

    private ShopLedgerEntrySetting $casioAdvanceSetting;

    private ShopLedgerEntrySetting $casioExpenseSetting;

    private ShopLedgerEntrySetting $sanaSalarySetting;

    private StaffPaymentCashbookProjectionService $projectionService;

    private ShopCashbookSalarySectionService $salarySectionService;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);

        $this->actor = User::factory()->create();
        $this->actor->assignRole('admin');

        $this->casio = Shop::query()->create([
            'name' => 'Casio Shop',
            'code' => 'CASIO',
            'warehouse_tag' => 'CS',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->sana = Shop::query()->create([
            'name' => 'Sana Shop',
            'code' => 'SANA',
            'warehouse_tag' => 'SN',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        ShopLedgerProfile::query()->create([
            'shop_id' => $this->casio->id,
            'name' => $this->casio->name,
            'code' => $this->casio->code,
            'slug' => 'casio',
        ]);

        ShopLedgerProfile::query()->create([
            'shop_id' => $this->sana->id,
            'name' => $this->sana->name,
            'code' => $this->sana->code,
            'slug' => 'sana',
        ]);

        $this->faisal = Employee::factory()->create(['name' => 'Faisal Rahman']);
        $this->shafeek = Employee::factory()->create(['name' => 'Mohammed Shafeek']);

        $salaryType = LedgerEntryType::where('code', 'salary')->firstOrFail();
        $advanceType = LedgerEntryType::where('code', 'staff_advance')->firstOrFail();

        // Casio salary category
        $this->casioSalarySetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $salaryType->id,
            'display_name' => 'Staff Salary',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_expense' => true,
            'effective_from' => '2026-01-01',
            'version' => 1,
        ]);

        // Casio advance category
        $this->casioAdvanceSetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $advanceType->id,
            'display_name' => 'Staff Advance',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_expense' => true,
            'effective_from' => '2026-01-01',
            'version' => 1,
        ]);

        // Casio expense (e.g. Rent) — must NOT appear in salary section
        $expenseType = LedgerEntryType::where('code', 'labour')->firstOrFail();
        $this->casioExpenseSetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $expenseType->id,
            'display_name' => 'Labour',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_expense' => true,
            'effective_from' => '2026-01-01',
            'version' => 1,
        ]);

        // Sana salary category
        $this->sanaSalarySetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $salaryType->id,
            'display_name' => 'Sana Staff Salary',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_expense' => true,
            'effective_from' => '2026-01-01',
            'version' => 1,
        ]);

        $this->projectionService = app(StaffPaymentCashbookProjectionService::class);
        $this->salarySectionService = app(ShopCashbookSalarySectionService::class);
    }

    // ─── Helper ────────────────────────────────────────────────────────────────

    /**
     * Configure the Salary bridge for Casio and sync a payment to create a ledger transaction.
     */
    private function makeBridgeAndPayment(
        Shop $shop,
        Employee $employee,
        string $paymentType,
        float $amount,
        string $fundSource,
        string $paidOn,
        ShopLedgerEntrySetting $setting,
    ): ShopStaffPayment {
        $typeEnum = match ($paymentType) {
            'advance', 'salary_advance' => SalaryHrTransactionType::SalaryAdvance,
            default => SalaryHrTransactionType::Salary,
        };

        ShopSalaryBridgeSetting::query()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'transaction_type' => $typeEnum->value,
            ],
            [
                'shop_ledger_entry_setting_id' => $setting->id,
                'default_payment_mode' => 'sales_cash',
                'allowed_payment_modes' => ['sales_cash', 'petty', 'company_payable'],
                'is_enabled' => true,
            ]
        );

        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $shop->id,
            'employee_id' => $employee->id,
            'paid_by' => $this->actor->id,
            'paid_on' => $paidOn,
            'amount' => $amount,
            'payment_type' => $paymentType,
            'fund_source' => $fundSource,
            'status' => 'paid',
        ]);

        $this->projectionService->syncPayment($payment, $this->actor->id);

        return $payment;
    }

    // ─── Tests ─────────────────────────────────────────────────────────────────

    /** @test */
    public function test_salary_payment_appears_in_casio_salary_section(): void
    {
        $this->makeBridgeAndPayment(
            $this->casio, $this->faisal, 'salary', 20000.0, 'sales',
            '2026-09-21', $this->casioSalarySetting
        );

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertTrue($data->hasAnyData());
        $this->assertCount(1, $data->groups);
        $this->assertSame('Salary', $data->groups[0]->typeLabel);
        $this->assertSame(20000.0, $data->groups[0]->total);
        $this->assertSame('Faisal Rahman', $data->groups[0]->transactions[0]->employeeName);
    }

    /** @test */
    public function test_salary_advance_appears_in_salary_section(): void
    {
        $this->makeBridgeAndPayment(
            $this->casio, $this->shafeek, 'advance', 3000.0, 'sales',
            '2026-09-21', $this->casioAdvanceSetting
        );

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertTrue($data->hasAnyData());
        $this->assertSame('Salary Advance', $data->groups[0]->typeLabel);
        $this->assertSame(3000.0, $data->groups[0]->total);
        $this->assertSame('Mohammed Shafeek', $data->groups[0]->transactions[0]->employeeName);
    }

    /** @test */
    public function test_sana_salary_does_not_appear_in_casio_section(): void
    {
        // Casio salary
        $this->makeBridgeAndPayment(
            $this->casio, $this->faisal, 'salary', 20000.0, 'sales',
            '2026-09-21', $this->casioSalarySetting
        );

        // Sana salary (different shop)
        $this->makeBridgeAndPayment(
            $this->sana, $this->shafeek, 'salary', 8000.0, 'sales',
            '2026-09-21', $this->sanaSalarySetting
        );

        $casioData = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertCount(1, $casioData->groups);
        $employeeNames = array_map(fn ($r) => $r->employeeName, $casioData->groups[0]->transactions);
        $this->assertContains('Faisal Rahman', $employeeNames);
        $this->assertNotContains('Mohammed Shafeek', $employeeNames);
    }

    /** @test */
    public function test_sales_funded_salary_shows_sales_cash_label(): void
    {
        $this->makeBridgeAndPayment(
            $this->casio, $this->faisal, 'salary', 20000.0, 'sales',
            '2026-09-21', $this->casioSalarySetting
        );

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertSame('Sales Cash', $data->groups[0]->transactions[0]->fundingLabel);
        $this->assertSame('sales', $data->groups[0]->transactions[0]->fundingSource);
    }

    /** @test */
    public function test_petty_funded_salary_shows_petty_label(): void
    {
        $this->makeBridgeAndPayment(
            $this->casio, $this->faisal, 'salary', 20000.0, 'petty',
            '2026-09-21', $this->casioSalarySetting
        );

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertSame('Petty', $data->groups[0]->transactions[0]->fundingLabel);
    }

    /** @test */
    public function test_company_funded_salary_shows_company_payable_label(): void
    {
        $this->makeBridgeAndPayment(
            $this->casio, $this->faisal, 'salary', 20000.0, 'company',
            '2026-09-21', $this->casioSalarySetting
        );

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertSame('Company Payable', $data->groups[0]->transactions[0]->fundingLabel);
    }

    /** @test */
    public function test_salary_transaction_id_appears_in_excluded_tx_ids(): void
    {
        $this->makeBridgeAndPayment(
            $this->casio, $this->faisal, 'salary', 20000.0, 'sales',
            '2026-09-21', $this->casioSalarySetting
        );

        $tx = ShopLedgerTransaction::where('shop_id', $this->casio->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->firstOrFail();

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertContains($tx->id, $data->excludedTxIds);
    }

    /** @test */
    public function test_salary_setting_id_appears_in_excluded_setting_ids(): void
    {
        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->casio->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $this->casioSalarySetting->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash'],
            'is_enabled' => true,
        ]);

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertContains($this->casioSalarySetting->id, $data->excludedSettingIds);
    }

    /** @test */
    public function test_void_salary_transaction_not_included(): void
    {
        $payment = $this->makeBridgeAndPayment(
            $this->casio, $this->faisal, 'salary', 20000.0, 'sales',
            '2026-09-21', $this->casioSalarySetting
        );

        // Void the transaction
        ShopLedgerTransaction::where('shop_id', $this->casio->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['status' => TransactionStatus::Void->value]);

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertFalse($data->hasAnyData());
    }

    /** @test */
    public function test_funding_breakdown_includes_all_sources(): void
    {
        $this->makeBridgeAndPayment(
            $this->casio, $this->faisal, 'salary', 20000.0, 'sales',
            '2026-09-21', $this->casioSalarySetting
        );

        // Shafeek salary via petty
        ShopSalaryBridgeSetting::query()->updateOrCreate(
            ['shop_id' => $this->casio->id, 'transaction_type' => SalaryHrTransactionType::Salary->value],
            ['shop_ledger_entry_setting_id' => $this->casioSalarySetting->id, 'default_payment_mode' => 'sales_cash', 'allowed_payment_modes' => ['sales_cash', 'petty'], 'is_enabled' => true]
        );

        $pettyPayment = ShopStaffPayment::query()->create([
            'shop_id' => $this->casio->id,
            'employee_id' => $this->shafeek->id,
            'paid_by' => $this->actor->id,
            'paid_on' => '2026-09-21',
            'amount' => 5000.0,
            'payment_type' => 'salary',
            'fund_source' => 'petty',
            'status' => 'paid',
        ]);
        $this->projectionService->syncPayment($pettyPayment, $this->actor->id);

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertArrayHasKey('Sales Cash', $data->fundingBreakdown);
        $this->assertArrayHasKey('Petty', $data->fundingBreakdown);
        $this->assertSame(20000.0, $data->fundingBreakdown['Sales Cash']);
        $this->assertSame(5000.0, $data->fundingBreakdown['Petty']);
    }

    /** @test */
    public function test_grand_total_is_sum_of_all_types(): void
    {
        // Salary ₹20,000 + Advance ₹3,000 = ₹23,000
        $this->makeBridgeAndPayment(
            $this->casio, $this->faisal, 'salary', 20000.0, 'sales',
            '2026-09-21', $this->casioSalarySetting
        );
        $this->makeBridgeAndPayment(
            $this->casio, $this->shafeek, 'advance', 3000.0, 'sales',
            '2026-09-21', $this->casioAdvanceSetting
        );

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertEqualsWithDelta(23000.0, $data->grandTotal, 0.01);
    }

    /** @test */
    public function test_date_filter_daily_scope(): void
    {
        // Sep 21 salary
        $this->makeBridgeAndPayment(
            $this->casio, $this->faisal, 'salary', 20000.0, 'sales',
            '2026-09-21', $this->casioSalarySetting
        );

        // Sep 20 salary — should NOT appear when filtering by Sep 21
        ShopSalaryBridgeSetting::query()->updateOrCreate(
            ['shop_id' => $this->casio->id, 'transaction_type' => SalaryHrTransactionType::Salary->value],
            ['shop_ledger_entry_setting_id' => $this->casioSalarySetting->id, 'default_payment_mode' => 'sales_cash', 'allowed_payment_modes' => ['sales_cash'], 'is_enabled' => true]
        );

        $prevPayment = ShopStaffPayment::query()->create([
            'shop_id' => $this->casio->id,
            'employee_id' => $this->shafeek->id,
            'paid_by' => $this->actor->id,
            'paid_on' => '2026-09-20',
            'amount' => 8000.0,
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'status' => 'paid',
        ]);
        $this->projectionService->syncPayment($prevPayment, $this->actor->id);

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertCount(1, $data->groups[0]->transactions);
        $this->assertSame(20000.0, $data->grandTotal);
    }

    /** @test */
    public function test_date_filter_monthly_scope_includes_all_month_transactions(): void
    {
        $this->makeBridgeAndPayment(
            $this->casio, $this->faisal, 'salary', 20000.0, 'sales',
            '2026-09-15', $this->casioSalarySetting
        );

        ShopSalaryBridgeSetting::query()->updateOrCreate(
            ['shop_id' => $this->casio->id, 'transaction_type' => SalaryHrTransactionType::Salary->value],
            ['shop_ledger_entry_setting_id' => $this->casioSalarySetting->id, 'default_payment_mode' => 'sales_cash', 'allowed_payment_modes' => ['sales_cash'], 'is_enabled' => true]
        );

        $midPayment = ShopStaffPayment::query()->create([
            'shop_id' => $this->casio->id,
            'employee_id' => $this->shafeek->id,
            'paid_by' => $this->actor->id,
            'paid_on' => '2026-09-21',
            'amount' => 5000.0,
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'status' => 'paid',
        ]);
        $this->projectionService->syncPayment($midPayment, $this->actor->id);

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-01', '2026-09-30');

        $this->assertCount(2, $data->groups[0]->transactions);
        $this->assertEqualsWithDelta(25000.0, $data->grandTotal, 0.01);
    }

    /** @test */
    public function test_missing_bridge_mapping_produces_no_transactions(): void
    {
        // No bridge configured for any type — direct ledger tx won't exist either
        // (projection service returns null when bridge is null)
        // Result: empty section
        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertFalse($data->hasAnyData());
        $this->assertSame(0.0, $data->grandTotal);
        $this->assertSame([], $data->excludedTxIds);
    }

    /** @test */
    public function test_normal_expense_not_included_in_salary_section(): void
    {
        // Create a labour transaction directly (NOT reference_type = ShopStaffPayment)
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->casioExpenseSetting->entry_type_id,
            'business_date' => '2026-09-21',
            'amount' => 500.0,
            'direction' => 'out',
            'funding_source' => 'sales',
            'status' => 'posted',
            'reference_type' => null,
            'reference_id' => null,
        ]);

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        // Labour expense must not appear in the salary section
        $this->assertFalse($data->hasAnyData());
    }

    /** @test */
    public function test_excluded_setting_ids_contains_bridge_mapped_settings(): void
    {
        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->casio->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $this->casioSalarySetting->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash'],
            'is_enabled' => true,
        ]);

        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->casio->id,
            'transaction_type' => SalaryHrTransactionType::SalaryAdvance->value,
            'shop_ledger_entry_setting_id' => $this->casioAdvanceSetting->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash'],
            'is_enabled' => true,
        ]);

        $data = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertContains($this->casioSalarySetting->id, $data->excludedSettingIds);
        $this->assertContains($this->casioAdvanceSetting->id, $data->excludedSettingIds);
        $this->assertNotContains($this->casioExpenseSetting->id, $data->excludedSettingIds);
    }

    /** @test */
    public function test_fund_source_change_updates_label_in_salary_section(): void
    {
        $payment = $this->makeBridgeAndPayment(
            $this->casio, $this->faisal, 'salary', 20000.0, 'sales',
            '2026-09-21', $this->casioSalarySetting
        );

        $dataBefore = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');
        $this->assertSame('Sales Cash', $dataBefore->groups[0]->transactions[0]->fundingLabel);

        // Admin changes fund source: Sales → Petty (existing correction flow updates the tx)
        ShopLedgerTransaction::where('shop_id', $this->casio->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['funding_source' => 'petty']);

        $dataAfter = $this->salarySectionService->getSalarySection($this->casio, '2026-09-21', '2026-09-21');

        $this->assertSame('Petty', $dataAfter->groups[0]->transactions[0]->fundingLabel);
        // Still same transaction — no duplicate
        $this->assertCount(1, $dataAfter->groups[0]->transactions);
    }
}
