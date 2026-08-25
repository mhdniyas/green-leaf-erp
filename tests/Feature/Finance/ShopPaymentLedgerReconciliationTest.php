<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentAllocation;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopPaymentLedgerReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $companyAccount;

    private DailyLedgerService $ledgerService;

    private CompanyPaymentReconciliationService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.user_access.main_admin_email' => 'settlement-admin@example.test']);
        $this->admin = User::factory()->create(['email' => 'settlement-admin@example.test']);
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        $this->shop = Shop::factory()->create();
        $this->profile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->shop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'settlement-test-'.$this->shop->id,
            'code' => $this->shop->code,
            'name' => $this->shop->name,
            'enabled' => true,
        ]);
        $this->companyAccount = CompanyAccount::query()->create([
            'name' => 'Settlement Bank',
            'account_type' => 'bank',
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
        $this->paymentService = app(CompanyPaymentReconciliationService::class);
    }

    public function test_finalized_payment_reconciles_rows_once_without_new_company_accounting(): void
    {
        $credit = $this->ledgerEntry($this->shop, 'cash_sales', 180000.00, 'none');
        $debit = $this->ledgerEntry($this->shop, 'rent_expense', 30000.00, 'sales');
        $payment = $this->finalizedPayment(150000.00);
        $journalCount = JournalEntry::query()->count();
        $statementCount = CompanyAccountStatementEntry::query()->count();

        $payload = $this->allocationPayload($payment, $credit, 180000.00, $debit, 30000.00);
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.accept-payment.reconcile', $this->profile->uuid), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('shop_payment_ledger_allocations', 2);
        $this->assertSame($journalCount, JournalEntry::query()->count());
        $this->assertSame($statementCount, CompanyAccountStatementEntry::query()->count());
        $this->assertDatabaseHas('shop_ledger_transactions', [
            'shop_id' => $this->shop->id,
            'reference_type' => ShopInvoicePaymentRequest::class,
            'reference_id' => $payment->id,
            'amount' => 150000.00,
        ]);
        $this->assertSame(0.00, (float) $this->ledgerService
            ->dailySummary($this->shop->id, today()->toDateString())
            ->closing_shop_position);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.accept-payment.reconcile', $this->profile->uuid), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('shop_payment_ledger_allocations', 2);
        $this->assertSame(1, ShopLedgerTransaction::query()
            ->where('reference_type', ShopInvoicePaymentRequest::class)
            ->where('reference_id', $payment->id)
            ->count());
    }

    public function test_partial_row_remains_open_and_cross_shop_rows_are_rejected(): void
    {
        $credit = $this->ledgerEntry($this->shop, 'cash_sales', 200000.00, 'none');
        $debit = $this->ledgerEntry($this->shop, 'rent_expense', 50000.00, 'sales');
        $payment = $this->finalizedPayment(150000.00);
        $foreignCredit = $this->ledgerEntry(Shop::factory()->create(), 'cash_sales', 200000.00, 'none');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.accept-payment.reconcile', $this->profile->uuid), $this->allocationPayload($payment, $foreignCredit, 200000.00, $debit, 50000.00))
            ->assertSessionHasErrors('allocations');
        $this->assertDatabaseCount('shop_payment_ledger_allocations', 0);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.accept-payment.reconcile', $this->profile->uuid), [
                'payment_ref' => $payment->secureRouteKey(),
                'month' => today()->format('Y-m'),
                'allocations' => [['ledger_ref' => $credit->secureRouteKey(), 'amount' => 150000.00]],
            ])
            ->assertRedirect();

        $this->assertSame(150000.00, (float) ShopPaymentLedgerAllocation::query()
            ->where('shop_ledger_transaction_id', $credit->id)
            ->value('amount'));
    }

    public function test_workspace_requires_finalized_payment_and_uses_public_references(): void
    {
        $pending = ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'requested_amount' => 500.00,
            'reconciled_amount' => 0.00,
            'reconciliation_status' => 'floating',
        ]);
        $credit = $this->ledgerEntry($this->shop, 'cash_sales', 500.00, 'none');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.accept-payment.reconcile', $this->profile->uuid), [
                'payment_ref' => $pending->secureRouteKey(),
                'allocations' => [['ledger_ref' => $credit->secureRouteKey(), 'amount' => 500.00]],
            ])->assertSessionHasErrors('payment_ref');

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.accept-payment', ['shop' => $this->profile->uuid, 'month' => today()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Shop Payment Reconciliation')
            ->assertSee('Record Payment')
            ->assertSee('Pending Payments')
            ->assertSee($this->profile->uuid, false)
            ->assertDontSee('/shops/'.$this->shop->id.'/accept-payment', false);
    }

    public function test_scoped_payment_recording_is_submission_only_until_an_imported_statement_matches_it(): void
    {
        $requestUuid = (string) str()->uuid();
        $payload = [
            'amount' => 1250.00,
            'payment_method' => 'online_upi',
            'payment_date' => today()->toDateString(),
            'payment_reference' => 'SHOP-RECEIPT-1250',
            'notes' => 'Shop cashbook test receipt',
            'request_uuid' => $requestUuid,
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.accept-payment.store', $this->profile->uuid), $payload)
            ->assertRedirect(route('admin.cashbook.shop.accept-payment', [
                'shop' => $this->profile->uuid,
                'month' => today()->format('Y-m'),
            ]));

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.accept-payment.store', $this->profile->uuid), $payload)
            ->assertRedirect();

        $payment = ShopInvoicePaymentRequest::query()->sole();
        $this->assertSame($this->shop->id, $payment->shop_id);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('floating', $payment->reconciliation_status);
        $this->assertSame($requestUuid, $payment->submission_uuid);
        $this->assertDatabaseCount('cashbook_company_account_statement_entries', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('shop_payment_ledger_allocations', 0);
        $this->assertDatabaseCount('shop_invoices', 0);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.accept-payment', ['shop' => $this->profile->uuid]))
            ->assertOk()
            ->assertSee('SHOP-RECEIPT-1250')
            ->assertSee('Awaiting Reconciliation')
            ->assertSee('Awaiting Statement');

        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => today()->toDateString(),
            'direction' => 'in',
            'amount' => 1250.00,
            'reference' => 'SHOP-RECEIPT-1250',
            'narration' => 'Imported bank receipt',
            'source' => 'import',
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-shop-payment', $statement), [
                'payment_request_ref' => $payment->secureRouteKey(),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('cashbook_company_account_statement_entries', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertTrue($statement->fresh()->is_finalized);
        $this->assertSame('reconciled', $payment->fresh()->reconciliation_status);
    }

    public function test_legacy_payment_routes_do_not_record_another_shop_payment(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.accept-payment'))
            ->assertRedirect(route('admin.cashbook.all-shops', ['month' => now()->format('Y-m')]));

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.settlement', $this->profile->uuid))
            ->assertRedirect(route('admin.cashbook.shop.accept-payment', [
                'shop' => $this->profile->uuid,
                'month' => now()->format('Y-m'),
            ]));

        $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.accept-payment'), ['shop_id' => $this->shop->id])
            ->assertStatus(410);

        $this->assertDatabaseCount('shop_invoice_payment_requests', 0);
        $this->assertDatabaseCount('cashbook_company_account_statement_entries', 0);
    }

    public function test_shop_detail_and_payables_keep_payment_recording_on_the_scoped_workspace(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', $this->profile->uuid))
            ->assertOk()
            ->assertSee(route('admin.cashbook.shop.accept-payment', $this->profile->uuid), false);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.payables'))
            ->assertOk()
            ->assertDontSee('accept-payment-form', false)
            ->assertDontSee('Accept Payment &amp; Settlement', false)
            ->assertSee('Shop Payables Report');
    }

    public function test_scoped_payment_workspace_rejects_numeric_and_other_shop_references(): void
    {
        $otherShop = Shop::factory()->create();
        $otherProfile = ShopLedgerProfile::query()->create([
            'shop_id' => $otherShop->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'settlement-test-'.$otherShop->id,
            'code' => $otherShop->code,
            'name' => $otherShop->name,
            'enabled' => true,
        ]);
        ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $otherShop->id,
            'payment_reference' => 'OTHER-SHOP-ONLY',
            'requested_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.accept-payment', $this->shop->id))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.accept-payment', $this->profile->uuid))
            ->assertOk()
            ->assertDontSee('OTHER-SHOP-ONLY');

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.accept-payment', $otherProfile->uuid))
            ->assertOk()
            ->assertSee('OTHER-SHOP-ONLY');
    }

    public function test_finance_v2_payment_creation_redirects_to_the_cashbook_shop_workspace(): void
    {
        $target = route('admin.cashbook.shop.accept-payment', ['shop' => $this->shop->public_uuid]);

        $this->actingAs($this->admin)
            ->get(route('admin.finance-v2.payments.create', ['shop_id' => $this->shop->id]))
            ->assertRedirect($target);

        $this->actingAs($this->admin)
            ->post(route('admin.finance-v2.payments.store'), ['shop_id' => $this->shop->id])
            ->assertRedirect($target);

        $this->assertDatabaseCount('shop_invoice_payment_requests', 0);
    }

    public function test_shop_finance_overview_uses_summary_cards_and_bounds_recent_payments(): void
    {
        foreach (range(1, 6) as $number) {
            ShopInvoicePaymentRequest::factory()->create([
                'shop_id' => $this->shop->id,
                'requested_by' => $this->admin->id,
                'payment_reference' => 'OVERVIEW-'.$number,
                'payment_date' => today()->toDateString(),
                'requested_amount' => 100.00 * $number,
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->uuid, 'month' => today()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Pending Payable to Company')
            ->assertSee('Payment Received')
            ->assertSee('Company → Shop Pending')
            ->assertSee('GL Bill Pending')
            ->assertSee('Current Net Balance')
            ->assertSee('OVERVIEW-6')
            ->assertDontSee('OVERVIEW-1')
            ->assertSee(route('admin.cashbook.shop.accept-payment', ['shop' => $this->profile->uuid, 'month' => today()->format('Y-m')]), false);
    }

    public function test_legacy_invoice_allocated_payment_is_rejected_before_shop_settlement_writes(): void
    {
        $credit = $this->ledgerEntry($this->shop, 'cash_sales', 500.00, 'none');
        $payment = $this->finalizedPayment(500.00);
        $invoice = ShopInvoice::factory()->create(['shop_id' => $this->shop->id]);
        ShopInvoicePaymentAllocation::query()->create([
            'payment_request_id' => $payment->id,
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 500.00,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.shop.accept-payment.reconcile', $this->profile->uuid), [
                'payment_ref' => $payment->secureRouteKey(),
                'allocations' => [['ledger_ref' => $credit->secureRouteKey(), 'amount' => 500.00]],
            ])
            ->assertSessionHasErrors('payment_ref');

        $this->assertDatabaseCount('shop_payment_ledger_allocations', 0);
        $this->assertSame(0, ShopLedgerTransaction::query()
            ->where('reference_type', ShopInvoicePaymentRequest::class)
            ->where('reference_id', $payment->id)
            ->count());
    }

    public function test_shop_owner_payment_request_does_not_create_a_pre_finalization_settlement(): void
    {
        $owner = User::factory()->create(['shop_id' => $this->shop->id]);
        $role = Role::findOrCreate('shop');
        $permission = Permission::findOrCreate('sales.order.create');
        $role->givePermissionTo($permission);
        $owner->assignRole($role);
        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'final_total' => 500.00,
            'paid_amount' => 0.00,
            'balance_amount' => 500.00,
        ]);

        $this->actingAs($owner)
            ->post(route('shop-owner.accounting.payment-requests.store'), [
                'invoice_id' => $invoice->id,
                'amount_mode' => 'custom',
                'amount' => 500.00,
                'payment_method' => 'cash',
                'payment_date' => today()->toDateString(),
            ])
            ->assertRedirect();

        $payment = ShopInvoicePaymentRequest::query()->sole();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('floating', $payment->reconciliation_status);
        $this->assertSame(0, ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->whereHas('entryType', fn ($query) => $query->where('code', 'shop_paid_company'))
            ->count());
    }

    public function test_legacy_finance_v2_payment_approval_still_allocates_its_pending_invoice(): void
    {
        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'final_total' => 500.00,
            'paid_amount' => 0.00,
            'balance_amount' => 500.00,
        ]);
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'request_type' => 'admin_v2',
            'payment_method' => 'cash',
            'payment_date' => today()->toDateString(),
            'requested_amount' => 500.00,
            'status' => 'pending',
            'reconciliation_status' => 'floating',
            'floating_amount' => 500.00,
        ]);

        app(ShopInvoiceService::class)->reviewPaymentRequestWithAmount(
            $payment,
            'approve',
            $this->admin->id,
            'Legacy Finance V2 approval.',
            500.00,
        );

        $this->assertDatabaseHas('shop_invoice_payment_allocations', [
            'payment_request_id' => $payment->id,
            'shop_invoice_id' => $invoice->id,
            'amount' => 500.00,
        ]);
        $this->assertSame(500.00, (float) $invoice->fresh()->paid_amount);
        $this->assertSame(0.00, (float) $invoice->fresh()->balance_amount);
    }

    private function finalizedPayment(float $amount): ShopInvoicePaymentRequest
    {
        $payment = ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'payment_method' => 'bank_transfer',
            'payment_date' => today()->toDateString(),
            'requested_amount' => $amount,
            'reconciled_amount' => 0.00,
            'floating_amount' => $amount,
            'reconciliation_status' => 'floating',
        ]);

        $this->paymentService->reconcilePayment($payment, [
            'company_account_id' => $this->companyAccount->id,
            'statement_amount' => $amount,
            'cleared_amount' => $amount,
            'difference_amount' => 0.00,
            'difference_action' => 'none',
            'business_date' => today()->toDateString(),
        ], $this->admin->id);

        return $payment->fresh();
    }

    private function ledgerEntry(Shop $shop, string $code, float $amount, string $fundingSource): ShopLedgerTransaction
    {
        return $this->ledgerService->recordEntry([
            'shop_id' => $shop->id,
            'business_date' => today()->toDateString(),
            'entry_type_code' => $code,
            'amount' => $amount,
            'funding_source' => $fundingSource,
            'entered_by' => $this->admin->id,
        ])['transaction'];
    }

    private function allocationPayload(ShopInvoicePaymentRequest $payment, ShopLedgerTransaction $credit, float $creditAmount, ShopLedgerTransaction $debit, float $debitAmount): array
    {
        return [
            'payment_ref' => $payment->secureRouteKey(),
            'month' => today()->format('Y-m'),
            'allocations' => [
                ['ledger_ref' => $credit->secureRouteKey(), 'amount' => $creditAmount],
                ['ledger_ref' => $debit->secureRouteKey(), 'amount' => $debitAmount],
            ],
        ];
    }
}
