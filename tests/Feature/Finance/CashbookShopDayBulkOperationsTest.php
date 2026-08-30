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
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookShopDayBulkOperationsTest extends TestCase
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

    private LedgerEntryType $rentExpenseType;

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

        $this->rentExpenseType = LedgerEntryType::firstOrCreate(
            ['code' => 'rent_expense'],
            ['name' => 'Shop Rent Expense', 'category' => 'expense', 'display_order' => 30, 'active' => true, 'is_system' => true]
        );

        $shopPaidCompanyType = LedgerEntryType::firstOrCreate(
            ['code' => 'shop_paid_company'],
            ['name' => 'Shop Remittance to Company', 'category' => 'expense', 'display_order' => 99, 'active' => true, 'is_system' => true]
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
            'entry_type_id' => $this->cardType->id,
            'company_account_id' => $this->hdfcBank->id,
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
            'entry_type_id' => $this->cashSalesType->id,
            'company_account_id' => $this->cashBox->id,
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
            'entry_type_id' => $this->rentExpenseType->id,
            'company_account_id' => $this->kotakBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => false,
            'include_in_income' => false,
            'include_in_expense' => true,
            'include_in_pl' => true,
            'settlement_behavior' => 'decrease',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $shopPaidCompanyType->id,
            'company_account_id' => $this->kotakBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
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

    public function test_1_2_3_day_details_partitions_entries_into_sections(): void
    {
        $date = '2026-08-25';

        // 1. Posted entry (Paytm ₹14,280)
        $tx1 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 14280.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // 2. Approved / unverified entry (Card ₹2,421)
        $tx2 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->cardType->code,
            'amount' => 2421.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx2['transaction'], $this->admin->id);

        // 3. Verified entry (Cash ₹961)
        $tx3 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 961.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx3['transaction'], $this->admin->id);
        $stmt3 = CompanyAccountStatementEntry::where('source_id', $tx3['transaction']->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($stmt3, $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->sanaProfile->slug,
            'date' => $date,
        ]));

        $response->assertOk()
            ->assertSee('Review Collections (1)')
            ->assertSee('Approve for receipt tracking')
            ->assertSee('Confirm Company Receipt (1)')
            ->assertSee('Confirm received')
            ->assertSee('Received Collections (1)')
            ->assertSee('₹14,280.00')
            ->assertSee('₹2,421.00')
            ->assertSee('₹961.00');
    }

    public function test_4_5_6_7_8_9_admin_can_accept_selected_entries_atomically(): void
    {
        $date = '2026-08-25';

        $tx1 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $tx2 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->cardType->code,
            'amount' => 15000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $bankBalanceBefore = (float) $this->kotakBank->fresh()->current_balance;

        // Accept both selected entries
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.accept-selected', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$tx1['transaction']->id, $tx2['transaction']->id],
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.show', [
            'shop' => $this->sanaProfile->slug,
            'date' => $date,
        ]))->assertSessionHas('success');

        // Both transactions must be approved
        $this->assertSame('approved', $tx1['transaction']->fresh()->status);
        $this->assertSame('approved', $tx2['transaction']->fresh()->status);

        // Pending unfinalized statements must be created
        $stmt1 = CompanyAccountStatementEntry::where('source_id', $tx1['transaction']->id)->first();
        $stmt2 = CompanyAccountStatementEntry::where('source_id', $tx2['transaction']->id)->first();
        $this->assertNotNull($stmt1);
        $this->assertNotNull($stmt2);
        $this->assertFalse((bool) $stmt1->is_finalized);
        $this->assertFalse((bool) $stmt2->is_finalized);

        // Company balance must NOT increase upon acceptance
        $this->assertSame($bankBalanceBefore, (float) $this->kotakBank->fresh()->current_balance);

        // Outstanding settlement remains ₹25,000 (acceptance does not reduce outstanding)
        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, $date);
        $this->assertSame(25000.00, $summary['settlement_summary']['outstanding_to_settle']);
        $this->assertSame(0.00, $summary['company_receipt_status']['verified_received']);
        $this->assertSame(25000.00, $summary['company_receipt_status']['pending_verification']);
    }

    public function test_10_11_12_13_14_15_admin_can_verify_selected_entries_atomically(): void
    {
        $date = '2026-08-25';

        // 1. Record and approve Paytm ₹20,000 (mapped to Kotak Bank)
        $txPaytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 20000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txPaytm['transaction'], $this->admin->id);

        // 2. Record and approve Card ₹30,000 (mapped to HDFC Bank)
        $txCard = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->cardType->code,
            'amount' => 30000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txCard['transaction'], $this->admin->id);

        $kotakBalanceBefore = (float) $this->kotakBank->fresh()->current_balance;
        $hdfcBalanceBefore = (float) $this->hdfcBank->fresh()->current_balance;
        $journalCountBefore = JournalEntry::count();

        // Verify both selected entries
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.verify-selected', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$txPaytm['transaction']->id, $txCard['transaction']->id],
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.show', [
            'shop' => $this->sanaProfile->slug,
            'date' => $date,
        ]))->assertSessionHas('success');

        // Statements must be finalized and reconciled
        $stmtPaytm = CompanyAccountStatementEntry::where('source_id', $txPaytm['transaction']->id)->firstOrFail();
        $stmtCard = CompanyAccountStatementEntry::where('source_id', $txCard['transaction']->id)->firstOrFail();
        $this->assertTrue((bool) $stmtPaytm->is_finalized);
        $this->assertTrue((bool) $stmtCard->is_finalized);
        $this->assertSame('reconciled', $stmtPaytm->status);
        $this->assertSame('reconciled', $stmtCard->status);

        // Mapped company accounts must increase exactly by verified amounts
        $this->assertSame($kotakBalanceBefore + 20000.00, (float) $this->kotakBank->fresh()->current_balance);
        $this->assertSame($hdfcBalanceBefore + 30000.00, (float) $this->hdfcBank->fresh()->current_balance);

        // Balanced GL journals must be created
        $this->assertGreaterThan($journalCountBefore, JournalEntry::count());

        // Shop settlement outstanding must be 0.00
        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, $date);
        $this->assertSame(50000.00, $summary['settlement_summary']['verified_company_received']);
        $this->assertSame(0.00, $summary['settlement_summary']['outstanding_to_settle']);
    }

    public function test_16_17_18_19_full_batch_rolls_back_if_any_entry_is_invalid(): void
    {
        $date = '2026-08-25';

        // Valid Sana entry
        $txValid = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Other shop's entry
        $txOtherShop = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->otherShop->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Attempt bulk accept with mixed shop IDs
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.accept-selected', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$txValid['transaction']->id, $txOtherShop['transaction']->id],
        ]);

        $response->assertRedirect()->assertSessionHas('error');

        // Verify valid transaction was NOT accepted (atomic rollback)
        $this->assertSame('posted', $txValid['transaction']->fresh()->status);
        $this->assertSame('posted', $txOtherShop['transaction']->fresh()->status);
    }

    public function test_20_duplicate_submission_does_not_duplicate_accounting(): void
    {
        $date = '2026-08-25';

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 15000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        // 1st verify
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.verify-selected', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$tx['transaction']->id],
        ]);

        $kotakBalance = (float) $this->kotakBank->fresh()->current_balance;

        // 2nd duplicate verify attempt
        $res2 = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.verify-selected', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$tx['transaction']->id],
        ]);

        $res2->assertRedirect()->assertSessionHas('error');

        // Bank balance must remain identical (no double increment)
        $this->assertSame($kotakBalance, (float) $this->kotakBank->fresh()->current_balance);
    }

    public function test_21_non_admin_cannot_execute_bulk_actions(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->post(route('admin.cashbook.shop.day.accept-selected', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => '2026-08-25',
            'transaction_ids' => [1],
        ]);

        $response->assertForbidden();
    }

    public function test_22_23_validation_rules_reject_empty_and_oversized_batches(): void
    {
        // Empty batch
        $resEmpty = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.accept-selected', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => '2026-08-25',
            'transaction_ids' => [],
        ]);
        $resEmpty->assertSessionHasErrors('transaction_ids');

        // Oversized batch (> 100)
        $oversizedIds = range(1, 105);
        $resOver = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.accept-selected', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => '2026-08-25',
            'transaction_ids' => $oversizedIds,
        ]);
        $resOver->assertSessionHasErrors('transaction_ids');
    }

    public function test_24_25_day_becomes_complete_and_updates_monthly_row(): void
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

        // Initially Needs Acceptance
        $monthData1 = $this->moneyPositionService->getShopMonthlyDailySummaries($this->sana->id, '2026-08');
        $dayRow1 = collect($monthData1['days'])->firstWhere('business_date', $date);
        $this->assertSame('Needs Acceptance', $dayRow1['status']);

        // Accept
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.accept-selected', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$tx['transaction']->id],
        ]);

        $monthData2 = $this->moneyPositionService->getShopMonthlyDailySummaries($this->sana->id, '2026-08');
        $dayRow2 = collect($monthData2['days'])->firstWhere('business_date', $date);
        $this->assertSame('Pending Verification', $dayRow2['status']);

        // Verify
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.verify-selected', [
            'shop' => $this->sanaProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$tx['transaction']->id],
        ]);

        $monthData3 = $this->moneyPositionService->getShopMonthlyDailySummaries($this->sana->id, '2026-08');
        $dayRow3 = collect($monthData3['days'])->firstWhere('business_date', $date);
        $this->assertSame('Complete', $dayRow3['status']);
        $this->assertSame(0.00, $dayRow3['outstanding']);
    }

    public function test_28_loading_day_details_causes_zero_mutations(): void
    {
        $date = '2026-08-25';

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 12000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $txCountBefore = ShopLedgerTransaction::count();
        $stmtCountBefore = CompanyAccountStatementEntry::count();
        $bankBalanceBefore = (float) $this->kotakBank->fresh()->current_balance;

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->sanaProfile->slug,
            'date' => $date,
        ]));
        $response->assertOk();

        $this->assertSame($txCountBefore, ShopLedgerTransaction::count());
        $this->assertSame($stmtCountBefore, CompanyAccountStatementEntry::count());
        $this->assertSame($bankBalanceBefore, (float) $this->kotakBank->fresh()->current_balance);
    }
}
