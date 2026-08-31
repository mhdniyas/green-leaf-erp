<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Cashbook\ShopPaymentLedgerReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('0 Payments Recorded')
            ->assertSee('Receive First Payment')
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
}
