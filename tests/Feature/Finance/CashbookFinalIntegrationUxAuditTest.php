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
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookFinalIntegrationUxAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopUser;

    private Shop $sana;

    private ShopLedgerProfile $sanaProfile;

    private CompanyAccount $kotakBank;

    private CompanyAccount $cashBox;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cashSalesType;

    private DailyLedgerService $dailyLedgerService;

    private CompanyMoneyPositionService $moneyPositionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->shopUser = User::factory()->create(['name' => 'Shop Staff', 'email' => 'staff@greenleaf.test']);

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true]);

        $this->sana = Shop::factory()->create(['name' => 'Sana', 'code' => 'SANA', 'status' => 'active']);

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

    public function test_scenario_a_online_paytm_end_to_end_lifecycle(): void
    {
        $businessDate = '2026-08-29';

        // 1. Shop records Paytm entry
        $entry = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $tx = $entry['transaction'];

        // Step A: Money Flow shows POSTED
        $mfResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', ['date' => $businessDate]));
        $detailUrl = route('admin.cashbook.transaction.show', $tx->id);
        $mfResponse->assertOk()
            ->assertSee('POSTED')
            ->assertSee($detailUrl, false);

        // Step B: Transaction Detail shows ONE action: APPROVE
        $txResponse = $this->actingAs($this->admin)->get($detailUrl);
        $txResponse->assertOk()
            ->assertSee('POSTED')
            ->assertSee('APPROVE')
            ->assertDontSee('VERIFY RECEIVED');

        // Step C: Perform APPROVE
        $approveResponse = $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.approve', $tx->id));
        $approveResponse->assertRedirect($detailUrl);

        // Step D: Transaction Detail now shows ONE action: VERIFY RECEIVED
        $txResponse2 = $this->actingAs($this->admin)->get($detailUrl);
        $txResponse2->assertOk()
            ->assertSee('NEEDS VERIFICATION')
            ->assertSee('VERIFY RECEIVED')
            ->assertDontSee('APPROVE');

        // Step E: Perform VERIFY RECEIVED
        $verifyResponse = $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.verify', $tx->id));
        $verifyResponse->assertRedirect($detailUrl);

        // Step F: Transaction Detail now shows COMPLETED / RECEIVED
        $txResponse3 = $this->actingAs($this->admin)->get($detailUrl);
        $txResponse3->assertOk()
            ->assertSee('RECEIVED')
            ->assertSee('Completed');

        // Step G: Bank Account Show reflects verified receipt
        $bankResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.bank-accounts.show', $this->kotakBank->id));
        $bankResponse->assertOk()
            ->assertSee('RECEIVED');
    }

    public function test_scenario_b_cash_sales_end_to_end_lifecycle(): void
    {
        $businessDate = '2026-08-29';

        // 1. Shop records cash sales
        $entry = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 14550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $tx = $entry['transaction'];
        $detailUrl = route('admin.cashbook.transaction.show', $tx->id);

        // Step A: Approve cash collection
        $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.approve', $tx->id));

        // Step B: Transaction Detail shows CASH WITH SHOP & VERIFY CASH RECEIVED
        $txResponse = $this->actingAs($this->admin)->get($detailUrl);
        $txResponse->assertOk()
            ->assertSee('CASH WITH SHOP')
            ->assertSee('VERIFY CASH RECEIVED');

        // Step C: Verify Company Money does NOT count unverified cash with shop into verified cash
        $financeResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance', ['date' => $businessDate]));
        $financeResponse->assertOk()
            ->assertSee('₹30,000.00')   // Verified cash box balance
            ->assertSee('₹14,550.00');  // Separate Cash With Shops

        // Step D: Admin performs VERIFY CASH RECEIVED
        $verifyResponse = $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.verify', $tx->id));
        $verifyResponse->assertRedirect($detailUrl);

        // Step E: Detail page shows RECEIVED
        $txResponse2 = $this->actingAs($this->admin)->get($detailUrl);
        $txResponse2->assertOk()
            ->assertSee('RECEIVED');
    }

    public function test_scenario_c_exception_flow_links_to_needs_attention(): void
    {
        $businessDate = '2026-08-29';

        $entry = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($entry['transaction'], $this->admin->id);

        // Flag as duplicate
        $stmt = CompanyAccountStatementEntry::where('source_id', $entry['transaction']->id)->firstOrFail();
        $stmt->update(['duplicate_status' => 'possible_duplicate']);

        $detailUrl = route('admin.cashbook.transaction.show', $entry['transaction']->id);
        $txResponse = $this->actingAs($this->admin)->get($detailUrl);

        $txResponse->assertOk()
            ->assertSee('NEEDS ATTENTION')
            ->assertSee('RESOLVE ISSUE')
            ->assertSee(route('admin.cashbook.finance.reconciliation'), false);
    }

    public function test_money_conservation_and_equations(): void
    {
        $businessDate = '2026-08-29';

        // 1. Paytm (48,962) approved
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        // 2. Cash sales (14,550) approved
        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 14550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        // 3. Floating Cheque (5,000)
        ShopInvoicePaymentRequest::create([
            'shop_id' => $this->sana->id,
            'requested_by' => $this->admin->id,
            'requested_amount' => 5000.00,
            'payment_method' => 'cheque',
            'cheque_number' => 'CHQ-5566',
            'cheque_status' => 'pending',
            'reconciliation_status' => 'floating',
            'payment_date' => $businessDate,
            'status' => 'pending',
            'funding_source' => 'shop_collection',
        ]);

        $summary = $this->moneyPositionService->getMoneyPositionSummary($businessDate);

        // Verified Company Money = Bank + Cash Box
        $this->assertEquals(500000.00, $summary['bank_accounts']['total_verified']);
        $this->assertEquals(30000.00, $summary['company_cash']['total_verified']);
        $this->assertEquals(530000.00, $summary['verified_company_money']);

        // Pending bank verification
        $this->assertEquals(48962.00, $summary['bank_accounts']['total_pending']);

        // Cash With Shops
        $this->assertEquals(14550.00, $summary['cash_with_shops']['total_cash_with_shops']);

        // Floating Cheques
        $this->assertEquals(5000.00, $summary['floating_cheques']['total_floating']);
    }

    public function test_authorization_prevents_unauthorized_actions(): void
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

        // Unauthorized shop manager cannot approve or verify
        $approveRes = $this->actingAs($this->shopUser)->post(route('admin.cashbook.transaction.approve', $paytm['transaction']->id));
        $approveRes->assertForbidden();

        $verifyRes = $this->actingAs($this->shopUser)->post(route('admin.cashbook.transaction.verify', $paytm['transaction']->id));
        $verifyRes->assertForbidden();
    }

    public function test_idempotency_prevents_duplicate_mutations(): void
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
        $tx = $paytm['transaction'];

        // Approve once
        $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.approve', $tx->id));
        $this->assertEquals('approved', $tx->fresh()->status);

        // Approve again (idempotent redirect with info)
        $approveAgain = $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.approve', $tx->id));
        $approveAgain->assertRedirect(route('admin.cashbook.transaction.show', $tx->id));

        // Verify once
        $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.verify', $tx->id));
        $this->assertEquals(548962.00, (float) $this->kotakBank->fresh()->current_balance);

        // Verify again (idempotent redirect without double-crediting bank)
        $verifyAgain = $this->actingAs($this->admin)->post(route('admin.cashbook.transaction.verify', $tx->id));
        $verifyAgain->assertRedirect(route('admin.cashbook.transaction.show', $tx->id));
        $this->assertEquals(548962.00, (float) $this->kotakBank->fresh()->current_balance);
    }
}
