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
use App\Services\Cashbook\HistoricalBankCollectionFetchService;
use App\Services\Cashbook\ReconciliationAutoMatchSuggestionService;
use App\Services\Cashbook\ReconciliationTransactionQuery;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ShopHistoricalBankCollectionFetchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $sanaJp;

    private CompanyAccount $hdfcAccount;

    private CompanyAccount $idfcAccount;

    private CompanyAccount $kotakAccount;

    private LedgerEntryType $paytmType;

    private HistoricalBankCollectionFetchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->sanaJp = Shop::factory()->create(['name' => 'Sana JP', 'code' => 'SANA-JP', 'status' => 'active']);

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

        $this->service = app(HistoricalBankCollectionFetchService::class);
    }

    private function createTransaction(string $businessDate, float $amount, ?int $accountId = null, string $status = 'posted'): ShopLedgerTransaction
    {
        return ShopLedgerTransaction::create([
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $accountId,
            'funding_source' => 'none',
            'business_date' => $businessDate,
            'direction' => 'income',
            'amount' => $amount,
            'status' => $status,
            'entered_by' => $this->admin->id,
        ]);
    }

    public function test_custom_period_filters_correctly_using_business_date(): void
    {
        // Out of range (before)
        $this->createTransaction('2026-07-31', 1000.00);

        // In range
        $tx1 = $this->createTransaction('2026-08-01', 2000.00);
        $tx2 = $this->createTransaction('2026-08-15', 3000.00);
        $tx3 = $this->createTransaction('2026-08-29', 4000.00);

        // Out of range (after)
        $this->createTransaction('2026-08-30', 5000.00);

        $preview = $this->service->preview(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-29'
        );

        $this->assertSame(3, $preview['source_count']);
        $this->assertSame(9000.00, (float) $preview['source_amount']);
        $this->assertSame(3, $preview['eligible_count']);
        $this->assertSame(9000.00, (float) $preview['eligible_amount']);
        $this->assertEqualsCanonicalizing([$tx1->id, $tx2->id, $tx3->id], $preview['eligible_ids']);
    }

    public function test_invalid_reversed_period_is_rejected(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson(route('admin.cashbook.api.historical-bank-collections.preview'), [
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->hdfcAccount->id,
            'from_date' => '2026-08-29',
            'to_date' => '2026-08-01',
        ]);

        $response->assertStatus(422);
    }

    public function test_classification_correctly_separates_eligible_already_linked_different_bank_reconciled_and_void(): void
    {
        // 1. Eligible (company_account_id = null)
        $eligible = $this->createTransaction('2026-08-10', 10000.00, null);

        // 2. Already linked (company_account_id = HDFC)
        $this->createTransaction('2026-08-11', 5000.00, $this->hdfcAccount->id);

        // 3. Different bank (company_account_id = IDFC)
        $this->createTransaction('2026-08-12', 3000.00, $this->idfcAccount->id);

        // 4. Reconciled (linked to finalized statement entry)
        $reconciledTx = $this->createTransaction('2026-08-13', 2500.00, null);
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcAccount->id,
            'transaction_date' => '2026-08-13',
            'direction' => 'in',
            'amount' => 2500.00,
            'source' => 'shop_collection',
            'source_type' => ShopLedgerTransaction::class,
            'source_id' => $reconciledTx->id,
            'status' => 'reconciled',
            'is_finalized' => true,
            'finalized_at' => now(),
            'matched_amount' => 2500.00,
        ]);

        // 5. Void transaction
        $this->createTransaction('2026-08-14', 1500.00, null, 'void');

        $preview = $this->service->preview(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-31'
        );

        $this->assertSame(5, $preview['source_count']);
        $this->assertSame(22000.00, (float) $preview['source_amount']);

        // Eligible
        $this->assertSame(1, $preview['eligible_count']);
        $this->assertSame(10000.00, (float) $preview['eligible_amount']);
        $this->assertSame([$eligible->id], $preview['eligible_ids']);

        // Already linked
        $this->assertSame(1, $preview['already_linked_count']);
        $this->assertSame(5000.00, (float) $preview['already_linked_amount']);

        // Different bank
        $this->assertSame(1, $preview['different_bank_count']);
        $this->assertSame(3000.00, (float) $preview['different_bank_amount']);
        $this->assertCount(1, $preview['different_banks_detail']);
        $this->assertSame('IDFC Current Account', $preview['different_banks_detail'][0]['bank_name']);

        // Reconciled (Locked)
        $this->assertSame(1, $preview['reconciled_count']);
        $this->assertSame(2500.00, (float) $preview['reconciled_amount']);

        // Void (Excluded)
        $this->assertSame(1, $preview['void_count']);
        $this->assertSame(1500.00, (float) $preview['void_amount']);
    }

    public function test_fetch_updates_only_eligible_records_preserving_date_amount_and_id(): void
    {
        $tx = $this->createTransaction('2026-08-15', 12500.00, null);
        $originalId = $tx->id;
        $originalDate = $tx->business_date->toDateString();
        $originalAmount = (float) $tx->amount;

        $result = $this->service->fetch(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-31',
            $this->admin->id
        );

        $this->assertSame(1, $result['updated_count']);
        $this->assertSame(12500.00, (float) $result['updated_amount']);

        $tx->refresh();
        $this->assertSame($originalId, $tx->id);
        $this->assertSame($originalDate, $tx->business_date->toDateString());
        $this->assertSame($originalAmount, (float) $tx->amount);
        $this->assertSame((int) $this->hdfcAccount->id, (int) $tx->company_account_id);
        $this->assertSame(1, ShopLedgerTransaction::where('shop_id', $this->sanaJp->id)->count());
    }

    public function test_fetch_is_fully_idempotent(): void
    {
        $this->createTransaction('2026-08-15', 12500.00, null);

        // Run 1
        $result1 = $this->service->fetch(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-31',
            $this->admin->id
        );
        $this->assertSame(1, $result1['updated_count']);

        // Run 2 (immediate re-run)
        $result2 = $this->service->fetch(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-31',
            $this->admin->id
        );
        $this->assertSame(0, $result2['updated_count']);
        $this->assertSame(0.0, (float) $result2['updated_amount']);
        $this->assertSame(1, $result2['skipped']['already_linked_count']);
        $this->assertSame(1, ShopLedgerTransaction::where('shop_id', $this->sanaJp->id)->count());
    }

    public function test_historical_fetch_does_not_mutate_bank_balance_or_create_statement_entries(): void
    {
        $openingBalance = (float) $this->hdfcAccount->current_balance;
        $this->createTransaction('2026-08-15', 12500.00, null);

        $this->service->fetch(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-31',
            $this->admin->id
        );

        $this->assertSame($openingBalance, (float) $this->hdfcAccount->fresh()->current_balance);
        $this->assertSame(0, CompanyAccountStatementEntry::where('company_account_id', $this->hdfcAccount->id)->count());
    }

    public function test_changing_settings_later_does_not_mutate_historical_transactions(): void
    {
        // 1. Configure HDFC for August
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

        $augTx = $this->createTransaction('2026-08-15', 12500.00, null);

        // Fetch August to HDFC
        $this->service->fetch(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-31',
            $this->admin->id
        );

        $this->assertSame((int) $this->hdfcAccount->id, (int) $augTx->fresh()->company_account_id);

        // 2. In September, admin switches setting to Kotak
        ShopLedgerEntrySetting::where('shop_id', $this->sanaJp->id)
            ->where('entry_type_id', $this->paytmType->id)
            ->update(['company_account_id' => $this->kotakAccount->id]);

        // August transaction remains HDFC
        $this->assertSame((int) $this->hdfcAccount->id, (int) $augTx->fresh()->company_account_id);

        // New September entry goes to Kotak
        $dailyLedgerService = app(DailyLedgerService::class);
        $septTx = $dailyLedgerService->recordEntry([
            'shop_id' => $this->sanaJp->id,
            'business_date' => '2026-09-01',
            'entry_type_code' => 'paytm',
            'amount' => 18000.00,
        ])['transaction'];

        $this->assertSame((int) $this->kotakAccount->id, (int) $septTx->company_account_id);
    }

    public function test_concurrency_race_condition_transaction_reconciled_before_fetch_is_protected(): void
    {
        $tx = $this->createTransaction('2026-08-15', 12500.00, null);

        // Preview sees it as eligible
        $preview = $this->service->preview(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-31'
        );
        $this->assertSame(1, $preview['eligible_count']);

        // Before fetch is called, another user reconciles this transaction
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->idfcAccount->id,
            'transaction_date' => '2026-08-15',
            'direction' => 'in',
            'amount' => 12500.00,
            'source' => 'shop_collection',
            'source_type' => ShopLedgerTransaction::class,
            'source_id' => $tx->id,
            'status' => 'reconciled',
            'is_finalized' => true,
            'finalized_at' => now(),
            'matched_amount' => 12500.00,
        ]);

        // Fetch is called
        $result = $this->service->fetch(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-31',
            $this->admin->id
        );

        // It is safely detected as reconciled during fetch and skipped
        $this->assertSame(0, $result['updated_count']);
        $this->assertSame(1, $result['skipped']['reconciled_count']);
        $this->assertNull($tx->fresh()->company_account_id);
    }

    public function test_api_endpoints_preview_and_fetch_work_via_http(): void
    {
        $this->actingAs($this->admin);
        $this->createTransaction('2026-08-20', 8500.00, null);

        // 1. Preview API
        $previewRes = $this->postJson(route('admin.cashbook.api.historical-bank-collections.preview'), [
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->hdfcAccount->id,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]);

        $previewRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('preview.eligible_count', 1)
            ->assertJsonPath('preview.eligible_amount', 8500);

        // 2. Fetch API
        $fetchRes = $this->postJson(route('admin.cashbook.api.historical-bank-collections.fetch'), [
            'shop_id' => $this->sanaJp->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->hdfcAccount->id,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]);

        $fetchRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.updated_count', 1)
            ->assertJsonPath('result.updated_amount', 8500);
    }

    public function test_unreconciled_cashbook_amount_correction_updates_existing_candidate_without_duplicate(): void
    {
        $tx = $this->createTransaction('2026-08-20', 12000.00, null);

        // Fetch links it to HDFC
        $this->service->fetch(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-31',
            $this->admin->id
        );

        $this->assertSame((int) $this->hdfcAccount->id, (int) $tx->fresh()->company_account_id);

        // Correct Cashbook amount to ₹12,500
        $dailyLedgerService = app(DailyLedgerService::class);
        $dailyLedgerService->updateEntry($tx->id, 12500.00);

        // Assert query feed shows exactly ONE candidate with the updated ₹12,500 amount
        $query = app(ReconciliationTransactionQuery::class);
        $request = Request::create('/admin/cashbook/finance/reconciliation', 'GET', [
            'company_account_id' => $this->hdfcAccount->id,
            'direction' => 'in',
            'type' => 'shop_collection',
        ]);
        $paginated = $query->paginate($request, '2026-08-01', '2026-08-31');

        $this->assertCount(1, $paginated->items());
        $this->assertSame((int) $tx->id, (int) $paginated->items()[0]->source_id);
        $this->assertSame(12500.00, (float) $paginated->items()[0]->amount);

        // Re-running Preview sees it as already linked with updated amount
        $preview = $this->service->preview(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-31'
        );

        $this->assertSame(0, $preview['eligible_count']);
        $this->assertSame(1, $preview['already_linked_count']);
        $this->assertSame(12500.00, (float) $preview['already_linked_amount']);
    }

    public function test_duplicate_source_warning_detected_for_multiple_active_transactions_on_same_date(): void
    {
        $tx1 = $this->createTransaction('2026-08-20', 12000.00, null);
        $tx2 = $this->createTransaction('2026-08-20', 500.00, null);

        $preview = $this->service->preview(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-31'
        );

        $this->assertSame(1, $preview['duplicate_source_warnings_count']);
        $this->assertCount(1, $preview['duplicate_source_warnings_detail']);
        $detail = $preview['duplicate_source_warnings_detail'][0];
        $this->assertSame('2026-08-20', $detail['business_date']);
        $this->assertSame(2, $detail['count']);
        $this->assertSame(12500.00, (float) $detail['total_amount']);
        $this->assertEqualsCanonicalizing([$tx1->id, $tx2->id], $detail['transaction_ids']);
    }

    public function test_same_date_exact_amount_auto_suggests_high_confidence(): void
    {
        $tx = $this->createTransaction('2026-08-20', 12500.00, $this->hdfcAccount->id);

        $statement = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'in',
            'amount' => 12500.00,
            'reference' => 'PAYTM-EXACT-01',
            'narration' => 'Sana JP Paytm settlement',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $query = app(ReconciliationTransactionQuery::class);
        $request = Request::create('/admin/cashbook/finance/reconciliation', 'GET', [
            'company_account_id' => $this->hdfcAccount->id,
            'direction' => 'in',
            'type' => 'shop_collection',
        ]);
        $rows = $query->paginate($request, '2026-08-01', '2026-08-31');

        $suggestionService = app(ReconciliationAutoMatchSuggestionService::class);
        $suggestions = $suggestionService->suggest(collect($rows->items()), graceDays: 2);

        $this->assertCount(1, $suggestions);
        $this->assertSame('SUGGESTED', $suggestions->first()->reconciliation_status);
        $this->assertSame('high', $suggestions->first()->suggestion['confidence']);
        $this->assertSame((int) $statement->id, (int) $suggestions->first()->suggestion['statement_entry_id']);
    }

    public function test_same_date_different_amount_is_not_auto_suggested(): void
    {
        $tx = $this->createTransaction('2026-08-20', 12500.00, $this->hdfcAccount->id);

        // Bank statement has ₹12,450 instead of ₹12,500
        $statement = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'in',
            'amount' => 12450.00,
            'reference' => 'PAYTM-DIFF-01',
            'narration' => 'Sana JP Paytm settlement ₹12,450',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $query = app(ReconciliationTransactionQuery::class);
        $request = Request::create('/admin/cashbook/finance/reconciliation', 'GET', [
            'company_account_id' => $this->hdfcAccount->id,
            'direction' => 'in',
            'type' => 'shop_collection',
        ]);
        $rows = $query->paginate($request, '2026-08-01', '2026-08-31');

        $suggestionService = app(ReconciliationAutoMatchSuggestionService::class);
        $suggestions = $suggestionService->suggest(collect($rows->items()), graceDays: 2);

        // MUST NOT be auto-suggested or auto-reconciled
        $this->assertSame('NEEDS_REVIEW', $suggestions->first()->reconciliation_status);

        // Preview flags the same-date amount difference
        $preview = $this->service->preview(
            $this->sanaJp->id,
            $this->paytmType->id,
            $this->hdfcAccount->id,
            '2026-08-01',
            '2026-08-31'
        );

        $this->assertSame(1, $preview['same_date_amount_differences_count']);
        $diff = $preview['same_date_amount_differences_detail'][0];
        $this->assertSame('2026-08-20', $diff['business_date']);
        $this->assertSame(12500.00, (float) $diff['expected_amount']);
        $this->assertSame(12450.00, (float) $diff['statement_amount']);
        $this->assertSame(-50.00, (float) $diff['difference']);
    }

    public function test_same_date_multiple_statements_is_ambiguous_and_needs_review(): void
    {
        $tx = $this->createTransaction('2026-08-20', 12500.00, $this->hdfcAccount->id);

        // Two bank statements on same date for ₹12,500
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'in',
            'amount' => 12500.00,
            'reference' => 'PAYTM-STMT-A',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'in',
            'amount' => 12500.00,
            'reference' => 'PAYTM-STMT-B',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $query = app(ReconciliationTransactionQuery::class);
        $request = Request::create('/admin/cashbook/finance/reconciliation', 'GET', [
            'company_account_id' => $this->hdfcAccount->id,
            'direction' => 'in',
            'type' => 'shop_collection',
        ]);
        $rows = $query->paginate($request, '2026-08-01', '2026-08-31');

        $suggestionService = app(ReconciliationAutoMatchSuggestionService::class);
        $suggestions = $suggestionService->suggest(collect($rows->items()), graceDays: 2);

        // Ambiguous -> MUST be NEEDS_REVIEW
        $this->assertSame('NEEDS_REVIEW', $suggestions->first()->reconciliation_status);
        $this->assertStringContainsString('possible exact-date matches', $suggestions->first()->suggestion['reason']);
    }

    public function test_reconciliation_deep_link_and_panel_resolves_shop_ledger_transaction_without_404(): void
    {
        $tx = $this->createTransaction('2026-08-20', 12500.00, $this->hdfcAccount->id);

        // Statement in HDFC for exact match
        $stmt = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'in',
            'amount' => 12500.00,
            'reference' => 'HDFC-PAYTM-20AUG',
            'narration' => 'UPI-PAYTM-SETTLEMENT',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $query = app(ReconciliationTransactionQuery::class);
        $request = Request::create('/admin/cashbook/finance/reconciliation', 'GET', [
            'company_account_id' => $this->hdfcAccount->id,
            'direction' => 'in',
            'type' => 'shop_collection',
        ]);
        $rows = $query->paginate($request, '2026-08-01', '2026-08-31');
        $row = $rows->items()[0];

        $this->assertSame('shop_ledger', $row->find_kind);
        $this->assertNotEmpty($row->source_ref);

        // Open reconciliation page with find_kind=shop_ledger & find_ref
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'workspace' => 'needs_reconciliation',
            'find_kind' => 'shop_ledger',
            'find_ref' => $row->source_ref,
            'month' => '2026-08',
            'direction' => 'in',
        ]));

        $response->assertOk();
        $response->assertSee('Sana JP · Paytm');
        $response->assertSee('₹12,500.00');
        $response->assertSee('2026-08-20');
        $response->assertSee('HDFC-PAYTM-20AUG');
    }

    public function test_reconciliation_matching_finalizes_shop_ledger_transaction(): void
    {
        $tx = $this->createTransaction('2026-08-20', 12500.00, $this->hdfcAccount->id);

        $stmt = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'in',
            'amount' => 12500.00,
            'reference' => 'HDFC-PAYTM-20AUG',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $sourceRef = $tx->secureRouteKey();

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.reconciliation.match-existing', [
            'statement' => $stmt->public_uuid,
        ]), [
            'candidate_ref' => $sourceRef,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $stmt->refresh();
        $this->assertTrue((bool) $stmt->is_finalized);
        $this->assertSame('reconciled', $stmt->status);
        $this->assertSame(ShopLedgerTransaction::class, $stmt->source_type);
        $this->assertSame($tx->id, (int) $stmt->source_id);
        $this->assertSame(12500.00, (float) $stmt->matched_amount);
    }

    public function test_invalid_find_ref_does_not_throw_404(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'workspace' => 'needs_reconciliation',
            'find_kind' => 'shop_ledger',
            'find_ref' => 'invalid-garbage-ref-key',
            'month' => '2026-08',
            'direction' => 'in',
        ]));

        $response->assertOk();
    }

    public function test_casio_card_idfc_isolation_and_scoping(): void
    {
        $casio = Shop::factory()->create(['name' => 'Casio', 'code' => 'CASIO', 'status' => 'active']);
        $cardType = LedgerEntryType::create([
            'code' => 'card',
            'name' => 'Card',
            'category' => 'income',
            'active' => true,
            'display_order' => 4,
        ]);

        $txCasio = ShopLedgerTransaction::create([
            'shop_id' => $casio->id,
            'entry_type_id' => $cardType->id,
            'business_date' => '2026-08-15',
            'amount' => 4500.00,
            'direction' => 'income',
            'funding_source' => 'shop',
            'company_account_id' => $this->idfcAccount->id,
            'status' => 'posted',
        ]);

        // Query for IDFC account
        $query = app(ReconciliationTransactionQuery::class);
        $request = Request::create('/admin/cashbook/finance/reconciliation', 'GET', [
            'company_account_id' => $this->idfcAccount->id,
            'direction' => 'in',
            'type' => 'shop_collection',
        ]);
        $rowsIdfc = $query->paginate($request, '2026-08-01', '2026-08-31');

        $this->assertCount(1, $rowsIdfc->items());
        $this->assertSame('Casio', $rowsIdfc->items()[0]->party_name);
        $this->assertSame('Card', $rowsIdfc->items()[0]->description);
        $this->assertSame($this->idfcAccount->id, (int) $rowsIdfc->items()[0]->company_account_id);

        // Query for HDFC account -> MUST NOT contain Casio Card
        $requestHdfc = Request::create('/admin/cashbook/finance/reconciliation', 'GET', [
            'company_account_id' => $this->hdfcAccount->id,
            'direction' => 'in',
            'type' => 'shop_collection',
        ]);
        $rowsHdfc = $query->paginate($requestHdfc, '2026-08-01', '2026-08-31');
        $this->assertCount(0, $rowsHdfc->items());
    }

    public function test_cannot_double_reconcile_already_reconciled_shop_collection(): void
    {
        $tx = $this->createTransaction('2026-08-20', 12500.00, $this->hdfcAccount->id);

        $stmt1 = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'in',
            'amount' => 12500.00,
            'reference' => 'HDFC-PAYTM-20AUG-1',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $stmt2 = CompanyAccountStatementEntry::create([
            'company_account_id' => $this->hdfcAccount->id,
            'transaction_date' => '2026-08-20',
            'direction' => 'in',
            'amount' => 12500.00,
            'reference' => 'HDFC-PAYTM-20AUG-2',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $sourceRef = $tx->secureRouteKey();

        // Match statement 1
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.reconciliation.match-existing', [
            'statement' => $stmt1->public_uuid,
        ]), [
            'candidate_ref' => $sourceRef,
        ])->assertRedirect();

        // Attempting to match statement 2 to same transaction must fail
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.finance.reconciliation.match-existing', [
            'statement' => $stmt2->public_uuid,
        ]), [
            'candidate_ref' => $sourceRef,
        ]);

        $response->assertSessionHasErrors('transaction_id');
        $stmt2->refresh();
        $this->assertFalse((bool) $stmt2->is_finalized);
    }
}
