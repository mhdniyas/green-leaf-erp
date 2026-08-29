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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashbookMoneyFlowLandingPageTest extends TestCase
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

    public function test_money_flow_route_is_accessible_to_authorized_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow'));
        $response->assertOk()
            ->assertViewIs('admin.cashbook.money-flow.index')
            ->assertSee('Money Flow')
            ->assertSee('Company Received')
            ->assertSee('Needs Attention')
            ->assertSee('Cash With Shops')
            ->assertSee('Floating Cheques');
    }

    public function test_summary_shows_verified_company_money_and_excludes_pending(): void
    {
        $businessDate = '2026-08-29';

        // 1. Sana records Paytm ₹48,962 and Card ₹12,500
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cardType->code,
            'amount' => 12500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($card['transaction'], $this->admin->id);

        // 2. Admin verifies Paytm ₹48,962 (Card remains pending)
        $stmtPaytm = CompanyAccountStatementEntry::where('source_id', $paytm['transaction']->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($stmtPaytm, $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $businessDate]));

        // Verified bank total = Initial 170,000 + 48,962 = 218,962.00
        $response->assertOk()
            ->assertSee('₹218,962.00')
            ->assertSee('₹12,500.00'); // Needs attention card shows ₹12,500
    }

    public function test_pending_online_displays_needs_verification_with_configured_destination(): void
    {
        $businessDate = '2026-08-29';

        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cardType->code,
            'amount' => 12500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($card['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $businessDate]));

        $response->assertOk()
            ->assertSee('Sana')
            ->assertSee('Card')
            ->assertSee('→ HDFC Bank')
            ->assertSee('NEEDS VERIFICATION');
    }

    public function test_cash_displays_cash_with_shop(): void
    {
        $businessDate = '2026-08-29';

        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 16550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $businessDate]));

        $response->assertOk()
            ->assertSee('Casio')
            ->assertSee('Cash')
            ->assertSee('Casio Store')
            ->assertSee('CASH WITH SHOP');
    }

    public function test_verified_online_displays_received(): void
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

        $stmtPaytm = CompanyAccountStatementEntry::where('source_id', $paytm['transaction']->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($stmtPaytm, $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $businessDate]));

        $response->assertOk()
            ->assertSee('Sana')
            ->assertSee('Paytm')
            ->assertSee('→ Kotak Bank')
            ->assertSee('RECEIVED');
    }

    public function test_floating_cheque_displays_floating_and_rejected_is_excluded(): void
    {
        $businessDate = '2026-08-29';

        $invoice = ShopInvoice::factory()->create(['shop_id' => $this->sana->id]);

        // Floating cheque ₹35,000
        ShopInvoicePaymentRequest::create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->sana->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => 'SUB-MF-1',
            'request_type' => 'custom',
            'payment_method' => 'cheque',
            'payment_date' => $businessDate,
            'requested_amount' => 35000.00,
            'floating_amount' => 35000.00,
            'status' => 'pending',
            'cheque_status' => 'pending',
        ]);

        // Rejected cheque ₹10,000
        ShopInvoicePaymentRequest::create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->sana->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => 'SUB-MF-2',
            'request_type' => 'custom',
            'payment_method' => 'cheque',
            'payment_date' => $businessDate,
            'requested_amount' => 10000.00,
            'floating_amount' => 0.00,
            'status' => 'rejected',
            'cheque_status' => 'rejected',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $businessDate]));

        $response->assertOk()
            ->assertSee('₹35,000.00')
            ->assertSee('FLOATING')
            ->assertSee('REJECTED');
    }

    public function test_filters_by_shop_and_status_work(): void
    {
        $businessDate = '2026-08-29';

        // Sana Paytm
        $sanaTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($sanaTx['transaction'], $this->admin->id);

        // Casio Cash
        $casioTx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 16550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($casioTx['transaction'], $this->admin->id);

        // Filter by Sana
        $responseSana = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', [
            'date' => $businessDate,
            'shop_id' => $this->sana->id,
        ]));
        $responseSana->assertOk();
        $itemsSana = $responseSana->viewData('items');
        $this->assertCount(1, $itemsSana);
        $this->assertSame('Sana', $itemsSana[0]['shop_name']);

        // Filter by Cash With Shop
        $responseCash = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', [
            'date' => $businessDate,
            'status' => 'cash_with_shop',
        ]));
        $responseCash->assertOk();
        $itemsCash = $responseCash->viewData('items');
        $this->assertCount(1, $itemsCash);
        $this->assertSame('Casio', $itemsCash[0]['shop_name']);
        $this->assertSame('CASH WITH SHOP', $itemsCash[0]['display_status']);
    }

    public function test_landing_page_exposes_view_and_does_not_expose_verify_or_reconcile_buttons(): void
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

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $businessDate]));

        $response->assertOk()
            ->assertSee('View')
            ->assertDontSee('>Verify<', false)
            ->assertDontSee('>Reconcile<', false)
            ->assertDontSee('>Finalize<', false)
            ->assertDontSee('unmatched'); // No technical 'unmatched' state leaking
    }

    public function test_query_count_is_bounded_and_historical_date_creates_zero_mutations(): void
    {
        $historicalDate = '2026-06-15';

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $historicalDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 15000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        $initialTxCount = ShopLedgerTransaction::count();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $historicalDate]));

        $response->assertOk();

        // Ensure bounded query count (no N+1 loops)
        $queryCount = count(DB::getQueryLog());
        $this->assertLessThan(50, $queryCount);

        // Ensure zero mutation
        $this->assertSame($initialTxCount, ShopLedgerTransaction::count());
    }
}
