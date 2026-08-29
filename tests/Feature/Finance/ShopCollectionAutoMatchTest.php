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
use App\Services\Cashbook\HistoricalBankCollectionFetchService;
use App\Services\Cashbook\ShopCollectionAutoMatchService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCollectionAutoMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $sana;

    private Shop $grandcity;

    private Shop $casio;

    private CompanyAccount $kotakBank;

    private CompanyAccount $idfcBank;

    private CompanyAccount $hdfcBank;

    private CompanyAccount $cashBox;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $cashSalesType;

    private ShopCollectionAutoMatchService $service;

    private HistoricalBankCollectionFetchService $fetchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->sana = Shop::factory()->create(['name' => 'Sana', 'code' => 'SANA', 'status' => 'active']);
        $this->grandcity = Shop::factory()->create(['name' => 'Grandcity', 'code' => 'GRANDCITY', 'status' => 'active']);
        $this->casio = Shop::factory()->create(['name' => 'Casio', 'code' => 'CASIO', 'status' => 'active']);

        $this->kotakBank = CompanyAccount::create([
            'name' => 'Kotak Bank',
            'account_type' => 'bank',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => 'KOTAK-11111111',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'enabled' => true,
            'is_default' => false,
        ]);

        $this->idfcBank = CompanyAccount::create([
            'name' => 'IDFC Bank',
            'account_type' => 'bank',
            'bank_name' => 'IDFC First',
            'account_number' => 'IDFC-22222222',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'enabled' => true,
            'is_default' => false,
        ]);

        $this->hdfcBank = CompanyAccount::create([
            'name' => 'HDFC Bank',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'account_number' => 'HDFC-33333333',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'enabled' => true,
            'is_default' => false,
        ]);

        $this->cashBox = CompanyAccount::create([
            'name' => 'Company Main Cash Box',
            'account_type' => 'cash',
            'bank_name' => 'Cash Vault',
            'account_number' => 'CASH-VAULT-01',
            'opening_balance' => 10000.00,
            'current_balance' => 10000.00,
            'enabled' => true,
            'is_default' => true,
        ]);

        $this->paytmType = LedgerEntryType::create([
            'code' => 'paytm',
            'name' => 'Paytm',
            'category' => 'income',
            'active' => true,
            'display_order' => 1,
        ]);

        $this->cardType = LedgerEntryType::create([
            'code' => 'card',
            'name' => 'Card',
            'category' => 'income',
            'active' => true,
            'display_order' => 2,
        ]);

        $this->cashSalesType = LedgerEntryType::create([
            'code' => 'cash_sales',
            'name' => 'Cash Sales',
            'category' => 'income',
            'active' => true,
            'display_order' => 3,
        ]);

        $this->service = app(ShopCollectionAutoMatchService::class);
        $this->fetchService = app(HistoricalBankCollectionFetchService::class);
    }

    private function setShopBankSetting(Shop $shop, LedgerEntryType $type, ?CompanyAccount $account): ShopLedgerEntrySetting
    {
        return ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $shop->id, 'entry_type_id' => $type->id],
            [
                'company_account_id' => $account?->id,
                'version' => 1,
                'effective_from' => '2026-01-01',
                'enabled' => true,
                'default_funding_source' => 'none',
                'allowed_funding_sources' => ['none'],
                'include_in_sales' => true,
                'include_in_income' => true,
                'include_in_expense' => false,
                'include_in_pl' => true,
                'include_in_payable' => true,
                'payable_direction' => 'add',
                'settlement_behavior' => 'none',
                'petty_behavior' => 'none',
                'company_pending_behavior' => 'none',
                'generates_secondary_entry' => false,
                'secondary_amount_mode' => 'same_amount',
                'display_order' => 0,
            ]
        );
    }

    private function createShopTx(Shop $shop, LedgerEntryType $type, string $date, float $amount, ?CompanyAccount $account): ShopLedgerTransaction
    {
        return ShopLedgerTransaction::create([
            'shop_id' => $shop->id,
            'entry_type_id' => $type->id,
            'business_date' => $date,
            'amount' => $amount,
            'direction' => 'income',
            'funding_source' => 'shop',
            'company_account_id' => $account?->id,
            'status' => 'posted',
        ]);
    }

    private function createStatement(CompanyAccount $account, string $date, float $amount, string $reference = 'STMT-REF'): CompanyAccountStatementEntry
    {
        return CompanyAccountStatementEntry::create([
            'company_account_id' => $account->id,
            'transaction_date' => $date,
            'direction' => 'in',
            'amount' => $amount,
            'reference' => $reference,
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);
    }

    public function test_shop_category_with_no_bank_setting_is_classified_as_bank_not_configured(): void
    {
        $this->setShopBankSetting($this->casio, $this->cardType, null);
        $this->createShopTx($this->casio, $this->cardType, '2026-08-22', 18450.00, null);

        $preview = $this->service->preview('2026-08-01', '2026-08-31');

        $this->assertSame(1, $preview['summary']['bank_not_configured_count']);
        $this->assertSame(0, $preview['summary']['exact_matches_count']);
        $this->assertSame(18450.00, (float) $preview['summary']['bank_not_configured_amount']);
    }

    public function test_category_configured_to_hdfc_searches_only_hdfc_statement(): void
    {
        $this->setShopBankSetting($this->sana, $this->paytmType, $this->hdfcBank);
        $this->createShopTx($this->sana, $this->paytmType, '2026-08-22', 12500.00, $this->hdfcBank);

        $hdfcStmt = $this->createStatement($this->hdfcBank, '2026-08-22', 12500.00, 'HDFC-STMT');
        $idfcStmt = $this->createStatement($this->idfcBank, '2026-08-22', 12500.00, 'IDFC-STMT');

        $preview = $this->service->preview('2026-08-01', '2026-08-31');

        $this->assertSame(1, $preview['summary']['exact_matches_count']);
        $this->assertSame($hdfcStmt->id, $preview['exact_matches'][0]['statement_id']);
    }

    public function test_category_configured_to_idfc_searches_only_idfc_statement(): void
    {
        $this->setShopBankSetting($this->grandcity, $this->paytmType, $this->idfcBank);
        $this->createShopTx($this->grandcity, $this->paytmType, '2026-08-22', 23726.00, $this->idfcBank);

        $idfcStmt = $this->createStatement($this->idfcBank, '2026-08-22', 23726.00, 'IDFC-STMT');
        $kotakStmt = $this->createStatement($this->kotakBank, '2026-08-22', 23726.00, 'KOTAK-STMT');

        $preview = $this->service->preview('2026-08-01', '2026-08-31');

        $this->assertSame(1, $preview['summary']['exact_matches_count']);
        $this->assertSame($idfcStmt->id, $preview['exact_matches'][0]['statement_id']);
    }

    public function test_paytm_amount_used_not_total_shop_income(): void
    {
        $this->setShopBankSetting($this->sana, $this->paytmType, $this->kotakBank);
        $this->createShopTx($this->sana, $this->paytmType, '2026-08-22', 48962.00, $this->kotakBank);
        $this->createShopTx($this->sana, $this->cashSalesType, '2026-08-22', 20000.00, null);

        $this->createStatement($this->kotakBank, '2026-08-22', 48962.00, 'KOTAK-PAYTM');

        $preview = $this->service->preview('2026-08-01', '2026-08-31');

        $this->assertSame(1, $preview['summary']['exact_matches_count']);
        $this->assertSame(48962.00, (float) $preview['exact_matches'][0]['amount']);
    }

    public function test_card_amount_used_independently_from_paytm(): void
    {
        $this->setShopBankSetting($this->casio, $this->cardType, $this->idfcBank);
        $this->setShopBankSetting($this->casio, $this->paytmType, $this->kotakBank);

        $this->createShopTx($this->casio, $this->cardType, '2026-08-22', 18450.00, $this->idfcBank);
        $this->createShopTx($this->casio, $this->paytmType, '2026-08-22', 50000.00, $this->kotakBank);

        $this->createStatement($this->idfcBank, '2026-08-22', 18450.00, 'IDFC-CARD');
        $this->createStatement($this->kotakBank, '2026-08-22', 50000.00, 'KOTAK-PAYTM');

        $preview = $this->service->preview('2026-08-01', '2026-08-31');

        $this->assertSame(2, $preview['summary']['exact_matches_count']);
    }

    public function test_unreconciled_transaction_with_wrong_bank_classified_as_bank_mapping_mismatch_and_reassignable(): void
    {
        $this->setShopBankSetting($this->sana, $this->paytmType, $this->kotakBank);
        $tx = $this->createShopTx($this->sana, $this->paytmType, '2026-08-22', 48962.00, $this->cashBox);

        $stmt = $this->createStatement($this->kotakBank, '2026-08-22', 48962.00, 'KOTAK-PAYTM');

        $preview = $this->service->preview('2026-08-01', '2026-08-31');

        $this->assertSame(1, $preview['summary']['bank_mapping_mismatches_count']);
        $this->assertSame(0, $preview['summary']['exact_matches_count']);

        $reassignResult = $this->service->reassignToConfiguredBank([$tx->id], $this->admin->id);
        $this->assertSame(1, $reassignResult['reassigned_count']);
        $this->assertSame(48962.00, (float) $reassignResult['reassigned_amount']);

        $tx->refresh();
        $this->assertSame($this->kotakBank->id, (int) $tx->company_account_id);

        $preview2 = $this->service->preview('2026-08-01', '2026-08-31');
        $this->assertSame(0, $preview2['summary']['bank_mapping_mismatches_count']);
        $this->assertSame(1, $preview2['summary']['exact_matches_count']);
    }

    public function test_reconciled_historical_mapping_never_auto_reassigned_when_current_setting_changes(): void
    {
        $this->setShopBankSetting($this->sana, $this->paytmType, $this->kotakBank);

        $tx = $this->createShopTx($this->sana, $this->paytmType, '2026-08-22', 48962.00, $this->hdfcBank);
        $stmt = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcBank->id,
            'transaction_date' => '2026-08-22',
            'direction' => 'in',
            'amount' => 48962.00,
            'status' => 'reconciled',
            'is_finalized' => true,
            'source_type' => ShopLedgerTransaction::class,
            'source_id' => $tx->id,
        ]);

        $preview = $this->service->preview('2026-08-01', '2026-08-31');

        $this->assertSame(1, $preview['summary']['already_reconciled_count']);
        $this->assertSame(0, $preview['summary']['bank_mapping_mismatches_count']);

        $reassignResult = $this->service->reassignToConfiguredBank([$tx->id], $this->admin->id);
        $this->assertSame(0, $reassignResult['reassigned_count']);

        $tx->refresh();
        $this->assertSame($this->hdfcBank->id, (int) $tx->company_account_id);
    }

    public function test_duplicate_source_warning_on_same_shop_category_date(): void
    {
        $this->setShopBankSetting($this->sana, $this->paytmType, $this->kotakBank);

        $this->createShopTx($this->sana, $this->paytmType, '2026-08-22', 20000.00, $this->kotakBank);
        $this->createShopTx($this->sana, $this->paytmType, '2026-08-22', 28962.00, $this->kotakBank);

        $preview = $this->service->preview('2026-08-01', '2026-08-31');

        $this->assertSame(2, $preview['summary']['duplicate_sources_count']);
    }

    public function test_reassign_bank_mapping_endpoint(): void
    {
        $this->setShopBankSetting($this->sana, $this->paytmType, $this->kotakBank);
        $tx = $this->createShopTx($this->sana, $this->paytmType, '2026-08-22', 48962.00, $this->cashBox);

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.finance.reconciliation.auto-match-shop-collections.reassign'), [
            'transaction_ids' => [$tx->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('result.reassigned_count', 1);

        $tx->refresh();
        $this->assertSame($this->kotakBank->id, (int) $tx->company_account_id);
    }

    public function test_zero_statement_rows_returns_no_statement_data(): void
    {
        // Sana Paytm -> Kotak, but Kotak has 0 statement rows in DB
        $this->setShopBankSetting($this->sana, $this->paytmType, $this->kotakBank);
        $this->createShopTx($this->sana, $this->paytmType, '2026-08-22', 48962.00, $this->kotakBank);

        $preview = $this->service->preview('2026-08-01', '2026-08-31');

        $this->assertSame(1, $preview['summary']['no_statement_data_count']);
        $this->assertSame('no_statement_data', $preview['grouped_by_bank'][$this->kotakBank->id]['no_match'][0]['status']);
        $this->assertFalse($preview['grouped_by_bank'][$this->kotakBank->id]['statement_coverage']['has_data']);
    }

    public function test_transaction_outside_statement_date_range_returns_outside_statement_coverage(): void
    {
        // Kotak has statements only up to 2026-08-18
        $this->setShopBankSetting($this->sana, $this->paytmType, $this->kotakBank);
        $this->createStatement($this->kotakBank, '2026-08-01', 10000.00);
        $this->createStatement($this->kotakBank, '2026-08-18', 20000.00);

        // Transaction is on 2026-08-22 (after statement coverage range)
        $this->createShopTx($this->sana, $this->paytmType, '2026-08-22', 48962.00, $this->kotakBank);

        $preview = $this->service->preview('2026-08-01', '2026-08-31');

        $this->assertSame(1, $preview['summary']['outside_coverage_count']);
        $this->assertSame('outside_statement_coverage', $preview['grouped_by_bank'][$this->kotakBank->id]['outside_coverage'][0]['status']);
        $this->assertSame('2026-08-01 → 2026-08-18', $preview['grouped_by_bank'][$this->kotakBank->id]['outside_coverage'][0]['statement_coverage']);
    }

    public function test_no_amount_match_when_date_in_range_but_amount_missing(): void
    {
        // Kotak has statements on 2026-08-22 for other amounts
        $this->setShopBankSetting($this->sana, $this->paytmType, $this->kotakBank);
        $this->createStatement($this->kotakBank, '2026-08-22', 1000.00);

        // Transaction is on 2026-08-22 for 48962.00 (which differs by more than grace amount)
        $this->createShopTx($this->sana, $this->paytmType, '2026-08-22', 48962.00, $this->kotakBank);

        $preview = $this->service->preview('2026-08-01', '2026-08-31');

        // On same date, it classifies as amount_difference
        $this->assertSame(1, $preview['summary']['amount_differences_count']);
    }

    public function test_cash_account_warning_flagged_for_direct_bank_category(): void
    {
        // Sana Paytm -> Cash Box (cash account)
        $this->setShopBankSetting($this->sana, $this->paytmType, $this->cashBox);
        $this->createShopTx($this->sana, $this->paytmType, '2026-08-22', 48962.00, $this->cashBox);

        $preview = $this->service->preview('2026-08-01', '2026-08-31');

        $this->assertTrue($preview['grouped_by_bank'][$this->cashBox->id]['is_cash_warning']);
    }
}
