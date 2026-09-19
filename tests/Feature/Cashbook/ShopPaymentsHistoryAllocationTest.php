<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyExpenseLedgerAllocation;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\DailyLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopPaymentsHistoryAllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $companyAccount;

    private DailyLedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.user_access.main_admin_email' => 'admin-history@example.test']);
        $this->admin = User::factory()->create(['email' => 'admin-history@example.test']);
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create(['name' => 'Casio Fresh', 'code' => 'CASIO']);
        $this->profile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'casio-fresh-'.$this->shop->id,
            'code' => $this->shop->code,
            'name' => $this->shop->name,
            'enabled' => true,
        ]);

        $this->companyAccount = CompanyAccount::query()->create([
            'name' => 'SIB Company Bank',
            'account_type' => 'bank',
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);

        Account::query()->firstOrCreate(['code' => '1010'], ['name' => 'Cash', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1020'], ['name' => 'Bank', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);

        foreach ([
            ['code' => 'cash_sales', 'name' => 'Cash Sales', 'category' => 'income'],
            ['code' => 'paytm', 'name' => 'Paytm', 'category' => 'income'],
            ['code' => 'rent_expense', 'name' => 'Rent', 'category' => 'expense'],
            ['code' => 'electricity', 'name' => 'Electricity', 'category' => 'expense'],
            ['code' => 'shop_paid_company', 'name' => 'Shop Paid Company', 'category' => 'settlement'],
        ] as $entryType) {
            $et = LedgerEntryType::query()->firstOrCreate(['code' => $entryType['code']], $entryType + ['active' => true]);
            ShopLedgerEntrySetting::query()->firstOrCreate([
                'shop_id' => $this->shop->id,
                'entry_type_id' => $et->id,
            ], [
                'enabled' => true,
                'effective_from' => '2026-01-01',
                'include_in_sales' => $entryType['category'] === 'income',
                'include_in_expense' => $entryType['category'] === 'expense',
            ]);
        }

        $this->ledgerService = app(DailyLedgerService::class);
    }

    public function test_payment_history_page_loads_with_allocation_summary_and_is_read_only(): void
    {
        // 1. Create a payment of ₹25,000
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'bank',
            'payment_reference' => 'TXN-99881',
            'payment_date' => '2026-08-15',
            'requested_amount' => 25000.00,
            'approved_amount' => 25000.00,
            'reconciled_amount' => 25000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        // Create settlement transaction of ₹15,000
        $settlement = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-14',
            'entry_type_code' => 'rent_expense',
            'amount' => 15000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // Create allocation of ₹15,000
        $allocation = ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $payment->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $settlement->id,
            'amount' => 15000.00,
            'reconciled_by' => $this->admin->id,
            'batch_uuid' => null,
        ]);

        // Snapshot DB count before GET
        $allocCountBefore = ShopPaymentLedgerAllocation::query()->count();
        $paymentCountBefore = ShopInvoicePaymentRequest::query()->count();

        // 2. Perform GET request to payment history
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.history.payments', [
                'shop' => $this->profile->slug,
                'month' => '2026-08',
            ]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.shops.history.payments');
        $response->assertViewHas('allocationSummary');
        $response->assertViewHas('unallocatedPayments');
        $response->assertViewHas('autoAllocateProposal');

        // Verify summary values
        $summary = $response->viewData('allocationSummary');
        $this->assertSame(25000.00, $summary['total_payment_amount']);
        $this->assertSame(15000.00, $summary['allocated_amount']);
        $this->assertSame(10000.00, $summary['unallocated_amount']);
        $this->assertSame(15000.00, $summary['shop_to_company_allocated']);
        $this->assertSame(1, $summary['allocation_count']);

        // Verify unallocated list contains this payment with ₹10,000 remaining
        $unallocList = $response->viewData('unallocatedPayments');
        $this->assertCount(1, $unallocList);
        $this->assertSame($payment->id, $unallocList->first()->id);
        $this->assertSame(10000.00, (float) $unallocList->first()->unallocated_amount_calc);

        // Verify GET request was 100% read-only
        $this->assertSame($allocCountBefore, ShopPaymentLedgerAllocation::query()->count());
        $this->assertSame($paymentCountBefore, ShopInvoicePaymentRequest::query()->count());

        // Verify UI output elements
        $response->assertSee('Allocation Summary');
        $response->assertSee('Unallocated / Partially Allocated');
        $response->assertSee('Payment History &amp; Allocation Details', false);
        $response->assertSee('TXN-99881');
        $response->assertSee('₹15,000.00');
        $response->assertSee('₹10,000.00');
        $response->assertSee('Partially Allocated');
        $response->assertSee('Shop → Company', false);
    }

    public function test_payment_history_supports_company_to_shop_allocations(): void
    {
        // 1. Create a direct company statement expense
        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => '2026-08-10',
            'entry_type' => 'company_expense',
            'direction' => 'out',
            'amount' => 8000.00,
            'reference' => 'COMP-EXP-001',
            'status' => 'reconciled',
            'is_finalized' => true,
        ]);

        // Create a shop ledger expense obligation
        $shopExpense = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-10',
            'entry_type_code' => 'electricity',
            'amount' => 8000.00,
            'funding_source' => 'company_later',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // Create Company -> Shop allocation
        CompanyExpenseLedgerAllocation::query()->create([
            'company_statement_entry_id' => $statement->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $shopExpense->id,
            'allocated_amount' => 8000.00,
            'allocation_date' => '2026-08-10',
            'status' => 'active',
            'allocated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.history.payments', [
                'shop' => $this->profile->slug,
                'month' => '2026-08',
            ]));

        $response->assertOk();
        $summary = $response->viewData('allocationSummary');
        $this->assertSame(8000.00, $summary['company_to_shop_allocated']);
        $this->assertSame(1, $summary['allocation_count']);
    }

    public function test_auto_allocate_action_can_be_triggered_from_payment_history_context(): void
    {
        // 1. Receive ₹30,000 unallocated payment
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'bank',
            'payment_reference' => 'BULK-ALLOC-TEST',
            'payment_date' => '2026-08-18',
            'requested_amount' => 30000.00,
            'approved_amount' => 30000.00,
            'reconciled_amount' => 30000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        // Create 2 open settlements
        $settlement1 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-05',
            'entry_type_code' => 'rent_expense',
            'amount' => 12000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $settlement2 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-10',
            'entry_type_code' => 'rent_expense',
            'amount' => 18000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // Check history page shows auto allocate proposal of ₹30,000
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.history.payments', [
                'shop' => $this->profile->slug,
                'month' => '2026-08',
            ]));

        $response->assertOk();
        $proposal = $response->viewData('autoAllocateProposal');
        $this->assertSame(30000.00, $proposal['proposed_total']);
        $this->assertSame(30000.00, $proposal['eligible_amount']);
        $this->assertSame(30000.00, $proposal['settlement_outstanding']);

        // Execute bulk allocation using existing endpoint
        $submissionUuid = (string) Str::uuid();
        $postResponse = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 30000.00,
                'submission_uuid' => $submissionUuid,
            ]);

        $postResponse->assertRedirect();

        // Verify allocations created
        $this->assertSame(2, ShopPaymentLedgerAllocation::query()->where('batch_uuid', $submissionUuid)->count());
        $this->assertSame(30000.00, (float) ShopPaymentLedgerAllocation::query()->where('batch_uuid', $submissionUuid)->sum('amount'));

        // Re-check history page: unallocated payments is now 0 and payment is Fully Allocated
        $refreshedResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.history.payments', [
                'shop' => $this->profile->slug,
                'month' => '2026-08',
            ]));

        $refreshedResponse->assertOk();
        $refreshedSummary = $refreshedResponse->viewData('allocationSummary');
        $this->assertSame(30000.00, $refreshedSummary['allocated_amount']);
        $this->assertSame(0.00, $refreshedSummary['unallocated_amount']);
        $this->assertCount(0, $refreshedResponse->viewData('unallocatedPayments'));
        $refreshedResponse->assertSee('Fully Allocated');
        $refreshedResponse->assertSee('Auto');
    }

    public function test_payment_history_filters_by_method_and_search(): void
    {
        // 1. Create 2 payments
        $bankPayment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'bank',
            'payment_reference' => 'HDFC-REF-101',
            'payment_date' => '2026-08-10',
            'requested_amount' => 15000.00,
            'approved_amount' => 15000.00,
            'reconciled_amount' => 15000.00,
            'shop_note' => 'Main bank collection',
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $cashPayment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_reference' => 'CASH-REF-202',
            'payment_date' => '2026-08-12',
            'requested_amount' => 5000.00,
            'approved_amount' => 5000.00,
            'reconciled_amount' => 5000.00,
            'shop_note' => 'Counter cash handoff',
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        // Filter by method=cash
        $responseCash = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.history.payments', [
                'shop' => $this->profile->slug,
                'month' => '2026-08',
                'payment_method' => 'cash',
            ]));

        $responseCash->assertOk();
        $paymentsCash = $responseCash->viewData('payments');
        $this->assertCount(1, $paymentsCash);
        $this->assertSame($cashPayment->id, $paymentsCash->first()->id);

        // Search by reference
        $responseSearch = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.history.payments', [
                'shop' => $this->profile->slug,
                'month' => '2026-08',
                'search' => 'HDFC-REF',
            ]));

        $responseSearch->assertOk();
        $paymentsSearch = $responseSearch->viewData('payments');
        $this->assertCount(1, $paymentsSearch);
        $this->assertSame($bankPayment->id, $paymentsSearch->first()->id);
    }

    public function test_allocation_can_be_removed_and_payment_recalculates_remaining(): void
    {
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'bank',
            'payment_reference' => 'REM-TEST-001',
            'payment_date' => '2026-08-20',
            'requested_amount' => 10000.00,
            'approved_amount' => 10000.00,
            'reconciled_amount' => 10000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $settlement = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-20',
            'entry_type_code' => 'rent_expense',
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $allocation = ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $payment->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $settlement->id,
            'amount' => 10000.00,
            'reconciled_by' => $this->admin->id,
        ]);

        // Verify currently fully allocated
        $this->assertSame(1, ShopPaymentLedgerAllocation::query()->count());

        // Remove allocation using route
        $removeResponse = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocations.remove', [
                'shop' => $this->profile->slug,
                'allocation' => $allocation->id,
            ]));

        $removeResponse->assertRedirect();
        $this->assertSame(0, ShopPaymentLedgerAllocation::active()->count());
        $this->assertSame(1, ShopPaymentLedgerAllocation::query()->where('status', 'reversed')->count());

        // View history page and verify ₹10,000 is unallocated now
        $historyResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.history.payments', [
                'shop' => $this->profile->slug,
                'month' => '2026-08',
            ]));

        $historyResponse->assertOk();
        $summary = $historyResponse->viewData('allocationSummary');
        $this->assertSame(0.00, $summary['allocated_amount']);
        $this->assertSame(10000.00, $summary['unallocated_amount']);
        $this->assertCount(1, $historyResponse->viewData('unallocatedPayments'));
    }
}
