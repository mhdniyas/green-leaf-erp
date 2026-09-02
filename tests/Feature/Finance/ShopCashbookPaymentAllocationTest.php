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
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Cashbook\ShopPaymentLedgerReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopCashbookPaymentAllocationTest extends TestCase
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

        config(['admin.user_access.main_admin_email' => 'shop-cashbook-admin@example.test']);
        $this->admin = User::factory()->create(['email' => 'shop-cashbook-admin@example.test']);
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
            ['code' => 'card', 'name' => 'Card', 'category' => 'income'],
            ['code' => 'income_cp', 'name' => 'CP', 'category' => 'income'],
            ['code' => 'rent_expense', 'name' => 'Rent', 'category' => 'expense'],
            ['code' => 'shop_paid_company', 'name' => 'Shop Paid Company', 'category' => 'settlement'],
        ] as $entryType) {
            LedgerEntryType::query()->firstOrCreate(['code' => $entryType['code']], $entryType + ['active' => true]);
        }

        $this->ledgerService = app(DailyLedgerService::class);
    }

    public function test_receive_payment_records_money_movement_without_auto_allocating(): void
    {
        $initialBalance = (float) $this->companyAccount->current_balance;
        $journalCount = JournalEntry::query()->count();

        // 1. Receive ₹80,000 from shop into SIB Company Bank
        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 80000.00,
                'payment_date' => '2026-08-30',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'NEFT-883921',
                'notes' => 'Bulk sales clearance receipt',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shop_invoice_payment_requests', [
            'shop_id' => $this->shop->id,
            'requested_amount' => 80000.00,
            'reconciled_amount' => 80000.00,
            'payment_reference' => 'NEFT-883921',
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        // 2. Company destination account updated exactly once
        $this->assertSame($initialBalance + 80000.00, (float) $this->companyAccount->fresh()->current_balance);
        $this->assertDatabaseHas('cashbook_company_account_statement_entries', [
            'company_account_id' => $this->companyAccount->id,
            'amount' => 80000.00,
            'direction' => 'in',
            'status' => 'reconciled',
            'is_finalized' => true,
        ]);

        // 3. Balanced journal entry created
        $this->assertSame($journalCount + 1, JournalEntry::query()->count());
        $journal = JournalEntry::query()->latest('id')->first();
        $debitTotal = (float) $journal->transactions()->where('type', 'debit')->sum('amount');
        $creditTotal = (float) $journal->transactions()->where('type', 'credit')->sum('amount');
        $this->assertSame(80000.00, $debitTotal);
        $this->assertSame(80000.00, $creditTotal);

        // 4. Initial allocations = 0 (Unallocated = ₹80,000)
        $this->assertDatabaseCount('shop_payment_ledger_allocations', 0);
    }

    public function test_cheque_payment_recorded_as_pending_floating(): void
    {
        $initialBalance = (float) $this->companyAccount->current_balance;

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 45000.00,
                'payment_date' => '2026-08-30',
                'payment_method' => 'cheque',
                'company_account_id' => $this->companyAccount->id,
                'cheque_bank_name' => 'HDFC Bank',
                'cheque_date' => '2026-09-05',
                'payment_reference' => 'CHQ-99182',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shop_invoice_payment_requests', [
            'shop_id' => $this->shop->id,
            'payment_method' => 'cheque',
            'requested_amount' => 45000.00,
            'reconciled_amount' => 0.00,
            'floating_amount' => 45000.00,
            'status' => 'pending',
            'reconciliation_status' => 'floating',
            'cheque_status' => 'pending',
        ]);

        // Balance not updated immediately for floating cheque
        $this->assertSame($initialBalance, (float) $this->companyAccount->fresh()->current_balance);
    }

    public function test_unauthorized_users_are_blocked(): void
    {
        $unauthorizedUser = User::factory()->create();

        $this->actingAs($unauthorizedUser)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 10000.00,
                'payment_date' => '2026-08-30',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
            ])
            ->assertForbidden();
    }

    public function test_manual_many_to_many_allocation_and_partial_allocation(): void
    {
        // Setup two daily settlements for shop
        $settlement1 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-24',
            'entry_type_code' => 'cash_sales',
            'amount' => 20000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $settlement2 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-25',
            'entry_type_code' => 'cash_sales',
            'amount' => 30000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // Record ₹80,000 payment
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 80000.00,
                'payment_date' => '2026-08-30',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'NEFT-883921',
            ]);

        $payment = ShopInvoicePaymentRequest::query()->where('shop_id', $this->shop->id)->firstOrFail();

        // Allocate: Aug 24 → ₹20,000, Aug 25 → ₹30,000 (Total ₹50,000 allocated, ₹30,000 remaining unallocated)
        $allocResponse = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlement1->id, 'amount' => 20000.00],
                    ['ledger_transaction_id' => $settlement2->id, 'amount' => 30000.00],
                ],
            ]);

        $allocResponse->assertRedirect();
        $this->assertDatabaseCount('shop_payment_ledger_allocations', 2);
        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'payment_request_id' => $payment->id,
            'shop_ledger_transaction_id' => $settlement1->id,
            'amount' => 20000.00,
        ]);
        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'payment_request_id' => $payment->id,
            'shop_ledger_transaction_id' => $settlement2->id,
            'amount' => 30000.00,
        ]);

        // Total allocated on payment = 50k, unallocated = 30k
        $allocatedSum = (float) ShopPaymentLedgerAllocation::where('payment_request_id', $payment->id)->sum('amount');
        $this->assertSame(50000.00, $allocatedSum);
        $this->assertSame(30000.00, round((float) $payment->requested_amount - $allocatedSum, 2));

        // Can allocate a second payment to remaining settlement or allocate remaining 30k to a third settlement
        $settlement3 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-26',
            'entry_type_code' => 'cash_sales',
            'amount' => 40000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // Allocate remaining ₹30k of the same payment to settlement 3
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlement3->id, 'amount' => 30000.00],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('shop_payment_ledger_allocations', 3);
        $this->assertSame(80000.00, (float) ShopPaymentLedgerAllocation::where('payment_request_id', $payment->id)->sum('amount'));
    }

    public function test_over_allocation_and_idor_protection(): void
    {
        $settlement = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-24',
            'entry_type_code' => 'cash_sales',
            'amount' => 20000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $foreignShop = Shop::factory()->create();
        $foreignSettlement = $this->ledgerService->recordEntry([
            'shop_id' => $foreignShop->id,
            'business_date' => '2026-08-24',
            'entry_type_code' => 'cash_sales',
            'amount' => 20000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 10000.00,
                'payment_date' => '2026-08-30',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
            ]);

        $payment = ShopInvoicePaymentRequest::query()->where('shop_id', $this->shop->id)->firstOrFail();

        // 1. Cannot allocate more than payment unallocated (trying to allocate 15k with 10k available)
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlement->id, 'amount' => 15000.00],
                ],
            ])
            ->assertSessionHasErrors('allocations');

        // 2. Cannot allocate to foreign shop settlement
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment->id,
                'allocations' => [
                    ['ledger_transaction_id' => $foreignSettlement->id, 'amount' => 10000.00],
                ],
            ])
            ->assertSessionHasErrors('allocations');
    }

    public function test_allocation_removal_restores_balances(): void
    {
        $settlement = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-24',
            'entry_type_code' => 'cash_sales',
            'amount' => 20000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 20000.00,
                'payment_date' => '2026-08-30',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
            ]);

        $payment = ShopInvoicePaymentRequest::query()->where('shop_id', $this->shop->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlement->id, 'amount' => 20000.00],
                ],
            ]);

        $allocation = ShopPaymentLedgerAllocation::query()->firstOrFail();

        // Remove allocation
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocations.remove', [
                'shop' => $this->profile->slug,
                'allocation' => $allocation->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseCount('shop_payment_ledger_allocations', 0);
        // Payment still exists and is 20k unallocated
        $this->assertDatabaseHas('shop_invoice_payment_requests', [
            'id' => $payment->id,
            'requested_amount' => 20000.00,
        ]);
    }

    public function test_shop_show_renders_summary_and_payments(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 50000.00,
                'payment_date' => '2026-08-30',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'TEST-SHOW-REF',
            ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk()
            ->assertViewHas('allShopPayments')
            ->assertViewHas('netPositionDirection')
            ->assertViewHas('unallocatedPayments', 50000.00)
            ->assertSee('Receive Payment')
            ->assertSee('TEST-SHOW-REF');
    }

    public function test_complete_real_many_to_many_specification_and_accounting_boundary(): void
    {
        $initialAccountBalance = (float) $this->companyAccount->current_balance;

        // 1. Daily settlements: Settlement A = ₹50,000, Settlement B = ₹60,000 (Total due = ₹110,000)
        $settlementA = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-07-20', // Month: July
            'entry_type_code' => 'cash_sales',
            'amount' => 50000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $settlementB = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-07-21', // Month: July
            'entry_type_code' => 'cash_sales',
            'amount' => 60000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // 2. Record Payment 1 = ₹80,000 into Company Account
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 80000.00,
                'payment_date' => '2026-08-01',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-1-80K',
            ])
            ->assertRedirect();

        $payment1 = ShopInvoicePaymentRequest::query()->where('payment_reference', 'PAY-1-80K')->firstOrFail();

        // Immediately after receipt verify:
        // Payment received = ₹80,000, Allocated = ₹0, Unallocated = ₹80,000
        $this->assertSame(80000.00, (float) $payment1->requested_amount);
        $this->assertSame(0.00, (float) $payment1->ledgerAllocations()->sum('amount'));

        // Company account incremented by ₹80,000
        $this->assertSame($initialAccountBalance + 80000.00, (float) $this->companyAccount->fresh()->current_balance);

        // Journal 1 exists and is balanced
        $journal1 = JournalEntry::query()->where('source_type', ShopInvoicePaymentRequest::class)->where('source_id', $payment1->id)->firstOrFail();
        $this->assertSame(80000.00, (float) $journal1->transactions()->where('type', 'debit')->sum('amount'));
        $this->assertSame(80000.00, (float) $journal1->transactions()->where('type', 'credit')->sum('amount'));

        // Settlements A and B remain OPEN (₹50k and ₹60k)
        $this->assertSame(0.00, (float) $settlementA->paymentLedgerAllocations()->sum('amount'));
        $this->assertSame(0.00, (float) $settlementB->paymentLedgerAllocations()->sum('amount'));

        // 3. First Manual Allocation: Payment 1 -> Settlement A (₹50,000), Settlement B (₹20,000)
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment1->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlementA->id, 'amount' => 50000.00],
                    ['ledger_transaction_id' => $settlementB->id, 'amount' => 20000.00],
                ],
            ])
            ->assertRedirect();

        // Verify Payment 1: Allocated = ₹70,000, Unallocated = ₹10,000
        $this->assertSame(70000.00, (float) $payment1->ledgerAllocations()->sum('amount'));

        // Verify Settlement A: Allocated = ₹50,000 (SETTLED)
        $this->assertSame(50000.00, (float) $settlementA->paymentLedgerAllocations()->sum('amount'));

        // Verify Settlement B: Allocated = ₹20,000 (Remaining = ₹40,000)
        $this->assertSame(20000.00, (float) $settlementB->paymentLedgerAllocations()->sum('amount'));

        // Verify Allocation does NOT change company account balance
        $this->assertSame($initialAccountBalance + 80000.00, (float) $this->companyAccount->fresh()->current_balance);

        // 4. Second Payment: Payment 2 = ₹40,000
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 40000.00,
                'payment_date' => '2026-08-02',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-2-40K',
            ])
            ->assertRedirect();

        $payment2 = ShopInvoicePaymentRequest::query()->where('payment_reference', 'PAY-2-40K')->firstOrFail();
        $this->assertSame(0.00, (float) $payment2->ledgerAllocations()->sum('amount'));
        $this->assertSame($initialAccountBalance + 120000.00, (float) $this->companyAccount->fresh()->current_balance);

        // Journal 2 exists and is balanced
        $journal2 = JournalEntry::query()->where('source_type', ShopInvoicePaymentRequest::class)->where('source_id', $payment2->id)->firstOrFail();
        $this->assertSame(40000.00, (float) $journal2->transactions()->where('type', 'debit')->sum('amount'));
        $this->assertSame(40000.00, (float) $journal2->transactions()->where('type', 'credit')->sum('amount'));

        // Allocate Payment 2 -> Settlement B = ₹40,000
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment2->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlementB->id, 'amount' => 40000.00],
                ],
            ])
            ->assertRedirect();

        // Payment 2: Allocated = ₹40,000, Unallocated = ₹0 (FULLY ALLOCATED)
        $this->assertSame(40000.00, (float) $payment2->ledgerAllocations()->sum('amount'));

        // Settlement B: Total allocated = ₹20,000 (from P1) + ₹40,000 (from P2) = ₹60,000 (SETTLED)
        $this->assertSame(60000.00, (float) $settlementB->paymentLedgerAllocations()->sum('amount'));

        // Verify Total Company Account Balance still exactly +₹120,000
        $this->assertSame($initialAccountBalance + 120000.00, (float) $this->companyAccount->fresh()->current_balance);

        // 5. Carry-forward summary view in August (even though settlements are July)
        $augustView = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $augustView->assertOk()
            ->assertViewHas('netSettlementDue', 110000.00)
            ->assertViewHas('totalSettlementAllocated', 110000.00)
            ->assertViewHas('settlementOutstanding', 0.00)
            ->assertViewHas('totalPaymentsReceived', 120000.00)
            ->assertViewHas('totalPaymentsAllocated', 110000.00)
            ->assertViewHas('unallocatedPayments', 10000.00)
            ->assertViewHas('netPositionDirection', 'company_owes_shop')
            ->assertViewHas('netPositionAmount', 10000.00);

        // 6. Remove Allocation: Remove Payment 2 -> Settlement B allocation
        $allocP2toB = ShopPaymentLedgerAllocation::query()
            ->where('payment_request_id', $payment2->id)
            ->where('shop_ledger_transaction_id', $settlementB->id)
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocations.remove', [
                'shop' => $this->profile->slug,
                'allocation' => $allocP2toB->id,
            ]))
            ->assertRedirect();

        // Payment 2 unallocated restored to ₹40,000
        $this->assertSame(0.00, (float) $payment2->fresh()->ledgerAllocations()->sum('amount'));

        // Settlement B allocated reduced to ₹20,000 (remaining ₹40,000)
        $this->assertSame(20000.00, (float) $settlementB->fresh()->paymentLedgerAllocations()->sum('amount'));

        // Company account balance and journals unchanged
        $this->assertSame($initialAccountBalance + 120000.00, (float) $this->companyAccount->fresh()->current_balance);
        $this->assertDatabaseHas('shop_invoice_payment_requests', ['id' => $payment2->id]);

        // Reallocate Payment 2 -> Settlement B ₹40,000
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment2->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlementB->id, 'amount' => 40000.00],
                ],
            ])
            ->assertRedirect();

        // 7. Over-allocation protection:
        // Try allocating more than payment remaining (Payment 2 has 0 remaining, trying to allocate 1)
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment2->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlementA->id, 'amount' => 1.00],
                ],
            ])
            ->assertSessionHasErrors('allocations');

        // Try allocating more than settlement remaining (Settlement A has 0 remaining, trying to allocate 1 from Payment 1's ₹10k)
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment1->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlementA->id, 'amount' => 1.00],
                ],
            ])
            ->assertSessionHasErrors('allocations');

        // 8. IDOR protection:
        $otherShop = Shop::factory()->create();
        $otherProfile = ShopLedgerProfile::query()->create([
            'shop_id' => $otherShop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'other-shop-'.$otherShop->id,
            'code' => 'OTHER',
            'name' => 'Other Shop',
            'enabled' => true,
        ]);

        // Post payment belonging to other shop on this shop URL (Cross-shop IDOR blocked with 404)
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $otherProfile->slug), [
                'payment_request_id' => $payment1->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlementB->id, 'amount' => 1000.00],
                ],
            ])
            ->assertNotFound();
    }

    public function test_empty_state_and_header_both_trigger_canonical_receive_payment_modal(): void
    {
        // 1. Initial shop view with 0 payments recorded
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk()
            ->assertSee('Receive Payment')
            ->assertSee('@click="openReceivePayment()"', false)
            ->assertSee('Receive Shop Payment')
            ->assertSee(route('admin.cashbook.shop.receive-payment', $this->profile->slug), false)
            ->assertSee('company_account_id')
            ->assertSee('SIB Company Bank');

        // 2. Submit payment using canonical modal form action
        $postResponse = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 80000.00,
                'payment_date' => '2026-08-30',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'TEST-FIRST-PAY-80K',
            ]);

        $postResponse->assertRedirect();

        // 3. Re-fetch shop view: shows 1 Payment Recorded, ₹80k unallocated, and Allocate action
        $updatedResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $updatedResponse->assertOk()
            ->assertSee('1 Payment Recorded')
            ->assertSee('TEST-FIRST-PAY-80K')
            ->assertSee('80,000.00')
            ->assertSee('Unallocated')
            ->assertSee('Allocate');
    }

    public function test_case_a_rent_deducted_from_sales_uses_canonical_company_payable(): void
    {
        // 1. Enter Gross Sales = ₹73,413 and Rent from sales = ₹9,584 on 2026-08-01
        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 73413.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'rent_expense',
            'amount' => 9584.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);

        // 2. Query open daily settlements via service
        $reconciliationService = app(ShopPaymentLedgerReconciliationService::class);
        $openSettlements = $reconciliationService->getOpenDailySettlements($this->shop->id, '2026-08');

        // Verify there is EXACTLY ONE target row for 2026-08-01 with Company Payable = ₹63,829.00
        $this->assertCount(1, $openSettlements);
        $settlement = $openSettlements->first();
        $this->assertSame('2026-08-01', $settlement['business_date']);
        $this->assertSame(63829.00, (float) $settlement['company_payable']);
        $this->assertSame(63829.00, (float) $settlement['remaining_due']);
        $this->assertSame(73413.00, (float) $settlement['gross_sales']);
        $this->assertSame(9584.00, (float) $settlement['deductions']);

        // 3. Receive payment of ₹70,000
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 70000.00,
                'payment_date' => '2026-08-05',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-CASE-A',
            ]);

        $payment = ShopInvoicePaymentRequest::query()->where('payment_reference', 'PAY-CASE-A')->firstOrFail();

        // 4. Over-allocation: cannot allocate > ₹63,829.00 (e.g. ₹64,000.00)
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlement['id'], 'amount' => 64000.00],
                ],
            ])
            ->assertSessionHasErrors('allocations');

        // 5. Valid allocation of exact canonical Company Payable (₹63,829.00)
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlement['id'], 'amount' => 63829.00],
                ],
            ])
            ->assertRedirect();

        // Verify remaining unallocated on payment = ₹6,171.00 (₹70,000 - ₹63,829)
        $this->assertSame(63829.00, (float) $payment->fresh()->ledgerAllocations()->sum('amount'));

        // Verify open settlements is now empty (0 remaining due)
        $updatedOpen = $reconciliationService->getOpenDailySettlements($this->shop->id, '2026-08');
        $this->assertSame(0.00, (float) ($updatedOpen->first()['remaining_due'] ?? 0.0));
    }

    public function test_case_b_shop_specific_config_consumed_without_duplicating_logic(): void
    {
        // Setup a different shop with another daily sales amount
        $shopB = Shop::factory()->create(['name' => 'Kochi Mega Shop', 'code' => 'KOCHI']);
        $profileB = ShopLedgerProfile::query()->create([
            'shop_id' => $shopB->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'kochi-shop-'.$shopB->id,
            'code' => $shopB->code,
            'name' => $shopB->name,
            'enabled' => true,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $shopB->id,
            'business_date' => '2026-08-10',
            'entry_type_code' => 'cash_sales',
            'amount' => 72000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $reconciliationService = app(ShopPaymentLedgerReconciliationService::class);
        $openSettlements = $reconciliationService->getOpenDailySettlements($shopB->id, '2026-08');

        $this->assertCount(1, $openSettlements);
        $settlement = $openSettlements->first();
        $this->assertSame('2026-08-10', $settlement['business_date']);
        $this->assertSame(72000.00, (float) $settlement['company_payable']);
        $this->assertSame(72000.00, (float) $settlement['remaining_due']);
        $this->assertSame(0.00, (float) $settlement['deductions']);
    }

    public function test_auto_allocate_and_clear_all_ui_and_fifo_settlement_behavior(): void
    {
        // 1. Setup 3 days of settlements
        // 01 Aug: Gross ₹73,413 - Rent ₹9,584 = ₹63,829
        $tx1 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 73413.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'rent_expense',
            'amount' => 9584.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);

        // 02 Aug: ₹82,097
        $tx2 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-02',
            'entry_type_code' => 'cash_sales',
            'amount' => 82097.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // 03 Aug: ₹69,768
        $tx3 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-03',
            'entry_type_code' => 'cash_sales',
            'amount' => 69768.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // 2. Receive Payment of ₹1,00,000
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 100000.00,
                'payment_date' => '2026-08-04',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-AUTO-100K',
            ]);

        $payment = ShopInvoicePaymentRequest::query()->where('payment_reference', 'PAY-AUTO-100K')->firstOrFail();

        // 3. Verify View Renders Auto Allocate and Clear All Buttons
        $viewResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $viewResponse->assertOk()
            ->assertSee('@click="autoAllocate()"', false)
            ->assertSee('@click="clearAllAllocations()"', false)
            ->assertSee('Auto Allocate')
            ->assertSee('Clear All');

        // 4. Test Auto Allocation FIFO distribution:
        // Day 1 (01 Aug): min(100,000, 63,829) = ₹63,829 (remaining payment = 36,171)
        // Day 2 (02 Aug): min(36,171, 82,097) = ₹36,171 (remaining payment = 0)
        // Day 3 (03 Aug): min(0, 69,768) = ₹0
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment->id,
                'allocations' => [
                    ['ledger_transaction_id' => $tx1->id, 'amount' => 63829.00],
                    ['ledger_transaction_id' => $tx2->id, 'amount' => 36171.00],
                ],
            ])
            ->assertRedirect();

        // 5. Verify results:
        $this->assertSame(100000.00, (float) $payment->fresh()->ledgerAllocations()->sum('amount'));

        $reconciliationService = app(ShopPaymentLedgerReconciliationService::class);
        $openRemaining = $reconciliationService->getOpenDailySettlements($this->shop->id, '2026-08');

        // Day 1: 0 remaining (settled)
        $day1 = $openRemaining->firstWhere('business_date', '2026-08-01');
        $this->assertSame(0.00, (float) $day1['remaining_due']);

        // Day 2: 82,097 - 36,171 = 45,926 remaining
        $day2 = $openRemaining->firstWhere('business_date', '2026-08-02');
        $this->assertSame(45926.00, (float) $day2['remaining_due']);

        // Day 3: 69,768 remaining
        $day3 = $openRemaining->firstWhere('business_date', '2026-08-03');
        $this->assertSame(69768.00, (float) $day3['remaining_due']);
    }

    public function test_confirm_settlement_allocation_submits_with_sparse_and_empty_rows(): void
    {
        // 1. Setup 3 days of settlements
        $tx1 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 50000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $tx2 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-02',
            'entry_type_code' => 'cash_sales',
            'amount' => 50000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $tx3 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-03',
            'entry_type_code' => 'cash_sales',
            'amount' => 50000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // 2. Receive Payment of ₹20,000
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 20000.00,
                'payment_date' => '2026-08-05',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-SPARSE-20K',
            ]);

        $payment = ShopInvoicePaymentRequest::query()->where('payment_reference', 'PAY-SPARSE-20K')->firstOrFail();

        // 3. Post full form payload with sparse amounts (row 1 has ₹20k, row 2 and 3 have empty / null amounts)
        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment->id,
                'allocations' => [
                    ['ledger_transaction_id' => $tx1->id, 'amount' => '20000.00'],
                    ['ledger_transaction_id' => $tx2->id, 'amount' => ''],
                    ['ledger_transaction_id' => $tx3->id, 'amount' => null],
                ],
            ]);

        $response->assertRedirect();

        // 4. Verify allocation succeeded and only row 1 was allocated
        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'payment_request_id' => $payment->id,
            'shop_ledger_transaction_id' => $tx1->id,
            'amount' => 20000.00,
        ]);

        $this->assertDatabaseMissing('shop_payment_ledger_allocations', [
            'payment_request_id' => $payment->id,
            'shop_ledger_transaction_id' => $tx2->id,
        ]);

        $this->assertSame(20000.00, (float) $payment->fresh()->ledgerAllocations()->sum('amount'));
    }

    public function test_payment_reconciliation_and_clickable_allocation_breakdown_workflow(): void
    {
        // 1. Setup daily settlements
        $tx1 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 63829.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $tx2 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-02',
            'entry_type_code' => 'cash_sales',
            'amount' => 82097.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // 2. Create an unreconciled payment of ₹100,000 (e.g. from shop request)
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'UNREC-PAY-100K',
            'payment_date' => '2026-08-04',
            'requested_amount' => 100000.00,
            'approved_amount' => 100000.00,
            'status' => 'approved',
            'reconciliation_status' => 'unreconciled',
        ]);

        // 3. Fully allocate payment against Settlement 1 (₹63,829) and Settlement 2 (₹36,171)
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment->id,
                'allocations' => [
                    ['ledger_transaction_id' => $tx1->id, 'amount' => 63829.00],
                    ['ledger_transaction_id' => $tx2->id, 'amount' => 36171.00],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(100000.00, (float) $payment->fresh()->ledgerAllocations()->sum('amount'));

        // 4. View Shop Cashbook page:
        // Shows: Allocation Status = "Fully Allocated", Reconciliation Status = "Unreconciled"
        // Shows: Clickable Allocated amount calling openAllocationBreakdownModal
        // Shows: Reconcile action button
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk()
            ->assertSee('Fully Allocated')
            ->assertSee('Unreconciled')
            ->assertSee('openAllocationBreakdownModal', false)
            ->assertSee('openReconcileModal', false)
            ->assertSee('Reconcile')
            ->assertSee('UNREC-PAY-100K');

        // 5. Reconcile the payment via canonical company payment reconciliation endpoint
        $reconcileResponse = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.payments.reconcile', $payment->id), [
                'company_account_id' => $this->companyAccount->id,
                'cleared_amount' => 100000.00,
                'difference_action' => 'none',
                'redirect_to' => route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']),
            ]);

        $reconcileResponse->assertRedirect(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        // 6. Assert reconciliation invariants:
        // - Reconciliation status is now 'reconciled'
        // - Allocation amount remains strictly unchanged at ₹100,000.00
        $this->assertSame('reconciled', $payment->fresh()->reconciliation_status);
        $this->assertSame(100000.00, (float) $payment->fresh()->ledgerAllocations()->sum('amount'));

        // Re-fetch Shop Cashbook: shows 'Reconciled' badge
        $updatedResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $updatedResponse->assertOk()
            ->assertSee('Fully Allocated')
            ->assertSee('Reconciled');
    }

    public function test_bottom_company_received_card_matches_canonical_reconciled_shop_payments(): void
    {
        // 1. Create 2 reconciled payments in August 2026: ₹500,000 + ₹100,000 = ₹600,000
        ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'PAY-500K',
            'payment_date' => '2026-08-30',
            'requested_amount' => 500000.00,
            'approved_amount' => 500000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'PAY-100K',
            'payment_date' => '2026-08-30',
            'requested_amount' => 100000.00,
            'approved_amount' => 100000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        // 2. Fetch Shop Cashbook for August 2026
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk();

        // Top summary & payment card should have ₹600,000.00
        $response->assertSee('₹600,000.00');

        // Verify monthlyData passed to view has company_received = 600000.00
        $monthlyData = $response->viewData('monthlyData');
        $this->assertEquals(600000.00, $monthlyData['summary']['company_received']);
    }

    public function test_bottom_company_received_card_reconciled_only_and_month_boundaries(): void
    {
        // 1. Reconciled payment in August (₹500,000)
        ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'AUG-REC-500K',
            'payment_date' => '2026-08-20',
            'requested_amount' => 500000.00,
            'approved_amount' => 500000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        // 2. Unreconciled payment in August (₹100,000)
        ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'AUG-UNREC-100K',
            'payment_date' => '2026-08-21',
            'requested_amount' => 100000.00,
            'approved_amount' => 100000.00,
            'status' => 'approved',
            'reconciliation_status' => 'unreconciled',
        ]);

        // 3. Reconciled payment in July (₹250,000)
        ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'JUL-REC-250K',
            'payment_date' => '2026-07-25',
            'requested_amount' => 250000.00,
            'approved_amount' => 250000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        // 4. View August 2026:
        // Monthly Verified Company Received should ONLY include August reconciled payment (₹500,000),
        // excluding the unreconciled ₹100,000 and July's ₹250,000.
        $augResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));
        $augResponse->assertOk();
        $augMonthlyData = $augResponse->viewData('monthlyData');
        $this->assertEquals(500000.00, $augMonthlyData['summary']['company_received']);

        // 5. View July 2026:
        // Monthly Verified Company Received should include July reconciled payment (₹250,000).
        $julResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-07']));
        $julResponse->assertOk();
        $julMonthlyData = $julResponse->viewData('monthlyData');
        $this->assertEquals(250000.00, $julMonthlyData['summary']['company_received']);
    }

    public function test_manual_and_direct_bank_and_merged_reconciled_payment_sources_display_unified(): void
    {
        // 1. Manual Unreconciled Payment (Source: MANUAL)
        $manualPayment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'cash',
            'payment_reference' => 'MANUAL-PAY-50K',
            'payment_date' => '2026-08-10',
            'requested_amount' => 50000.00,
            'approved_amount' => 50000.00,
            'status' => 'approved',
            'reconciliation_status' => 'unreconciled',
        ]);

        // 2. Direct Bank Import Payment (Source: BANK IMPORT)
        $bankStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => '2026-08-12',
            'value_date' => '2026-08-12',
            'entry_type' => 'bank_transfer',
            'direction' => 'in',
            'amount' => 100000.00,
            'reference' => 'NEFT-SANA-100K',
            'narration' => 'Direct transfer from Sana shop',
            'status' => 'reconciled',
            'is_finalized' => true,
            'import_fingerprint' => 'FINGERPRINT-100K',
        ]);

        $bankImportPayment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'bank_import',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'NEFT-SANA-100K',
            'payment_date' => '2026-08-12',
            'requested_amount' => 100000.00,
            'approved_amount' => 100000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        CompanyPaymentReconciliation::query()->create([
            'payment_request_id' => $bankImportPayment->id,
            'shop_id' => $this->shop->id,
            'company_account_id' => $this->companyAccount->id,
            'statement_entry_id' => $bankStatement->id,
            'statement_amount' => 100000.00,
            'cleared_amount' => 100000.00,
            'difference_amount' => 0.00,
            'difference_action' => 'none',
            'status' => 'approved',
            'is_finalized' => true,
        ]);

        // 3. Manual Payment later reconciled to Bank Statement (Source: MANUAL → BANK RECONCILED, merged into 1 row)
        $mergedStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => '2026-08-15',
            'value_date' => '2026-08-15',
            'entry_type' => 'bank_transfer',
            'direction' => 'in',
            'amount' => 75000.00,
            'reference' => 'RTGS-MATCH-75K',
            'status' => 'reconciled',
            'is_finalized' => true,
        ]);

        $mergedPayment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'REC-MERGED-75K',
            'payment_date' => '2026-08-15',
            'requested_amount' => 75000.00,
            'approved_amount' => 75000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        CompanyPaymentReconciliation::query()->create([
            'payment_request_id' => $mergedPayment->id,
            'shop_id' => $this->shop->id,
            'company_account_id' => $this->companyAccount->id,
            'statement_entry_id' => $mergedStatement->id,
            'statement_amount' => 75000.00,
            'cleared_amount' => 75000.00,
            'difference_action' => 'none',
            'status' => 'approved',
            'is_finalized' => true,
        ]);

        // 4. Random unmatched statement entry from outside (Must NOT appear under Shop)
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => '2026-08-18',
            'value_date' => '2026-08-18',
            'entry_type' => 'unidentified_credit',
            'direction' => 'in',
            'amount' => 999999.00,
            'reference' => 'RANDOM-UNMATCHED-999K',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        // 5. Query Shop Cashbook view
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk();

        // Check sources
        $response->assertSee('MANUAL');
        $response->assertSee('BANK IMPORT');
        $response->assertSee('MANUAL → BANK RECONCILED');

        // Check references
        $response->assertSee('MANUAL-PAY-50K');
        $response->assertSee('NEFT-SANA-100K');
        // Assert random unmatched entry does NOT appear as a shop payment
        $paymentsCollection = $response->viewData('allShopPayments');
        $this->assertFalse($paymentsCollection->contains(fn ($p) => $p->payment_reference === 'RANDOM-UNMATCHED-999K'));

        // Check paginator items count: exactly 3 rows, no duplicate 75k rows
        $this->assertCount(3, $paymentsCollection);
    }

    public function test_summary_and_bulk_allocation_include_direct_bank_backed_receipts_once(): void
    {
        $settlementOne = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 70000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $settlementTwo = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-02',
            'entry_type_code' => 'cash_sales',
            'amount' => 70000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 62497.00,
                'payment_date' => '2026-08-03',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-REQUEST-62497',
            ])
            ->assertRedirect();

        foreach ([11963.00, 7346.00, 6436.00, 5411.00] as $amount) {
            $receiptTransaction = $this->ledgerService->recordEntry([
                'shop_id' => $this->shop->id,
                'business_date' => '2026-08-04',
                'entry_type_code' => 'cash_sales',
                'amount' => $amount,
                'funding_source' => 'none',
                'entered_by' => $this->admin->id,
            ])['transaction'];

            CompanyAccountStatementEntry::query()->create([
                'company_account_id' => $this->companyAccount->id,
                'transaction_date' => '2026-08-04',
                'value_date' => '2026-08-04',
                'direction' => 'in',
                'amount' => $amount,
                'reference' => 'SHOP-TX-'.$receiptTransaction->id,
                'source_type' => ShopLedgerTransaction::class,
                'source_id' => $receiptTransaction->id,
                'status' => 'reconciled',
                'is_finalized' => true,
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk()
            ->assertSee('Total Received')
            ->assertSee('Total Allocated')
            ->assertSee('Total Unallocated')
            ->assertSee('Eligible Unallocated')
            ->assertSee('Settlement Outstanding')
            ->assertSee('Auto Allocate All');

        $summary = $response->viewData('shopPaymentSummary');
        $this->assertSame(5, $summary['payment_count']);
        $this->assertSame(4, $summary['direct_receipt_count']);
        $this->assertSame(93653.00, $summary['received']);
        $this->assertSame(93653.00, $summary['unallocated']);

        $this->assertDatabaseCount('shop_invoice_payment_requests', 1);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 93653.00,
                'submission_uuid' => (string) Str::uuid(),
            ])
            ->assertRedirect();

        $this->assertSame(5, ShopInvoicePaymentRequest::query()->count());
        $this->assertSame(5, CompanyPaymentReconciliation::query()->where('is_finalized', true)->count());
        $this->assertSame(93653.00, (float) ShopPaymentLedgerAllocation::query()->sum('amount'));
        $this->assertSame(93653.00, (float) ShopPaymentLedgerAllocation::query()
            ->whereIn('shop_ledger_transaction_id', [$settlementOne->id, $settlementTwo->id])
            ->sum('amount'));

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 93653.00,
                'submission_uuid' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('expected_total');

        $this->assertSame(93653.00, (float) ShopPaymentLedgerAllocation::query()->sum('amount'));
    }

    public function test_server_side_pagination_and_month_query_preservation(): void
    {
        // 1. Create 25 payments for August 2026
        for ($i = 1; $i <= 25; $i++) {
            $dayStr = str_pad((string) min(28, $i), 2, '0', STR_PAD_LEFT);
            ShopInvoicePaymentRequest::query()->create([
                'shop_id' => $this->shop->id,
                'requested_by' => $this->admin->id,
                'submission_uuid' => (string) str()->uuid(),
                'request_type' => 'invoice_payment',
                'payment_method' => 'bank_transfer',
                'payment_reference' => 'PAY-PAGINATE-'.$i,
                'payment_date' => '2026-08-'.$dayStr,
                'requested_amount' => 1000.00 * $i,
                'approved_amount' => 1000.00 * $i,
                'status' => 'approved',
                'reconciliation_status' => 'reconciled',
            ]);
        }

        // 2. Query Page 1
        $page1Response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $page1Response->assertOk();
        $paginator1 = $page1Response->viewData('allShopPayments');
        $this->assertEquals(25, $paginator1->total());
        $this->assertCount(20, $paginator1);
        $this->assertEquals(1, $paginator1->currentPage());

        // Check that page 1 sees payment 25 (newest date / id first)
        $page1Response->assertSee('PAY-PAGINATE-25');

        // 3. Query Page 2
        $page2Response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', [
                'shop' => $this->profile->slug,
                'month' => '2026-08',
                'payments_page' => 2,
            ]));

        $page2Response->assertOk();
        $paginator2 = $page2Response->viewData('allShopPayments');
        $this->assertEquals(25, $paginator2->total());
        $this->assertCount(5, $paginator2);
        $this->assertEquals(2, $paginator2->currentPage());

        // Check that page 2 sees oldest payments
        $page2Response->assertSee('PAY-PAGINATE-1');
    }

    public function test_cross_shop_payments_isolation(): void
    {
        // 1. Create a second shop
        $otherShop = Shop::factory()->create([
            'name' => 'Other Shop City Center',
            'code' => 'OTHER01',
            'status' => 'active',
        ]);

        ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $otherShop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'OTHER-SHOP-PAYMENT-SECRET-999',
            'payment_date' => '2026-08-15',
            'requested_amount' => 888888.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        // 2. Query our main shop
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk();
        $response->assertDontSee('OTHER-SHOP-PAYMENT-SECRET-999');
        $response->assertDontSee('888,888.00');
    }

    public function test_integrity_flag_shows_allocation_error_when_requested_amount_differs_from_actual_payment_amount(): void
    {
        $settlement = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-15',
            'entry_type_code' => 'cash_sales',
            'amount' => 100000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'MISMATCH-100K-70K',
            'payment_date' => '2026-08-15',
            'requested_amount' => 70000.00,
            'approved_amount' => 100000.00,
            'reconciled_amount' => 100000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $payment->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $settlement->id,
            'amount' => 70000.00,
            'reconciled_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk();

        $items = collect($response->viewData('allShopPaymentsFormattedList'));
        $row = $items->firstWhere('id', $payment->id);

        $this->assertNotNull($row);
        $this->assertSame(100000.00, (float) $row['amount']);
        $this->assertSame(70000.00, (float) $row['actual_allocated']);
        $this->assertSame(30000.00, (float) $row['actual_unallocated']);
        $this->assertSame('ALLOCATION_ERROR', $row['allocation_integrity_flag']);
        $this->assertSame('15 Aug 2026', $row['last_allocated_date']);
        $this->assertTrue((bool) $row['can_allocate']);
    }

    public function test_manual_allocation_uses_actual_payment_amount_instead_of_requested_amount(): void
    {
        $settlement = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-20',
            'entry_type_code' => 'cash_sales',
            'amount' => 100000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'ACTUAL-AMOUNT-100K',
            'payment_date' => '2026-08-20',
            'requested_amount' => 70000.00,
            'approved_amount' => 100000.00,
            'reconciled_amount' => 100000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $payment->id,
                'allocations' => [
                    ['ledger_transaction_id' => $settlement->id, 'amount' => 100000.00],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(100000.00, (float) $payment->fresh()->ledgerAllocations()->sum('amount'));
    }

    public function test_clear_allocation_and_clear_reallocate_are_idempotent_and_scoped_to_selected_payment(): void
    {
        $txA = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-10',
            'entry_type_code' => 'cash_sales',
            'amount' => 70000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $txB = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-11',
            'entry_type_code' => 'cash_sales',
            'amount' => 30000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $txC = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-12',
            'entry_type_code' => 'cash_sales',
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $paymentA = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'PAY-CLEAR-A',
            'payment_date' => '2026-08-12',
            'requested_amount' => 100000.00,
            'approved_amount' => 100000.00,
            'reconciled_amount' => 100000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        $paymentB = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) str()->uuid(),
            'request_type' => 'invoice_payment',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'PAY-CLEAR-B',
            'payment_date' => '2026-08-12',
            'requested_amount' => 5000.00,
            'approved_amount' => 5000.00,
            'reconciled_amount' => 5000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
        ]);

        // Existing partial allocation for A and unrelated allocation for B.
        ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $paymentA->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $txA->id,
            'amount' => 70000.00,
            'reconciled_by' => $this->admin->id,
        ]);

        ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $paymentB->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $txC->id,
            'amount' => 5000.00,
            'reconciled_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment.clear', $this->profile->slug), [
                'payment_request_id' => $paymentA->id,
                'month' => '2026-08',
            ])
            ->assertRedirect();

        $this->assertSame(0.00, (float) $paymentA->fresh()->ledgerAllocations()->sum('amount'));
        $this->assertSame(5000.00, (float) $paymentB->fresh()->ledgerAllocations()->sum('amount'));

        // Second clear remains no-op for A.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment.clear', $this->profile->slug), [
                'payment_request_id' => $paymentA->id,
                'month' => '2026-08',
            ])
            ->assertRedirect();

        $this->assertSame(0.00, (float) $paymentA->fresh()->ledgerAllocations()->sum('amount'));

        // Clear & reallocate rebuilds A to 100k using two open settlements.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment.clear-reallocate', $this->profile->slug), [
                'payment_request_id' => $paymentA->id,
                'month' => '2026-08',
            ])
            ->assertRedirect();

        $this->assertSame(100000.00, (float) $paymentA->fresh()->ledgerAllocations()->sum('amount'));

        // Running clear & reallocate again must not duplicate money.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment.clear-reallocate', $this->profile->slug), [
                'payment_request_id' => $paymentA->id,
                'month' => '2026-08',
            ])
            ->assertRedirect();

        $this->assertSame(100000.00, (float) $paymentA->fresh()->ledgerAllocations()->sum('amount'));
        $this->assertSame(5000.00, (float) $paymentB->fresh()->ledgerAllocations()->sum('amount'));
    }

    // ── BULK ALLOCATION INCIDENT FIX TESTS ──────────────────────────────

    public function test_bulk_allocation_money_less_than_settlements(): void
    {
        // Settlement: ₹250 (2 days of ₹125 each). Money: ₹100.
        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 125.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-02',
            'entry_type_code' => 'cash_sales',
            'amount' => 125.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 100.00,
                'payment_date' => '2026-08-03',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-100-OF-250',
            ])
            ->assertRedirect();

        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 100.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect();

        // All money allocated (₹100), outstanding remains (₹150).
        $this->assertSame(100.00, (float) ShopPaymentLedgerAllocation::query()->where('shop_id', $this->shop->id)->sum('amount'));

        // Verify summary shows correct remaining.
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk();
        $summary = $response->viewData('shopPaymentSummary');
        $this->assertSame(100.00, $summary['received']);
        $this->assertSame(100.00, $summary['allocated']);
        $this->assertSame(0.00, $summary['unallocated']);
        $this->assertSame(0.00, $summary['eligible_unallocated']);

        // Verify status message renders.
        $response->assertSee('All available money allocated');
        $response->assertSee('Settlement outstanding');
    }

    public function test_bulk_allocation_money_more_than_settlements(): void
    {
        // Settlement: ₹100. Money: ₹250.
        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 100.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 250.00,
                'payment_date' => '2026-08-03',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-250-OF-100',
            ])
            ->assertRedirect();

        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 100.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect();

        // Only ₹100 allocated (capped by settlements), ₹150 remains.
        $this->assertSame(100.00, (float) ShopPaymentLedgerAllocation::query()->where('shop_id', $this->shop->id)->sum('amount'));

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk();
        $summary = $response->viewData('shopPaymentSummary');
        $this->assertSame(250.00, $summary['received']);
        $this->assertSame(100.00, $summary['allocated']);
        $this->assertSame(150.00, $summary['unallocated']);
        $this->assertSame(150.00, $summary['eligible_unallocated']);

        $response->assertSee('All eligible settlements cleared');
    }

    public function test_bulk_allocation_older_settlements_appear_in_preview_and_receive_allocation(): void
    {
        // July settlement (older period): ₹200.
        $julyTx = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-07-15',
            'entry_type_code' => 'cash_sales',
            'amount' => 200.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // August settlement: ₹100.
        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 100.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // August money: ₹250.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 250.00,
                'payment_date' => '2026-08-05',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-250-CROSS-MONTH',
            ])
            ->assertRedirect();

        // Verify the open settlements include July via the view page.
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk();
        $openSettlements = $response->viewData('openSettlementTransactions');
        // July settlement must appear in all-time open settlements.
        $this->assertNotNull($openSettlements->firstWhere('id', $julyTx->id));

        // Execute allocation: ₹250 money, ₹300 outstanding → allocate ₹250.
        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 250.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect();

        // July should be fully settled first (FIFO).
        $julyAlloc = ShopPaymentLedgerAllocation::query()
            ->where('shop_ledger_transaction_id', $julyTx->id)
            ->sum('amount');
        $this->assertSame(200.00, (float) $julyAlloc);

        // Total allocated: ₹250. Remaining outstanding: ₹50.
        $this->assertSame(250.00, (float) ShopPaymentLedgerAllocation::query()->where('shop_id', $this->shop->id)->sum('amount'));
    }

    public function test_bulk_allocation_with_existing_partial_allocations(): void
    {
        // Settlement: ₹200.
        $tx = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 200.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // Money: ₹150.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 150.00,
                'payment_date' => '2026-08-02',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-PARTIAL-A',
            ])
            ->assertRedirect();

        $paymentA = ShopInvoicePaymentRequest::query()->where('payment_reference', 'PAY-PARTIAL-A')->firstOrFail();

        // Manually allocate ₹30 from payment A.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payment', $this->profile->slug), [
                'payment_request_id' => $paymentA->id,
                'allocations' => [
                    ['ledger_transaction_id' => $tx->id, 'amount' => 30.00],
                ],
            ])
            ->assertRedirect();

        // ₹120 remains unallocated. ₹170 settlement remaining.
        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 120.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect();

        // Total allocated should be ₹30 (manual) + ₹120 (bulk) = ₹150.
        $this->assertSame(150.00, (float) ShopPaymentLedgerAllocation::query()->where('shop_id', $this->shop->id)->sum('amount'));

        // Settlement remaining: ₹200 - ₹150 = ₹50.
        $reconciliationService = app(ShopPaymentLedgerReconciliationService::class);
        $open = $reconciliationService->getOpenDailySettlements($this->shop->id);
        $day = $open->firstWhere('business_date', '2026-08-01');
        $this->assertSame(50.00, (float) $day['remaining_due']);
    }

    public function test_bulk_allocation_excludes_ineligible_pending_cheque(): void
    {
        // Settlement: ₹200.
        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 200.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Cheque (pending) payment: ₹150 — will be floating/unreconciled.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 150.00,
                'payment_date' => '2026-08-02',
                'payment_method' => 'cheque',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'CHQ-PENDING-150',
            ])
            ->assertRedirect();

        // Also add a reconciled bank payment: ₹50 — eligible.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 50.00,
                'payment_date' => '2026-08-02',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-BANK-50',
            ])
            ->assertRedirect();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk();
        $summary = $response->viewData('shopPaymentSummary');

        // Summary includes only reconciled payments (₹50 bank).
        // Cheque (₹150) is floating/unreconciled and excluded from summary totals.
        $this->assertSame(50.00, $summary['received']);
        $this->assertSame(50.00, $summary['unallocated']);
        $this->assertSame(50.00, $summary['eligible_unallocated']);

        // The cheque IS visible in the payment list but not in summary.
        $payments = $response->viewData('allShopPaymentsFormattedList');
        $chequePayment = collect($payments)->firstWhere('reference', 'CHQ-PENDING-150');
        $this->assertNotNull($chequePayment);
        $this->assertSame('CHEQUE', $chequePayment['source']);
    }

    public function test_fully_allocated_receipts_remain_in_received_totals(): void
    {
        // Settlement: ₹100.
        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 100.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Money: ₹100.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 100.00,
                'payment_date' => '2026-08-02',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-100-EXACT',
            ])
            ->assertRedirect();

        // Before allocation.
        $responseBefore = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));
        $summaryBefore = $responseBefore->viewData('shopPaymentSummary');
        $this->assertSame(100.00, $summaryBefore['received']);

        // Allocate all.
        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 100.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect();

        // After allocation, received total must be unchanged.
        $responseAfter = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));
        $summaryAfter = $responseAfter->viewData('shopPaymentSummary');
        $this->assertSame(100.00, $summaryAfter['received']);
        $this->assertSame(100.00, $summaryAfter['allocated']);
        $this->assertSame(0.00, $summaryAfter['unallocated']);

        // Fully settled message.
        $responseAfter->assertSee('Fully settled');
    }

    public function test_direct_receipt_deduplication_before_and_after_conversion(): void
    {
        // Settlement: ₹200 across 2 days.
        $settlementTx = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 100.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-02',
            'entry_type_code' => 'cash_sales',
            'amount' => 100.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Create a direct bank receipt (CompanyAccountStatementEntry linked to a settlement).
        $directReceiptTx = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-03',
            'entry_type_code' => 'cash_sales',
            'amount' => 80.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => '2026-08-03',
            'value_date' => '2026-08-03',
            'direction' => 'in',
            'amount' => 80.00,
            'reference' => 'DIRECT-RECEIPT-80',
            'source_type' => ShopLedgerTransaction::class,
            'source_id' => $directReceiptTx->id,
            'status' => 'reconciled',
            'is_finalized' => true,
        ]);

        // BEFORE conversion — verify counts.
        $responseBefore = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));
        $responseBefore->assertOk();
        $summaryBefore = $responseBefore->viewData('shopPaymentSummary');
        $this->assertSame(1, $summaryBefore['direct_receipt_count']);
        $this->assertSame(80.00, $summaryBefore['received']);
        $this->assertSame(80.00, $summaryBefore['unallocated']);

        $paymentCountBefore = ShopInvoicePaymentRequest::query()->where('shop_id', $this->shop->id)->count();

        // Execute bulk allocation — converts direct receipt to payment request then allocates.
        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 80.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect();

        // AFTER conversion — direct receipt moved to payment request path.
        $paymentCountAfter = ShopInvoicePaymentRequest::query()->where('shop_id', $this->shop->id)->count();
        $this->assertSame($paymentCountBefore + 1, $paymentCountAfter);

        $responseAfter = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));
        $summaryAfter = $responseAfter->viewData('shopPaymentSummary');

        // After conversion, direct receipt count drops to 0 (now tracked as payment request).
        $this->assertSame(0, $summaryAfter['direct_receipt_count']);
        // Received total must remain ₹80 — not doubled.
        $this->assertSame(80.00, $summaryAfter['received']);
        // All money allocated against settlements.
        $this->assertSame(80.00, $summaryAfter['allocated']);
        $this->assertSame(0.00, $summaryAfter['unallocated']);

        // Total allocation records must sum to exactly ₹80.
        $this->assertSame(80.00, (float) ShopPaymentLedgerAllocation::query()->where('shop_id', $this->shop->id)->sum('amount'));
    }

    public function test_same_uuid_retry_returns_original_result(): void
    {
        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 100.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 100.00,
                'payment_date' => '2026-08-02',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-IDEM-100',
            ])
            ->assertRedirect();

        $uuid = (string) Str::uuid();

        // First submission.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 100.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $allocCountAfterFirst = ShopPaymentLedgerAllocation::query()->where('shop_id', $this->shop->id)->count();
        $allocSumAfterFirst = (float) ShopPaymentLedgerAllocation::query()->where('shop_id', $this->shop->id)->sum('amount');

        // Retry with same UUID and same expected_total — idempotent.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 100.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // No additional allocations created.
        $this->assertSame($allocCountAfterFirst, ShopPaymentLedgerAllocation::query()->where('shop_id', $this->shop->id)->count());
        $this->assertSame($allocSumAfterFirst, (float) ShopPaymentLedgerAllocation::query()->where('shop_id', $this->shop->id)->sum('amount'));
    }

    public function test_changed_payload_same_uuid_returns_conflict(): void
    {
        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 200.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 200.00,
                'payment_date' => '2026-08-02',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-CONFLICT-200',
            ])
            ->assertRedirect();

        $uuid = (string) Str::uuid();

        // First submission with expected_total = 200.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 200.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect();

        // Same UUID with DIFFERENT expected_total → conflict error.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 150.00,
                'submission_uuid' => $uuid,
            ])
            ->assertSessionHasErrors('submission_uuid');

        // Allocation total unchanged (₹200).
        $this->assertSame(200.00, (float) ShopPaymentLedgerAllocation::query()->where('shop_id', $this->shop->id)->sum('amount'));
    }

    public function test_preview_clear_cancel_cause_no_financial_writes(): void
    {
        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 100.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 100.00,
                'payment_date' => '2026-08-02',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-NO-WRITE-100',
            ])
            ->assertRedirect();

        $allocBefore = ShopPaymentLedgerAllocation::query()->count();
        $paymentsBefore = ShopInvoicePaymentRequest::query()->count();

        // Loading the page (preview) should not create any allocations.
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']))
            ->assertOk();

        $this->assertSame($allocBefore, ShopPaymentLedgerAllocation::query()->count());
        $this->assertSame($paymentsBefore, ShopInvoicePaymentRequest::query()->count());
    }

    public function test_bulk_allocation_shop_month_isolation(): void
    {
        // Setup shop A (test fixture) with settlement + money.
        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 100.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 100.00,
                'payment_date' => '2026-08-02',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-SHOP-A',
            ])
            ->assertRedirect();

        // Setup shop B with its own settlement + money.
        $shopB = Shop::factory()->create(['name' => 'Other Fresh', 'code' => 'OTHER']);
        $profileB = ShopLedgerProfile::query()->create([
            'shop_id' => $shopB->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'other-fresh-'.$shopB->id,
            'code' => $shopB->code,
            'name' => $shopB->name,
            'enabled' => true,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $shopB->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 50.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $profileB->slug), [
                'amount' => 50.00,
                'payment_date' => '2026-08-02',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-SHOP-B',
            ])
            ->assertRedirect();

        // Allocate shop A only.
        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 100.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect();

        // Shop A: ₹100 allocated.
        $this->assertSame(100.00, (float) ShopPaymentLedgerAllocation::query()->where('shop_id', $this->shop->id)->sum('amount'));
        // Shop B: ₹0 allocated (untouched).
        $this->assertSame(0.00, (float) ShopPaymentLedgerAllocation::query()->where('shop_id', $shopB->id)->sum('amount'));
    }

    public function test_summary_shows_settlement_outstanding_after_full_allocation(): void
    {
        // Settlement: ₹300 (₹200 + ₹100). Money: ₹200.
        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 200.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-02',
            'entry_type_code' => 'cash_sales',
            'amount' => 100.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $this->profile->slug), [
                'amount' => 200.00,
                'payment_date' => '2026-08-03',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-200-OF-300',
            ])
            ->assertRedirect();

        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $this->profile->slug), [
                'month' => '2026-08',
                'expected_total' => 200.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'month' => '2026-08']));

        $response->assertOk();

        // Settlement Outstanding card should show ₹100 via the view data.
        $settlementOutstanding = $response->viewData('settlementOutstanding');
        $this->assertSame(100.00, (float) $settlementOutstanding);

        // The view must render Settlement Outstanding text.
        $response->assertSee('Settlement Outstanding');
        $response->assertSee('All available money allocated');
    }

    public function test_unreceived_cash_sales_keeps_settlement_outstanding_positive_when_paytm_card_fully_allocated(): void
    {
        $shop = Shop::factory()->create(['name' => 'Test Shop A', 'code' => 'TEST_A']);
        $profile = ShopLedgerProfile::query()->create([
            'shop_id' => $shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'test-shop-a-'.$shop->id,
            'code' => $shop->code,
            'name' => $shop->name,
            'enabled' => true,
        ]);

        // 1. Cash Sales = ₹500 (held by shop, unreceived by company).
        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // 2. Paytm = ₹1000 (received directly into company bank).
        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-02',
            'entry_type_code' => 'paytm',
            'amount' => 1000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Record Company Receipt of Paytm (₹1000).
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $profile->slug), [
                'amount' => 1000.00,
                'payment_date' => '2026-08-02',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAYTM-1000-RECV',
            ])
            ->assertRedirect();

        // 3. Allocate Paytm received money (₹1000).
        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $profile->slug), [
                'month' => '2026-08',
                'expected_total' => 1000.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $profile->slug, 'month' => '2026-08']));

        $response->assertOk();

        // Total Received = ₹1000, Total Allocated = ₹1000, Unallocated = ₹0.
        $summary = $response->viewData('shopPaymentSummary');
        $this->assertSame(1000.00, (float) $summary['received']);
        $this->assertSame(1000.00, (float) $summary['allocated']);
        $this->assertSame(0.00, (float) $summary['unallocated']);

        // BUT Settlement Outstanding must equal ₹500 (Cash Sales remaining due).
        $settlementOutstanding = $response->viewData('settlementOutstanding');
        $this->assertSame(500.00, (float) $settlementOutstanding);

        // Status banner must show debt remains (amber banner rendered in HTML).
        $response->assertSee('All available money allocated. Settlement outstanding:');
    }

    public function test_fully_settled_status_is_allowed_when_all_obligations_are_genuinely_cleared(): void
    {
        $shop = Shop::factory()->create(['name' => 'Test Shop B', 'code' => 'TEST_B']);
        $profile = ShopLedgerProfile::query()->create([
            'shop_id' => $shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'test-shop-b-'.$shop->id,
            'code' => $shop->code,
            'name' => $shop->name,
            'enabled' => true,
        ]);

        // 1. Paytm = ₹1000 obligation.
        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'paytm',
            'amount' => 1000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // 2. Receive and allocate ₹1000.
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $profile->slug), [
                'amount' => 1000.00,
                'payment_date' => '2026-08-01',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAYTM-FULL-SETTLE',
            ])
            ->assertRedirect();

        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $profile->slug), [
                'month' => '2026-08',
                'expected_total' => 1000.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $profile->slug, 'month' => '2026-08']));

        $response->assertOk();

        // All obligations cleared => Settlement Outstanding = 0.
        $settlementOutstanding = $response->viewData('settlementOutstanding');
        $this->assertSame(0.00, (float) $settlementOutstanding);

        // Fully settled banner is allowed.
        $response->assertSee('Fully settled — all money allocated and all settlements cleared.');
    }

    public function test_shop_paid_company_plus_allocation_does_not_cause_double_deduction(): void
    {
        $shop = Shop::factory()->create(['name' => 'Test Shop C', 'code' => 'TEST_C']);
        $profile = ShopLedgerProfile::query()->create([
            'shop_id' => $shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'test-shop-c-'.$shop->id,
            'code' => $shop->code,
            'name' => $shop->name,
            'enabled' => true,
        ]);

        // 1. Cash Sales = ₹300, Paytm = ₹700 (Total Gross Payable = ₹1000).
        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'cash_sales',
            'amount' => 300.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'paytm',
            'amount' => 700.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // 2. Record shop_paid_company entry (e.g. ₹700 Paytm received into company account).
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.receive-payment', $profile->slug), [
                'amount' => 700.00,
                'payment_date' => '2026-08-01',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAYTM-700-RECV',
            ])
            ->assertRedirect();

        // 3. Allocate ₹700.
        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.allocate-payments.bulk', $profile->slug), [
                'month' => '2026-08',
                'expected_total' => 700.00,
                'submission_uuid' => $uuid,
            ])
            ->assertRedirect();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $profile->slug, 'month' => '2026-08']));

        $response->assertOk();

        // Settlement Outstanding must equal ₹300 (₹1000 gross - ₹700 allocated), NOT 0 or negative.
        $settlementOutstanding = $response->viewData('settlementOutstanding');
        $this->assertSame(300.00, (float) $settlementOutstanding);
    }

    public function test_details_shows_fully_partially_and_not_allocated_statuses_and_reconciles_totals(): void
    {
        $shop = Shop::factory()->create(['name' => 'Test Details Shop', 'code' => 'TEST_DET']);
        $profile = ShopLedgerProfile::query()->create([
            'shop_id' => $shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'test-details-shop-'.$shop->id,
            'code' => $shop->code,
            'name' => $shop->name,
            'enabled' => true,
        ]);

        foreach ([['paytm', 20000.00, '2026-08-01'], ['card', 20000.00, '2026-08-02'], ['income_cp', 20000.00, '2026-08-03']] as [$type, $amt, $date]) {
            $this->ledgerService->recordEntry([
                'shop_id' => $shop->id,
                'business_date' => $date,
                'entry_type_code' => $type,
                'amount' => $amt,
                'funding_source' => 'none',
                'entered_by' => $this->admin->id,
            ]);
        }

        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.receive-payment', $profile->slug), [
            'amount' => 20000.00,
            'payment_date' => '2026-08-01',
            'payment_method' => 'bank',
            'company_account_id' => $this->companyAccount->id,
            'payment_reference' => 'PAY1-FULL',
        ]);
        $pay1 = ShopInvoicePaymentRequest::query()->where('payment_reference', 'PAY1-FULL')->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.receive-payment', $profile->slug), [
            'amount' => 20000.00,
            'payment_date' => '2026-08-02',
            'payment_method' => 'bank',
            'company_account_id' => $this->companyAccount->id,
            'payment_reference' => 'PAY2-PART',
        ]);
        $pay2 = ShopInvoicePaymentRequest::query()->where('payment_reference', 'PAY2-PART')->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.receive-payment', $profile->slug), [
            'amount' => 15000.00,
            'payment_date' => '2026-08-03',
            'payment_method' => 'bank',
            'company_account_id' => $this->companyAccount->id,
            'payment_reference' => 'PAY3-NONE',
        ]);
        $pay3 = ShopInvoicePaymentRequest::query()->where('payment_reference', 'PAY3-NONE')->firstOrFail();

        $tx1 = ShopLedgerTransaction::query()->where('shop_id', $shop->id)->whereDate('business_date', '2026-08-01')->where('settlement_delta', '>', 0)->firstOrFail();
        ShopPaymentLedgerAllocation::query()->create([
            'shop_id' => $shop->id,
            'payment_request_id' => $pay1->id,
            'shop_ledger_transaction_id' => $tx1->id,
            'amount' => 20000.00,
            'allocated_by' => $this->admin->id,
        ]);

        $tx2 = ShopLedgerTransaction::query()->where('shop_id', $shop->id)->whereDate('business_date', '2026-08-02')->where('settlement_delta', '>', 0)->firstOrFail();
        ShopPaymentLedgerAllocation::query()->create([
            'shop_id' => $shop->id,
            'payment_request_id' => $pay2->id,
            'shop_ledger_transaction_id' => $tx2->id,
            'amount' => 1000.00,
            'allocated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $profile->slug, 'month' => '2026-08']));
        $response->assertOk();

        $paymentsList = $response->viewData('allShopPaymentsFormattedList');
        $items = collect($paymentsList);

        $item1 = $items->firstWhere('reference', 'PAY1-FULL');
        $item2 = $items->firstWhere('reference', 'PAY2-PART');
        $item3 = $items->firstWhere('reference', 'PAY3-NONE');

        $this->assertSame('Fully Allocated', $item1['allocation_status_label']);
        $this->assertSame(20000.00, (float) $item1['amount']);
        $this->assertSame(20000.00, (float) $item1['allocated']);
        $this->assertSame(0.00, (float) $item1['unallocated']);

        $this->assertSame('Partially Allocated', $item2['allocation_status_label']);
        $this->assertSame(20000.00, (float) $item2['amount']);
        $this->assertSame(1000.00, (float) $item2['allocated']);
        $this->assertSame(19000.00, (float) $item2['unallocated']);

        $this->assertSame('Not Allocated', $item3['allocation_status_label']);
        $this->assertSame(15000.00, (float) $item3['amount']);
        $this->assertSame(0.00, (float) $item3['allocated']);
        $this->assertSame(15000.00, (float) $item3['unallocated']);

        $summary = $response->viewData('shopPaymentSummary');
        $sumReceived = $items->sum('amount');
        $sumAllocated = $items->sum('allocated');
        $sumUnallocated = $items->sum('unallocated');

        $this->assertSame((float) $summary['received'], (float) $sumReceived);
        $this->assertSame((float) $summary['allocated'], (float) $sumAllocated);
        $this->assertSame((float) $summary['unallocated'], (float) $sumUnallocated);
    }

    public function test_clear_one_payment_allocation_leaves_payment_receipt_intact(): void
    {
        $shop = Shop::factory()->create(['name' => 'Test Clear One', 'code' => 'CLR_ONE']);
        $profile = ShopLedgerProfile::query()->create([
            'shop_id' => $shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'test-clear-one-'.$shop->id,
            'code' => $shop->code,
            'name' => $shop->name,
            'enabled' => true,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'paytm',
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.receive-payment', $profile->slug), [
            'amount' => 5000.00,
            'payment_date' => '2026-08-01',
            'payment_method' => 'bank',
            'company_account_id' => $this->companyAccount->id,
            'payment_reference' => 'SINGLE-PAY-REF',
        ]);
        $pay = ShopInvoicePaymentRequest::query()->where('payment_reference', 'SINGLE-PAY-REF')->firstOrFail();

        $tx = ShopLedgerTransaction::query()->where('shop_id', $shop->id)->whereDate('business_date', '2026-08-01')->where('settlement_delta', '>', 0)->firstOrFail();
        ShopPaymentLedgerAllocation::query()->create([
            'shop_id' => $shop->id,
            'payment_request_id' => $pay->id,
            'shop_ledger_transaction_id' => $tx->id,
            'amount' => 5000.00,
            'allocated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocate-payment.clear', $profile->slug), [
            'payment_request_id' => $pay->id,
            'month' => '2026-08',
        ])->assertRedirect();

        $this->assertDatabaseHas('shop_invoice_payment_requests', [
            'id' => $pay->id,
            'payment_reference' => 'SINGLE-PAY-REF',
        ]);
        $this->assertSame(5000.00, (float) $pay->fresh()->requested_amount);

        $this->assertDatabaseMissing('shop_payment_ledger_allocations', [
            'payment_request_id' => $pay->id,
        ]);
    }

    public function test_clear_all_allocations_leaves_payment_requests_and_shop_paid_company_intact(): void
    {
        $shop = Shop::factory()->create(['name' => 'Test Clear All', 'code' => 'CLR_ALL']);
        $profile = ShopLedgerProfile::query()->create([
            'shop_id' => $shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'test-clear-all-'.$shop->id,
            'code' => $shop->code,
            'name' => $shop->name,
            'enabled' => true,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-01',
            'entry_type_code' => 'paytm',
            'amount' => 8000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.receive-payment', $profile->slug), [
            'amount' => 8000.00,
            'payment_date' => '2026-08-01',
            'payment_method' => 'bank',
            'company_account_id' => $this->companyAccount->id,
            'payment_reference' => 'CLEAR-ALL-PAY-REF',
        ]);
        $pay = ShopInvoicePaymentRequest::query()->where('payment_reference', 'CLEAR-ALL-PAY-REF')->firstOrFail();

        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocate-payments.bulk', $profile->slug), [
            'month' => '2026-08',
            'expected_total' => 8000.00,
            'submission_uuid' => $uuid,
        ]);

        $this->assertDatabaseHas('shop_payment_ledger_allocations', ['shop_id' => $shop->id]);

        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocate-payments.clear-all', $profile->slug), [
            'month' => '2026-08',
        ])->assertRedirect();

        $this->assertDatabaseHas('shop_invoice_payment_requests', ['id' => $pay->id, 'payment_reference' => 'CLEAR-ALL-PAY-REF']);
        $this->assertSame(8000.00, (float) $pay->fresh()->requested_amount);

        $this->assertDatabaseMissing('shop_payment_ledger_allocations', ['shop_id' => $shop->id]);

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'shop_id' => $shop->id,
            'amount' => 8000.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', ['shop' => $profile->slug, 'month' => '2026-08']));
        $summary = $response->viewData('shopPaymentSummary');
        $this->assertSame(8000.00, (float) $summary['received']);
        $this->assertSame(0.00, (float) $summary['allocated']);
        $this->assertSame(8000.00, (float) $summary['unallocated']);

        $uuid2 = (string) Str::uuid();
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocate-payments.bulk', $profile->slug), [
            'month' => '2026-08',
            'expected_total' => 8000.00,
            'submission_uuid' => $uuid2,
        ])->assertRedirect();

        $response2 = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', ['shop' => $profile->slug, 'month' => '2026-08']));
        $summary2 = $response2->viewData('shopPaymentSummary');
        $this->assertSame(8000.00, (float) $summary2['received']);
        $this->assertSame(8000.00, (float) $summary2['allocated']);
        $this->assertSame(0.00, (float) $summary2['unallocated']);
    }

    public function test_non_admin_cannot_clear_allocations(): void
    {
        $shop = Shop::factory()->create(['name' => 'Test Security Shop', 'code' => 'SEC_SHOP']);
        $profile = ShopLedgerProfile::query()->create([
            'shop_id' => $shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'test-security-shop-'.$shop->id,
            'code' => $shop->code,
            'name' => $shop->name,
            'enabled' => true,
        ]);

        $regularUser = User::factory()->create(['email' => 'regular-staff@example.test']);

        $response = $this->actingAs($regularUser)->post(route('admin.cashbook.shop.allocate-payments.clear-all', $profile->slug), [
            'month' => '2026-08',
        ]);
        $response->assertStatus(403);
    }

    public function test_clear_all_only_affects_selected_shop_not_another_shop(): void
    {
        $shopA = Shop::factory()->create(['name' => 'Shop A', 'code' => 'SHOP_A']);
        $profileA = ShopLedgerProfile::query()->create([
            'shop_id' => $shopA->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'shop-a-'.$shopA->id,
            'code' => $shopA->code,
            'name' => $shopA->name,
            'enabled' => true,
        ]);

        $shopB = Shop::factory()->create(['name' => 'Shop B', 'code' => 'SHOP_B']);
        $profileB = ShopLedgerProfile::query()->create([
            'shop_id' => $shopB->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'shop-b-'.$shopB->id,
            'code' => $shopB->code,
            'name' => $shopB->name,
            'enabled' => true,
        ]);

        foreach ([[$shopA, $profileA, 'A'], [$shopB, $profileB, 'B']] as [$s, $p, $label]) {
            $this->ledgerService->recordEntry([
                'shop_id' => $s->id,
                'business_date' => '2026-08-01',
                'entry_type_code' => 'paytm',
                'amount' => 3000.00,
                'funding_source' => 'none',
                'entered_by' => $this->admin->id,
            ]);

            $this->actingAs($this->admin)->post(route('admin.cashbook.shop.receive-payment', $p->slug), [
                'amount' => 3000.00,
                'payment_date' => '2026-08-01',
                'payment_method' => 'bank',
                'company_account_id' => $this->companyAccount->id,
                'payment_reference' => 'PAY-'.$label,
            ]);

            $uuid = (string) Str::uuid();
            $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocate-payments.bulk', $p->slug), [
                'month' => '2026-08',
                'expected_total' => 3000.00,
                'submission_uuid' => $uuid,
            ]);
        }

        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocate-payments.clear-all', $profileA->slug), [
            'month' => '2026-08',
        ])->assertRedirect();

        $this->assertDatabaseMissing('shop_payment_ledger_allocations', ['shop_id' => $shopA->id]);
        $this->assertDatabaseHas('shop_payment_ledger_allocations', ['shop_id' => $shopB->id]);
    }

    public function test_settings_driven_company_payable_includes_payable_and_excludes_non_payable(): void
    {
        $shop = Shop::factory()->create();
        $profile = ShopLedgerProfile::create(['shop_id' => $shop->id]);

        $incomeType1 = LedgerEntryType::firstOrCreate(['code' => 'custom_payable_sales'], ['name' => 'Custom Payable Sales', 'category' => 'income']);
        $incomeType2 = LedgerEntryType::firstOrCreate(['code' => 'custom_non_payable_audit'], ['name' => 'Custom Audit', 'category' => 'income']);

        ShopLedgerEntrySetting::create([
            'shop_id' => $shop->id,
            'entry_type_id' => $incomeType1->id,
            'effective_from' => '2026-01-01',
            'version' => 1,
            'enabled' => true,
            'include_in_sales' => true,
            'include_in_payable' => true,
            'payable_direction' => 'add',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $shop->id,
            'entry_type_id' => $incomeType2->id,
            'effective_from' => '2026-01-01',
            'version' => 1,
            'enabled' => true,
            'include_in_sales' => true,
            'include_in_payable' => false,
            'generates_secondary_entry' => true,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-01',
            'entry_type_id' => $incomeType1->id,
            'entry_type_code' => $incomeType1->code,
            'amount' => 5000.00,
            'entered_by' => $this->admin->id,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-01',
            'entry_type_id' => $incomeType2->id,
            'entry_type_code' => $incomeType2->code,
            'amount' => 2000.00,
            'entered_by' => $this->admin->id,
        ]);

        $summary = app(CompanyMoneyPositionService::class)->getShopMonthlyDailySummaries($shop->id, '2026-08')['summary'];

        $this->assertSame(7000.00, (float) $summary['total_collections']);
        $this->assertSame(5000.00, (float) $summary['company_payable']);
        $this->assertSame(2000.00, (float) $summary['non_payable_audit']);
        $this->assertSame(5000.00, (float) $summary['still_to_receive']);
    }

    public function test_expenses_do_not_reduce_still_to_receive_collection_card(): void
    {
        $shop = Shop::factory()->create();
        $profile = ShopLedgerProfile::create(['shop_id' => $shop->id]);

        $incomeType = LedgerEntryType::firstOrCreate(['code' => 'shop_cash_income'], ['name' => 'Shop Cash Income', 'category' => 'income']);
        $expenseType = LedgerEntryType::firstOrCreate(['code' => 'shop_exp_deduct'], ['name' => 'Shop Exp Deduct', 'category' => 'expense']);

        ShopLedgerEntrySetting::create([
            'shop_id' => $shop->id,
            'entry_type_id' => $incomeType->id,
            'effective_from' => '2026-01-01',
            'version' => 1,
            'enabled' => true,
            'include_in_sales' => true,
            'include_in_payable' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $shop->id,
            'entry_type_id' => $expenseType->id,
            'effective_from' => '2026-01-01',
            'version' => 1,
            'enabled' => true,
            'include_in_expense' => true,
            'include_in_payable' => false,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-01',
            'entry_type_id' => $incomeType->id,
            'entry_type_code' => $incomeType->code,
            'amount' => 10000.00,
            'entered_by' => $this->admin->id,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-01',
            'entry_type_id' => $expenseType->id,
            'entry_type_code' => $expenseType->code,
            'amount' => 3000.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);

        $summary = app(CompanyMoneyPositionService::class)->getShopMonthlyDailySummaries($shop->id, '2026-08')['summary'];

        $this->assertSame(10000.00, (float) $summary['company_payable']);
        $this->assertSame(10000.00, (float) $summary['still_to_receive']);
    }

    public function test_payable_direction_subtract_is_respected(): void
    {
        $shop = Shop::factory()->create();
        $profile = ShopLedgerProfile::create(['shop_id' => $shop->id]);

        $addType = LedgerEntryType::firstOrCreate(['code' => 'add_type_income'], ['name' => 'Add Type Income', 'category' => 'income']);
        $subType = LedgerEntryType::firstOrCreate(['code' => 'sub_type_income'], ['name' => 'Sub Type Income', 'category' => 'income']);

        ShopLedgerEntrySetting::create([
            'shop_id' => $shop->id,
            'entry_type_id' => $addType->id,
            'effective_from' => '2026-01-01',
            'version' => 1,
            'enabled' => true,
            'include_in_payable' => true,
            'payable_direction' => 'add',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $shop->id,
            'entry_type_id' => $subType->id,
            'effective_from' => '2026-01-01',
            'version' => 1,
            'enabled' => true,
            'include_in_payable' => true,
            'payable_direction' => 'subtract',
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-01',
            'entry_type_id' => $addType->id,
            'entry_type_code' => $addType->code,
            'amount' => 10000.00,
            'entered_by' => $this->admin->id,
        ]);

        $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-01',
            'entry_type_id' => $subType->id,
            'entry_type_code' => $subType->code,
            'amount' => 2000.00,
            'entered_by' => $this->admin->id,
        ]);

        $summary = app(CompanyMoneyPositionService::class)->getShopMonthlyDailySummaries($shop->id, '2026-08')['summary'];

        $this->assertSame(8000.00, (float) $summary['company_payable']);
        $this->assertSame(8000.00, (float) $summary['still_to_receive']);
    }
}
