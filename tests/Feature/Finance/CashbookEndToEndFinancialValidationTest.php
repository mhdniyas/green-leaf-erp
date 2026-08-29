<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashbookEndToEndFinancialValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shopA;

    private Shop $shopB;

    private CompanyAccount $accountA;

    private CompanyAccount $accountB;

    private CompanyAccount $cashBox;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $cashSalesType;

    private LedgerEntryType $rentExpenseType;

    private LedgerEntryType $vehicleExpenseType;

    private LedgerEntryType $pettyTransferType;

    private LedgerEntryType $shopPaidCompanyType;

    private DailyLedgerService $dailyLedgerService;

    private CompanyPaymentReconciliationService $reconciliationService;

    private CompanyMoneyPositionService $moneyPositionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true]);

        $this->shopA = Shop::factory()->create(['name' => 'Shop A', 'code' => 'SHOP-A', 'status' => 'active']);
        $this->shopB = Shop::factory()->create(['name' => 'Shop B', 'code' => 'SHOP-B', 'status' => 'active']);

        $this->accountA = CompanyAccount::create([
            'name' => 'Account A (Kotak)',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => 'ACC-A-1001',
            'account_type' => 'bank',
            'current_balance' => 0.00,
            'enabled' => true,
        ]);

        $this->accountB = CompanyAccount::create([
            'name' => 'Account B (HDFC)',
            'bank_name' => 'HDFC Bank',
            'account_number' => 'ACC-B-2002',
            'account_type' => 'bank',
            'current_balance' => 0.00,
            'enabled' => true,
        ]);

        $this->cashBox = CompanyAccount::create([
            'name' => 'Company Cash Box',
            'bank_name' => 'Company Main Vault',
            'account_number' => 'CASH-MAIN',
            'account_type' => 'cash',
            'current_balance' => 0.00,
            'enabled' => true,
        ]);

        $this->paytmType = LedgerEntryType::firstOrCreate(
            ['code' => 'paytm_sales'],
            [
                'name' => 'Paytm Collection',
                'category' => 'income',
                'display_order' => 10,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->cardType = LedgerEntryType::firstOrCreate(
            ['code' => 'card_sales'],
            [
                'name' => 'Card Collection',
                'category' => 'income',
                'display_order' => 11,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->cashSalesType = LedgerEntryType::firstOrCreate(
            ['code' => 'cash_sales'],
            [
                'name' => 'Cash Sales',
                'category' => 'income',
                'display_order' => 1,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->rentExpenseType = LedgerEntryType::firstOrCreate(
            ['code' => 'rent_expense'],
            [
                'name' => 'Rent Expense',
                'category' => 'expense',
                'display_order' => 30,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->vehicleExpenseType = LedgerEntryType::firstOrCreate(
            ['code' => 'vehicle_expense'],
            [
                'name' => 'Vehicle Expense',
                'category' => 'expense',
                'display_order' => 31,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->pettyTransferType = LedgerEntryType::firstOrCreate(
            ['code' => 'petty_transfer'],
            [
                'name' => 'Sales to Petty Cash',
                'category' => 'expense',
                'display_order' => 32,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->shopPaidCompanyType = LedgerEntryType::firstOrCreate(
            ['code' => 'shop_paid_company'],
            [
                'name' => 'Shop Remittance to Company',
                'category' => 'expense',
                'display_order' => 99,
                'active' => true,
                'is_system' => true,
            ]
        );

        // Settings for Shop A: Paytm -> Account A, Card -> Account B, Cash -> Cash Box
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->accountA->id,
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
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $this->cardType->id,
            'company_account_id' => $this->accountB->id,
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
            'shop_id' => $this->shopA->id,
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
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $this->rentExpenseType->id,
            'company_account_id' => $this->accountA->id,
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
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $this->vehicleExpenseType->id,
            'company_account_id' => $this->accountA->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'company',
            'include_in_sales' => false,
            'include_in_income' => false,
            'include_in_expense' => true,
            'include_in_pl' => true,
            'settlement_behavior' => 'none',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $this->pettyTransferType->id,
            'company_account_id' => $this->cashBox->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => false,
            'include_in_income' => false,
            'include_in_expense' => true,
            'include_in_pl' => false,
            'settlement_behavior' => 'decrease',
            'petty_behavior' => 'increase',
            'company_pending_behavior' => 'none',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $this->shopPaidCompanyType->id,
            'company_account_id' => $this->accountA->id,
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

        // Settings for Shop B: Paytm -> Account B
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopB->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->accountB->id,
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
            'shop_id' => $this->shopB->id,
            'entry_type_id' => $this->shopPaidCompanyType->id,
            'company_account_id' => $this->accountB->id,
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

    /**
     * SCENARIO A: MIXED ONLINE + CASH SHOP DAY
     * Step A1 -> A7 progressive validation.
     */
    public function test_scenario_a_mixed_online_and_cash_lifecycle(): void
    {
        $businessDate = '2026-08-29';

        // ── STEP A1: RECORD Gross Sales ₹60,000 (Paytm 30k, Card 10k, Cash 20k) ──
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 30000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cardType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 20000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Verify A1 Invariants
        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->shopA->id)->whereDate('business_date', $businessDate)->firstOrFail();
        $this->assertSame(60000.00, (float) $snapshot->closing_shop_position);
        $this->assertSame(0.00, (float) $this->accountA->fresh()->current_balance);
        $this->assertSame(0.00, (float) $this->accountB->fresh()->current_balance);
        $this->assertSame(0.00, (float) $this->cashBox->fresh()->current_balance);
        $this->assertSame(0, CompanyAccountStatementEntry::count());

        // ── STEP A2: APPROVE PAYTM ₹30,000 ──
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $stmtPaytm = CompanyAccountStatementEntry::where('source_id', $paytm['transaction']->id)->firstOrFail();
        $this->assertFalse((bool) $stmtPaytm->is_finalized);
        $this->assertSame('unmatched', $stmtPaytm->status);
        $this->assertSame($this->accountA->id, $stmtPaytm->company_account_id);
        $this->assertSame(0.00, (float) $this->accountA->fresh()->current_balance);
        $this->assertSame(60000.00, (float) $snapshot->fresh()->closing_shop_position);

        // ── STEP A3: VERIFY PAYTM ₹30,000 ──
        $this->reconciliationService->verifyPendingShopCollection($stmtPaytm, $this->admin->id);

        $this->assertSame(30000.00, (float) $this->accountA->fresh()->current_balance);
        $this->assertTrue((bool) $stmtPaytm->fresh()->is_finalized);
        $this->assertSame('reconciled', $stmtPaytm->fresh()->status);
        $this->assertSame(30000.00, (float) $snapshot->fresh()->closing_shop_position);

        // ── STEP A4: APPROVE CARD ₹10,000 ──
        $this->dailyLedgerService->approveEntry($card['transaction'], $this->admin->id);

        $stmtCard = CompanyAccountStatementEntry::where('source_id', $card['transaction']->id)->firstOrFail();
        $this->assertFalse((bool) $stmtCard->is_finalized);
        $this->assertSame($this->accountB->id, $stmtCard->company_account_id);
        $this->assertSame(0.00, (float) $this->accountB->fresh()->current_balance);
        $this->assertSame(30000.00, (float) $snapshot->fresh()->closing_shop_position);

        // ── STEP A5: VERIFY CARD ₹10,000 ──
        $this->reconciliationService->verifyPendingShopCollection($stmtCard, $this->admin->id);

        $this->assertSame(10000.00, (float) $this->accountB->fresh()->current_balance);
        $this->assertSame(20000.00, (float) $snapshot->fresh()->closing_shop_position);

        // ── STEP A6: APPROVE CASH ₹20,000 ──
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        $stmtCash = CompanyAccountStatementEntry::where('source_id', $cash['transaction']->id)->firstOrFail();
        $this->assertFalse((bool) $stmtCash->is_finalized);
        $this->assertSame($this->cashBox->id, $stmtCash->company_account_id);
        $this->assertSame(0.00, (float) $this->cashBox->fresh()->current_balance);
        $this->assertSame(20000.00, (float) $snapshot->fresh()->closing_shop_position);

        // ── STEP A7: VERIFY CASH RECEIVED ₹20,000 ──
        $this->reconciliationService->verifyPendingShopCollection($stmtCash, $this->admin->id);

        $this->assertSame(20000.00, (float) $this->cashBox->fresh()->current_balance);
        $this->assertSame(0.00, (float) $snapshot->fresh()->closing_shop_position);

        // Final Consolidated Assertion
        $moneySummary = $this->moneyPositionService->getMoneyPositionSummary($businessDate);
        $this->assertSame(60000.00, $moneySummary['verified_company_money']);
        $this->assertSame(0.00, $moneySummary['expected_in_transit_money']);
        $this->assertSame(0.00, $moneySummary['cash_with_shops']['total_cash_with_shops']);
    }

    /**
     * SCENARIO B: SETTLEMENT DEDUCTIONS & PAYABLE DECOMPOSITION
     */
    public function test_scenario_b_settlement_deductions_and_payable_decomposition(): void
    {
        $businessDate = '2026-08-29';

        // 1. Sales: Cash ₹20,000, Paytm ₹25,000, Card ₹5,000 (Total ₹50,000)
        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 20000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 25000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cardType->code,
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($card['transaction'], $this->admin->id);

        // 2. Rent from Sales ₹5,000
        $rent = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->rentExpenseType->code,
            'amount' => 5000.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($rent['transaction'], $this->admin->id);

        // 3. Verify Paytm ₹25,000
        $stmtPaytm = CompanyAccountStatementEntry::where('source_id', $paytm['transaction']->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($stmtPaytm, $this->admin->id);

        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shopA->id, $businessDate);

        // Gross Sales = ₹50,000
        $this->assertSame(50000.00, $summary['gross_sales']);
        // Settlement Deductions = ₹5,000
        $this->assertSame(5000.00, $summary['total_deductions']);
        // Expected Payable = 50,000 - 5,000 = ₹45,000
        $this->assertSame(45000.00, $summary['settlement_summary']['expected_payable']);
        // Verified Company Received = ₹25,000
        $this->assertSame(25000.00, $summary['settlement_summary']['verified_company_received']);
        // Outstanding = 45,000 - 25,000 = ₹20,000
        $this->assertSame(20000.00, $summary['settlement_summary']['outstanding_to_settle']);

        // Decomposition:
        // Pending Bank (Card) = ₹5,000
        // Physical Cash Remaining With Shop = Gross Cash (20k) - Rent (5k) = ₹15,000
        // Total Unsettled = 5,000 + 15,000 = ₹20,000 (Matches Outstanding!)
        $this->assertSame(5000.00, $summary['company_receipt_status']['pending_verification']);
        $this->assertSame(15000.00, $summary['company_receipt_status']['cash_still_with_shop']);
        $this->assertSame(
            $summary['settlement_summary']['outstanding_to_settle'],
            round($summary['company_receipt_status']['pending_verification'] + $summary['company_receipt_status']['cash_still_with_shop'], 2)
        );
    }

    /**
     * SCENARIO C: COMPANY-PAID EXPENSE
     */
    public function test_scenario_c_company_paid_expense_does_not_reduce_shop_payable(): void
    {
        $businessDate = '2026-08-29';

        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 50000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        $vehicle = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->vehicleExpenseType->code,
            'amount' => 3000.00,
            'funding_source' => 'company',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($vehicle['transaction'], $this->admin->id);

        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->shopA->id)->whereDate('business_date', $businessDate)->firstOrFail();
        $this->assertSame(50000.00, (float) $snapshot->closing_shop_position);

        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shopA->id, $businessDate);
        $this->assertSame(50000.00, $summary['settlement_summary']['expected_payable']);
        $this->assertSame(0.00, $summary['total_deductions']);
    }

    /**
     * SCENARIO D: PETTY TRANSFER (SALES TO PETTY CASH)
     */
    public function test_scenario_d_petty_transfer_from_sales_reduces_payable_and_increases_petty(): void
    {
        $businessDate = '2026-08-29';

        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 50000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        $petty = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->pettyTransferType->code,
            'amount' => 2000.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($petty['transaction'], $this->admin->id);

        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->shopA->id)->whereDate('business_date', $businessDate)->firstOrFail();
        // Closing shop position = 50k - 2k = 48k
        $this->assertSame(48000.00, (float) $snapshot->closing_shop_position);
        // Closing petty = 2k
        $this->assertSame(2000.00, (float) $snapshot->closing_petty);
    }

    /**
     * SCENARIO E: CHEQUES (FLOATING, CLEARED, REJECTED)
     */
    public function test_scenario_e_cheque_lifecycle(): void
    {
        $invoice = ShopInvoice::factory()->create(['shop_id' => $this->shopA->id]);

        $cheque = ShopInvoicePaymentRequest::create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->shopA->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => 'SUB-E-1',
            'request_type' => 'custom',
            'payment_method' => 'cheque',
            'payment_date' => '2026-08-29',
            'requested_amount' => 40000.00,
            'floating_amount' => 40000.00,
            'status' => 'pending',
            'cheque_status' => 'pending',
        ]);

        $summary = $this->moneyPositionService->getFloatingChequesSummary('2026-08-29');
        $this->assertSame(40000.00, $summary['total_floating']);
        $this->assertSame(0.00, $summary['cleared_today']);

        // Mark Cleared
        $cheque->update([
            'cheque_status' => 'cleared',
            'status' => 'approved',
            'reconciled_amount' => 40000.00,
            'floating_amount' => 0.00,
            'reconciliation_status' => 'reconciled',
        ]);

        $summaryCleared = $this->moneyPositionService->getFloatingChequesSummary('2026-08-29');
        $this->assertSame(0.00, $summaryCleared['total_floating']);
        $this->assertSame(40000.00, $summaryCleared['cleared_today']);

        // Rejected Cheque
        $rejectedCheque = ShopInvoicePaymentRequest::create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->shopA->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => 'SUB-E-2',
            'request_type' => 'custom',
            'payment_method' => 'cheque',
            'payment_date' => '2026-08-29',
            'requested_amount' => 15000.00,
            'floating_amount' => 0.00,
            'status' => 'rejected',
            'cheque_status' => 'rejected',
        ]);

        $summaryRejected = $this->moneyPositionService->getFloatingChequesSummary('2026-08-29');
        $this->assertSame(15000.00, $summaryRejected['rejected_total']);
        $this->assertSame(0.00, $summaryRejected['total_floating']);
    }

    /**
     * SCENARIO F: DUPLICATE VERIFY IDEMPOTENCY
     */
    public function test_scenario_f_duplicate_verify_is_strictly_idempotent(): void
    {
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_id', $paytm['transaction']->id)->firstOrFail();

        // Call verify 3 times in a row
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);

        // Assert bank balance incremented exactly once (10k, not 30k)
        $this->assertSame(10000.00, (float) $this->accountA->fresh()->current_balance);
        // Assert shop_paid_company created exactly once
        $this->assertSame(1, ShopLedgerTransaction::where('reference_type', CompanyAccountStatementEntry::class)->where('reference_id', $statement->id)->count());
        // Assert journal entries created exactly once
        $this->assertSame(1, JournalEntry::count());
    }

    /**
     * SCENARIO G: EDIT BEFORE VERIFY
     */
    public function test_scenario_g_edit_amount_before_verify_updates_statement_and_verifies_cleanly(): void
    {
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_id', $paytm['transaction']->id)->firstOrFail();
        $this->assertSame(10000.00, (float) $statement->amount);

        // Edit amount from 10k to 12k
        $this->dailyLedgerService->updateEntryAmount($paytm['transaction']->id, 12000.00, $this->admin->id);
        $this->assertSame(12000.00, (float) $statement->fresh()->amount);

        // Verify at 12k
        $this->reconciliationService->verifyPendingShopCollection($statement->fresh(), $this->admin->id);

        $this->assertSame(12000.00, (float) $this->accountA->fresh()->current_balance);
    }

    /**
     * SCENARIO H: VOID BEFORE VERIFY
     */
    public function test_scenario_h_void_before_verify_supersedes_statement_and_blocks_verification(): void
    {
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_id', $paytm['transaction']->id)->firstOrFail();

        // Void the source transaction
        $this->dailyLedgerService->voidEntry($paytm['transaction']->id, $this->admin->id, 'Mistaken entry');

        $this->assertSame('superseded', $statement->fresh()->status);

        // Verification must be rejected
        $this->expectException(ValidationException::class);
        $this->reconciliationService->verifyPendingShopCollection($statement->fresh(), $this->admin->id);
    }

    /**
     * SCENARIO I: FAILURE ROLLBACK
     */
    public function test_scenario_i_failure_during_verification_rolls_back_cleanly(): void
    {
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_id', $paytm['transaction']->id)->firstOrFail();

        // Disable company account temporarily to induce failure
        $this->accountA->update(['enabled' => false]);

        try {
            $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);
            $this->fail('Should have thrown validation exception');
        } catch (ValidationException) {
            // Expected
        }

        // Assert clean rollback
        $this->assertSame(0.00, (float) $this->accountA->fresh()->current_balance);
        $this->assertFalse((bool) $statement->fresh()->is_finalized);
        $this->assertSame(0, JournalEntry::count());

        // Re-enable and retry
        $this->accountA->update(['enabled' => true]);
        $this->reconciliationService->verifyPendingShopCollection($statement->fresh(), $this->admin->id);

        $this->assertSame(10000.00, (float) $this->accountA->fresh()->current_balance);
        $this->assertTrue((bool) $statement->fresh()->is_finalized);
    }

    /**
     * SCENARIO J: CROSS SHOP / CROSS ACCOUNT ISOLATION
     */
    public function test_scenario_j_cross_shop_cross_account_isolation(): void
    {
        $businessDate = '2026-08-29';

        // Shop A Paytm ₹10,000 -> Account A
        $shopATx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($shopATx['transaction'], $this->admin->id);

        // Shop B Paytm ₹10,000 -> Account B
        $shopBTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopB->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($shopBTx['transaction'], $this->admin->id);

        $stmtA = CompanyAccountStatementEntry::where('source_id', $shopATx['transaction']->id)->firstOrFail();
        $stmtB = CompanyAccountStatementEntry::where('source_id', $shopBTx['transaction']->id)->firstOrFail();

        // Verify Shop A
        $this->reconciliationService->verifyPendingShopCollection($stmtA, $this->admin->id);
        $this->assertSame(10000.00, (float) $this->accountA->fresh()->current_balance);
        $this->assertSame(0.00, (float) $this->accountB->fresh()->current_balance);

        // Verify Shop B
        $this->reconciliationService->verifyPendingShopCollection($stmtB, $this->admin->id);
        $this->assertSame(10000.00, (float) $this->accountA->fresh()->current_balance);
        $this->assertSame(10000.00, (float) $this->accountB->fresh()->current_balance);
    }

    /**
     * SCENARIO K: HISTORICAL DAY SETTLEMENT STABILITY
     */
    public function test_scenario_k_historical_day_settlement_stability(): void
    {
        $historicalDate = '2026-06-15';

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $historicalDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 15000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        $stmt = CompanyAccountStatementEntry::where('source_id', $tx['transaction']->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($stmt, $this->admin->id);

        $initialTxCount = ShopLedgerTransaction::count();
        $initialStmtCount = CompanyAccountStatementEntry::count();

        // Repeatedly read summary
        $summary1 = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shopA->id, $historicalDate);
        $summary2 = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shopA->id, $historicalDate);

        $this->assertSame($summary1, $summary2);
        $this->assertSame(15000.00, $summary1['gross_sales']);
        $this->assertSame(15000.00, $summary1['settlement_summary']['verified_company_received']);
        $this->assertSame(0.00, $summary1['settlement_summary']['outstanding_to_settle']);

        // Assert zero mutation
        $this->assertSame($initialTxCount, ShopLedgerTransaction::count());
        $this->assertSame($initialStmtCount, CompanyAccountStatementEntry::count());
    }

    /**
     * SCENARIO L: REAL DAILY SETTLEMENT MIXED SHAPE
     */
    public function test_scenario_l_real_daily_settlement_mixed_fixture(): void
    {
        $businessDate = '2026-08-29';

        // 1. Paytm ₹30,000 (Approved & Verified)
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 30000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);
        $stmtPaytm = CompanyAccountStatementEntry::where('source_id', $paytm['transaction']->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($stmtPaytm, $this->admin->id);

        // 2. Card ₹10,000 (Approved, Pending Bank Verification)
        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cardType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($card['transaction'], $this->admin->id);

        // 3. Cash ₹25,000 (Approved, Cash With Shop)
        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 25000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        // 4. Rent from Sales ₹3,000 (Deduction from sales)
        $rent = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->rentExpenseType->code,
            'amount' => 3000.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($rent['transaction'], $this->admin->id);

        // 5. Petty transfer from sales ₹2,000 (Deduction from sales)
        $petty = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->pettyTransferType->code,
            'amount' => 2000.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($petty['transaction'], $this->admin->id);

        // 6. Vehicle expense paid by company ₹1,500 (Company funded, NO deduction from sales)
        $vehicle = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shopA->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->vehicleExpenseType->code,
            'amount' => 1500.00,
            'funding_source' => 'company',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($vehicle['transaction'], $this->admin->id);

        // 7. Floating cheque ₹5,000
        $invoice = ShopInvoice::factory()->create(['shop_id' => $this->shopA->id]);
        ShopInvoicePaymentRequest::create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->shopA->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => 'SUB-L-1',
            'request_type' => 'custom',
            'payment_method' => 'cheque',
            'payment_date' => $businessDate,
            'requested_amount' => 5000.00,
            'floating_amount' => 5000.00,
            'status' => 'pending',
            'cheque_status' => 'pending',
        ]);

        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shopA->id, $businessDate);

        // Gross Sales = 30k + 10k + 25k = 65,000
        $this->assertSame(65000.00, $summary['gross_sales']);
        // Settlement Deductions = 3k (rent) + 2k (petty) = 5,000
        $this->assertSame(5000.00, $summary['total_deductions']);
        // Expected Payable = 65,000 - 5,000 = 60,000
        $this->assertSame(60000.00, $summary['settlement_summary']['expected_payable']);
        // Verified Company Received = 30,000 (Paytm)
        $this->assertSame(30000.00, $summary['settlement_summary']['verified_company_received']);
        // Outstanding to Settle = 60,000 - 30,000 = 30,000
        $this->assertSame(30000.00, $summary['settlement_summary']['outstanding_to_settle']);

        // Decomposition check:
        // Pending Bank (Card) = 10,000
        // Physical Cash Remaining With Shop = Gross Cash (25k) - Deductions (5k) = 20,000
        // Floating Cheques = 5,000
        // Total = 10,000 + 20,000 = 30,000 (Matches Outstanding!)
        $this->assertSame(10000.00, $summary['company_receipt_status']['pending_verification']);
        $this->assertSame(20000.00, $summary['company_receipt_status']['cash_still_with_shop']);
        $this->assertSame(5000.00, $summary['company_receipt_status']['floating_cheques']);
        $this->assertSame(
            $summary['settlement_summary']['outstanding_to_settle'],
            round($summary['company_receipt_status']['pending_verification'] + $summary['company_receipt_status']['cash_still_with_shop'], 2)
        );
    }
}
