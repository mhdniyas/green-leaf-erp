<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookShopSettlementOperationalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $sana;

    private Shop $casio;

    private CompanyAccount $kotakBank;

    private CompanyAccount $hdfcBank;

    private CompanyAccount $cashBox;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $cashSalesType;

    private LedgerEntryType $rentExpenseType;

    private LedgerEntryType $vehicleExpenseType;

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

        $this->sana = Shop::factory()->create(['name' => 'Sana', 'code' => 'SANA', 'status' => 'active']);
        $this->casio = Shop::factory()->create(['name' => 'Casio', 'code' => 'CASIO', 'status' => 'active']);

        $this->kotakBank = CompanyAccount::create([
            'name' => 'Kotak Bank',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => '9988776655',
            'account_type' => 'bank',
            'current_balance' => 520000.00,
            'enabled' => true,
        ]);

        $this->hdfcBank = CompanyAccount::create([
            'name' => 'HDFC Bank',
            'bank_name' => 'HDFC Bank Ltd',
            'account_number' => '1122334455',
            'account_type' => 'bank',
            'current_balance' => 150000.00,
            'enabled' => true,
        ]);

        $this->cashBox = CompanyAccount::create([
            'name' => 'Main Cash Box',
            'bank_name' => 'Main Company Cash Box',
            'account_number' => 'CASH-MAIN',
            'account_type' => 'cash',
            'current_balance' => 50000.00,
            'enabled' => true,
        ]);

        $this->paytmType = LedgerEntryType::firstOrCreate(
            ['code' => 'paytm_sales'],
            [
                'name' => 'Paytm / UPI Collection',
                'category' => 'income',
                'display_order' => 10,
                'active' => true,
                'is_system' => true,
            ]
        );

        $this->cardType = LedgerEntryType::firstOrCreate(
            ['code' => 'card_sales'],
            [
                'name' => 'Card / POS Collection',
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
                'name' => 'Shop Rent Expense',
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
            'entry_type_id' => $this->shopPaidCompanyType->id,
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

    public function test_shop_day_displays_gross_sales_and_collections_accurately(): void
    {
        // 1. Sana records Paytm ₹48,962, Cash ₹16,550, Card ₹2,780 on 2026-08-22
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 16550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->cardType->code,
            'amount' => 2780.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        // Leave Card as unapproved POSTED

        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, '2026-08-22');

        // Gross sales = 48962 + 16550 + 2780 = 68292.00
        $this->assertSame(68292.00, $summary['gross_sales']);
        $this->assertCount(3, $summary['collections']);

        // Check Paytm: approved online -> NEEDS VERIFICATION, destination -> Kotak Bank
        $paytmCol = collect($summary['collections'])->firstWhere('code', $this->paytmType->code);
        $this->assertSame('NEEDS VERIFICATION', $paytmCol['status']);
        $this->assertSame('Kotak Bank', $paytmCol['destination_account']);
        $this->assertSame('verify_received', $paytmCol['action_type']);

        // Check Cash: approved cash -> CASH WITH SHOP, destination -> Main Cash Box
        $cashCol = collect($summary['collections'])->firstWhere('code', $this->cashSalesType->code);
        $this->assertSame('CASH WITH SHOP', $cashCol['status']);
        $this->assertSame('verify_cash_received', $cashCol['action_type']);

        // Check Card: unapproved -> POSTED
        $cardCol = collect($summary['collections'])->firstWhere('code', $this->cardType->code);
        $this->assertSame('POSTED', $cardCol['status']);
        $this->assertSame('approve', $cardCol['action_type']);
    }

    public function test_settlement_deductions_reduce_expected_payable(): void
    {
        // 1. Gross sales: Cash ₹10,000
        $sales = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($sales['transaction'], $this->admin->id);

        // 2. Rent paid from sales: ₹2,000
        $rent = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->rentExpenseType->code,
            'amount' => 2000.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($rent['transaction'], $this->admin->id);

        // 3. Vehicle expense paid by company: ₹500 (does NOT reduce payable)
        $vehicle = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->vehicleExpenseType->code,
            'amount' => 500.00,
            'funding_source' => 'company',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($vehicle['transaction'], $this->admin->id);

        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, '2026-08-22');

        $this->assertSame(10000.00, $summary['gross_sales']);
        $this->assertSame(2000.00, $summary['total_deductions']);
        $this->assertSame(8000.00, $summary['settlement_summary']['expected_payable']);
    }

    public function test_verification_decomposes_payable_equation_exactly_without_double_counting(): void
    {
        // 1. Sana records Paytm ₹48,962 and Cash ₹16,550 on 2026-08-22
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 16550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        // 2. Admin verifies Paytm ₹48,962
        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $paytm['transaction']->id)
            ->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);

        // 3. Floating cheque ₹5,000 for Sana
        $invoice = ShopInvoice::factory()->create(['shop_id' => $this->sana->id]);
        ShopInvoicePaymentRequest::create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->sana->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => 'SUB-2001',
            'request_type' => 'custom',
            'payment_method' => 'cheque',
            'payment_date' => '2026-08-22',
            'requested_amount' => 5000.00,
            'floating_amount' => 5000.00,
            'status' => 'pending',
            'cheque_status' => 'pending',
        ]);

        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, '2026-08-22');

        // Total sales = 48962 + 16550 = 65512
        $this->assertSame(65512.00, $summary['gross_sales']);
        $this->assertSame(48962.00, $summary['company_receipt_status']['verified_received']);
        $this->assertSame(0.00, $summary['company_receipt_status']['pending_verification']);
        $this->assertSame(16550.00, $summary['company_receipt_status']['cash_still_with_shop']);
        $this->assertSame(5000.00, $summary['company_receipt_status']['floating_cheques']);

        // Expected payable = 65512.00
        // Outstanding to settle = 65512 - 48962 = 16550.00
        $this->assertSame(65512.00, $summary['settlement_summary']['expected_payable']);
        $this->assertSame(48962.00, $summary['settlement_summary']['verified_company_received']);
        $this->assertSame(16550.00, $summary['settlement_summary']['outstanding_to_settle']);

        // Check Paytm collection status changed to VERIFIED
        $paytmCol = collect($summary['collections'])->firstWhere('code', $this->paytmType->code);
        $this->assertSame('VERIFIED', $paytmCol['status']);
        $this->assertNull($paytmCol['action_type']);
    }

    public function test_operational_settlement_api_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.cashbook.api.shop-settlement-summary', [
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'summary' => [
                    'shop_id',
                    'business_date',
                    'gross_sales',
                    'collections',
                    'settlement_adjustments',
                    'company_receipt_status',
                    'settlement_summary',
                ],
            ]);
    }

    public function test_no_cross_shop_leakage_in_settlement_summary(): void
    {
        // Sana records cash 10,000
        $sanaCash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($sanaCash['transaction'], $this->admin->id);

        // Casio records cash 5,000
        $casioCash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($casioCash['transaction'], $this->admin->id);

        $sanaSummary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, '2026-08-22');
        $casioSummary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->casio->id, '2026-08-22');

        $this->assertSame(10000.00, $sanaSummary['gross_sales']);
        $this->assertSame(10000.00, $sanaSummary['company_receipt_status']['cash_still_with_shop']);

        $this->assertSame(5000.00, $casioSummary['gross_sales']);
        $this->assertSame(5000.00, $casioSummary['company_receipt_status']['cash_still_with_shop']);
    }

    public function test_historical_day_renders_correctly_without_state_mutation(): void
    {
        // Sana records Paytm ₹12,000 on historical date 2026-07-15
        $record = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-07-15',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 12000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($record['transaction'], $this->admin->id);

        $initialCount = ShopLedgerTransaction::count();

        // Retrieve summary for historical day
        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->sana->id, '2026-07-15');

        $this->assertSame(12000.00, $summary['gross_sales']);
        $this->assertSame(12000.00, $summary['company_receipt_status']['pending_verification']);
        // Verify database records count is unchanged (no side-effect mutation)
        $this->assertSame($initialCount, ShopLedgerTransaction::count());
    }

    public function test_reconciled_transaction_cannot_be_modified_or_voided(): void
    {
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-22',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 15000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $statement = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $paytm['transaction']->id)
            ->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($statement, $this->admin->id);

        // Attempting to update or void throws exception
        $this->expectException(\RuntimeException::class);
        $this->dailyLedgerService->updateEntryAmount($paytm['transaction']->id, 20000.00, $this->admin->id);
    }
}
