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
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookExceptionsReconciliationCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $sana;

    private ShopLedgerProfile $sanaProfile;

    private CompanyAccount $kotakBank;

    private CompanyAccount $cashBox;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cashSalesType;

    private DailyLedgerService $dailyLedgerService;

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
    }

    public function test_normal_lifecycle_states_are_not_exceptions(): void
    {
        $businessDate = '2026-08-29';

        // 1. Normal approved online collection (NEEDS VERIFICATION)
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        // 2. Normal approved cash collection (CASH WITH SHOP)
        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 14550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        // 3. Normal floating cheque
        ShopInvoicePaymentRequest::create([
            'shop_id' => $this->sana->id,
            'requested_by' => $this->admin->id,
            'requested_amount' => 5000.00,
            'payment_method' => 'cheque',
            'cheque_number' => 'CHQ-7788',
            'cheque_status' => 'pending',
            'reconciliation_status' => 'floating',
            'payment_date' => $businessDate,
            'status' => 'pending',
            'funding_source' => 'shop_collection',
        ]);

        // Detail page of normal collection shows normal VERIFY action, not RESOLVE ISSUE
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.transaction.show', $paytm['transaction']->id));
        $response->assertOk()
            ->assertSee('NEEDS VERIFICATION')
            ->assertSee('VERIFY RECEIVED')
            ->assertDontSee('RESOLVE ISSUE');

        // Cash detail page shows VERIFY CASH RECEIVED, not RESOLVE ISSUE
        $cashResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.transaction.show', $cash['transaction']->id));
        $cashResponse->assertOk()
            ->assertSee('CASH WITH SHOP')
            ->assertSee('VERIFY CASH RECEIVED')
            ->assertDontSee('RESOLVE ISSUE');
    }

    public function test_duplicate_statement_shows_needs_attention_and_resolve_issue(): void
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

        // Flag statement entry as duplicate
        $stmt = CompanyAccountStatementEntry::where('source_id', $paytm['transaction']->id)->firstOrFail();
        $stmt->update(['duplicate_status' => 'possible_duplicate']);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.transaction.show', $paytm['transaction']->id));

        $response->assertOk()
            ->assertSee('NEEDS ATTENTION')
            ->assertSee('RESOLVE ISSUE')
            ->assertSee('Possible duplicate bank statement entry detected.');
    }

    public function test_needs_attention_page_renders_clean_workspace(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation'));

        $response->assertOk()
            ->assertSee('Needs Attention')
            ->assertSee('Review exceptions');
    }
}
