<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentAllocation;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookReconciliationPhase1Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private CompanyAccount $companyAccount;

    private Account $bankAccount;

    private Account $arAccount;

    private Account $incomeAccount;

    private Account $expenseAccount;

    private CompanyPaymentReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        $this->shop = Shop::factory()->create();
        $this->companyAccount = CompanyAccount::query()->create([
            'name' => 'Test Bank Account',
            'account_type' => 'bank',
            'bank_name' => 'South Indian Bank',
            'enabled' => true,
        ]);

        $this->bankAccount = Account::query()->firstOrCreate(
            ['code' => '1020'],
            ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]
        );

        $this->arAccount = Account::query()->firstOrCreate(
            ['code' => '1100'],
            ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]
        );
        $this->incomeAccount = Account::query()->firstOrCreate(
            ['code' => '4200'],
            ['name' => 'Other Income', 'type' => 'revenue', 'is_active' => true]
        );
        $this->expenseAccount = Account::query()->firstOrCreate(
            ['code' => '5900'],
            ['name' => 'Miscellaneous Expense', 'type' => 'expense', 'is_active' => true]
        );
        Account::query()->firstOrCreate(
            ['code' => '1500'],
            ['name' => 'Shop Petty Advances', 'type' => 'asset', 'is_active' => true]
        );
        LedgerEntryType::query()->firstOrCreate(
            ['code' => 'company_to_petty'],
            ['name' => 'Company to Petty', 'category' => 'transfer', 'active' => true]
        );

        $this->service = app(CompanyPaymentReconciliationService::class);
    }

    public function test_action_center_renders_summary_filters_actions_and_queue(): void
    {
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => today()->toDateString(),
            'direction' => 'in',
            'amount' => 1250.00,
            'reference' => 'BANK-IN-AC',
            'narration' => 'Unmatched customer receipt',
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => today()->toDateString(),
            'direction' => 'out',
            'amount' => 450.00,
            'reference' => 'BANK-OUT-AC',
            'narration' => 'Pending vendor payment',
            'status' => 'unmatched',
            'journal_entry_id' => $this->journalEntry('PENDING-JE', 'out', 450.00)->id,
            'imported_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'workspace' => 'statements',
            'company_account_uuid' => $this->companyAccount->public_uuid,
            'month' => today()->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertSee('Cashbook Action Center');
        $response->assertSee('Needs Reconciliation');
        $response->assertSee('Statement Movements');
        $response->assertSee('Reconciled History');
        $response->assertSee('Awaiting Reconciliation');
        $response->assertSee('Unmatched Statements');
        $response->assertSee('Partial');
        $response->assertSee('Finalized This Month');

        $defaultWorkspaceResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'company_account_uuid' => $this->companyAccount->public_uuid,
            'month' => today()->format('Y-m'),
        ]));

        $defaultWorkspaceResponse->assertOk()
            ->assertSee('Statement Movements')
            ->assertSee('Classify / Match');

        $statementResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'workspace' => 'statements',
            'company_account_uuid' => $this->companyAccount->public_uuid,
            'month' => today()->format('Y-m'),
        ]));

        $statementResponse->assertOk()->assertSee('Classify / Match')->assertSee('Selected Statement');
    }

    public function test_statement_search_does_not_carry_to_other_workspaces(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'workspace' => 'statements',
            'company_account_uuid' => $this->companyAccount->public_uuid,
            'month' => today()->format('Y-m'),
            'search' => 'BANK-ONLY',
        ]));

        $response->assertOk()->assertDontSee(
            'workspace=needs_reconciliation&amp;company_account_uuid='.$this->companyAccount->public_uuid.'&amp;month='.today()->format('Y-m').'&amp;search=BANK-ONLY',
            false,
        );

        $needsReconciliationResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'workspace' => 'needs_reconciliation',
            'company_account_uuid' => $this->companyAccount->public_uuid,
            'month' => today()->format('Y-m'),
            'search' => 'BANK-ONLY',
        ]));

        $needsReconciliationResponse->assertOk()->assertDontSee('value="BANK-ONLY"', false);
    }

    public function test_unmatched_out_statement_opens_match_panel_then_create_page_with_out_actions_only(): void
    {
        $statement = $this->statementEntry('out', 25000.00, [
            'reference' => 'OUT-CLASSIFY-001',
            'narration' => 'Supplier bank debit',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'classify_statement' => $statement->public_uuid,
            'company_account_uuid' => $this->companyAccount->public_uuid,
            'month' => today()->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertSee('Match Transaction');
        $response->assertSee('Possible Matches');
        $response->assertSee('Create New Transaction');
        $response->assertSee('OUT');
        $response->assertSee('25,000.00');
        $response->assertSee('OUT-CLASSIFY-001');
        $response->assertSee('Supplier bank debit');
        $response->assertSee($this->companyAccount->name);
        $response->assertSee('data-classification-action="match-existing"', false);
        $response->assertDontSee('data-classification-action="payable"', false);
        $response->assertDontSee('data-classification-action="vendor-payment"', false);
        $response->assertDontSee('data-classification-action="custom-expense"', false);

        $createResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation.create-transaction', $statement));

        $createResponse->assertOk();
        $createResponse->assertSee('Create Transaction');
        $createResponse->assertSee('Back to Possible Matches');
        $createResponse->assertSee('Expense');
        $createResponse->assertSee('Payable');
        $createResponse->assertSee('Vendor');
        $createResponse->assertSee('data-classification-action="custom-expense"', false);
        $createResponse->assertDontSee('data-classification-action="payable"', false);
        $createResponse->assertDontSee('data-classification-action="vendor-payment"', false);
        $createResponse->assertDontSee('data-classification-action="salary-payment"', false);
        $createResponse->assertDontSee('data-classification-action="salary-advance"', false);
        $createResponse->assertDontSee('data-classification-action="shop-petty"', false);
        $createResponse->assertDontSee('data-classification-action="purchaser-funding"', false);
        $createResponse->assertDontSee('data-classification-action="match-existing"', false);
        $createResponse->assertDontSee('data-classification-action="custom-income"', false);
        $createResponse->assertDontSee('data-classification-action="shop-payment"', false);
        $createResponse->assertDontSee('data-classification-action="direct-company-sale"', false);
    }

    public function test_pending_shop_payment_finds_real_statement_then_moves_to_history(): void
    {
        $payment = $this->shopPayment(1250.00, 'SHOP-FIND-STATEMENT');
        $statement = $this->statementEntry('in', 1250.00, ['reference' => 'REAL-BANK-CREDIT']);

        $queue = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'month' => today()->format('Y-m'),
            'workspace' => 'needs_reconciliation',
        ]));

        $queue->assertOk()
            ->assertSee('Shop Payment')
            ->assertSee('Pending Reconciliation')
            ->assertSee('View Details')
            ->assertSee('Reconcile')
            ->assertDontSee('Find Statement')
            ->assertDontSee('Awaiting Statement')
            ->assertDontSee('UNRECONCILED');
        $queue->assertSee('Matching Statement Movements')->assertSee('pending-candidates', false);
        $finder = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation.pending-candidates', [
            'find_kind' => 'shop_payment',
            'find_ref' => $payment->secureRouteKey(),
        ]));
        $finder->assertOk()->assertJsonPath('candidates.0.reference', 'REAL-BANK-CREDIT');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'find_kind' => 'shop_payment',
            'find_ref' => $payment->secureRouteKey(),
            'details' => 1,
            'month' => today()->format('Y-m'),
            'workspace' => 'needs_reconciliation',
        ]))->assertOk()->assertSee('Transaction Details')->assertSee('Amount Submitted')->assertSee('Current Status');

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.reconciliation.classify-shop-payment', $statement), [
            'payment_request_ref' => $payment->secureRouteKey(),
        ])->assertRedirect();

        $this->assertTrue($statement->fresh()->is_finalized);
        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'workspace' => 'history',
            'month' => today()->format('Y-m'),
        ]))->assertOk()->assertSee('Shop Payment')->assertSee('REAL-BANK-CREDIT');
    }

    public function test_unmatched_in_statement_opens_match_panel_then_create_page_with_in_actions_only(): void
    {
        $statement = $this->statementEntry('in', 3200.00, [
            'reference' => 'IN-CLASSIFY-001',
            'narration' => 'Unknown bank credit',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'classify_statement' => $statement->public_uuid,
            'company_account_uuid' => $this->companyAccount->public_uuid,
            'month' => today()->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertSee('Match Transaction');
        $response->assertSee('Possible Matches');
        $response->assertSee('Create New Transaction');
        $response->assertSee('IN');
        $response->assertSee('3,200.00');
        $response->assertSee('IN-CLASSIFY-001');
        $response->assertSee('Unknown bank credit');
        $response->assertSee('data-classification-action="match-existing"', false);
        $response->assertDontSee('data-classification-action="custom-income"', false);

        $createResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation.create-transaction', $statement));

        $createResponse->assertOk();
        $createResponse->assertSee('Other Income');
        $createResponse->assertSee('Shop Payment');
        $createResponse->assertSee('Direct Sale');
        $createResponse->assertSee('data-classification-action="custom-income"', false);
        $createResponse->assertDontSee('data-classification-action="shop-payment"', false);
        $createResponse->assertDontSee('data-classification-action="direct-company-sale"', false);
        $createResponse->assertDontSee('data-classification-action="match-existing"', false);
        $createResponse->assertDontSee('data-classification-action="payable"', false);
        $createResponse->assertDontSee('data-classification-action="vendor-payment"', false);
        $createResponse->assertDontSee('data-classification-action="custom-expense"', false);
        $createResponse->assertDontSee('data-classification-action="salary-payment"', false);
        $createResponse->assertDontSee('data-classification-action="salary-advance"', false);
        $createResponse->assertDontSee('data-classification-action="shop-petty"', false);
        $createResponse->assertDontSee('data-classification-action="purchaser-funding"', false);
        $response->assertDontSee('data-classification-action="payable"', false);
        $response->assertDontSee('data-classification-action="vendor-payment"', false);
        $response->assertDontSee('data-classification-action="custom-expense"', false);
        $response->assertDontSee('data-classification-action="salary-payment"', false);
        $response->assertDontSee('data-classification-action="salary-advance"', false);
        $response->assertDontSee('data-classification-action="shop-petty"', false);
        $response->assertDontSee('data-classification-action="purchaser-funding"', false);
    }

    public function test_shop_payment_tab_shows_five_recent_rows_and_only_exact_compatible_payments(): void
    {
        $statement = $this->statementEntry('in', 1250.00, ['reference' => 'SHOP-STMT-ELIGIBLE']);
        $eligiblePayment = $this->shopPayment(1250.00, 'SHOP-PAY-ELIGIBLE');
        $this->shopPayment(1200.00, 'SHOP-PAY-WRONG-AMOUNT');
        $cashPayment = $this->shopPayment(1250.00, 'SHOP-PAY-CASH', ['payment_method' => 'cash']);
        $legacyPayment = $this->shopPayment(1250.00, 'SHOP-PAY-LEGACY');
        $legacyInvoice = ShopInvoice::factory()->create(['shop_id' => $this->shop->id]);
        ShopInvoicePaymentAllocation::query()->create([
            'payment_request_id' => $legacyPayment->id,
            'shop_invoice_id' => $legacyInvoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 1250.00,
            'created_by' => $this->admin->id,
        ]);

        foreach (range(1, 6) as $index) {
            $this->shopPayment(100.00 + $index, 'SHOP-RECENT-'.$index, [
                'payment_date' => today()->subDays($index)->toDateString(),
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.reconciliation.create-transaction', [
                'statement' => $statement,
                'type' => 'shop-payment',
            ]))
            ->assertOk()
            ->assertSee('Shop Payment')
            ->assertSee('Recent Transactions')
            ->assertSee('Eligible Payments')
            ->assertSee('SHOP-PAY-ELIGIBLE')
            ->assertDontSee('SHOP-PAY-WRONG-AMOUNT')
            ->assertDontSee('SHOP-PAY-CASH')
            ->assertDontSee('SHOP-PAY-LEGACY')
            ->assertSee('data-classification-action="shop-payment"', false);

        $this->assertSame(1250.00, (float) $eligiblePayment->requested_amount);
        $this->assertSame('cash', $cashPayment->payment_method);
        $response->assertDontSee('SHOP-RECENT-6');
    }

    public function test_statement_first_shop_payment_reuses_statement_creates_one_journal_and_finalizes_once(): void
    {
        $statement = $this->statementEntry('in', 1500.00, ['reference' => 'SHOP-STMT-FINALIZE']);
        $paymentRequest = $this->shopPayment(1500.00, 'SHOP-PAY-FINALIZE');

        $payload = ['payment_request_ref' => $paymentRequest->secureRouteKey()];

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-shop-payment', $statement), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $statement->refresh();
        $paymentRequest->refresh();

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertTrue((bool) $statement->is_finalized);
        $this->assertSame('reconciled', $statement->status);
        $this->assertNotNull($statement->journal_entry_id);
        $this->assertSame(ShopInvoicePaymentRequest::class, $statement->source_type);
        $this->assertSame($paymentRequest->id, $statement->source_id);
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertSame('reconciled', $paymentRequest->reconciliation_status);
        $this->assertSame(1500.00, (float) $paymentRequest->reconciled_amount);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => 'SHOP-PAYMENT-'.$paymentRequest->id]))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee('Shop Payment')
            ->assertSee('1,500.00');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-shop-payment', $statement), $payload)
            ->assertSessionHasErrors('statement');

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, JournalEntry::query()->count());
    }

    public function test_statement_first_shop_payment_rejects_wrong_direction_amount_and_finalized_payment(): void
    {
        $wrongDirectionStatement = $this->statementEntry('out', 900.00);
        $paymentRequest = $this->shopPayment(900.00, 'SHOP-PAY-WRONG-DIRECTION');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-shop-payment', $wrongDirectionStatement), [
                'payment_request_ref' => $paymentRequest->secureRouteKey(),
            ])
            ->assertSessionHasErrors('statement');

        $wrongAmountStatement = $this->statementEntry('in', 800.00);
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-shop-payment', $wrongAmountStatement), [
                'payment_request_ref' => $paymentRequest->secureRouteKey(),
            ])
            ->assertSessionHasErrors('payment_request_ref');

        $finalizedPayment = $this->shopPayment(700.00, 'SHOP-PAY-FINALIZED', [
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'reconciled_amount' => 700.00,
            'floating_amount' => 0.00,
        ]);
        $finalizedStatement = $this->statementEntry('in', 700.00);
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-shop-payment', $finalizedStatement), [
                'payment_request_ref' => $finalizedPayment->secureRouteKey(),
            ])
            ->assertSessionHasErrors('payment_request_ref');

        $this->assertSame(0, JournalEntry::query()->count());
        $this->assertFalse((bool) $wrongDirectionStatement->fresh()->is_finalized);
        $this->assertFalse((bool) $wrongAmountStatement->fresh()->is_finalized);
        $this->assertFalse((bool) $finalizedStatement->fresh()->is_finalized);
    }

    public function test_create_transaction_vendor_tab_loads_only_selected_vendor_bills(): void
    {
        $statement = $this->statementEntry('out', 100000.00, [
            'reference' => 'VENDOR-LAZY-001',
        ]);
        $selectedSupplier = Supplier::factory()->create(['name' => 'Selected Vendor']);
        $otherSupplier = Supplier::factory()->create(['name' => 'Other Vendor']);
        $selectedInvoice = PurchaseInvoice::factory()->for($selectedSupplier)->create([
            'invoice_number' => 'SELECTED-BILL-001',
            'amount' => 100000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
        ]);
        PurchaseInvoice::factory()->for($otherSupplier)->create([
            'invoice_number' => 'OTHER-BILL-001',
            'amount' => 100000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'payment_paid_by' => 'vendor_credit',
        ]);

        $defaultResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation.create-transaction', $statement));

        $defaultResponse->assertOk();
        $defaultResponse->assertSee('Expense');
        $defaultResponse->assertDontSee('SELECTED-BILL-001');
        $defaultResponse->assertDontSee('OTHER-BILL-001');

        $vendorResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation.create-transaction', [
            'statement' => $statement,
            'type' => 'vendor',
            'supplier_id' => $selectedSupplier->id,
        ]));

        $vendorResponse->assertOk();
        $vendorResponse->assertSee('Vendor Payment');
        $vendorResponse->assertSee($selectedSupplier->name);
        $vendorResponse->assertSee($selectedInvoice->invoice_number);
        $vendorResponse->assertDontSee('OTHER-BILL-001');
        $vendorResponse->assertSee('data-classification-action="vendor-payment"', false);
    }

    public function test_classification_context_uses_public_uuid_and_not_numeric_statement_id(): void
    {
        $statement = $this->statementEntry('out', 880.00, ['reference' => 'PUBLIC-CONTEXT']);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'classify_statement' => $statement->public_uuid,
            'company_account_uuid' => $this->companyAccount->public_uuid,
            'month' => today()->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertSee('"statement_public_uuid":"'.$statement->public_uuid.'"', false);
        $response->assertSee('"company_account_public_uuid":"'.$this->companyAccount->public_uuid.'"', false);
        $response->assertSee('"amount":880', false);
        $response->assertDontSee('"statement_id"', false);
        $response->assertDontSee('statement_id=', false);
        $response->assertDontSee('source_id', false);
    }

    public function test_forged_classification_uuid_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.reconciliation', [
                'classify_statement' => (string) Str::uuid(),
                'company_account_uuid' => $this->companyAccount->public_uuid,
            ]))
            ->assertNotFound();
    }

    public function test_finalized_statement_cannot_be_classified(): void
    {
        $statement = $this->statementEntry('in', 1100.00, [
            'status' => 'reconciled',
            'is_finalized' => true,
            'finalized_at' => now(),
            'matched_amount' => 1100.00,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.reconciliation', [
                'classify_statement' => $statement->public_uuid,
                'company_account_uuid' => $this->companyAccount->public_uuid,
            ]))
            ->assertNotFound();
    }

    public function test_linked_statement_still_shows_reconcile_action(): void
    {
        $journalEntry = $this->journalEntry('LINKED-RECONCILE', 'out', 450.00);
        $this->statementEntry('out', 450.00, [
            'reference' => 'LINKED-STATEMENT',
            'journal_entry_id' => $journalEntry->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'workspace' => 'statements',
            'company_account_uuid' => $this->companyAccount->public_uuid,
            'status' => 'pending',
            'month' => today()->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertSee('Reconcile');
        $response->assertSee('LINKED-RECONCILE');
        $response->assertSee('name="journal_entry_id"', false);
    }

    public function test_partial_statement_still_shows_continue_reconciliation_action(): void
    {
        $this->statementEntry('in', 900.00, [
            'reference' => 'PARTIAL-STATEMENT',
            'status' => 'partially_matched',
            'matched_amount' => 300.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'workspace' => 'statements',
            'company_account_uuid' => $this->companyAccount->public_uuid,
            'status' => 'partial',
            'month' => today()->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertSee('Continue Reconciliation');
        $response->assertSee('PARTIAL-STATEMENT');
    }

    public function test_statement_first_custom_income_reuses_statement_and_finalizes_once(): void
    {
        $statement = $this->statementEntry('in', 1250.00, ['reference' => 'INCOME-STMT']);
        $category = $this->category('income', 'Bank Interest', $this->incomeAccount);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-company-accounting', $statement), [
                'type' => 'income',
                'company_accounting_category_id' => $category->id,
                'description' => 'Bank interest income',
            ])
            ->assertRedirect();

        $statement->refresh();
        $entry = CompanyAccountingEntry::query()->firstOrFail();
        $journal = JournalEntry::query()->with('transactions.account')->firstOrFail();

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, CompanyAccountingEntry::query()->count());
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertTrue($statement->is_finalized);
        $this->assertSame('reconciled', $statement->status);
        $this->assertSame($journal->id, $statement->journal_entry_id);
        $this->assertSame(CompanyAccountingEntry::class, $statement->source_type);
        $this->assertSame($entry->id, $statement->source_id);
        $this->assertTrue($journal->is_balanced);
        $this->assertTrue($journal->transactions->contains(fn ($transaction): bool => $transaction->type === 'debit' && $transaction->account?->code === '1020' && (float) $transaction->amount === 1250.00));
        $this->assertTrue($journal->transactions->contains(fn ($transaction): bool => $transaction->type === 'credit' && $transaction->account?->code === '4200' && (float) $transaction->amount === 1250.00));

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $journal->reference]))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee('Other Income')
            ->assertSee('1,250.00');
    }

    public function test_custom_income_rejects_out_statement(): void
    {
        $statement = $this->statementEntry('out', 1250.00);
        $category = $this->category('income', 'Wrong Income', $this->incomeAccount);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-company-accounting', $statement), [
                'type' => 'income',
                'company_accounting_category_id' => $category->id,
            ])
            ->assertSessionHasErrors('statement');

        $this->assertSame(0, CompanyAccountingEntry::query()->count());
        $this->assertSame(0, JournalEntry::query()->count());
        $this->assertFalse($statement->fresh()->is_finalized);
    }

    public function test_statement_first_custom_expense_reuses_statement_category_mapping_and_finalizes_once(): void
    {
        $statement = $this->statementEntry('out', 890.00, ['reference' => 'EXPENSE-STMT']);
        $category = $this->category('expense', 'Bank Charges', $this->expenseAccount);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-company-accounting', $statement), [
                'type' => 'expense',
                'company_accounting_category_id' => $category->id,
                'description' => 'Monthly bank charge',
            ])
            ->assertRedirect();

        $statement->refresh();
        $entry = CompanyAccountingEntry::query()->firstOrFail();
        $journal = JournalEntry::query()->with('transactions.account')->firstOrFail();

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame($category->id, $entry->company_accounting_category_id);
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertTrue($statement->is_finalized);
        $this->assertSame($journal->id, $statement->journal_entry_id);
        $this->assertSame(CompanyAccountingEntry::class, $statement->source_type);
        $this->assertTrue($journal->is_balanced);
        $this->assertTrue($journal->transactions->contains(fn ($transaction): bool => $transaction->type === 'debit' && $transaction->account?->code === '5900' && (float) $transaction->amount === 890.00));
        $this->assertTrue($journal->transactions->contains(fn ($transaction): bool => $transaction->type === 'credit' && $transaction->account?->code === '1020' && (float) $transaction->amount === 890.00));

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $journal->reference]))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee('Other Expense')
            ->assertSee('890.00');
    }

    public function test_custom_expense_rejects_in_statement(): void
    {
        $statement = $this->statementEntry('in', 890.00);
        $category = $this->category('expense', 'Wrong Expense', $this->expenseAccount);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-company-accounting', $statement), [
                'type' => 'expense',
                'company_accounting_category_id' => $category->id,
            ])
            ->assertSessionHasErrors('statement');

        $this->assertSame(0, CompanyAccountingEntry::query()->count());
        $this->assertSame(0, JournalEntry::query()->count());
        $this->assertFalse($statement->fresh()->is_finalized);
    }

    public function test_statement_first_shop_petty_reuses_statement_funds_shop_and_finalizes_once(): void
    {
        $shop = Shop::factory()->create(['accounting_enabled' => true, 'accounting_mode' => 'owned']);
        $statement = $this->statementEntry('out', 2000.00, ['reference' => 'PETTY-STMT']);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-shop-petty', $statement), [
                'shop_uuid' => $shop->public_uuid,
                'notes' => 'Petty top-up from imported statement',
            ])
            ->assertRedirect();

        $statement->refresh();
        $transaction = ShopLedgerTransaction::query()->with('entryType')->firstOrFail();
        $snapshot = ShopDailyLedgerSnapshot::query()->where('shop_id', $shop->id)->firstOrFail();
        $journal = JournalEntry::query()->with('transactions.account')->firstOrFail();

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, ShopLedgerTransaction::query()->count());
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertSame('company_to_petty', $transaction->entryType?->code);
        $this->assertSame(2000.00, (float) $transaction->petty_delta);
        $this->assertSame(2000.00, (float) $snapshot->closing_petty);
        $this->assertTrue($statement->is_finalized);
        $this->assertSame($journal->id, $statement->journal_entry_id);
        $this->assertSame(ShopLedgerTransaction::class, $statement->source_type);
        $this->assertSame($transaction->id, $statement->source_id);
        $this->assertTrue($journal->is_balanced);
        $this->assertTrue($journal->transactions->contains(fn ($transaction): bool => $transaction->type === 'debit' && $transaction->account?->code === '1500' && (float) $transaction->amount === 2000.00));
        $this->assertTrue($journal->transactions->contains(fn ($transaction): bool => $transaction->type === 'credit' && $transaction->account?->code === '1020' && (float) $transaction->amount === 2000.00));

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-shop-petty', $statement), [
                'shop_uuid' => $shop->public_uuid,
                'notes' => 'Retry',
            ])
            ->assertSessionHasErrors('statement');

        $this->assertSame(1, ShopLedgerTransaction::query()->count());
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertSame(2000.00, (float) $snapshot->fresh()->closing_petty);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $journal->reference]))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee('Shop Petty Funding')
            ->assertSee('2,000.00');
    }

    public function test_shop_petty_rejects_in_statement(): void
    {
        $shop = Shop::factory()->create(['accounting_enabled' => true, 'accounting_mode' => 'owned']);
        $statement = $this->statementEntry('in', 2000.00);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-shop-petty', $statement), [
                'shop_uuid' => $shop->public_uuid,
            ])
            ->assertSessionHasErrors('statement');

        $this->assertSame(0, ShopLedgerTransaction::query()->count());
        $this->assertSame(0, JournalEntry::query()->count());
        $this->assertFalse($statement->fresh()->is_finalized);
    }

    public function test_statement_entry_and_reconciliation_stores_journal_entry_id_and_finalized_flags_upon_full_match(): void
    {
        $paymentRequest = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 1000.00,
            'payment_method' => 'online_upi',
            'status' => 'pending',
            'reconciliation_status' => 'unreconciled',
            'requested_by' => $this->admin->id,
        ]);

        $journalEntry = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-TEST-1000',
            'description' => 'Shop payment request #'.$paymentRequest->id,
            'source_type' => ShopInvoicePaymentRequest::class,
            'source_id' => $paymentRequest->id,
            'source_event' => 'client-balance-payment:'.$paymentRequest->id,
            'created_by' => $this->admin->id,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->bankAccount->id,
            'type' => 'debit',
            'amount' => 1000.00,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->arAccount->id,
            'type' => 'credit',
            'amount' => 1000.00,
        ]);

        $statementEntry = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 1000.00,
            'reference' => 'BANK-STMT-1000',
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $reconciliation = $this->service->reconcilePayment(
            $paymentRequest,
            [
                'company_account_id' => $this->companyAccount->id,
                'statement_entry_id' => $statementEntry->id,
                'journal_entry_id' => $journalEntry->id,
                'statement_amount' => 1000.00,
                'cleared_amount' => 1000.00,
                'difference_amount' => 0,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );

        $this->assertEquals($journalEntry->id, $reconciliation->journal_entry_id);
        $this->assertTrue($reconciliation->is_finalized);
        $this->assertNotNull($reconciliation->finalized_at);

        $statementEntry->refresh();
        $this->assertEquals($journalEntry->id, $statementEntry->journal_entry_id);
        $this->assertTrue($statementEntry->is_finalized);
        $this->assertEquals('reconciled', $statementEntry->status);
    }

    public function test_reconciliation_fails_if_journal_entry_is_unbalanced(): void
    {
        $paymentRequest = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 1000.00,
            'payment_method' => 'online_upi',
            'status' => 'pending',
            'reconciliation_status' => 'unreconciled',
            'requested_by' => $this->admin->id,
        ]);

        $unbalancedJe = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-UNBALANCED',
            'source_type' => ShopInvoicePaymentRequest::class,
            'source_id' => $paymentRequest->id,
            'source_event' => 'unbalanced:'.$paymentRequest->id,
            'created_by' => $this->admin->id,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $unbalancedJe->id,
            'account_id' => $this->bankAccount->id,
            'type' => 'debit',
            'amount' => 1000.00,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $unbalancedJe->id,
            'account_id' => $this->arAccount->id,
            'type' => 'credit',
            'amount' => 800.00, // Unbalanced!
        ]);

        $statementEntry = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 1000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->reconcilePayment(
            $paymentRequest,
            [
                'company_account_id' => $this->companyAccount->id,
                'statement_entry_id' => $statementEntry->id,
                'journal_entry_id' => $unbalancedJe->id,
                'statement_amount' => 1000.00,
                'cleared_amount' => 1000.00,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );
    }

    public function test_reconciliation_fails_if_direction_does_not_match_bank_cash_line(): void
    {
        $paymentRequest = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 1000.00,
            'payment_method' => 'online_upi',
            'status' => 'pending',
            'reconciliation_status' => 'unreconciled',
            'requested_by' => $this->admin->id,
        ]);

        $je = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-WRONG-DIR',
            'source_type' => ShopInvoicePaymentRequest::class,
            'source_id' => $paymentRequest->id,
            'source_event' => 'wrong-dir:'.$paymentRequest->id,
            'created_by' => $this->admin->id,
        ]);

        // Has Credit 1020 Bank instead of Debit 1020 for an IN statement entry
        JournalTransaction::query()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->arAccount->id,
            'type' => 'debit',
            'amount' => 1000.00,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->bankAccount->id,
            'type' => 'credit',
            'amount' => 1000.00,
        ]);

        $statementEntry = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in', // Deposit direction requires Debit 1020
            'amount' => 1000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->reconcilePayment(
            $paymentRequest,
            [
                'company_account_id' => $this->companyAccount->id,
                'statement_entry_id' => $statementEntry->id,
                'journal_entry_id' => $je->id,
                'statement_amount' => 1000.00,
                'cleared_amount' => 1000.00,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );
    }

    public function test_reconciliation_fails_if_journal_entry_already_linked_to_finalized_statement_entry(): void
    {
        $je = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-DUPLICATE-CHECK',
            'created_by' => $this->admin->id,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->bankAccount->id,
            'type' => 'debit',
            'amount' => 500.00,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->arAccount->id,
            'type' => 'credit',
            'amount' => 500.00,
        ]);

        // Existing finalized statement entry linked to JE
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'journal_entry_id' => $je->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 500.00,
            'status' => 'reconciled',
            'is_finalized' => true,
            'finalized_at' => now(),
            'imported_by' => $this->admin->id,
        ]);

        $paymentRequest2 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => 500.00,
            'payment_method' => 'online_upi',
            'status' => 'pending',
            'reconciliation_status' => 'unreconciled',
            'requested_by' => $this->admin->id,
        ]);

        $statementEntry2 = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 500.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->reconcilePayment(
            $paymentRequest2,
            [
                'company_account_id' => $this->companyAccount->id,
                'statement_entry_id' => $statementEntry2->id,
                'journal_entry_id' => $je->id,
                'statement_amount' => 500.00,
                'cleared_amount' => 500.00,
                'difference_action' => 'none',
            ],
            (int) $this->admin->id
        );
    }

    public function test_statement_journal_reconciliation_rejects_wrong_direction_account_and_amount(): void
    {
        $cashAccount = Account::query()->firstOrCreate(
            ['code' => '1010'],
            ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]
        );

        $incomingJournal = $this->createStatementJournal($this->bankAccount, 'debit', 1000.00);
        $outStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'out',
            'amount' => 1000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->assertValidationFailure(
            fn () => $this->service->reconcileStatementJournal($outStatement, $incomingJournal, 1000.00, (int) $this->admin->id),
            'does not contain a matching Credit Bank/Cash line'
        );

        $cashJournal = $this->createStatementJournal($cashAccount, 'debit', 1000.00);
        $bankStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 1000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->assertValidationFailure(
            fn () => $this->service->reconcileStatementJournal($bankStatement, $cashJournal, 1000.00, (int) $this->admin->id),
            'expected 1020'
        );

        $smallJournal = $this->createStatementJournal($this->bankAccount, 'debit', 500.00);
        $largeStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 1000.00,
            'status' => 'unmatched',
            'imported_by' => $this->admin->id,
        ]);

        $this->assertValidationFailure(
            fn () => $this->service->reconcileStatementJournal($largeStatement, $smallJournal, 1000.00, (int) $this->admin->id),
            'greater than the remaining journal balance'
        );
    }

    private function createStatementJournal(Account $cashBankAccount, string $cashBankType, float $amount): JournalEntry
    {
        $journalEntry = JournalEntry::query()->create([
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-GENERIC-'.Str::random(8),
            'created_by' => $this->admin->id,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cashBankAccount->id,
            'type' => $cashBankType,
            'amount' => $amount,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->arAccount->id,
            'type' => $cashBankType === 'debit' ? 'credit' : 'debit',
            'amount' => $amount,
        ]);

        return $journalEntry;
    }

    private function assertValidationFailure(callable $callback, string $expectedMessage): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($expectedMessage, $exception->getMessage());

            return;
        }

        $this->fail('Expected validation failure.');
    }

    private function journalEntry(string $reference, string $direction, float $amount): JournalEntry
    {
        $journalEntry = JournalEntry::query()->create([
            'entry_date' => today()->toDateString(),
            'reference' => $reference,
            'description' => $reference,
            'created_by' => $this->admin->id,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $direction === 'in' ? $this->bankAccount->id : $this->arAccount->id,
            'type' => 'debit',
            'amount' => $amount,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $direction === 'in' ? $this->arAccount->id : $this->bankAccount->id,
            'type' => 'credit',
            'amount' => $amount,
        ]);

        return $journalEntry;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function statementEntry(string $direction, float $amount, array $overrides = []): CompanyAccountStatementEntry
    {
        return CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->companyAccount->id,
            'transaction_date' => $overrides['transaction_date'] ?? today()->toDateString(),
            'direction' => $direction,
            'amount' => $amount,
            'reference' => $overrides['reference'] ?? 'STATEMENT-'.Str::random(8),
            'narration' => $overrides['narration'] ?? 'Statement row',
            'status' => $overrides['status'] ?? 'unmatched',
            'journal_entry_id' => $overrides['journal_entry_id'] ?? null,
            'is_finalized' => $overrides['is_finalized'] ?? false,
            'finalized_at' => $overrides['finalized_at'] ?? null,
            'matched_amount' => $overrides['matched_amount'] ?? 0,
            'imported_by' => $this->admin->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function shopPayment(float $amount, string $reference, array $overrides = []): ShopInvoicePaymentRequest
    {
        return ShopInvoicePaymentRequest::query()->create(array_merge([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'request_type' => 'custom',
            'payment_method' => 'online_upi',
            'payment_reference' => $reference,
            'payment_date' => today()->toDateString(),
            'requested_amount' => $amount,
            'status' => 'pending',
            'reconciliation_status' => 'floating',
            'reconciled_amount' => 0.00,
            'floating_amount' => $amount,
            'shop_advance_amount' => 0.00,
        ], $overrides));
    }

    private function category(string $type, string $name, Account $account): CompanyAccountingCategory
    {
        return CompanyAccountingCategory::query()->updateOrCreate(
            ['type' => $type, 'name' => $name],
            [
                'account_id' => $account->id,
                'is_active' => true,
            ],
        );
    }
}
