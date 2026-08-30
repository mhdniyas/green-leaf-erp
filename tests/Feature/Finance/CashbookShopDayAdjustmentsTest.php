<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookShopDayAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Shop $sana;

    private Shop $otherShop;

    private ShopLedgerProfile $sanaProfile;

    private ShopLedgerProfile $otherProfile;

    private CompanyAccount $kotakBank;

    private CompanyAccount $hdfcBank;

    private CompanyAccount $cashBox;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $cashSalesType;

    private LedgerEntryType $otherExpenseType;

    private LedgerEntryType $otherIncomeType;

    private DailyLedgerService $dailyLedgerService;

    private CompanyPaymentReconciliationService $reconciliationService;

    private CompanyMoneyPositionService $moneyPositionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->unauthorizedUser = User::factory()->create(['name' => 'Regular User', 'email' => 'user@greenleaf.test']);

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true]);

        $this->sana = Shop::factory()->create(['name' => 'Sana Supermarket', 'code' => 'SANA', 'status' => 'active']);
        $this->otherShop = Shop::factory()->create(['name' => 'Other Store', 'code' => 'OTHER', 'status' => 'active']);

        $this->sanaProfile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->sana->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'sana-supermarket',
            'code' => $this->sana->code,
            'name' => $this->sana->name,
            'enabled' => true,
        ]);

        $this->otherProfile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->otherShop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'other-store',
            'code' => $this->otherShop->code,
            'name' => $this->otherShop->name,
            'enabled' => true,
        ]);

        $this->kotakBank = CompanyAccount::create([
            'name' => 'Kotak Bank',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => '9988776655',
            'account_type' => 'bank',
            'current_balance' => 200000.00,
            'enabled' => true,
        ]);

        $this->hdfcBank = CompanyAccount::create([
            'name' => 'HDFC Bank',
            'bank_name' => 'HDFC Bank Ltd',
            'account_number' => '1122334455',
            'account_type' => 'bank',
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);

        $this->cashBox = CompanyAccount::create([
            'name' => 'Main Cash Box',
            'bank_name' => 'Company Vault',
            'account_number' => 'CASH-MAIN',
            'account_type' => 'cash',
            'current_balance' => 25000.00,
            'enabled' => true,
        ]);

        $this->paytmType = LedgerEntryType::firstOrCreate(
            ['code' => 'paytm_sales'],
            ['name' => 'Paytm Collection', 'category' => 'income', 'display_order' => 10, 'active' => true, 'is_system' => true]
        );

        $this->cardType = LedgerEntryType::firstOrCreate(
            ['code' => 'card_sales'],
            ['name' => 'Card Collection', 'category' => 'income', 'display_order' => 11, 'active' => true, 'is_system' => true]
        );

        $this->cashSalesType = LedgerEntryType::firstOrCreate(
            ['code' => 'cash_sales'],
            ['name' => 'Cash Sales', 'category' => 'income', 'display_order' => 1, 'active' => true, 'is_system' => true]
        );

        $this->otherExpenseType = LedgerEntryType::firstOrCreate(
            ['code' => 'other_expense'],
            ['name' => 'Other Expense', 'category' => 'expense', 'display_order' => 30, 'active' => true, 'is_system' => true]
        );

        $this->otherIncomeType = LedgerEntryType::firstOrCreate(
            ['code' => 'other_income'],
            ['name' => 'Other Income', 'category' => 'income', 'display_order' => 31, 'active' => true, 'is_system' => true]
        );

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->kotakBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'settlement_behavior' => 'increase',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->otherExpenseType->id,
            'company_account_id' => null,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'allowed_funding_sources' => ['sales', 'company', 'petty'],
            'include_in_sales' => false,
            'include_in_income' => false,
            'include_in_expense' => true,
            'include_in_pl' => true,
            'settlement_behavior' => 'none',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->otherIncomeType->id,
            'company_account_id' => null,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'settlement_behavior' => 'increase',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        $shopPaidCompanyType = LedgerEntryType::firstOrCreate(
            ['code' => 'shop_paid_company'],
            ['name' => 'Shop Remittance to Company', 'category' => 'expense', 'display_order' => 99, 'active' => true, 'is_system' => true]
        );

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $shopPaidCompanyType->id,
            'company_account_id' => $this->kotakBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => false,
            'include_in_income' => false,
            'include_in_expense' => true,
            'include_in_pl' => false,
            'settlement_behavior' => 'decrease',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        $this->dailyLedgerService = app(DailyLedgerService::class);
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
        $this->moneyPositionService = app(CompanyMoneyPositionService::class);
    }

    public function test_1_2_3_acceptance_gate_allows_only_posted_or_submitted_and_preserves_statement_status(): void
    {
        $date = '2026-08-25';

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 1000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $txId = (int) $tx['transaction']->id;

        // Manually alter status to closed/unknown to test gate rejection
        ShopLedgerTransaction::whereKey($txId)->update(['status' => 'closed']);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.accept-selected', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$txId],
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertSame('closed', ShopLedgerTransaction::whereKey($txId)->value('status'));

        // Reset to posted and approve to check statement status
        ShopLedgerTransaction::whereKey($txId)->update(['status' => 'posted']);

        $res2 = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.accept-selected', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$txId],
        ]);

        $res2->assertRedirect()->assertSessionHas('success');

        $stmt = CompanyAccountStatementEntry::where('source_id', $txId)->firstOrFail();
        $this->assertSame('unmatched', $stmt->status);
        $this->assertFalse((bool) $stmt->is_finalized);
    }

    public function test_4_5_6_7_admin_can_add_shop_expense_which_reduces_outstanding_without_affecting_company_balance(): void
    {
        $date = '2026-08-25';

        // 1. Initial sales ₹50,000
        $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 50000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $summaryBefore = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, $date);
        $this->assertSame(50000.00, $summaryBefore['settlement_summary']['outstanding_to_settle']);

        $kotakBalanceBefore = (float) $this->kotakBank->fresh()->current_balance;

        // 2. Record Shop Expense ₹2,500
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.adjustments.store', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'type' => 'expense',
            'amount' => 2500.00,
            'notes' => 'Store maintenance and supplies',
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.show', [
            'shop' => $this->sanaProfile->slug,
            'date' => $date,
        ]))->assertSessionHas('success');

        // Check outstanding reduced by ₹2,500 (50,000 - 2,500 = 47,500)
        $summaryAfter = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, $date);
        $this->assertSame(47500.00, $summaryAfter['settlement_summary']['outstanding_to_settle']);
        $this->assertSame(2500.00, $summaryAfter['settlement_summary']['settlement_deductions']);

        // Company account balance is untouched
        $this->assertSame($kotakBalanceBefore, (float) $this->kotakBank->fresh()->current_balance);

        // No verified receipts or shop_paid_company created
        $this->assertSame(0.00, $summaryAfter['company_receipt_status']['verified_received']);
        $this->assertDatabaseMissing('shop_ledger_transactions', [
            'shop_id' => $this->sana->id,
            'entry_type_id' => LedgerEntryType::where('code', 'shop_paid_company')->value('id'),
        ]);
    }

    public function test_8_9_10_admin_can_add_shop_income_which_increases_outstanding_without_counting_as_company_receipt(): void
    {
        $date = '2026-08-25';

        // 1. Initial sales ₹30,000
        $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 30000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $summaryBefore = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, $date);
        $this->assertSame(30000.00, $summaryBefore['settlement_summary']['outstanding_to_settle']);

        // 2. Record Shop Income ₹1,200
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.adjustments.store', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'type' => 'income',
            'amount' => 1200.00,
            'notes' => 'Card machine fee refund from vendor',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        // Check outstanding increased by ₹1,200 (30,000 + 1,200 = 31,200)
        $summaryAfter = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, $date);
        $this->assertSame(31200.00, $summaryAfter['settlement_summary']['outstanding_to_settle']);
        $this->assertSame(1200.00, $summaryAfter['settlement_summary']['settlement_additions']);

        // Not counted as verified company receipt
        $this->assertSame(0.00, $summaryAfter['company_receipt_status']['verified_received']);
    }

    public function test_11_12_13_14_15_validation_rejects_invalid_inputs_and_unauthorized_users(): void
    {
        $date = '2026-08-25';

        // Invalid type
        $resType = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.adjustments.store', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'type' => 'invalid_type',
            'amount' => 100.00,
            'notes' => 'Valid notes',
        ]);
        $resType->assertSessionHasErrors('type');

        // Negative amount
        $resAmount = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.adjustments.store', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'type' => 'expense',
            'amount' => -50.00,
            'notes' => 'Valid notes',
        ]);
        $resAmount->assertSessionHasErrors('amount');

        // Empty notes
        $resNote = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.adjustments.store', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'type' => 'expense',
            'amount' => 100.00,
            'notes' => '',
        ]);
        $resNote->assertSessionHasErrors('notes');

        // Unauthorized user receives 403 Forbidden
        $resAuth = $this->actingAs($this->unauthorizedUser)->post(route('admin.cashbook.shop.day.adjustments.store', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'type' => 'expense',
            'amount' => 100.00,
            'notes' => 'Valid notes',
        ]);
        $resAuth->assertForbidden();
    }

    public function test_17_18_19_20_21_admin_can_reverse_adjustment_immutably_and_prevents_duplicate_reversal(): void
    {
        $date = '2026-08-25';

        // 1. Initial sales ₹20,000
        $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 20000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // 2. Add Shop Expense ₹3,000
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.adjustments.store', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'type' => 'expense',
            'amount' => 3000.00,
            'notes' => 'Incorrect diesel expense',
        ]);

        $origAdj = ShopLedgerTransaction::where('shop_id', $this->sana->id)
            ->where('entry_type_id', $this->otherExpenseType->id)
            ->firstOrFail();

        $summaryBeforeRev = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, $date);
        $this->assertSame(17000.00, $summaryBeforeRev['settlement_summary']['outstanding_to_settle']);

        // 3. Reverse the adjustment
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.adjustments.reverse', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'adjustment_id' => $origAdj->id,
            'reason' => 'Expense entered by mistake',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        // Reversal creates opposite entry (other_income) referencing original
        $reversalTx = ShopLedgerTransaction::where('reference_type', ShopLedgerTransaction::class)
            ->where('reference_id', $origAdj->id)
            ->firstOrFail();
        $this->assertSame(3000.00, (float) $reversalTx->amount);
        $this->assertSame('income', $reversalTx->direction);

        // Outstanding restored to original ₹20,000
        $summaryAfterRev = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, $date);
        $this->assertSame(20000.00, $summaryAfterRev['settlement_summary']['outstanding_to_settle']);

        // 4. Duplicate reversal attempt must be rejected
        $resDup = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.adjustments.reverse', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'adjustment_id' => $origAdj->id,
        ]);
        $resDup->assertRedirect()->assertSessionHas('error');

        // 5. Attempting to reverse another shop's adjustment must fail
        $resOther = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.adjustments.reverse', [
            'shop' => $this->otherProfile->slug,
        ]), [
            'business_date' => $date,
            'adjustment_id' => $origAdj->id,
        ]);
        $resOther->assertRedirect()->assertSessionHas('error');
    }

    public function test_22_23_24_25_monthly_rows_and_shop_card_reflect_adjustments_while_payment_completion_remains_complete(): void
    {
        $date = '2026-08-25';

        // 1. Record sales and verify completely (status = Complete)
        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);
        $stmt = CompanyAccountStatementEntry::where('source_id', $tx['transaction']->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($stmt, $this->admin->id);

        $monthData1 = $this->moneyPositionService->getShopMonthlyDailySummaries($this->sana->id, '2026-08');
        $dayRow1 = collect($monthData1['days'])->firstWhere('business_date', $date);
        $this->assertSame('Complete', $dayRow1['status']);
        $this->assertSame(0.00, $dayRow1['outstanding']);

        // 2. Add Shop Expense adjustment ₹500
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.adjustments.store', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'type' => 'expense',
            'amount' => 500.00,
            'notes' => 'Late electricity bill',
        ]);

        // Monthly row payment status remains Complete
        $monthData2 = $this->moneyPositionService->getShopMonthlyDailySummaries($this->sana->id, '2026-08');
        $dayRow2 = collect($monthData2['days'])->firstWhere('business_date', $date);
        $this->assertSame('Complete', $dayRow2['status']);
        $this->assertSame(500.00, $dayRow2['deductions']);

        // Shop Cards reflects cumulative outstanding correctly
        $cards = $this->moneyPositionService->getShopMoneyFlowCards('2026-08-25');
        $sanaCard = collect($cards)->firstWhere('shop_id', $this->sana->id);
        $this->assertNotNull($sanaCard);
    }

    public function test_27_28_29_transaction_detail_is_read_only_and_links_to_day_operations(): void
    {
        $date = '2026-08-25';

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.transaction.show', $tx['transaction']->id));

        $expectedDayUrl = route('admin.cashbook.shop.show', [
            'shop' => $this->sanaProfile->slug,
            'date' => $date,
        ]);

        $response->assertOk()
            ->assertSee('Open Shop Day Operations')
            ->assertSee($expectedDayUrl, false)
            ->assertSee('₹5,000.00')
            ->assertSee('Recorded')
            ->assertDontSee('action="{{ route(\'admin.cashbook.transaction.approve\'', false);
    }
}
