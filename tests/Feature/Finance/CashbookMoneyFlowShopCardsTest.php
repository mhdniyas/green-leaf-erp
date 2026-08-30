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
use App\Models\User;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashbookMoneyFlowShopCardsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Shop $sana;

    private Shop $casio;

    private Shop $town;

    private CompanyAccount $kotakBank;

    private CompanyAccount $hdfcBank;

    private CompanyAccount $cashBox;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $cashSalesType;

    private LedgerEntryType $rentExpenseType;

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

        $this->unauthorizedUser = User::factory()->create(['name' => 'Regular User', 'email' => 'user@greenleaf.test']);

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true]);

        $this->sana = Shop::factory()->create(['name' => 'Sana Supermarket', 'code' => 'SANA', 'status' => 'active']);
        $this->casio = Shop::factory()->create(['name' => 'Casio Store', 'code' => 'CASIO', 'status' => 'active']);
        $this->town = Shop::factory()->create(['name' => 'Town Mart', 'code' => 'TOWN', 'status' => 'active']);

        $this->kotakBank = CompanyAccount::create([
            'name' => 'Kotak Bank',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => '9988776655',
            'account_type' => 'bank',
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);

        $this->hdfcBank = CompanyAccount::create([
            'name' => 'HDFC Bank',
            'bank_name' => 'HDFC Bank Ltd',
            'account_number' => '1122334455',
            'account_type' => 'bank',
            'current_balance' => 50000.00,
            'enabled' => true,
        ]);

        $this->cashBox = CompanyAccount::create([
            'name' => 'Main Cash Box',
            'bank_name' => 'Company Vault',
            'account_number' => 'CASH-MAIN',
            'account_type' => 'cash',
            'current_balance' => 20000.00,
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

        $this->shopPaidCompanyType = LedgerEntryType::firstOrCreate(
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

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
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

        $this->dailyLedgerService = app(DailyLedgerService::class);
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
        $this->moneyPositionService = app(CompanyMoneyPositionService::class);
    }

    public function test_1_money_flow_displays_all_applicable_shops(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow'));

        $response->assertOk()
            ->assertViewIs('admin.cashbook.money-flow.index')
            ->assertSee('Shop Summary Positions')
            ->assertSee('Sana Supermarket')
            ->assertSee('Casio Store')
            ->assertSee('Town Mart');

        $cards = $response->viewData('shopCards');
        $this->assertCount(3, $cards);
    }

    public function test_2_each_card_links_to_correct_shop_page_without_date_query(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow'));

        $response->assertOk();
        $cards = $response->viewData('shopCards');

        $sanaCard = collect($cards)->firstWhere('shop_id', $this->sana->id);
        $this->assertNotNull($sanaCard);

        $expectedUrl = route('admin.cashbook.shop.show', ['shop' => $sanaCard['shop_slug']]);
        $this->assertSame($expectedUrl, $sanaCard['open_shop_url']);
        $this->assertStringNotContainsString('date=', $sanaCard['open_shop_url']);

        $response->assertSee($expectedUrl);
    }

    public function test_3_posted_entries_count_as_pending_acceptance(): void
    {
        $date = '2026-08-25';

        // Unapproved / posted transaction
        $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 12500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $date]));
        $response->assertOk();

        $cards = $response->viewData('shopCards');
        $sanaCard = collect($cards)->firstWhere('shop_id', $this->sana->id);

        $this->assertSame(12500.00, $sanaCard['total_collection']);
        $this->assertSame(12500.00, $sanaCard['pending_acceptance']);
        $this->assertSame(1, $sanaCard['pending_acceptance_count']);
        $this->assertSame(0.00, $sanaCard['pending_verification']);
        $this->assertSame(0.00, $sanaCard['company_received']);
        $this->assertSame('Needs Acceptance', $sanaCard['status']);
    }

    public function test_4_approved_unverified_entries_count_as_pending_verification(): void
    {
        $date = '2026-08-25';

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->cardType->code,
            'amount' => 18000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $date]));
        $response->assertOk();

        $cards = $response->viewData('shopCards');
        $sanaCard = collect($cards)->firstWhere('shop_id', $this->sana->id);

        $this->assertSame(18000.00, $sanaCard['total_collection']);
        $this->assertSame(0.00, $sanaCard['pending_acceptance']);
        $this->assertSame(18000.00, $sanaCard['pending_verification']);
        $this->assertSame(1, $sanaCard['pending_verification_count']);
        $this->assertSame(0.00, $sanaCard['company_received']);
        $this->assertSame('Pending Verification', $sanaCard['status']);
    }

    public function test_5_approved_entries_do_not_count_as_company_received(): void
    {
        $date = '2026-08-25';

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 25000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        $cards = $this->moneyPositionService->getShopMoneyFlowCards($date, $date);
        $sanaCard = collect($cards)->firstWhere('shop_id', $this->sana->id);

        $this->assertSame(0.00, $sanaCard['company_received']);
        $this->assertSame(25000.00, $sanaCard['pending_verification']);
    }

    public function test_6_reconciled_entries_count_as_company_received(): void
    {
        $date = '2026-08-25';

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 30000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        $stmt = CompanyAccountStatementEntry::where('source_id', $tx['transaction']->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($stmt, $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $date]));
        $response->assertOk();

        $cards = $response->viewData('shopCards');
        $sanaCard = collect($cards)->firstWhere('shop_id', $this->sana->id);

        $this->assertSame(30000.00, $sanaCard['total_collection']);
        $this->assertSame(30000.00, $sanaCard['company_received']);
        $this->assertSame(0.00, $sanaCard['pending_acceptance']);
        $this->assertSame(0.00, $sanaCard['pending_verification']);
        $this->assertSame('Complete', $sanaCard['status']);
    }

    public function test_7_current_outstanding_uses_existing_settlement_logic(): void
    {
        $date = '2026-08-25';

        // 1. Sales: Paytm ₹50,000
        $txPaytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 50000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txPaytm['transaction'], $this->admin->id);

        // 2. Expense: Rent ₹10,000 (sales-funded deduction)
        $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->rentExpenseType->code,
            'amount' => 10000.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);

        // 3. Verify Paytm receipt: ₹50,000
        $stmt = CompanyAccountStatementEntry::where('source_id', $txPaytm['transaction']->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($stmt, $this->admin->id);

        // Net settlement delta = +50,000 (sales) - 10,000 (rent) - 50,000 (verified remittance) = -10,000 (settled)
        $cards = $this->moneyPositionService->getShopMoneyFlowCards($date, $date);
        $sanaCard = collect($cards)->firstWhere('shop_id', $this->sana->id);

        $this->assertSame(50000.00, $sanaCard['total_collection']);
        $this->assertSame(50000.00, $sanaCard['company_received']);
        $this->assertSame(0.00, $sanaCard['current_outstanding']);
    }

    public function test_8_default_calculation_runs_through_today(): void
    {
        $today = today()->toDateString();

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $today,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 9500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $cards = $this->moneyPositionService->getShopMoneyFlowCards();
        $casioCard = collect($cards)->firstWhere('shop_id', $this->casio->id);

        $this->assertSame(9500.00, $casioCard['total_collection']);
        $this->assertSame(9500.00, $casioCard['pending_acceptance']);
        $this->assertSame(9500.00, $casioCard['current_outstanding']);
    }

    public function test_9_date_and_date_range_filters_work_correctly(): void
    {
        // Day 1: 2026-08-20 (₹10,000)
        $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-20',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Day 2: 2026-08-21 (₹15,000)
        $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-21',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 15000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Range test: 2026-08-20 to 2026-08-21 (Total = ₹25,000)
        $rangeCards = $this->moneyPositionService->getShopMoneyFlowCards('2026-08-20', '2026-08-21');
        $sanaRangeCard = collect($rangeCards)->firstWhere('shop_id', $this->sana->id);
        $this->assertSame(25000.00, $sanaRangeCard['total_collection']);

        // Single date test: 2026-08-20 (Total = ₹10,000)
        $singleCards = $this->moneyPositionService->getShopMoneyFlowCards('2026-08-20', '2026-08-20');
        $sanaSingleCard = collect($singleCards)->firstWhere('shop_id', $this->sana->id);
        $this->assertSame(10000.00, $sanaSingleCard['total_collection']);
    }

    public function test_10_loading_money_flow_causes_zero_accounting_mutations(): void
    {
        $date = '2026-08-25';

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 20000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        $txCountBefore = ShopLedgerTransaction::count();
        $stmtCountBefore = CompanyAccountStatementEntry::count();
        $bankBalanceBefore = (float) $this->kotakBank->fresh()->current_balance;

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $date]));
        $response->assertOk();

        $this->assertSame($txCountBefore, ShopLedgerTransaction::count());
        $this->assertSame($stmtCountBefore, CompanyAccountStatementEntry::count());
        $this->assertSame($bankBalanceBefore, (float) $this->kotakBank->fresh()->current_balance);
    }

    public function test_11_existing_transaction_list_remains_present(): void
    {
        $date = '2026-08-25';

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $date]));

        $response->assertOk()
            ->assertSee('Daily Money Flow Collections')
            ->assertSee('₹48,962.00')
            ->assertSee('Paytm')
            ->assertSee('View');
    }

    public function test_12_unauthorized_users_cannot_access_admin_money_flow(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('admin.cashbook.money-flow'));
        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'You do not have access to that page.');

        $guestResponse = $this->get(route('admin.cashbook.money-flow'));
        $guestResponse->assertRedirect();
    }

    public function test_13_query_count_is_bounded_and_does_not_grow_linearly_per_shop(): void
    {
        $date = '2026-08-25';

        // Add more active shops to verify query count is constant
        Shop::factory()->count(10)->create(['status' => 'active']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $cards = $this->moneyPositionService->getShopMoneyFlowCards($date, $date);

        $queryCount = count(DB::getQueryLog());

        // Should be strictly bounded (under 10 queries even with 13+ shops)
        $this->assertLessThan(10, $queryCount);
        $this->assertGreaterThanOrEqual(13, count($cards));
    }
}
