<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentCompanyPayableMatch;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CompanyPayableReceiptMatchingService;
use App\Services\Cashbook\ShopPaymentLedgerReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminShopPaymentsControlCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $bankAccount;

    private CompanyAccount $cashAccount;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $cashType;

    private LedgerEntryType $expenseType;

    private ShopLedgerEntrySetting $paytmSetting;

    private ShopLedgerEntrySetting $cardSetting;

    private ShopLedgerEntrySetting $cashSetting;

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
            'name' => 'HDFC Direct Bank',
            'account_number' => '1234567890',
            'account_type' => 'bank',
            'enabled' => true,
            'current_balance' => 100000.00,
        ]);

        $this->cashAccount = CompanyAccount::query()->create([
            'name' => 'Company Cash Desk',
            'account_number' => 'CASH-DESK',
            'account_type' => 'cash',
            'enabled' => true,
            'current_balance' => 50000.00,
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

        $this->cashType = LedgerEntryType::query()->create([
            'code' => 'shop_cash_collection',
            'name' => 'Cash',
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

        // Direct bank setting (has company_account_id)
        $this->paytmSetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'display_name' => 'Paytm',
            'company_account_id' => $this->bankAccount->id,
            'enabled' => true,
            'effective_from' => '2026-01-01',
        ]);

        // Direct bank setting (has company_account_id)
        $this->cardSetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cardType->id,
            'display_name' => 'Card',
            'company_account_id' => $this->bankAccount->id,
            'enabled' => true,
            'effective_from' => '2026-01-01',
        ]);

        // Shop-held cash setting (null company_account_id)
        $this->cashSetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cashType->id,
            'display_name' => 'Cash',
            'company_account_id' => null,
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

        // Setup Company Payable relation with Paytm + Card + Cash
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
            'display_order' => 1,
        ]);

        ShopCashbookRelationItem::query()->create([
            'relation_id' => $this->payableRelation->id,
            'shop_ledger_entry_setting_id' => $this->cardSetting->id,
            'role' => 'add',
            'display_order' => 2,
        ]);

        ShopCashbookRelationItem::query()->create([
            'relation_id' => $this->payableRelation->id,
            'shop_ledger_entry_setting_id' => $this->cashSetting->id,
            'role' => 'add',
            'display_order' => 3,
        ]);
    }

    public function test_default_month_is_current_month(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', $this->profile->slug));

        $response->assertOk();
        $response->assertViewHas('month', now()->format('Y-m'));
    }

    public function test_custom_month_filter_loads_only_month_data(): void
    {
        // Sept tx
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 10000.00,
            'direction' => 'income',
            'business_date' => '2026-09-05',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        // Oct tx
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 20000.00,
            'direction' => 'income',
            'business_date' => '2026-10-05',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEquals(10000.00, $summary['company_payable']);
        $this->assertEquals(10000.00, $summary['payable_bank']);
        $this->assertEquals(0.00, $summary['payable_cash']);

        $dailyRows = $response->viewData('dailyRows');
        $this->assertCount(1, $dailyRows);
        $this->assertEquals('2026-09-05', $dailyRows[0]['business_date']);
    }

    public function test_gross_company_payable_uses_authoritative_formula_net(): void
    {
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 12000.00,
            'direction' => 'income',
            'business_date' => '2026-09-10',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cardType->id,
            'amount' => 8000.00,
            'direction' => 'income',
            'business_date' => '2026-09-10',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEquals(20000.00, $summary['company_payable']);
        $this->assertEquals(0.00, $summary['received']);
        $this->assertEquals(20000.00, $summary['pending_verification']);
    }

    public function test_dynamic_contributors_loaded_from_company_payable_relation(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $contributors = $response->viewData('dynamicContributors');
        $this->assertCount(3, $contributors);
        $labels = array_column($contributors, 'label');
        $this->assertContains('Paytm', $labels);
        $this->assertContains('Card', $labels);
        $this->assertContains('Cash', $labels);
    }

    public function test_direct_bank_vs_shop_held_partition_by_company_account_id(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $contributors = $response->viewData('dynamicContributors');
        $byLabel = collect($contributors)->keyBy('label');

        $this->assertTrue($byLabel['Paytm']['is_direct_bank']);
        $this->assertTrue($byLabel['Card']['is_direct_bank']);
        $this->assertFalse($byLabel['Cash']['is_direct_bank']);
    }

    public function test_received_for_date_calculated_strictly_from_active_matches(): void
    {
        // 1. Create collection for 2026-09-15
        $tx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cashType->id,
            'amount' => 5000.00,
            'direction' => 'income',
            'business_date' => '2026-09-15',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        // 2. Create receipt and match ₹3,000 to this date
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-16',
            'requested_amount' => 3000.00,
            'approved_amount' => 3000.00,
            'reconciled_amount' => 3000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        ShopPaymentCompanyPayableMatch::query()->create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment->id,
            'payable_business_date' => '2026-09-15',
            'amount' => 3000.00,
            'status' => 'active',
            'matched_by' => $this->admin->id,
            'matched_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $dailyRows = $response->viewData('dailyRows');
        $this->assertEquals(5000.00, $dailyRows[0]['company_payable']);
        $this->assertEquals(3000.00, $dailyRows[0]['received']);
        $this->assertEquals(2000.00, $dailyRows[0]['pending']);
    }

    public function test_no_double_reduction_of_company_payable_from_matches(): void
    {
        // Gross payable = 10,000
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 10000.00,
            'direction' => 'income',
            'business_date' => '2026-09-10',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        // Verified receipt and match of 10,000
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.verify-day', $this->profile->slug), [
            'business_date' => '2026-09-10',
            'month' => '2026-09',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $summary = $response->viewData('summary');
        // Gross remains 10,000 (NOT reduced to 0), Received = 10,000, Pending = 0
        $this->assertEquals(10000.00, $summary['company_payable']);
        $this->assertEquals(10000.00, $summary['received']);
        $this->assertEquals(0.00, $summary['pending_verification']);
    }

    public function test_bulk_verify_all_direct_bank_processes_each_transaction_safely(): void
    {
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 5000.00,
            'direction' => 'income',
            'business_date' => '2026-09-01',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cardType->id,
            'amount' => 7000.00,
            'direction' => 'income',
            'business_date' => '2026-09-02',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.verify-all-direct', $this->profile->slug), [
            'month' => '2026-09',
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));
        $response->assertSessionHas('success');

        // Check that matches were created 1:1 for both dates
        $matches = ShopPaymentCompanyPayableMatch::query()->where('shop_id', $this->shop->id)->where('status', 'active')->get();
        $this->assertCount(2, $matches);
        $this->assertEquals(12000.00, $matches->sum('amount'));
    }

    public function test_record_manual_cash_receipt_creates_canonical_payment_request_via_service(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.record-cash', $this->profile->slug), [
            'amount' => 25000.00,
            'payment_date' => '2026-09-18',
            'payment_method' => 'cash',
            'company_account_id' => $this->cashAccount->id,
            'payment_reference' => 'RCP-CASH-001',
            'notes' => 'Received from shop manager',
            'auto_match_fifo' => 0,
            'month' => '2026-09',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payment = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $this->shop->id)
            ->where('payment_reference', 'RCP-CASH-001')
            ->first();

        $this->assertNotNull($payment);
        $this->assertEquals(25000.00, (float) $payment->requested_amount);
        $this->assertEquals('approved', $payment->status);
        $this->assertEquals('reconciled', $payment->reconciliation_status);
    }

    public function test_record_manual_cash_receipt_with_auto_match_fifo_links_oldest_dates(): void
    {
        // Create 2 outstanding cash collection dates
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cashType->id,
            'amount' => 8000.00,
            'direction' => 'income',
            'business_date' => '2026-09-01',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cashType->id,
            'amount' => 12000.00,
            'direction' => 'income',
            'business_date' => '2026-09-02',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        // Record 15,000 cash receipt with auto_match_fifo
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.record-cash', $this->profile->slug), [
            'amount' => 15000.00,
            'payment_date' => '2026-09-10',
            'payment_method' => 'cash',
            'company_account_id' => $this->cashAccount->id,
            'payment_reference' => 'RCP-FIFO-01',
            'auto_match_fifo' => 1,
            'month' => '2026-09',
        ]);

        $response->assertRedirect();

        $matches = ShopPaymentCompanyPayableMatch::query()
            ->where('shop_id', $this->shop->id)
            ->where('status', 'active')
            ->orderBy('payable_business_date')
            ->get();

        $this->assertCount(2, $matches);
        // 01 Sep: 8,000 fully matched
        $this->assertEquals('2026-09-01', $matches[0]->payable_business_date->toDateString());
        $this->assertEquals(8000.00, (float) $matches[0]->amount);
        // 02 Sep: 7,000 partially matched (remaining 5,000 due)
        $this->assertEquals('2026-09-02', $matches[1]->payable_business_date->toDateString());
        $this->assertEquals(7000.00, (float) $matches[1]->amount);
    }

    public function test_cross_month_fifo_matching_covers_previous_month_outstanding_payable(): void
    {
        // Aug 28 payable of 5,000
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cashType->id,
            'amount' => 5000.00,
            'direction' => 'income',
            'business_date' => '2026-08-28',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        // Sept 01 payable of 10,000
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cashType->id,
            'amount' => 10000.00,
            'direction' => 'income',
            'business_date' => '2026-09-01',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        // Sept receipt of 8,000
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-05',
            'requested_amount' => 8000.00,
            'approved_amount' => 8000.00,
            'reconciled_amount' => 8000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $service = app(CompanyPayableReceiptMatchingService::class);
        $preview = $service->previewFifoMatch($payment);

        $this->assertCount(2, $preview['matches']);
        // First match is Aug 28 (5,000)
        $this->assertEquals('2026-08-28', $preview['matches'][0]['business_date']);
        $this->assertEquals(5000.00, $preview['matches'][0]['match_now']);
        // Second match is Sept 01 (3,000)
        $this->assertEquals('2026-09-01', $preview['matches'][1]['business_date']);
        $this->assertEquals(3000.00, $preview['matches'][1]['match_now']);
    }

    public function test_fifo_match_preview_json_endpoint_returns_proposed_matches(): void
    {
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cashType->id,
            'amount' => 10000.00,
            'direction' => 'income',
            'business_date' => '2026-09-01',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-05',
            'requested_amount' => 10000.00,
            'approved_amount' => 10000.00,
            'reconciled_amount' => 10000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.cashbook.shop.history.payments.fifo-preview', [
            'shop' => $this->profile->slug,
            'payment_request_id' => $payment->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('payment_id', $payment->id);
        $response->assertJsonPath('total_proposed_match', 10000);
    }

    public function test_custom_match_to_payable_action_validates_and_stores_matches(): void
    {
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cashType->id,
            'amount' => 20000.00,
            'direction' => 'income',
            'business_date' => '2026-09-01',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-05',
            'requested_amount' => 15000.00,
            'approved_amount' => 15000.00,
            'reconciled_amount' => 15000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.match-payable', $this->profile->slug), [
            'payment_request_id' => $payment->id,
            'mode' => 'custom',
            'matches' => [
                ['business_date' => '2026-09-01', 'amount' => 15000.00],
            ],
            'month' => '2026-09',
        ]);

        $response->assertRedirect();
        $this->assertEquals(1, ShopPaymentCompanyPayableMatch::query()->where('payment_request_id', $payment->id)->count());
    }

    public function test_matching_cannot_exceed_receipt_available_amount(): void
    {
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cashType->id,
            'amount' => 50000.00,
            'direction' => 'income',
            'business_date' => '2026-09-01',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        // Receipt of only 10,000
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-05',
            'requested_amount' => 10000.00,
            'approved_amount' => 10000.00,
            'reconciled_amount' => 10000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.match-payable', $this->profile->slug), [
            'payment_request_id' => $payment->id,
            'mode' => 'custom',
            'matches' => [
                ['business_date' => '2026-09-01', 'amount' => 15000.00], // Exceeds 10,000
            ],
            'month' => '2026-09',
        ]);

        $response->assertSessionHasErrors('matches');
    }

    public function test_expense_allocation_action_calls_auto_allocate_and_decreases_pending_allocation(): void
    {
        // 1. Expense obligation of 6,000
        ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->expenseType->id,
            'amount' => 6000.00,
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

        // 2. Receipt of 10,000
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'requested_amount' => 10000.00,
            'approved_amount' => 10000.00,
            'reconciled_amount' => 10000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.allocate-expenses', $this->profile->slug), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
        ]);

        $response->assertRedirect();

        // 6,000 allocated, 4,000 remaining unallocated
        $this->assertEquals(1, ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->count());
        $this->assertEquals(6000.00, (float) ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->sum('amount'));
    }

    public function test_all_actions_preserve_selected_month_parameter_in_redirect(): void
    {
        $selectedMonth = '2026-07';

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.verify-all-direct', $this->profile->slug), [
            'month' => $selectedMonth,
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => $selectedMonth,
        ]));
    }

    public function test_verify_all_direct_bank_approves_posted_transactions_and_creates_matches(): void
    {
        // Direct bank transaction with status 'posted'
        $tx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->paytmType->id,
            'amount' => 15000.00,
            'direction' => 'income',
            'business_date' => '2026-09-08',
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.verify-all-direct', $this->profile->slug), [
            'month' => '2026-09',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $tx->refresh();
        $this->assertEquals('approved', $tx->status);

        // Match created
        $this->assertEquals(1, ShopPaymentCompanyPayableMatch::query()->where('shop_id', $this->shop->id)->count());
        $match = ShopPaymentCompanyPayableMatch::query()->where('shop_id', $this->shop->id)->first();
        $this->assertEquals('2026-09-08', $match->payable_business_date->toDateString());
        $this->assertEquals(15000.00, (float) $match->amount);
    }

    public function test_reversed_expense_allocations_remain_in_db_for_audit(): void
    {
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'requested_amount' => 5000.00,
            'approved_amount' => 5000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $obligation = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->expenseType->id,
            'amount' => 5000.00,
            'direction' => 'expense',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $allocation = ShopPaymentLedgerAllocation::query()->create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment->id,
            'shop_ledger_transaction_id' => $obligation->id,
            'amount' => 5000.00,
            'status' => 'active',
            'allocated_by' => $this->admin->id,
            'allocated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.undo-receipt-allocations', $this->profile->slug), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Row remains in database with status reversed and audit fields
        $allocation->refresh();
        $this->assertEquals('reversed', $allocation->status);
        $this->assertEquals($this->admin->id, $allocation->reversed_by);
        $this->assertNotNull($allocation->reversed_at);
        $this->assertNotNull($allocation->reversal_reason);
    }

    public function test_reversed_payable_matches_remain_in_db_for_audit(): void
    {
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'requested_amount' => 8000.00,
            'approved_amount' => 8000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $match = ShopPaymentCompanyPayableMatch::query()->create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment->id,
            'payable_business_date' => '2026-09-06',
            'amount' => 8000.00,
            'status' => 'active',
            'matched_by' => $this->admin->id,
            'matched_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.undo-receipt-matches', $this->profile->slug), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Row remains in database with status reversed and audit fields
        $match->refresh();
        $this->assertEquals('reversed', $match->status);
        $this->assertEquals($this->admin->id, $match->reversed_by);
        $this->assertNotNull($match->reversed_at);
        $this->assertNotNull($match->reversal_reason);
    }

    public function test_repeated_undo_is_idempotent(): void
    {
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'requested_amount' => 4000.00,
            'approved_amount' => 4000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $match = ShopPaymentCompanyPayableMatch::query()->create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment->id,
            'payable_business_date' => '2026-09-06',
            'amount' => 4000.00,
            'status' => 'active',
            'matched_by' => $this->admin->id,
            'matched_at' => now(),
        ]);

        // First undo
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.undo-receipt-matches', $this->profile->slug), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
        ])->assertRedirect();

        $match->refresh();
        $this->assertEquals('reversed', $match->status);

        // Second undo should be idempotent and not fail
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.undo-receipt-matches', $this->profile->slug), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
        ])->assertRedirect();

        $match->refresh();
        $this->assertEquals('reversed', $match->status);
    }

    public function test_undo_match_does_not_unverify_receipt(): void
    {
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'requested_amount' => 7000.00,
            'approved_amount' => 7000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        ShopPaymentCompanyPayableMatch::query()->create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment->id,
            'payable_business_date' => '2026-09-06',
            'amount' => 7000.00,
            'status' => 'active',
            'matched_by' => $this->admin->id,
            'matched_at' => now(),
        ]);

        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.undo-receipt-matches', $this->profile->slug), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
        ]);

        $payment->refresh();
        $this->assertEquals('approved', $payment->status);
        $this->assertEquals('reconciled', $payment->reconciliation_status);
    }

    public function test_undo_allocation_does_not_remove_payable_match(): void
    {
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'requested_amount' => 5000.00,
            'approved_amount' => 5000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $obligation = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->expenseType->id,
            'amount' => 5000.00,
            'direction' => 'expense',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $match = ShopPaymentCompanyPayableMatch::query()->create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment->id,
            'payable_business_date' => '2026-09-06',
            'amount' => 5000.00,
            'status' => 'active',
            'matched_by' => $this->admin->id,
            'matched_at' => now(),
        ]);

        $allocation = ShopPaymentLedgerAllocation::query()->create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment->id,
            'shop_ledger_transaction_id' => $obligation->id,
            'amount' => 5000.00,
            'status' => 'active',
            'allocated_by' => $this->admin->id,
            'allocated_at' => now(),
        ]);

        // Undo allocation only
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.undo-receipt-allocations', $this->profile->slug), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
        ]);

        $allocation->refresh();
        $this->assertEquals('reversed', $allocation->status);

        // Match remains completely active
        $match->refresh();
        $this->assertEquals('active', $match->status);
    }

    public function test_clear_and_reallocate_no_longer_deletes_allocation_history(): void
    {
        $this->profile->update([
            'payment_configuration' => [
                'expense_allocation' => [
                    'enabled' => true,
                    'auto_allocate' => true,
                    'category_ids' => [$this->expenseSetting->id],
                ],
            ],
        ]);

        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'requested_amount' => 5000.00,
            'approved_amount' => 5000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $obligation = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->expenseType->id,
            'amount' => 5000.00,
            'direction' => 'expense',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $oldAllocation = ShopPaymentLedgerAllocation::query()->create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment->id,
            'shop_ledger_transaction_id' => $obligation->id,
            'amount' => 5000.00,
            'status' => 'active',
            'allocated_by' => $this->admin->id,
            'allocated_at' => now(),
        ]);

        $service = app(ShopPaymentLedgerReconciliationService::class);
        $res = $service->clearAndReallocatePayment($payment, (int) $this->admin->id);

        $this->assertEquals(1, $res['cleared_count']);

        // Old allocation row is preserved as reversed, not deleted!
        $oldAllocation->refresh();
        $this->assertEquals('reversed', $oldAllocation->status);

        // A new active allocation row was created
        $activeCount = ShopPaymentLedgerAllocation::query()
            ->where('payment_request_id', $payment->id)
            ->where('status', 'active')
            ->count();
        $this->assertEquals(1, $activeCount);

        // Total rows in DB is 2 (1 reversed + 1 active)
        $totalCount = ShopPaymentLedgerAllocation::query()
            ->where('payment_request_id', $payment->id)
            ->count();
        $this->assertEquals(2, $totalCount);
    }

    public function test_individual_day_match_undo_only_changes_selected_match(): void
    {
        $payment1 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-05',
            'requested_amount' => 3000.00,
            'approved_amount' => 3000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $payment2 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-05',
            'requested_amount' => 4000.00,
            'approved_amount' => 4000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $match1 = ShopPaymentCompanyPayableMatch::query()->create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment1->id,
            'payable_business_date' => '2026-09-05',
            'amount' => 3000.00,
            'status' => 'active',
            'matched_by' => $this->admin->id,
            'matched_at' => now(),
        ]);

        $match2 = ShopPaymentCompanyPayableMatch::query()->create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment2->id,
            'payable_business_date' => '2026-09-05',
            'amount' => 4000.00,
            'status' => 'active',
            'matched_by' => $this->admin->id,
            'matched_at' => now(),
        ]);

        // Undo only match1
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.undo-match', $this->profile->slug), [
            'match_id' => $match1->id,
            'month' => '2026-09',
        ])->assertRedirect();

        $match1->refresh();
        $this->assertEquals('reversed', $match1->status);

        $match2->refresh();
        $this->assertEquals('active', $match2->status);
    }

    public function test_clear_all_day_reverses_only_active_matches_for_that_shop_and_date(): void
    {
        $payment1 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-05',
            'requested_amount' => 2000.00,
            'approved_amount' => 2000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $matchSept5 = ShopPaymentCompanyPayableMatch::query()->create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment1->id,
            'payable_business_date' => '2026-09-05',
            'amount' => 2000.00,
            'status' => 'active',
            'matched_by' => $this->admin->id,
            'matched_at' => now(),
        ]);

        $matchSept6 = ShopPaymentCompanyPayableMatch::query()->create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment1->id,
            'payable_business_date' => '2026-09-06',
            'amount' => 2000.00,
            'status' => 'active',
            'matched_by' => $this->admin->id,
            'matched_at' => now(),
        ]);

        // Clear all matches for Sept 5 only
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.undo-day-matches', $this->profile->slug), [
            'business_date' => '2026-09-05',
            'month' => '2026-09',
        ])->assertRedirect();

        $matchSept5->refresh();
        $this->assertEquals('reversed', $matchSept5->status);

        $matchSept6->refresh();
        $this->assertEquals('active', $matchSept6->status);
    }

    public function test_repeated_allocation_reversal_and_reallocation_preserves_history_without_unique_constraint_collision(): void
    {
        $this->profile->update([
            'payment_configuration' => [
                'expense_allocation' => [
                    'enabled' => true,
                    'auto_allocate' => true,
                    'category_ids' => [$this->expenseSetting->id],
                ],
            ],
        ]);

        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'requested_amount' => 5000.00,
            'approved_amount' => 5000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $obligation = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->expenseType->id,
            'amount' => 1200.00,
            'direction' => 'expense',
            'business_date' => '2026-09-06',
            'funding_source' => 'shop_cash',
            'status' => 'approved',
        ]);

        $service = app(ShopPaymentLedgerReconciliationService::class);

        // 1. Initial Allocation
        $allocResult1 = $service->allocatePayment(
            payment: $payment,
            allocations: [
                ['ledger_transaction_id' => $obligation->id, 'amount' => 1200.00],
            ],
            userId: (int) $this->admin->id,
        );

        $this->assertCount(1, $allocResult1);
        $this->assertEquals(1200.00, $allocResult1->first()->amount);

        // 2. Undo / Reversal
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.undo-receipt-allocations', $this->profile->slug), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
        ])->assertRedirect();

        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'payment_request_id' => $payment->id,
            'shop_ledger_transaction_id' => $obligation->id,
            'amount' => 1200.00,
            'status' => 'reversed',
        ]);
        $this->assertEquals(0, ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->where('status', 'active')->count());

        // 3. Allocate ₹1,200 to SAME transaction again
        $allocResult2 = $service->allocatePayment(
            payment: $payment,
            allocations: [
                ['ledger_transaction_id' => $obligation->id, 'amount' => 1200.00],
            ],
            userId: (int) $this->admin->id,
        );

        $this->assertCount(1, $allocResult2);

        // Assert: 1 reversed + 1 active
        $this->assertEquals(1, ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->where('status', 'reversed')->count());
        $this->assertEquals(1, ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->where('status', 'active')->count());
        $this->assertEquals(2, ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->count());

        // Active total must be ₹1,200, NOT ₹2,400
        $activeTotal = (float) ShopPaymentLedgerAllocation::query()
            ->where('payment_request_id', $payment->id)
            ->where('status', 'active')
            ->sum('amount');
        $this->assertEquals(1200.00, $activeTotal);

        $snapshot = $service->allocationIntegritySnapshot($payment->fresh());
        $this->assertEquals(1200.00, $snapshot['actual_allocated']);
        $this->assertEquals(3800.00, $snapshot['actual_remaining']);

        // 4. Undo / Reverse again
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.undo-receipt-allocations', $this->profile->slug), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
        ])->assertRedirect();

        // 5. Allocate ₹1,200 to SAME transaction third time
        $allocResult3 = $service->allocatePayment(
            payment: $payment,
            allocations: [
                ['ledger_transaction_id' => $obligation->id, 'amount' => 1200.00],
            ],
            userId: (int) $this->admin->id,
        );

        $this->assertCount(1, $allocResult3);

        // Assert: 2 reversed + 1 active
        $this->assertEquals(2, ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->where('status', 'reversed')->count());
        $this->assertEquals(1, ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->where('status', 'active')->count());
        $this->assertEquals(3, ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->count());

        $activeTotalFinal = (float) ShopPaymentLedgerAllocation::query()
            ->where('payment_request_id', $payment->id)
            ->where('status', 'active')
            ->sum('amount');
        $this->assertEquals(1200.00, $activeTotalFinal);

        $snapshotFinal = $service->allocationIntegritySnapshot($payment->fresh());
        $this->assertEquals(1200.00, $snapshotFinal['actual_allocated']);
        $this->assertEquals(3800.00, $snapshotFinal['actual_remaining']);
    }
}
