<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookTransactionDetailOneNextActionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

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

    private CompanyPaymentReconciliationService $reconciliationService;

    private CompanyMoneyPositionService $moneyPositionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->viewer = User::factory()->create(['name' => 'Store Viewer', 'email' => 'viewer@greenleaf.test']);

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
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
        $this->moneyPositionService = app(CompanyMoneyPositionService::class);
    }

    public function test_posted_collection_shows_approve_action_only(): void
    {
        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => $this->cardType->code,
            'amount' => 12500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        // Kept as unapproved posted

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.transaction.show', $card['transaction']->id));

        $response->assertOk()
            ->assertSee('POSTED')
            ->assertSee('₹12,500.00')
            ->assertSee('APPROVE')
            ->assertDontSee('VERIFY RECEIVED')
            ->assertDontSee('VERIFY CASH RECEIVED')
            ->assertDontSee('RECONCILE');
    }

    public function test_approved_online_shows_verify_received_only(): void
    {
        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => $this->cardType->code,
            'amount' => 12500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($card['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.transaction.show', $card['transaction']->id));

        $response->assertOk()
            ->assertSee('NEEDS VERIFICATION')
            ->assertSee('₹12,500.00')
            ->assertSee('VERIFY RECEIVED')
            ->assertSee('→ HDFC Bank')
            ->assertDontSee('>APPROVE<', false)
            ->assertDontSee('VERIFY CASH RECEIVED')
            ->assertDontSee('RECONCILE');
    }

    public function test_approved_cash_shows_verify_cash_received_only(): void
    {
        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 14550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.transaction.show', $cash['transaction']->id));

        $response->assertOk()
            ->assertSee('CASH WITH SHOP')
            ->assertSee('₹14,550.00')
            ->assertSee('📍 Sana Shop')
            ->assertSee('VERIFY CASH RECEIVED')
            ->assertDontSee('>APPROVE<', false)
            ->assertDontSee('VERIFY RECEIVED')
            ->assertDontSee('RECONCILE');
    }

    public function test_verified_collection_shows_no_financial_action_and_displays_completed(): void
    {
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $stmt = CompanyAccountStatementEntry::where('source_id', $paytm['transaction']->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($stmt, $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.transaction.show', $paytm['transaction']->id));

        $response->assertOk()
            ->assertSee('RECEIVED')
            ->assertSee('✓ Completed')
            ->assertSee('Kotak Bank')
            ->assertDontSee('>APPROVE<', false)
            ->assertDontSee('VERIFY RECEIVED')
            ->assertDontSee('VERIFY CASH RECEIVED')
            ->assertDontSee('RECONCILE');
    }

    public function test_approve_action_executes_and_redirects_to_detail(): void
    {
        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => $this->cardType->code,
            'amount' => 12500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.transaction.approve', $card['transaction']->id));

        $response->assertRedirect(route('admin.cashbook.transaction.show', $card['transaction']->id));
        $this->assertSame('approved', $card['transaction']->fresh()->status);
    }

    public function test_verify_action_executes_and_redirects_to_detail(): void
    {
        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => $this->cardType->code,
            'amount' => 12500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($card['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.transaction.verify', $card['transaction']->id));

        $response->assertRedirect(route('admin.cashbook.transaction.show', $card['transaction']->id));

        $stmt = CompanyAccountStatementEntry::where('source_id', $card['transaction']->id)->firstOrFail();
        $this->assertTrue((bool) $stmt->is_finalized);
        $this->assertSame('reconciled', $stmt->status);
    }

    public function test_unauthorized_user_is_forbidden_from_actions(): void
    {
        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => $this->cardType->code,
            'amount' => 12500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->actingAs($this->viewer)
            ->post(route('admin.cashbook.transaction.approve', $card['transaction']->id))
            ->assertForbidden();

        $this->actingAs($this->viewer)
            ->post(route('admin.cashbook.transaction.verify', $card['transaction']->id))
            ->assertForbidden();
    }

    public function test_money_flow_and_shop_day_views_link_to_canonical_transaction_detail(): void
    {
        $businessDate = '2026-08-29';

        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $targetDetailUrl = route('admin.cashbook.transaction.show', $paytm['transaction']->id);

        // Check Money Flow landing page
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.money-flow', ['date' => $businessDate]))
            ->assertOk()
            ->assertSee($targetDetailUrl, false);

        // Check Shop Day page
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->sanaProfile->slug, 'date' => $businessDate]))
            ->assertOk()
            ->assertSee($targetDetailUrl, false);
    }
}
