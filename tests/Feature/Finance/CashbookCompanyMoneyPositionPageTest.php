<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookCompanyMoneyPositionPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $sana;

    private Shop $casio;

    private ShopLedgerProfile $sanaProfile;

    private CompanyAccount $kotakBank;

    private CompanyAccount $hdfcBank;

    private CompanyAccount $cashBox;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $cashSalesType;

    private DailyLedgerService $dailyLedgerService;

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

        $this->sanaProfile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->sana->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'sana-store',
            'code' => $this->sana->code,
            'name' => $this->sana->name,
            'enabled' => true,
        ]);

        $this->kotakBank = CompanyAccount::create([
            'name' => 'Kotak Bank',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => '9988776655',
            'account_type' => 'bank',
            'current_balance' => 500000.00,
            'is_default' => true,
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
            'bank_name' => 'Company Vault',
            'account_number' => 'CASH-MAIN',
            'account_type' => 'cash',
            'current_balance' => 30000.00,
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

        $this->dailyLedgerService = app(DailyLedgerService::class);
        $this->moneyPositionService = app(CompanyMoneyPositionService::class);
    }

    public function test_company_money_page_renders_hero_and_separate_accounts(): void
    {
        $businessDate = '2026-08-29';

        // 1. Paytm into Kotak
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        // 2. Card into HDFC
        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cardType->code,
            'amount' => 2780.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($card['transaction'], $this->admin->id);

        // 3. Cash with Sana shop
        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 14550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        // 4. Floating Cheque
        ShopInvoicePaymentRequest::create([
            'shop_id' => $this->sana->id,
            'requested_by' => $this->admin->id,
            'requested_amount' => 5000.00,
            'payment_method' => 'cheque',
            'cheque_number' => 'CHQ-9901',
            'cheque_status' => 'pending',
            'reconciliation_status' => 'floating',
            'payment_date' => $businessDate,
            'status' => 'pending',
            'funding_source' => 'shop_collection',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance', ['date' => $businessDate]));

        $response->assertOk()
            ->assertSee('COMPANY MONEY')
            ->assertSee('Verified Company Money')
            ->assertSee('Pending Verification')
            ->assertSee('Cash With Shops')
            ->assertSee('Floating Cheques')
            ->assertSee('Kotak Bank')
            ->assertSee('HDFC Bank')
            ->assertSee('Main Cash Box')
            ->assertSee('₹680,000.00') // Verified company money (500k + 150k + 30k)
            ->assertSee('₹51,742.00')  // Pending in transit (48962 + 2780)
            ->assertSee('₹5,000.00')   // Floating cheque
            ->assertDontSee('>VERIFY<', false)
            ->assertDontSee('>RECONCILE<', false);
    }

    public function test_bank_account_show_renders_verified_pending_projected_and_no_inline_verify_buttons(): void
    {
        $businessDate = '2026-08-29';

        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cardType->code,
            'amount' => 2780.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($card['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.bank-accounts.show', $this->hdfcBank->id));

        $targetDetailUrl = route('admin.cashbook.transaction.show', $card['transaction']->id);

        $response->assertOk()
            ->assertSee('HDFC Bank')
            ->assertSee('Verified Balance')
            ->assertSee('Needs Verification')
            ->assertSee('Projected')
            ->assertSee('₹150,000.00') // Verified
            ->assertSee('₹2,780.00')   // Needs verification
            ->assertSee('₹152,780.00') // Projected
            ->assertSee($targetDetailUrl, false)
            ->assertDontSee('>VERIFY<', false)
            ->assertDontSee('>RECONCILE<', false);
    }

    public function test_bank_statement_tabs_and_view_links(): void
    {
        $businessDate = '2026-08-29';

        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cardType->code,
            'amount' => 2780.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($card['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.bank-accounts.statement', [
            'account' => $this->hdfcBank->id,
            'tab' => 'needs_verification',
        ]));

        $targetDetailUrl = route('admin.cashbook.transaction.show', $card['transaction']->id);

        $response->assertOk()
            ->assertSee('All')
            ->assertSee('Needs Verification')
            ->assertSee('Verified')
            ->assertSee('Needs Attention')
            ->assertSee('₹2,780.00')
            ->assertSee($targetDetailUrl, false)
            ->assertDontSee('is_finalized')
            ->assertDontSee('matched_amount');
    }

    public function test_company_cash_box_separates_verified_cash_from_cash_with_shops(): void
    {
        $businessDate = '2026-08-29';

        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 14550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.bank-accounts.show', $this->cashBox->id));

        $response->assertOk()
            ->assertSee('Verified Company Cash')
            ->assertSee('Cash With Shops')
            ->assertSee('₹30,000.00')  // Verified cash in company vault
            ->assertSee('₹14,550.00'); // Physical cash with shops
    }
}
