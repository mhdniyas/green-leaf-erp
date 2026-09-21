<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

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
use App\Services\Cashbook\StaffPaymentCashbookProjectionService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffSalarySyncBridgeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shopCasio;

    private Shop $shopSana;

    private Employee $employee;

    private ShopLedgerEntrySetting $casioSalarySetting1;

    private ShopLedgerEntrySetting $casioSalarySetting2;

    private ShopLedgerEntrySetting $casioAdvanceSetting;

    private ShopLedgerEntrySetting $sanaSalarySetting;

    private StaffPaymentCashbookProjectionService $projectionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shopCasio = Shop::query()->create([
            'name' => 'Casio Shop',
            'code' => 'CASIO',
            'warehouse_tag' => 'CS',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->shopSana = Shop::query()->create([
            'name' => 'Sana Shop',
            'code' => 'SANA',
            'warehouse_tag' => 'SN',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        ShopLedgerProfile::query()->create([
            'shop_id' => $this->shopCasio->id,
            'name' => $this->shopCasio->name,
            'code' => $this->shopCasio->code,
            'slug' => 'casio',
        ]);

        ShopLedgerProfile::query()->create([
            'shop_id' => $this->shopSana->id,
            'name' => $this->shopSana->name,
            'code' => $this->shopSana->code,
            'slug' => 'sana',
        ]);

        $this->employee = Employee::factory()->create([
            'name' => 'Rahul Sharma',
        ]);

        $salaryType = LedgerEntryType::query()->where('code', 'salary')->firstOrFail();
        $advanceType = LedgerEntryType::query()->where('code', 'staff_advance')->firstOrFail();
        $labourType = LedgerEntryType::query()->where('code', 'labour')->firstOrFail();

        // Casio Category 1: Staff Salary
        $this->casioSalarySetting1 = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shopCasio->id,
            'entry_type_id' => $salaryType->id,
            'display_name' => 'Staff Salary (Primary)',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_expense' => true,
            'effective_from' => '2026-01-01',
            'version' => 1,
        ]);

        // Casio Category 2: Employee Wages (under labour)
        $this->casioSalarySetting2 = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shopCasio->id,
            'entry_type_id' => $labourType->id,
            'display_name' => 'Employee Wages (Secondary)',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_expense' => true,
            'effective_from' => '2026-01-01',
            'version' => 1,
        ]);

        // Casio Advance
        $this->casioAdvanceSetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shopCasio->id,
            'entry_type_id' => $advanceType->id,
            'display_name' => 'Staff Advance Payout',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_expense' => true,
            'effective_from' => '2026-01-01',
            'version' => 1,
        ]);

        // Sana Category
        $this->sanaSalarySetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shopSana->id,
            'entry_type_id' => $salaryType->id,
            'display_name' => 'Sana Staff Salary',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_expense' => true,
            'effective_from' => '2026-01-01',
            'version' => 1,
        ]);

        $this->projectionService = app(StaffPaymentCashbookProjectionService::class);
    }

    public function test_new_salary_uses_configured_salary_bridge_category(): void
    {
        // Configure Casio Salary Bridge -> Category 1
        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shopCasio->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $this->casioSalarySetting1->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash'],
            'is_enabled' => true,
        ]);

        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopCasio->id,
            'employee_id' => $this->employee->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-10',
            'amount' => 3000.00,
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'status' => 'paid',
        ]);

        $tx = $this->projectionService->syncPayment($payment, $this->admin->id);

        $this->assertInstanceOf(ShopLedgerTransaction::class, $tx);
        $this->assertSame((int) $this->shopCasio->id, (int) $tx->shop_id);
        $this->assertSame((int) $this->casioSalarySetting1->entry_type_id, (int) $tx->entry_type_id);
        $this->assertEquals(3000.00, (float) $tx->amount);
        $this->assertSame('2026-09-10', $tx->business_date->toDateString());
        $this->assertSame(TransactionStatus::Posted->value, $tx->status);
    }

    public function test_salary_update_from_3000_to_4000_updates_same_transaction_without_duplicates(): void
    {
        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shopCasio->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $this->casioSalarySetting1->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash'],
            'is_enabled' => true,
        ]);

        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopCasio->id,
            'employee_id' => $this->employee->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-10',
            'amount' => 3000.00,
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'status' => 'paid',
        ]);

        $tx1 = $this->projectionService->syncPayment($payment, $this->admin->id);
        $this->assertSame(1, ShopLedgerTransaction::where('reference_type', ShopStaffPayment::class)->count());
        $this->assertEquals(3000.00, (float) $tx1->amount);

        // HR updates salary: ₹3,000 -> ₹4,000
        $payment->amount = 4000.00;
        $payment->save();

        $tx2 = $this->projectionService->syncPayment($payment, $this->admin->id);

        // Must be the exact same transaction ID and no duplicate rows
        $this->assertSame($tx1->id, $tx2->id);
        $this->assertSame(1, ShopLedgerTransaction::where('reference_type', ShopStaffPayment::class)->count());
        $this->assertEquals(4000.00, (float) $tx2->fresh()->amount);
    }

    public function test_bridge_category_changed_afterward_does_not_reclassify_existing_transaction(): void
    {
        // 1. Initial configuration: Category 1
        $bridge = ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shopCasio->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $this->casioSalarySetting1->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash'],
            'is_enabled' => true,
        ]);

        $payment1 = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopCasio->id,
            'employee_id' => $this->employee->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-10',
            'amount' => 3000.00,
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'status' => 'paid',
        ]);

        $tx1 = $this->projectionService->syncPayment($payment1, $this->admin->id);
        $this->assertSame((int) $this->casioSalarySetting1->entry_type_id, (int) $tx1->entry_type_id);

        // 2. Admin later changes Salary Bridge to Category 2
        $bridge->shop_ledger_entry_setting_id = $this->casioSalarySetting2->id;
        $bridge->save();

        // 3. Existing payment is synced/updated
        $payment1->amount = 3500.00;
        $payment1->save();
        $this->projectionService->syncPayment($payment1, $this->admin->id);

        // Existing transaction MUST retain Category 1 (not silently reclassified)
        $this->assertSame((int) $this->casioSalarySetting1->entry_type_id, (int) $tx1->fresh()->entry_type_id);
        $this->assertEquals(3500.00, (float) $tx1->fresh()->amount);
    }

    public function test_new_salary_after_bridge_change_uses_new_category(): void
    {
        // 1. Initial configuration: Category 1
        $bridge = ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shopCasio->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $this->casioSalarySetting1->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash'],
            'is_enabled' => true,
        ]);

        // 2. Admin later changes Salary Bridge to Category 2
        $bridge->shop_ledger_entry_setting_id = $this->casioSalarySetting2->id;
        $bridge->save();

        // 3. A brand new salary payment created after bridge change MUST use the new Category 2
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopCasio->id,
            'employee_id' => $this->employee->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-15',
            'amount' => 5000.00,
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'status' => 'paid',
        ]);

        $tx = $this->projectionService->syncPayment($payment, $this->admin->id);
        $this->assertSame((int) $this->casioSalarySetting2->entry_type_id, (int) $tx->entry_type_id);
    }

    public function test_missing_salary_mapping_skips_safely_without_guessing(): void
    {
        // Bridge is NOT configured for Casio Salary
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopCasio->id,
            'employee_id' => $this->employee->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-10',
            'amount' => 3000.00,
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'status' => 'paid',
        ]);

        $tx = $this->projectionService->syncPayment($payment, $this->admin->id);

        // Must return null and create zero cashbook rows
        $this->assertNull($tx);
        $this->assertSame(0, ShopLedgerTransaction::where('reference_type', ShopStaffPayment::class)->count());

        // Audit sync reports as skipped
        $results = $this->projectionService->syncAndAuditShopPayments((int) $this->shopCasio->id, '2026-09-10');
        $this->assertCount(1, $results['skipped']);
        $this->assertStringContainsString('category is not configured', $results['skipped'][0]['reason']);
    }

    public function test_deleted_or_cancelled_hr_payment_removes_financial_effect(): void
    {
        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shopCasio->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $this->casioSalarySetting1->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash'],
            'is_enabled' => true,
        ]);

        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopCasio->id,
            'employee_id' => $this->employee->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-10',
            'amount' => 3000.00,
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'status' => 'paid',
        ]);

        $tx = $this->projectionService->syncPayment($payment, $this->admin->id);
        $this->assertSame(TransactionStatus::Posted->value, $tx->status);

        // Cancel the payment in HR
        $payment->status = 'cancelled';
        $payment->save();

        $voidedTx = $this->projectionService->syncPayment($payment, $this->admin->id);
        $this->assertSame(TransactionStatus::Void->value, $voidedTx->status);
        $this->assertEquals(0.0, (float) $voidedTx->pl_delta);
    }

    public function test_manual_sync_cleans_only_genuine_shop_staff_payment_orphans(): void
    {
        // 1. Create an orphan Cashbook transaction referencing a non-existent payment ID 9999
        $orphanTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shopCasio->id,
            'entry_type_id' => $this->casioSalarySetting1->entry_type_id,
            'business_date' => '2026-09-10',
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => 9999,
            'amount' => 2500.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => TransactionStatus::Posted->value,
        ]);

        // 2. Create another unrelated transaction (not a staff payment projection)
        $unrelatedTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shopCasio->id,
            'entry_type_id' => $this->casioSalarySetting1->entry_type_id,
            'business_date' => '2026-09-10',
            'reference_type' => null,
            'reference_id' => null,
            'amount' => 1000.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => TransactionStatus::Posted->value,
        ]);

        // 3. Run sync and audit
        $results = $this->projectionService->syncAndAuditShopPayments((int) $this->shopCasio->id, '2026-09-10');

        $this->assertCount(1, $results['orphans']);
        $this->assertSame($orphanTx->id, $results['orphans'][0]['transaction_id']);

        // Orphan is deleted, unrelated transaction remains intact
        $this->assertDatabaseMissing('shop_ledger_transactions', ['id' => $orphanTx->id]);
        $this->assertDatabaseHas('shop_ledger_transactions', ['id' => $unrelatedTx->id]);
    }

    public function test_payment_date_change_recalculates_both_old_and_new_dates(): void
    {
        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shopCasio->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $this->casioSalarySetting1->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash'],
            'is_enabled' => true,
        ]);

        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopCasio->id,
            'employee_id' => $this->employee->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-10',
            'amount' => 3000.00,
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'status' => 'paid',
        ]);

        $tx = $this->projectionService->syncPayment($payment, $this->admin->id);
        $this->assertSame('2026-09-10', $tx->business_date->toDateString());

        // Change date to 2026-09-12
        $payment->paid_on = '2026-09-12';
        $payment->save();

        $updatedTx = $this->projectionService->syncPayment($payment, $this->admin->id);
        $this->assertSame('2026-09-12', $updatedTx->fresh()->business_date->toDateString());
        $this->assertSame($tx->id, $updatedTx->id);
    }

    public function test_shop_change_clears_old_shop_and_creates_projection_using_new_shop_bridge(): void
    {
        // Casio Bridge -> Casio Category 1
        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shopCasio->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $this->casioSalarySetting1->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash'],
            'is_enabled' => true,
        ]);

        // Sana Bridge -> Sana Category
        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shopSana->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $this->sanaSalarySetting->id,
            'default_payment_mode' => 'sales_cash',
            'allowed_payment_modes' => ['sales_cash'],
            'is_enabled' => true,
        ]);

        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopCasio->id,
            'employee_id' => $this->employee->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-10',
            'amount' => 3000.00,
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'status' => 'paid',
        ]);

        $txCasio = $this->projectionService->syncPayment($payment, $this->admin->id);
        $this->assertSame((int) $this->shopCasio->id, (int) $txCasio->shop_id);
        $this->assertSame((int) $this->casioSalarySetting1->entry_type_id, (int) $txCasio->entry_type_id);

        // Move payment from Casio -> Sana
        $payment->shop_id = $this->shopSana->id;
        $payment->save();

        $txSana = $this->projectionService->syncPayment($payment, $this->admin->id);

        // Old Casio transaction must be gone
        $this->assertDatabaseMissing('shop_ledger_transactions', ['id' => $txCasio->id]);

        // New Sana transaction must exist with Sana's category
        $this->assertInstanceOf(ShopLedgerTransaction::class, $txSana);
        $this->assertSame((int) $this->shopSana->id, (int) $txSana->shop_id);
        $this->assertSame((int) $this->sanaSalarySetting->entry_type_id, (int) $txSana->entry_type_id);
    }

    public function test_payment_mode_and_default_settlement_is_not_executed_in_step_2(): void
    {
        // Configure Bridge with company_payable mode and settlement ID
        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shopCasio->id,
            'transaction_type' => SalaryHrTransactionType::Salary->value,
            'shop_ledger_entry_setting_id' => $this->casioSalarySetting1->id,
            'default_payment_mode' => 'company_payable',
            'allowed_payment_modes' => ['company_payable'],
            'is_enabled' => true,
        ]);

        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shopCasio->id,
            'employee_id' => $this->employee->id,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-09-10',
            'amount' => 3000.00,
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'status' => 'paid',
        ]);

        $tx = $this->projectionService->syncPayment($payment, $this->admin->id);

        // Transaction is created and mapped to entry_type_id
        $this->assertInstanceOf(ShopLedgerTransaction::class, $tx);
        $this->assertSame((int) $this->casioSalarySetting1->entry_type_id, (int) $tx->entry_type_id);

        // Preserves payment fund_source without executing/allocating company payable settlements in Step 2
        $this->assertSame('sales', $tx->funding_source);
    }
}
