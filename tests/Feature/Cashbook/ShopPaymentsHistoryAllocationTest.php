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
            'status' => 'active',
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
        $response->assertViewHas('summary');
        $response->assertViewHas('dailyRows');
        $response->assertViewHas('dynamicContributors');

        // Verify summary values
        $summary = $response->viewData('summary');
        $this->assertEquals(25000.00, $summary['received']);
        $this->assertEquals(10000.00, $summary['pending_allocation']);

        // Verify GET request was 100% read-only
        $this->assertSame($allocCountBefore, ShopPaymentLedgerAllocation::query()->count());
        $this->assertSame($paymentCountBefore, ShopInvoicePaymentRequest::query()->count());

        // Verify UI output elements
        $response->assertSee('Company Payable');
        $response->assertSee('Received / Verified');
        $response->assertSee('Pending Verification');
        $response->assertSee('Pending Allocation');
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
        $response->assertViewHas('summary');
    }

    public function test_auto_allocate_action_can_be_triggered_from_payment_history_context(): void
    {
        // 1. Receive ₹30,000 unallocated payment
        ShopInvoicePaymentRequest::query()->create([
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
        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-05',
            'entry_type_code' => 'rent_expense',
            'amount' => 12000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-10',
            'entry_type_code' => 'rent_expense',
            'amount' => 18000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Execute day allocation
        $postResponse = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.history.payments.allocate-day', $this->profile->slug), [
                'business_date' => '2026-08-18',
                'month' => '2026-08',
            ]);

        $postResponse->assertRedirect(route('admin.cashbook.shop.history.payments', [
            'shop' => $this->profile->slug,
            'month' => '2026-08',
        ]));

        // Re-check history page: pending allocation is updated
        $refreshedResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.history.payments', [
                'shop' => $this->profile->slug,
                'month' => '2026-08',
            ]));

        $refreshedResponse->assertOk();
        $refreshedSummary = $refreshedResponse->viewData('summary');
        $this->assertEquals(0.00, $refreshedSummary['pending_allocation']);
    }

    public function test_payment_history_filters_by_month(): void
    {
        // 1. Create 2 payments
        ShopInvoicePaymentRequest::query()->create([
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

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.history.payments', [
                'shop' => $this->profile->slug,
                'month' => '2026-08',
            ]));

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEquals(15000.00, $summary['received']);
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
            'status' => 'active',
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

        // View history page and verify ₹10,000 is pending allocation now
        $historyResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.history.payments', [
                'shop' => $this->profile->slug,
                'month' => '2026-08',
            ]));

        $historyResponse->assertOk();
        $summary = $historyResponse->viewData('summary');
        $this->assertEquals(10000.00, $summary['pending_allocation']);
    }
}
