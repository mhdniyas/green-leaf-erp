<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyExpenseLedgerAllocation;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\Cashbook\CompanyExpenseAllocationService;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashbookShopCompanySettlementEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $kotakBank;

    private CompanyAccount $cashAccount;

    private DailyLedgerService $dailyLedgerService;

    private CompanyMoneyPositionService $moneyPositionService;

    private CompanyExpenseAllocationService $allocationService;

    private BalanceCalculator $balanceCalculator;

    private LedgerEntryType $cashSalesType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cpType;

    private LedgerEntryType $cashPurchaseType;

    private LedgerEntryType $rentType;

    private LedgerEntryType $vehicleType;

    private LedgerEntryType $glBillType;

    private LedgerEntryType $salaryType;

    private LedgerEntryType $toShopType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');
        $this->shop = Shop::factory()->create(['name' => 'Casio Veg', 'code' => 'AV_CASIO']);
        $this->profile = ShopLedgerProfile::create([
            'shop_id' => $this->shop->id,
            'name' => 'Casio Veg',
            'code' => 'AV_CASIO',
            'slug' => 'av-casio-casio',
        ]);

        $this->kotakBank = CompanyAccount::create([
            'name' => 'Kotak Bank Main',
            'account_type' => 'bank',
            'account_number' => '1234567890',
            'enabled' => true,
            'current_balance' => 100000.00,
        ]);

        $this->cashAccount = CompanyAccount::create([
            'name' => 'Main Cash Box',
            'account_type' => 'cash',
            'account_number' => 'CASH-MAIN',
            'enabled' => true,
            'current_balance' => 50000.00,
        ]);

        $this->dailyLedgerService = app(DailyLedgerService::class);
        $this->moneyPositionService = app(CompanyMoneyPositionService::class);
        $this->allocationService = app(CompanyExpenseAllocationService::class);
        $this->balanceCalculator = app(BalanceCalculator::class);

        $this->cashSalesType = LedgerEntryType::firstOrCreate(['code' => 'cash_sales'], [
            'name' => 'Cash Sales',
            'category' => 'income',
            'direction' => 'income',
        ]);

        $this->cardType = LedgerEntryType::firstOrCreate(['code' => 'card'], [
            'name' => 'Card',
            'category' => 'income',
            'direction' => 'income',
        ]);

        $this->paytmType = LedgerEntryType::firstOrCreate(['code' => 'paytm'], [
            'name' => 'Paytm',
            'category' => 'income',
            'direction' => 'income',
        ]);

        $this->cpType = LedgerEntryType::firstOrCreate(['code' => 'income_cp'], [
            'name' => 'CP',
            'category' => 'income',
            'direction' => 'income',
        ]);

        $this->cashPurchaseType = LedgerEntryType::firstOrCreate(['code' => 'cash_purchase'], [
            'name' => 'Cash Purchase',
            'category' => 'expense',
            'direction' => 'expense',
        ]);

        $this->rentType = LedgerEntryType::firstOrCreate(['code' => 'rent'], [
            'name' => 'Rent',
            'category' => 'expense',
            'direction' => 'expense',
        ]);

        $this->vehicleType = LedgerEntryType::firstOrCreate(['code' => 'vehicle'], [
            'name' => 'Vehicle Expense',
            'category' => 'expense',
            'direction' => 'expense',
        ]);

        $this->glBillType = LedgerEntryType::firstOrCreate(['code' => 'purchase_bill'], [
            'name' => 'GL Bill',
            'category' => 'expense',
            'direction' => 'expense',
        ]);

        $this->salaryType = LedgerEntryType::firstOrCreate(['code' => 'salary'], [
            'name' => 'Salary',
            'category' => 'expense',
            'direction' => 'expense',
        ]);

        $this->toShopType = LedgerEntryType::firstOrCreate(['code' => 'to_casio'], [
            'name' => 'To Casio',
            'category' => 'expense',
            'direction' => 'expense',
        ]);

        // Default Settings
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cashSalesType->id,
            'company_account_id' => $this->cashAccount->id,
            'enabled' => true,
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
            'effective_from' => '2026-01-01',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cardType->id,
            'company_account_id' => $this->kotakBank->id,
            'enabled' => true,
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
            'effective_from' => '2026-01-01',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->rentType->id,
            'enabled' => true,
            'include_in_expense' => true,
            'include_in_pl' => true,
            'default_funding_source' => 'sales',
            'effective_from' => '2026-01-01',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->vehicleType->id,
            'enabled' => true,
            'include_in_expense' => true,
            'include_in_pl' => true,
            'default_funding_source' => 'company',
            'effective_from' => '2026-01-01',
        ]);
    }

    public function test_historical_settings_and_accepted_entry_immutability(): void
    {
        $augDate = '2026-08-15';

        // 1. Record and approve August entry with Card
        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $augDate,
            'entry_type_code' => $this->cardType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        $freshTx = $tx['transaction']->fresh();
        $this->assertEquals(10000.00, $freshTx->settlement_delta);
        $this->assertEquals($this->kotakBank->id, $freshTx->company_account_id);

        // 2. Change setting in September to point to null or a different account
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)->where('entry_type_id', $this->cardType->id)->first();
        $setting->update([
            'company_account_id' => null,
            'settlement_behavior' => 'none',
        ]);

        // 3. Recalculate August day snapshot
        $this->balanceCalculator->recalculate($this->shop->id, $augDate);

        // Stored deltas on accepted transaction must remain 100% stable
        $freshTxAfter = $tx['transaction']->fresh();
        $this->assertEquals(10000.00, $freshTxAfter->settlement_delta);
        $this->assertEquals($this->kotakBank->id, $freshTxAfter->company_account_id);
    }

    public function test_bidirectional_obligations_and_statement_formulas(): void
    {
        $date = '2026-08-20';

        // 1. Shop Collections (Shop owes Green Leaf ₹25,000)
        $cardTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->cardType->code,
            'amount' => 15000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cardTx['transaction'], $this->admin->id);

        $cashTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cashTx['transaction'], $this->admin->id);

        // 2. Sales Deductions (Rent ₹5,000 paid from sales)
        $rentTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->rentType->code,
            'amount' => 5000.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($rentTx['transaction'], $this->admin->id);

        // 3. Company Obligation (Vehicle ₹2,000 paid by shop funds / company_later)
        $vehicleTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->vehicleType->code,
            'amount' => 2000.00,
            'funding_source' => 'company_later',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($vehicleTx['transaction'], $this->admin->id);

        // 4. Calculate settlement position
        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shop->id, $date);

        // Shop Obligation: Gross ₹25,000 - Deductions ₹5,000 = ₹20,000
        $this->assertEquals(25000.00, $summary['shop_obligation_gross']);
        $this->assertEquals(5000.00, $summary['shop_sales_deductions']);
        $this->assertEquals(20000.00, $summary['shop_outstanding']);

        // Company Obligation: Vehicle paid by shop = ₹2,000
        $this->assertEquals(2000.00, $summary['company_obligation_gross']);
        $this->assertEquals(2000.00, $summary['company_outstanding']);

        // Net Result: ₹20,000 - ₹2,000 = ₹18,000 (Shop owes Green Leaf)
        $this->assertEquals(18000.00, $summary['net_amount']);
        $this->assertEquals('shop_owes_company', $summary['net_direction']);
        $this->assertEquals('Casio Veg OWES GREEN LEAF ₹18,000.00', $summary['display_statement']);
    }

    public function test_company_expense_allocation_layer_and_immutability(): void
    {
        $date = '2026-08-22';

        // 1. Vehicle expense ₹3,000 paid by shop funds (company owes shop)
        $vehicleTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->vehicleType->code,
            'amount' => 3000.00,
            'funding_source' => 'company_later',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($vehicleTx['transaction'], $this->admin->id);

        $tx = $vehicleTx['transaction']->fresh();
        $this->assertEquals('approved', $tx->status);

        // 2. Create Company Account Statement Entry representing ₹2,000 partial payment to shop
        $statement = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->kotakBank->id,
            'transaction_date' => $date,
            'direction' => 'out',
            'amount' => 2000.00,
            'source' => 'bank_feed',
            'status' => 'reconciled',
            'is_finalized' => true,
        ]);

        // 3. Allocate ₹2,000 to vehicle expense
        $allocations = $this->allocationService->allocate(
            $statement,
            $this->shop->id,
            [['ledger_transaction_id' => $tx->id, 'amount' => 2000.00]],
            $this->admin->id
        );

        $this->assertCount(1, $allocations);

        // 4. Verify original Cashbook transaction was NOT altered
        $this->assertEquals(3000.00, $tx->fresh()->amount);
        $this->assertEquals('approved', $tx->fresh()->status);
        $this->assertEquals($date, $tx->fresh()->business_date->toDateString());

        // 5. Check coverage status
        $coverage = $this->allocationService->getObligationCoverage($tx->fresh());
        $this->assertEquals(3000.00, $coverage['payable_amount']);
        $this->assertEquals(2000.00, $coverage['covered_amount']);
        $this->assertEquals(1000.00, $coverage['remaining_amount']);
        $this->assertEquals('Partially covered', $coverage['status']);

        // 6. Test allocation soft-reversal
        $reversed = $this->allocationService->reverseAllocation($allocations->first(), $this->admin->id, 'Bank error');
        $this->assertEquals('reversed', $reversed->status);
        $this->assertNotNull($reversed->reversed_at);

        $coverageAfterReversal = $this->allocationService->getObligationCoverage($tx->fresh());
        $this->assertEquals(3000.00, $coverageAfterReversal['remaining_amount']);
        $this->assertEquals('Uncovered', $coverageAfterReversal['status']);
    }

    public function test_petty_cash_opening_movements_and_closing_calculation(): void
    {
        $prevDate = '2026-08-24';
        $currDate = '2026-08-25';

        // Setup previous day snapshot with closing petty ₹1,500
        ShopDailyLedgerSnapshot::create([
            'shop_id' => $this->shop->id,
            'business_date' => $prevDate,
            'closing_petty' => 1500.00,
            'closing_shop_position' => 0.00,
            'closing_company_pending' => 0.00,
        ]);

        // Current day: Sales transferred to petty ₹500
        $pettyTransferType = LedgerEntryType::firstOrCreate(['code' => 'sales_to_petty'], [
            'name' => 'Sales to Petty',
            'category' => 'transfer',
            'direction' => 'transfer',
        ]);
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $pettyTransferType->id,
            'enabled' => true,
            'petty_behavior' => 'increase',
            'settlement_behavior' => 'decrease',
            'effective_from' => '2026-01-01',
        ]);

        $transferTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $currDate,
            'entry_type_code' => $pettyTransferType->code,
            'amount' => 500.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($transferTx['transaction'], $this->admin->id);

        // Petty expense ₹800
        $expenseTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $currDate,
            'entry_type_code' => $this->vehicleType->code,
            'amount' => 800.00,
            'funding_source' => 'petty',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($expenseTx['transaction'], $this->admin->id);

        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shop->id, $currDate);

        // Opening ₹1,500 + Sales to Petty ₹500 - Petty Expense ₹800 = Closing Petty ₹1,200
        $petty = $summary['petty_position'];
        $this->assertEquals(1500.00, $petty['opening_petty']);
        $this->assertEquals(500.00, $petty['sales_transferred_to_petty']);
        $this->assertEquals(800.00, $petty['petty_expenses']);
        $this->assertEquals(1200.00, $petty['closing_petty']);
    }

    public function test_ui_shows_dominant_statement_and_blocks_unconfigured_verification(): void
    {
        $date = '2026-08-26';

        // 1. Paytm is not configured
        $paytmTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 8000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytmTx['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'date' => $date,
        ]));

        $response->assertOk();
        $response->assertSee('Shop Settlement Statement');
        $response->assertSee('Casio Veg OWES GREEN LEAF ₹8,000.00');
        $response->assertSee('Destination account not configured');
        $response->assertSee('Setup required');
    }

    public function test_rent_and_expenses_funding_source_variations_and_greenleaf_payable_rules(): void
    {
        $date = '2026-08-27';

        // 1. Sales ₹50,000
        $salesTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 50000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($salesTx['transaction'], $this->admin->id);

        // 2. Rent ₹15,000 from sales (reduces shop debt, NOT company payable)
        $rentTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->rentType->code,
            'amount' => 15000.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($rentTx['transaction'], $this->admin->id);

        // 3. GL Bill ₹20,000 paid directly by company (audit only, no shop reimbursement)
        $glBillTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->glBillType->code,
            'amount' => 20000.00,
            'funding_source' => 'company',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($glBillTx['transaction'], $this->admin->id);

        // 4. Vehicle ₹4,000 paid by shop funds (company owes shop reimbursement)
        $vehTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->vehicleType->code,
            'amount' => 4000.00,
            'funding_source' => 'company_later',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($vehTx['transaction'], $this->admin->id);

        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shop->id, $date);

        // Shop Obligation: Gross ₹50,000 - Rent ₹15,000 = ₹35,000
        $this->assertEquals(50000.00, $summary['shop_obligation_gross']);
        $this->assertEquals(15000.00, $summary['shop_sales_deductions']);
        $this->assertEquals(35000.00, $summary['shop_outstanding']);

        // Company Obligation: Vehicle ₹4,000 (GL Bill by company is ₹0, Rent is ₹0)
        $this->assertEquals(4000.00, $summary['company_obligation_gross']);
        $this->assertEquals(4000.00, $summary['company_outstanding']);

        // Net: ₹35,000 - ₹4,000 = ₹31,000 (Shop owes Green Leaf)
        $this->assertEquals(31000.00, $summary['net_amount']);
        $this->assertEquals('shop_owes_company', $summary['net_direction']);
        $this->assertEquals('Casio Veg OWES GREEN LEAF ₹31,000.00', $summary['display_statement']);
    }

    public function test_fully_settled_and_company_owes_shop_states(): void
    {
        $date = '2026-08-28';

        // 1. Vehicle expense ₹10,000 paid by shop (company owes shop ₹10,000)
        $vehTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->vehicleType->code,
            'amount' => 10000.00,
            'funding_source' => 'company_later',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($vehTx['transaction'], $this->admin->id);

        // Position when company owes shop
        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shop->id, $date);
        $this->assertEquals(0.00, $summary['shop_outstanding']);
        $this->assertEquals(10000.00, $summary['company_outstanding']);
        $this->assertEquals('company_owes_shop', $summary['net_direction']);
        $this->assertEquals('GREEN LEAF OWES Casio Veg ₹10,000.00', $summary['display_statement']);

        // 2. Fully settled state: Company reimburses ₹10,000
        $statement = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->kotakBank->id,
            'transaction_date' => $date,
            'direction' => 'out',
            'amount' => 10000.00,
            'source' => 'bank_feed',
            'status' => 'reconciled',
            'is_finalized' => true,
        ]);

        $this->allocationService->allocate(
            $statement,
            $this->shop->id,
            [['ledger_transaction_id' => $vehTx['transaction']->id, 'amount' => 10000.00]],
            $this->admin->id
        );

        $summaryAfter = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shop->id, $date);
        $this->assertEquals(0.00, $summaryAfter['shop_outstanding']);
        $this->assertEquals(0.00, $summaryAfter['company_outstanding']);
        $this->assertEquals(0.00, $summaryAfter['net_amount']);
        $this->assertEquals('settled', $summaryAfter['net_direction']);
        $this->assertEquals('GREEN LEAF AND Casio Veg ARE FULLY SETTLED', $summaryAfter['display_statement']);
    }

    public function test_cross_shop_allocation_isolation(): void
    {
        $date = '2026-08-29';
        $otherShop = Shop::factory()->create(['name' => 'Sana Veg', 'code' => 'AV_SANA']);

        // Vehicle in other shop
        $otherVeh = $this->dailyLedgerService->recordEntry([
            'shop_id' => $otherShop->id,
            'business_date' => $date,
            'entry_type_code' => $this->vehicleType->code,
            'amount' => 5000.00,
            'funding_source' => 'company_later',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($otherVeh['transaction'], $this->admin->id);

        $statement = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->kotakBank->id,
            'transaction_date' => $date,
            'direction' => 'out',
            'amount' => 5000.00,
            'source' => 'bank_feed',
            'status' => 'reconciled',
            'is_finalized' => true,
        ]);

        // Attempt to allocate other shop's expense using Casio's shop ID
        $this->expectException(ValidationException::class);
        $this->allocationService->allocate(
            $statement,
            $this->shop->id,
            [['ledger_transaction_id' => $otherVeh['transaction']->id, 'amount' => 5000.00]],
            $this->admin->id
        );
    }

    public function test_three_day_opening_activity_closing_carry_forward_and_historical_stability(): void
    {
        $day1 = '2026-08-01';
        $day2 = '2026-08-02';
        $day3 = '2026-08-03';

        // Day 1: Sales ₹10,000. Verified ₹0.
        $tx1 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $day1,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx1['transaction'], $this->admin->id);
        $this->balanceCalculator->recalculate($this->shop->id, $day1);

        $summaryDay1 = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shop->id, $day1);
        $this->assertEquals(0.00, $summaryDay1['opening_shop_outstanding']);
        $this->assertEquals(10000.00, $summaryDay1['day_shop_obligations']);
        $this->assertEquals(10000.00, $summaryDay1['closing_shop_outstanding']);
        $this->assertEquals('Casio Veg OWES GREEN LEAF ₹10,000.00', $summaryDay1['display_statement']);

        // Day 2: Sales ₹5,000. Verified payment ₹10,000 (reimburses Day 1).
        $tx2 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $day2,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx2['transaction'], $this->admin->id);

        $txCard2 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $day2,
            'entry_type_code' => $this->cardType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txCard2['transaction'], $this->admin->id);

        // Verify Card receipt on Day 2
        $stmtCard = CompanyAccountStatementEntry::where('source_id', $txCard2['transaction']->id)->first();
        $stmtCard->update(['status' => 'reconciled', 'is_finalized' => true]);

        $this->balanceCalculator->recalculate($this->shop->id, $day2);

        $summaryDay2 = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shop->id, $day2);
        $this->assertEquals(10000.00, $summaryDay2['opening_shop_outstanding']);
        $this->assertEquals(15000.00, $summaryDay2['day_shop_obligations']); // 5,000 cash + 10,000 card
        $this->assertEquals(10000.00, $summaryDay2['day_shop_payments']); // 10,000 verified
        $this->assertEquals(15000.00, $summaryDay2['closing_shop_outstanding']); // 10,000 opening + 15,000 - 10,000 = 15,000

        // Check Day 1 again: Must be stable and unchanged!
        $summaryDay1Again = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shop->id, $day1);
        $this->assertEquals(10000.00, $summaryDay1Again['closing_shop_outstanding']);
        $this->assertEquals('Casio Veg OWES GREEN LEAF ₹10,000.00', $summaryDay1Again['display_statement']);
    }

    public function test_admin_company_payment_creation_and_reconciliation_workflow(): void
    {
        $date = '2026-08-30';
        $initialBalance = $this->kotakBank->fresh()->current_balance;

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.company-payments.store', [
            'shop' => $this->profile->slug,
        ]), [
            'company_account_id' => $this->kotakBank->id,
            'payment_date' => $date,
            'amount' => 15000.00,
            'destination_reference' => 'CHQ-98765',
            'notes' => 'Advance shop reimbursement',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check bank balance decremented
        $this->assertEquals($initialBalance - 15000.00, $this->kotakBank->fresh()->current_balance);

        // Check statement entry created
        $stmt = CompanyAccountStatementEntry::where('company_account_id', $this->kotakBank->id)
            ->where('source_type', Shop::class)
            ->where('source_id', $this->shop->id)
            ->first();

        $this->assertNotNull($stmt);
        $this->assertEquals(15000.00, (float) $stmt->amount);
        $this->assertEquals('out', $stmt->direction);
        $this->assertTrue((bool) $stmt->is_finalized);
        $this->assertEquals('reconciled', $stmt->status);
    }

    public function test_admin_company_expense_allocation_and_reversal_workflow(): void
    {
        $date = '2026-08-30';

        // 1. Vehicle expense ₹5,000
        $veh = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->vehicleType->code,
            'amount' => 5000.00,
            'funding_source' => 'company_later',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($veh['transaction'], $this->admin->id);

        // 2. Company Payment statement ₹5,000
        $stmt = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->kotakBank->id,
            'transaction_date' => $date,
            'direction' => 'out',
            'amount' => 5000.00,
            'source' => 'manual',
            'source_type' => Shop::class,
            'source_id' => $this->shop->id,
            'status' => 'reconciled',
            'is_finalized' => true,
        ]);

        // 3. Store allocation via HTTP POST
        $allocResponse = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocations.store', [
            'shop' => $this->profile->slug,
        ]), [
            'statement_entry_id' => $stmt->id,
            'allocations' => [
                ['ledger_transaction_id' => $veh['transaction']->id, 'amount' => 5000.00],
            ],
            'allocation_date' => $date,
            'notes' => 'Allocated vehicle reimbursement',
        ]);

        $allocResponse->assertRedirect();
        $allocResponse->assertSessionHas('success');

        $allocation = CompanyExpenseLedgerAllocation::where('shop_ledger_transaction_id', $veh['transaction']->id)->first();
        $this->assertNotNull($allocation);
        $this->assertEquals('active', $allocation->status);

        // 4. Reverse allocation via HTTP POST
        $revResponse = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocations.reverse', [
            'shop' => $this->profile->slug,
            'allocation' => $allocation->id,
        ]), [
            'reason' => 'Wrong invoice attached',
        ]);

        $revResponse->assertRedirect();
        $revResponse->assertSessionHas('success');
        $this->assertEquals('reversed', $allocation->fresh()->status);
        $this->assertEquals('Wrong invoice attached', $allocation->fresh()->reversal_reason);
    }

    public function test_cp_and_salary_contra_pair_net_zero_settlement_semantics(): void
    {
        $date = '2026-08-31';

        // 1. CP Income ₹2,495 + Auto Secondary Cash Purchase Expense ₹2,495
        $cpSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cpType->id,
            'enabled' => true,
            'include_in_sales' => false,
            'include_in_income' => true,
            'generates_secondary_entry' => true,
            'secondary_entry_type_id' => $this->cashPurchaseType->id,
            'secondary_amount_mode' => 'fixed',
            'effective_from' => '2026-01-01',
        ]);

        $cpTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->cpType->code,
            'amount' => 2495.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cpTx['transaction'], $this->admin->id);

        // Check that child expense was generated with settlement_delta = 0
        $child = ShopLedgerTransaction::where('parent_transaction_id', $cpTx['transaction']->id)->first();
        $this->assertNotNull($child);
        $this->assertEquals(0.00, (float) $child->settlement_delta);
        $this->assertEquals('none', $child->funding_source);

        // Position check: CP pair should net to zero on sales obligation!
        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($this->shop->id, $date);
        $this->assertEquals(0.00, $summary['shop_obligation_gross']);
        $this->assertEquals(0.00, $summary['shop_sales_deductions']);
        $this->assertEquals(0.00, $summary['shop_outstanding']);
        $this->assertEquals('settled', $summary['net_direction']);
    }

    public function test_expense_audit_date_range_report_view_and_filtering(): void
    {
        $date = '2026-08-30';

        $veh = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->vehicleType->code,
            'amount' => 3500.00,
            'funding_source' => 'company_later',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($veh['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.expense-audit', [
            'shop_id' => $this->shop->id,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]));

        $response->assertOk();
        $response->assertSee('Expense Audit Report');
        $response->assertSee('Vehicle Expense');
        $response->assertSee('3,500.00');
        $response->assertSee('Uncovered');
    }

    public function test_company_payment_creation_validation_requires_active_source_account_and_date(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.company-payments.store', [
            'shop' => $this->profile->slug,
        ]), [
            'company_account_id' => 99999, // non-existent
            'payment_date' => 'invalid-date',
            'amount' => 0,
        ]);

        $response->assertSessionHasErrors(['company_account_id', 'payment_date', 'amount']);
    }

    public function test_unverified_company_payment_does_not_reduce_outstanding(): void
    {
        $date = '2026-08-30';

        $veh = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'entry_type_code' => $this->vehicleType->code,
            'amount' => 7000.00,
            'funding_source' => 'company_later',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($veh['transaction'], $this->admin->id);

        // Draft/unverified statement entry
        $unverifiedStmt = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->kotakBank->id,
            'transaction_date' => $date,
            'direction' => 'out',
            'amount' => 7000.00,
            'source' => 'bank_feed',
            'status' => 'pending',
            'is_finalized' => false,
        ]);

        // Attempt allocation from unverified statement
        $this->expectException(ValidationException::class);
        $this->allocationService->allocate(
            $unverifiedStmt,
            $this->shop->id,
            [['ledger_transaction_id' => $veh['transaction']->id, 'amount' => 7000.00]],
            $this->admin->id
        );
    }
}
