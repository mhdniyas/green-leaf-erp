<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CashbookTransactionReversalService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookMonthlyBankingVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $idfcBank;

    private CompanyAccount $hdfcBank;

    private LedgerEntryType $cardType;

    private LedgerEntryType $paytmType;

    private DailyLedgerService $dailyLedgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true]);

        $this->shop = Shop::factory()->create(['name' => 'Casio', 'code' => 'AV_CASIO', 'status' => 'active']);
        $this->profile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'av-casio-casio',
            'code' => $this->shop->code,
            'name' => $this->shop->name,
            'enabled' => true,
        ]);

        $this->idfcBank = CompanyAccount::create([
            'name' => 'IDFC BANK',
            'bank_name' => 'IDFC First Bank',
            'account_number' => '1122334455',
            'account_type' => 'bank',
            'current_balance' => 200000.00,
            'enabled' => true,
        ]);

        $this->hdfcBank = CompanyAccount::create([
            'name' => 'HDFC BANK',
            'bank_name' => 'HDFC Bank Ltd',
            'account_number' => '9988776655',
            'account_type' => 'bank',
            'current_balance' => 300000.00,
            'enabled' => true,
        ]);

        $this->cardType = LedgerEntryType::firstOrCreate(
            ['code' => 'card'],
            ['name' => 'Card', 'category' => 'income', 'is_active' => true]
        );
        $this->paytmType = LedgerEntryType::firstOrCreate(
            ['code' => 'paytm'],
            ['name' => 'Paytm', 'category' => 'income', 'is_active' => true]
        );
        LedgerEntryType::firstOrCreate(
            ['code' => 'shop_paid_company'],
            ['name' => 'Shop Paid Company', 'category' => 'settlement', 'is_active' => true]
        );

        $this->dailyLedgerService = app(DailyLedgerService::class);
    }

    public function test_confirm_received_submission_heals_missing_statement_and_persists_reconciliation(): void
    {
        $businessDate = '2026-08-01';

        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->cardType->id],
            ['company_account_id' => $this->idfcBank->id, 'enabled' => true, 'effective_from' => '2026-01-01']
        );

        // Create transaction directly in approved status with NO prior statement entry
        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'entry_type_id' => $this->cardType->id,
            'entry_type_code' => $this->cardType->code,
            'direction' => 'income',
            'amount' => 11963.00,
            'funding_source' => 'none',
            'status' => 'approved',
            'entered_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
        ]);

        // Submit Confirm received
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.day.verify-selected', $this->profile->slug),
            [
                'business_date' => $businessDate,
                'transaction_ids' => [$tx->id],
            ]
        );

        $response->assertRedirect(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'date' => $businessDate,
        ]));
        $response->assertSessionHas('success');

        // Verify statement entry was created and finalized
        $stmt = CompanyAccountStatementEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $tx->id)
            ->first();

        $this->assertNotNull($stmt);
        $this->assertSame('reconciled', $stmt->status);
        $this->assertTrue((bool) $stmt->is_finalized);
        $this->assertEquals(11963.00, (float) $stmt->amount);
        $this->assertSame((int) $this->idfcBank->id, (int) $stmt->company_account_id);
    }

    public function test_monthly_page_renders_banking_section_below_existing_table_and_filters_by_bank(): void
    {
        $month = '2026-08';

        // Connect Card -> IDFC Bank, Paytm -> HDFC Bank
        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->cardType->id],
            ['company_account_id' => $this->idfcBank->id, 'enabled' => true, 'effective_from' => '2026-01-01']
        );
        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->paytmType->id],
            ['company_account_id' => $this->hdfcBank->id, 'enabled' => true, 'effective_from' => '2026-01-01']
        );

        // Day 1 (Aug 1): Card ₹11,963 (approved)
        $txCard1 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => $this->cardType->code,
            'amount' => 11963.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txCard1['transaction'], $this->admin->id);

        // Day 2 (Aug 2): Card ₹5,400 (approved)
        $txCard2 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-02',
            'entry_type_code' => $this->cardType->code,
            'amount' => 5400.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txCard2['transaction'], $this->admin->id);

        // Day 3 (Aug 3): Paytm ₹20,000 destined for HDFC Bank
        $txPaytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-03',
            'entry_type_code' => $this->paytmType->code,
            'amount' => 20000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txPaytm['transaction'], $this->admin->id);

        // Visit Monthly Page with IDFC Bank selected
        $responseIdfc = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'month' => $month,
            'bank_account_id' => $this->idfcBank->id,
        ]));

        $responseIdfc->assertOk()
            ->assertSee('Daily Settlement Summaries') // Existing monthly table preserved
            ->assertSee('Banking &middot; Payment Verification', false) // New section below
            ->assertSee('IDFC BANK')
            ->assertSee('₹11,963.00')
            ->assertSee('₹5,400.00')
            ->assertSee('Verify received');

        // Visit Monthly Page with HDFC Bank selected
        $responseHdfc = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'month' => $month,
            'bank_account_id' => $this->hdfcBank->id,
        ]));

        $responseHdfc->assertOk()
            ->assertSee('Banking &middot; Payment Verification', false)
            ->assertSee('HDFC BANK')
            ->assertSee('₹20,000.00');

        // Visit Single Day Page -> Banking Section must NOT be rendered
        $dayResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'date' => '2026-08-01',
        ]));

        $dayResponse->assertOk()
            ->assertDontSee('Banking &middot; Payment Verification', false);
    }

    public function test_row_verification_from_monthly_banking_section_verifies_payment_and_redirects_with_params(): void
    {
        $month = '2026-08';

        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->cardType->id],
            ['company_account_id' => $this->idfcBank->id, 'enabled' => true, 'effective_from' => '2026-01-01']
        );

        $txCard = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-05',
            'entry_type_code' => $this->cardType->code,
            'amount' => 8500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txCard['transaction'], $this->admin->id);

        // Click row action "Verify received" from monthly view
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.day.verify-selected', $this->profile->slug),
            [
                'business_date' => '2026-08-05',
                'transaction_ids' => [$txCard['transaction']->id],
                'return_to' => 'monthly',
                'month' => $month,
                'bank_account_id' => $this->idfcBank->id,
                'banking_status' => 'pending',
            ]
        );

        $response->assertRedirect(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'month' => $month,
            'bank_account_id' => $this->idfcBank->id,
            'banking_status' => 'pending',
        ]));
        $response->assertSessionHas('success');

        // Check statement is reconciled
        $stmt = CompanyAccountStatementEntry::where('source_id', $txCard['transaction']->id)->first();
        $this->assertNotNull($stmt);
        $this->assertSame('reconciled', $stmt->status);
        $this->assertTrue((bool) $stmt->is_finalized);
    }

    public function test_asynchronous_row_verification_returns_json_with_confirmed_status_and_updated_totals(): void
    {
        $month = '2026-08';

        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->cardType->id],
            ['company_account_id' => $this->idfcBank->id, 'enabled' => true, 'effective_from' => '2026-01-01']
        );

        $txCard1 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-10',
            'entry_type_code' => $this->cardType->code,
            'amount' => 4500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txCard1['transaction'], $this->admin->id);

        $txCard2 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-11',
            'entry_type_code' => $this->cardType->code,
            'amount' => 3500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txCard2['transaction'], $this->admin->id);

        // Submit AJAX / JSON request for txCard1
        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.shop.day.verify-selected', $this->profile->slug),
            [
                'business_date' => '2026-08-10',
                'transaction_ids' => [$txCard1['transaction']->id],
                'return_to' => 'monthly',
                'month' => $month,
                'bank_account_id' => $this->idfcBank->id,
                'banking_status' => 'pending',
            ]
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'verified_count' => 1,
                'transaction_ids' => [$txCard1['transaction']->id],
                'banking_totals' => [
                    'total_count' => 2,
                    'total_amount' => 8000.00,
                    'pending_count' => 1,
                    'pending_amount' => 3500.00,
                    'verified_count' => 1,
                    'verified_amount' => 4500.00,
                ],
            ]);

        $stmt = CompanyAccountStatementEntry::where('source_id', $txCard1['transaction']->id)->first();
        $this->assertNotNull($stmt);
        $this->assertSame('reconciled', $stmt->status);
        $this->assertTrue((bool) $stmt->is_finalized);
    }

    public function test_asynchronous_verification_handles_validation_errors_cleanly(): void
    {
        $month = '2026-08';

        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->cardType->id],
            ['company_account_id' => $this->idfcBank->id, 'enabled' => true, 'effective_from' => '2026-01-01']
        );

        // Transaction in posted status (not approved yet)
        $txCard = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-12',
            'entry_type_code' => $this->cardType->code,
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.shop.day.verify-selected', $this->profile->slug),
            [
                'business_date' => '2026-08-12',
                'transaction_ids' => [$txCard['transaction']->id],
                'return_to' => 'monthly',
                'month' => $month,
                'bank_account_id' => $this->idfcBank->id,
            ]
        );

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure(['message', 'error']);
    }

    public function test_asynchronous_verification_is_idempotent_and_does_not_double_post(): void
    {
        $month = '2026-08';

        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->cardType->id],
            ['company_account_id' => $this->idfcBank->id, 'enabled' => true, 'effective_from' => '2026-01-01']
        );

        $txCard = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-15',
            'entry_type_code' => $this->cardType->code,
            'amount' => 6000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txCard['transaction'], $this->admin->id);

        // First verification
        $firstResponse = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.shop.day.verify-selected', $this->profile->slug),
            [
                'business_date' => '2026-08-15',
                'transaction_ids' => [$txCard['transaction']->id],
                'return_to' => 'monthly',
                'month' => $month,
                'bank_account_id' => $this->idfcBank->id,
            ]
        );
        $firstResponse->assertOk()->assertJson(['success' => true]);

        $settlementCount = ShopLedgerTransaction::where('shop_id', $this->shop->id)
            ->where('business_date', '2026-08-15')
            ->where('direction', 'settlement')
            ->count();
        $this->assertSame(1, $settlementCount);

        // Second verification (repeated request)
        $secondResponse = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.shop.day.verify-selected', $this->profile->slug),
            [
                'business_date' => '2026-08-15',
                'transaction_ids' => [$txCard['transaction']->id],
                'return_to' => 'monthly',
                'month' => $month,
                'bank_account_id' => $this->idfcBank->id,
            ]
        );
        $secondResponse->assertOk()->assertJson(['success' => true]);

        // Still only 1 settlement transaction (no duplicate posting)
        $settlementCountAfter = ShopLedgerTransaction::where('shop_id', $this->shop->id)
            ->where('business_date', '2026-08-15')
            ->where('direction', 'settlement')
            ->count();
        $this->assertSame(1, $settlementCountAfter);
    }

    public function test_verification_creates_canonical_shop_payment_request_and_updates_monthly_received_totals(): void
    {
        $month = '2026-08';

        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->cardType->id],
            ['company_account_id' => $this->idfcBank->id, 'enabled' => true, 'effective_from' => '2026-01-01']
        );

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-16',
            'entry_type_code' => $this->cardType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.shop.day.verify-selected', $this->profile->slug),
            [
                'business_date' => '2026-08-16',
                'transaction_ids' => [$tx['transaction']->id],
                'return_to' => 'monthly',
                'month' => $month,
                'bank_account_id' => $this->idfcBank->id,
            ]
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'verified_count' => 1,
                'company_money_received' => [
                    'received' => 10000.00,
                    'allocated' => 0.00,
                    'unallocated' => 10000.00,
                ],
                'shop_payments' => [
                    'total' => 1,
                ],
            ]);

        // Assert ShopInvoicePaymentRequest exists with reconciled status
        $paymentRequest = ShopInvoicePaymentRequest::where('shop_id', $this->shop->id)->first();
        $this->assertNotNull($paymentRequest);
        $this->assertSame('approved', $paymentRequest->status);
        $this->assertSame('reconciled', $paymentRequest->reconciliation_status);
        $this->assertEquals(10000.00, (float) $paymentRequest->requested_amount);
        $this->assertEquals(10000.00, (float) $paymentRequest->approved_amount);
        $this->assertEquals(10000.00, (float) $paymentRequest->reconciled_amount);

        // Assert CompanyPaymentReconciliation exists
        $recon = CompanyPaymentReconciliation::where('payment_request_id', $paymentRequest->id)->first();
        $this->assertNotNull($recon);
        $this->assertSame('approved', $recon->status);
        $this->assertTrue((bool) $recon->is_finalized);
        $this->assertEquals(10000.00, (float) $recon->statement_amount);
    }

    public function test_allocating_payment_updates_allocated_and_unallocated_without_altering_received(): void
    {
        $month = '2026-08';

        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->cardType->id],
            ['company_account_id' => $this->idfcBank->id, 'enabled' => true, 'effective_from' => '2026-01-01']
        );

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-18',
            'entry_type_code' => $this->cardType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        // Verify payment
        $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.shop.day.verify-selected', $this->profile->slug),
            [
                'business_date' => '2026-08-18',
                'transaction_ids' => [$tx['transaction']->id],
                'return_to' => 'monthly',
                'month' => $month,
                'bank_account_id' => $this->idfcBank->id,
            ]
        );

        $paymentRequest = ShopInvoicePaymentRequest::where('shop_id', $this->shop->id)->first();
        $this->assertNotNull($paymentRequest);

        // Create settlement transaction to allocate against
        $settlementTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-18',
            'entry_type_id' => $this->cardType->id,
            'direction' => 'settlement',
            'amount' => 4000.00,
            'funding_source' => 'none',
            'settlement_delta' => 4000.00,
            'status' => 'posted',
            'entered_by' => $this->admin->id,
        ]);

        // Allocate ₹4,000
        ShopPaymentLedgerAllocation::create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $paymentRequest->id,
            'shop_ledger_transaction_id' => $settlementTx->id,
            'amount' => 4000.00,
            'reconciled_by' => $this->admin->id,
        ]);

        // Check monthly view metrics
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'month' => $month,
            'bank_account_id' => $this->idfcBank->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('totalPaymentsReceived', 10000.00);
        $response->assertViewHas('totalPaymentsAllocated', 4000.00);
        $response->assertViewHas('unallocatedPayments', 6000.00);
    }

    public function test_reversal_cancels_linked_shop_payment_request_and_cleans_monthly_totals(): void
    {
        $month = '2026-08';

        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->cardType->id],
            ['company_account_id' => $this->idfcBank->id, 'enabled' => true, 'effective_from' => '2026-01-01']
        );

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-20',
            'entry_type_code' => $this->cardType->code,
            'amount' => 7000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        // Verify payment
        $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.shop.day.verify-selected', $this->profile->slug),
            [
                'business_date' => '2026-08-20',
                'transaction_ids' => [$tx['transaction']->id],
                'return_to' => 'monthly',
                'month' => $month,
                'bank_account_id' => $this->idfcBank->id,
            ]
        );

        $paymentRequest = ShopInvoicePaymentRequest::where('shop_id', $this->shop->id)->first();
        $this->assertSame('approved', $paymentRequest->status);

        // Reverse transaction
        $reversalService = app(CashbookTransactionReversalService::class);
        $reversalService->reverseReconciledTransaction($tx['transaction'], $this->admin->id, 'Test reversal reason');

        $paymentRequest->refresh();
        $this->assertSame('cancelled', $paymentRequest->status);
        $this->assertSame('cancelled', $paymentRequest->reconciliation_status);

        // Check monthly view has 0 received payments
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'month' => $month,
            'bank_account_id' => $this->idfcBank->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('totalPaymentsReceived', 0.00);
        $response->assertViewHas('unallocatedPayments', 0.00);
    }

    public function test_cross_month_verification_preserves_canonical_payment_date(): void
    {
        ShopLedgerEntrySetting::updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $this->cardType->id],
            ['company_account_id' => $this->idfcBank->id, 'enabled' => true, 'effective_from' => '2026-01-01']
        );

        // August 31 transaction verified on September 2
        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-31',
            'entry_type_code' => $this->cardType->code,
            'amount' => 15000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx['transaction'], $this->admin->id);

        $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.shop.day.verify-selected', $this->profile->slug),
            [
                'business_date' => '2026-08-31',
                'transaction_ids' => [$tx['transaction']->id],
                'return_to' => 'monthly',
                'month' => '2026-08',
                'bank_account_id' => $this->idfcBank->id,
            ]
        );

        $paymentRequest = ShopInvoicePaymentRequest::where('shop_id', $this->shop->id)->first();
        $this->assertNotNull($paymentRequest);
        $this->assertSame('2026-08-31', $paymentRequest->payment_date?->format('Y-m-d'));

        // Check August view includes the payment in its payments list
        $responseAug = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'month' => '2026-08',
            'bank_account_id' => $this->idfcBank->id,
        ]));
        $responseAug->assertOk();
        $responseAug->assertViewHas('allShopPayments', fn ($payments) => $payments->total() === 1 && $payments->first()->id === $paymentRequest->id);

        // Check September view does NOT include August payment in September payments list
        $responseSep = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
            'bank_account_id' => $this->idfcBank->id,
        ]));
        $responseSep->assertOk();
        $responseSep->assertViewHas('allShopPayments', fn ($payments) => $payments->total() === 0);
    }
}
