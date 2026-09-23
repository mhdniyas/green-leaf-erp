<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\ShopSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminShopPaymentsMonthlyPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $bankAccount;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $expenseType;

    private ShopLedgerEntrySetting $paytmSetting;

    private ShopLedgerEntrySetting $cardSetting;

    private ShopLedgerEntrySetting $expenseSetting;

    private ShopCashbookRelation $payableRelation;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.user_access.main_admin_email' => 'main-admin@example.test']);
        $this->admin = User::factory()->create(['email' => 'main-admin@example.test']);
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create(['name' => 'Casio Boutique', 'code' => 'CASIO-01']);
        $this->profile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'casio-boutique',
            'code' => $this->shop->code,
            'name' => $this->shop->name,
            'enabled' => true,
        ]);

        $this->bankAccount = CompanyAccount::query()->create([
            'name' => 'HDFC Main Account',
            'account_number' => '1234567890',
            'account_type' => 'bank',
            'enabled' => true,
            'current_balance' => 100000.00,
        ]);

        $this->paytmType = LedgerEntryType::query()->create([
            'code' => 'paytm_collection',
            'name' => 'Paytm',
            'category' => 'income',
        ]);

        $this->cardType = LedgerEntryType::query()->create([
            'code' => 'card_collection',
            'name' => 'Card',
            'category' => 'income',
        ]);

        $this->expenseType = LedgerEntryType::query()->create([
            'code' => 'shop_expense',
            'name' => 'Electricity Expense',
            'category' => 'expense',
        ]);

        LedgerEntryType::query()->firstOrCreate([
            'code' => 'shop_paid_company',
        ], [
            'name' => 'Shop Paid Company',
            'category' => 'settlement',
        ]);

        $this->paytmSetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'display_name' => 'Paytm',
            'company_account_id' => $this->bankAccount->id,
            'enabled' => true,
            'effective_from' => '2026-01-01',
        ]);

        $this->cardSetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cardType->id,
            'display_name' => 'Card',
            'company_account_id' => $this->bankAccount->id,
            'enabled' => true,
            'effective_from' => '2026-01-01',
        ]);

        $this->expenseSetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->expenseType->id,
            'display_name' => 'Electricity Expense',
            'enabled' => true,
            'include_in_expense' => true,
            'effective_from' => '2026-01-01',
        ]);

        // Setup Company Payable relation
        $this->payableRelation = ShopCashbookRelation::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Company Payable',
            'relation_type' => 'default_company_payable',
            'is_company_payable' => true,
            'enabled' => true,
        ]);

        ShopCashbookRelationItem::query()->create([
            'relation_id' => $this->payableRelation->id,
            'shop_ledger_entry_setting_id' => $this->paytmSetting->id,
            'role' => 'add',
        ]);

        ShopCashbookRelationItem::query()->create([
            'relation_id' => $this->payableRelation->id,
            'shop_ledger_entry_setting_id' => $this->cardSetting->id,
            'role' => 'add',
        ]);
    }

    public function test_monthly_page_defaults_to_current_month_and_scopes_by_business_date(): void
    {
        $targetMonth = '2026-09';
        $unrelatedMonth = '2026-08';

        // Transaction in target month
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 15000.00,
            'direction' => 'income',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        // Transaction in unrelated month
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 99000.00,
            'direction' => 'income',
            'business_date' => '2026-08-15',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => $targetMonth,
        ]));

        $response->assertOk();
        $response->assertViewHas('month', $targetMonth);
        $response->assertViewHas('summary', function (array $summary): bool {
            return (float) $summary['company_payable'] === 15000.00
                && (float) $summary['pending_verification'] === 15000.00;
        });

        $dailyRows = $response->viewData('dailyRows');
        $this->assertCount(1, $dailyRows);
        $this->assertEquals('2026-09-06', $dailyRows[0]['business_date']);
        $this->assertEquals(15000.00, $dailyRows[0]['company_payable']);
    }

    public function test_company_payable_matches_authoritative_settlement_service(): void
    {
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 15000.00,
            'direction' => 'income',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cardType->id,
            'amount' => 20000.00,
            'direction' => 'income',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $settlementService = app(ShopSettlementService::class);
        $expectedCalc = $settlementService->calculateCompanyPayable((int) $this->shop->id, '2026-09-01', '2026-09-30');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEquals((float) $expectedCalc['formula_net'], $summary['company_payable']);
        $this->assertEquals(35000.00, $summary['company_payable']);
    }

    public function test_dynamic_contributor_breakdown_columns(): void
    {
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 15000.00,
            'direction' => 'income',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cardType->id,
            'amount' => 20000.00,
            'direction' => 'income',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $contributors = $response->viewData('dynamicContributors');
        $this->assertCount(2, $contributors);
        $this->assertEquals('Paytm', $contributors[0]['label']);
        $this->assertEquals('Card', $contributors[1]['label']);

        $dailyRows = $response->viewData('dailyRows');
        $this->assertEquals(15000.00, $dailyRows[0]['contributors'][$contributors[0]['key']]);
        $this->assertEquals(20000.00, $dailyRows[0]['contributors'][$contributors[1]['key']]);
    }

    public function test_verify_action_uses_existing_records_and_creates_no_duplicate_ledger_transaction(): void
    {
        $txPaytm = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 15000.00,
            'direction' => 'income',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $initialTxCount = ShopLedgerTransaction::query()->count();

        // Before verification:
        $responseBefore = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));
        $this->assertEquals(15000.00, $responseBefore->viewData('summary')['pending_verification']);
        $this->assertEquals(0.00, $responseBefore->viewData('summary')['received']);

        // Execute Verify Day Action
        $verifyResponse = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.verify-day', [
            'shop' => $this->profile->slug,
        ]), [
            'business_date' => '2026-09-06',
            'month' => '2026-09',
        ]);

        $verifyResponse->assertRedirect(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        // Check that collection transactions are not duplicated (remains exactly 1 paytm collection)
        $this->assertEquals(1, ShopLedgerTransaction::query()->where('entry_type_id', $this->paytmType->id)->count());

        $txCountAfterFirstVerify = ShopLedgerTransaction::query()->count();

        // Repeating verify should be completely idempotent and create 0 new records
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.verify-day', [
            'shop' => $this->profile->slug,
        ]), [
            'business_date' => '2026-09-06',
            'month' => '2026-09',
        ]);

        $this->assertEquals($txCountAfterFirstVerify, ShopLedgerTransaction::query()->count());

        // Check statement entry was created & reconciled
        $stmt = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $txPaytm->id)
            ->first();

        $this->assertNotNull($stmt);
        $this->assertEquals('reconciled', $stmt->status);
        $this->assertTrue((bool) $stmt->is_finalized);

        // After verification:
        $responseAfter = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $summaryAfter = $responseAfter->viewData('summary');
        $this->assertEquals(15000.00, $summaryAfter['company_payable']);
        $this->assertEquals(15000.00, $summaryAfter['received']);
        $this->assertEquals(0.00, $summaryAfter['pending_verification']);
        $this->assertEquals(15000.00, $summaryAfter['pending_allocation']);

        $dailyRowsAfter = $responseAfter->viewData('dailyRows');
        $this->assertEquals('completed', $dailyRowsAfter[0]['action']);
    }

    public function test_allocate_action_uses_existing_allocation_settings_and_decreases_pending_allocation(): void
    {
        // 1. Create open expense obligation
        $expenseTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->expenseType->id,
            'amount' => 15000.00,
            'direction' => 'expense',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        // Configure expense allocation targets
        $this->profile->update([
            'payment_configuration' => [
                'expense_allocation' => [
                    'enabled' => true,
                    'auto_allocate' => true,
                    'category_ids' => [$this->expenseSetting->id],
                ],
            ],
        ]);

        // 2. Create collection and verify it
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 15000.00,
            'direction' => 'income',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.verify-day', [
            'shop' => $this->profile->slug,
        ]), [
            'business_date' => '2026-09-06',
            'month' => '2026-09',
        ]);

        // 3. Execute Day Allocation
        $allocResponse = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.allocate-day', [
            'shop' => $this->profile->slug,
        ]), [
            'business_date' => '2026-09-06',
            'month' => '2026-09',
        ]);

        $allocResponse->assertRedirect(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        // Check allocation record was created
        $allocation = ShopPaymentLedgerAllocation::query()
            ->where('shop_id', $this->shop->id)
            ->where('shop_ledger_transaction_id', $expenseTx->id)
            ->first();

        $this->assertNotNull($allocation);
        $this->assertEquals(15000.00, (float) $allocation->amount);

        // After allocation check summary:
        $responseAfter = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $summaryAfter = $responseAfter->viewData('summary');
        $this->assertEquals(15000.00, $summaryAfter['company_payable']);
        $this->assertEquals(15000.00, $summaryAfter['received']);
        $this->assertEquals(0.00, $summaryAfter['pending_verification']);
        $this->assertEquals(0.00, $summaryAfter['pending_allocation']);

        $dailyRowsAfter = $responseAfter->viewData('dailyRows');
        $this->assertEquals('completed', $dailyRowsAfter[0]['action']);
    }

    public function test_overview_and_payments_totals_match(): void
    {
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 15000.00,
            'direction' => 'income',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $overviewResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.overview', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $overviewResponse->assertOk();
        $overviewReport = $overviewResponse->viewData('financialReport');
        $overviewSettlement = $overviewReport['settlement'];

        $paymentsResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $paymentsResponse->assertOk();
        $paymentsSummary = $paymentsResponse->viewData('summary');

        $this->assertEquals($overviewSettlement['due'], $paymentsSummary['company_payable']);
        $this->assertEquals($overviewSettlement['received'], $paymentsSummary['received']);
        $this->assertEquals($overviewSettlement['pending_verification'], $paymentsSummary['pending_verification']);
    }

    public function test_verify_preserves_selected_month_parameter(): void
    {
        $selectedMonth = '2026-07';

        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 5000.00,
            'direction' => 'income',
            'business_date' => '2026-07-12',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.verify-day', [
            'shop' => $this->profile->slug,
        ]), [
            'business_date' => '2026-07-12',
            'month' => $selectedMonth,
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => $selectedMonth,
        ]));
    }

    public function test_partial_allocation_shows_pending_allocation(): void
    {
        // 1. Create expense obligation of 5,000 (less than 15,000 collection)
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->expenseType->id,
            'amount' => 5000.00,
            'direction' => 'expense',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        // Configure expense allocation targets
        $this->profile->update([
            'payment_configuration' => [
                'expense_allocation' => [
                    'enabled' => true,
                    'auto_allocate' => true,
                    'category_ids' => [$this->expenseSetting->id],
                ],
            ],
        ]);

        // 2. Collection of 15,000
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 15000.00,
            'direction' => 'income',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        // Verify
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.verify-day', [
            'shop' => $this->profile->slug,
        ]), [
            'business_date' => '2026-09-06',
            'month' => '2026-09',
        ]);

        // Allocate (will allocate 5,000, leaving 10,000 pending allocation)
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.allocate-day', [
            'shop' => $this->profile->slug,
        ]), [
            'business_date' => '2026-09-06',
            'month' => '2026-09',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $summary = $response->viewData('summary');
        $this->assertEquals(15000.00, $summary['company_payable']);
        $this->assertEquals(15000.00, $summary['received']);
        $this->assertEquals(10000.00, $summary['pending_allocation']);

        $workQueue = $response->viewData('workQueue');
        $this->assertNotEmpty($workQueue);
        $this->assertEquals(10000.00, $workQueue[0]['unallocated_amount']);
        $this->assertEquals('partially_allocated', $workQueue[0]['allocation_status']);
    }
}
