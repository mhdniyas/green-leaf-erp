<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Cashbook\ReconciliationTransactionQuery;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

class ShopPaymentCategoryBankIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $sanaJp;

    private Shop $grandcity;

    private Shop $casio;

    private CompanyAccount $hdfcAccount;

    private CompanyAccount $idfcAccount;

    private CompanyAccount $kotakAccount;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $cashType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->sanaJp = Shop::factory()->create(['name' => 'Sana JP', 'code' => 'SANA-JP', 'status' => 'active']);
        $this->grandcity = Shop::factory()->create(['name' => 'Grandcity', 'code' => 'GRANDCITY', 'status' => 'active']);
        $this->casio = Shop::factory()->create(['name' => 'Casio', 'code' => 'CASIO', 'status' => 'active']);

        $this->hdfcAccount = CompanyAccount::create([
            'name' => 'HDFC Current Account',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'account_number' => 'HDFC-1234567890',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'enabled' => true,
            'is_default' => false,
        ]);

        $this->idfcAccount = CompanyAccount::create([
            'name' => 'IDFC Current Account',
            'account_type' => 'bank',
            'bank_name' => 'IDFC First Bank',
            'account_number' => 'IDFC-9876543210',
            'opening_balance' => 50000.00,
            'current_balance' => 50000.00,
            'enabled' => true,
            'is_default' => false,
        ]);

        $this->kotakAccount = CompanyAccount::create([
            'name' => 'Kotak Bank Account',
            'account_type' => 'bank',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => 'KOTAK-55555555',
            'opening_balance' => 75000.00,
            'current_balance' => 75000.00,
            'enabled' => true,
            'is_default' => false,
        ]);

        $this->paytmType = LedgerEntryType::create([
            'code' => 'paytm',
            'name' => 'Paytm',
            'category' => 'income',
            'active' => true,
            'display_order' => 3,
        ]);

        $this->cardType = LedgerEntryType::create([
            'code' => 'card',
            'name' => 'Card',
            'category' => 'income',
            'active' => true,
            'display_order' => 2,
        ]);

        $this->cashType = LedgerEntryType::create([
            'code' => 'cash_sales',
            'name' => 'Cash Sales',
            'category' => 'income',
            'active' => true,
            'display_order' => 1,
        ]);
    }

    public function test_sana_jp_paytm_mapped_to_hdfc_records_hdfc_company_account_id(): void
    {
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->hdfcAccount->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'include_in_payable' => false,
        ]);

        $dailyLedgerService = app(DailyLedgerService::class);
        $result = $dailyLedgerService->recordEntry([
            'shop_id' => $this->sanaJp->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => 'paytm',
            'amount' => 12500.00,
            'entered_by' => $this->admin->id,
            'notes' => 'Sana JP daily Paytm collections',
        ]);

        $tx = $result['transaction'];
        $this->assertSame((int) $this->hdfcAccount->id, (int) $tx->company_account_id);
        $this->assertSame('2026-08-29', $tx->business_date->toDateString());
        $this->assertSame(12500.00, (float) $tx->amount);
    }

    public function test_grandcity_paytm_mapped_to_idfc_records_idfc_company_account_id(): void
    {
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->grandcity->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->idfcAccount->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'include_in_payable' => false,
        ]);

        $dailyLedgerService = app(DailyLedgerService::class);
        $result = $dailyLedgerService->recordEntry([
            'shop_id' => $this->grandcity->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => 'paytm',
            'amount' => 25000.00,
            'entered_by' => $this->admin->id,
        ]);

        $tx = $result['transaction'];
        $this->assertSame((int) $this->idfcAccount->id, (int) $tx->company_account_id);
    }

    public function test_same_paytm_category_can_map_to_different_accounts_for_different_shops(): void
    {
        // Sana JP -> HDFC
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->hdfcAccount->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        // Grandcity -> IDFC
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->grandcity->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->idfcAccount->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        // Casio -> Kotak
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->kotakAccount->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        $dailyLedgerService = app(DailyLedgerService::class);

        $tx1 = $dailyLedgerService->recordEntry([
            'shop_id' => $this->sanaJp->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => 'paytm',
            'amount' => 1000.00,
        ])['transaction'];

        $tx2 = $dailyLedgerService->recordEntry([
            'shop_id' => $this->grandcity->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => 'paytm',
            'amount' => 2000.00,
        ])['transaction'];

        $tx3 = $dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => 'paytm',
            'amount' => 3000.00,
        ])['transaction'];

        $this->assertSame((int) $this->hdfcAccount->id, (int) $tx1->company_account_id);
        $this->assertSame((int) $this->idfcAccount->id, (int) $tx2->company_account_id);
        $this->assertSame((int) $this->kotakAccount->id, (int) $tx3->company_account_id);
    }

    public function test_unmapped_paytm_does_not_appear_under_an_arbitrary_bank(): void
    {
        // Setting exists but company_account_id is null
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => null,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        $dailyLedgerService = app(DailyLedgerService::class);
        $tx = $dailyLedgerService->recordEntry([
            'shop_id' => $this->sanaJp->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => 'paytm',
            'amount' => 5000.00,
        ])['transaction'];

        $this->assertNull($tx->company_account_id);

        // Does not appear in reconciliation candidate feed for HDFC
        $query = app(ReconciliationTransactionQuery::class);
        $request = Request::create('/admin/cashbook/finance/reconciliation', 'GET', [
            'company_account_id' => $this->hdfcAccount->id,
            'direction' => 'in',
        ]);
        $paginated = $query->paginate($request, '2026-08-01', '2026-08-31');

        $this->assertCount(0, $paginated->items());
    }

    public function test_cash_sales_do_not_become_bank_reconciliation_candidates(): void
    {
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->cashType->id,
            'company_account_id' => null,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        $dailyLedgerService = app(DailyLedgerService::class);
        $dailyLedgerService->recordEntry([
            'shop_id' => $this->sanaJp->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => 'cash_sales',
            'amount' => 45000.00,
        ]);

        $query = app(ReconciliationTransactionQuery::class);
        $request = Request::create('/admin/cashbook/finance/reconciliation', 'GET', [
            'direction' => 'in',
            'type' => 'shop_collection',
        ]);
        $paginated = $query->paginate($request, '2026-08-01', '2026-08-31');

        $this->assertCount(0, $paginated->items());
    }

    public function test_reconciliation_candidate_uses_shop_sales_date_and_displays_correctly(): void
    {
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->hdfcAccount->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        $dailyLedgerService = app(DailyLedgerService::class);
        $tx = $dailyLedgerService->recordEntry([
            'shop_id' => $this->sanaJp->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => 'paytm',
            'amount' => 12500.00,
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $query = app(ReconciliationTransactionQuery::class);
        $request = Request::create('/admin/cashbook/finance/reconciliation', 'GET', [
            'company_account_id' => $this->hdfcAccount->id,
            'direction' => 'in',
            'type' => 'shop_collection',
        ]);
        $paginated = $query->paginate($request, '2026-08-01', '2026-08-31');

        $this->assertCount(1, $paginated->items());
        $candidate = $paginated->items()[0];

        $this->assertSame((int) $tx->id, (int) $candidate->source_id);
        $this->assertSame('Sana JP', $candidate->party_name);
        $this->assertSame('Paytm', $candidate->description);
        $this->assertSame('2026-08-29', (string) $candidate->transaction_date);
        $this->assertSame(12500.00, (float) $candidate->amount);
        $this->assertSame((int) $this->hdfcAccount->id, (int) $candidate->company_account_id);
        $this->assertSame('NEEDS_REVIEW', $candidate->reconciliation_status);
    }

    public function test_recording_shop_transaction_does_not_increase_finalized_bank_balance(): void
    {
        $openingBalance = (float) $this->hdfcAccount->current_balance;

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->hdfcAccount->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        $dailyLedgerService = app(DailyLedgerService::class);
        $dailyLedgerService->recordEntry([
            'shop_id' => $this->sanaJp->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => 'paytm',
            'amount' => 12500.00,
        ]);

        // Assert bank balance is NOT modified merely by shop recording Paytm
        $this->assertSame($openingBalance, (float) $this->hdfcAccount->fresh()->current_balance);
        $this->assertSame(0, CompanyAccountStatementEntry::where('company_account_id', $this->hdfcAccount->id)->count());
    }

    public function test_reconciled_shop_transaction_cannot_be_edited_or_deleted(): void
    {
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->hdfcAccount->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        $dailyLedgerService = app(DailyLedgerService::class);
        $tx = $dailyLedgerService->recordEntry([
            'shop_id' => $this->sanaJp->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => 'paytm',
            'amount' => 12500.00,
        ])['transaction'];

        $this->assertTrue($tx->canBeEditedByShopOwner());

        // Simulate a finalized reconciliation against a bank statement
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcAccount->id,
            'transaction_date' => '2026-08-29',
            'direction' => 'in',
            'amount' => 12500.00,
            'reference' => 'PAYTM-SETTLEMENT-001',
            'source' => 'shop_collection',
            'source_type' => ShopLedgerTransaction::class,
            'source_id' => $tx->id,
            'status' => 'reconciled',
            'is_finalized' => true,
            'finalized_at' => now(),
            'matched_amount' => 12500.00,
        ]);

        $this->assertFalse($tx->fresh()->canBeEditedByShopOwner());

        // DailyLedgerService update throws exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reconciled transactions cannot be modified.');
        $dailyLedgerService->updateEntry($tx->id, 15000.00);
    }

    public function test_admin_settings_endpoint_saves_company_account_id(): void
    {
        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => null,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'include_in_payable' => false,
            'generates_secondary_entry' => false,
            'secondary_amount_mode' => 'same_amount',
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson(route('admin.cashbook.api.shop-settings.update'), [
            'setting_id' => $setting->id,
            'company_account_id' => $this->hdfcAccount->id,
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'include_in_payable' => false,
            'generates_secondary_entry' => false,
            'secondary_amount_mode' => 'same_amount',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame((int) $this->hdfcAccount->id, (int) $setting->fresh()->company_account_id);
    }

    public function test_existing_shop_settlement_and_snapshot_balances_remain_unchanged(): void
    {
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->hdfcAccount->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'include_in_payable' => false,
            'settlement_behavior' => 'increase',
        ]);

        $dailyLedgerService = app(DailyLedgerService::class);
        $result = $dailyLedgerService->recordEntry([
            'shop_id' => $this->sanaJp->id,
            'business_date' => '2026-08-29',
            'entry_type_code' => 'paytm',
            'amount' => 12500.00,
        ]);

        $snapshot = $result['snapshot'];
        $this->assertSame(12500.00, (float) $snapshot->total_sales);
        $this->assertSame(12500.00, (float) $snapshot->total_income);
        $this->assertSame(12500.00, (float) $snapshot->settlement_increase);
        $this->assertSame(12500.00, (float) $snapshot->closing_shop_position);
    }
}
