<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\CompanyPayableSettlement;
use App\Models\DirectCompanySale;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Services\Cashbook\ReconciliationAutoMatchSuggestionService;
use App\Services\Cashbook\ReconciliationTransactionQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ReconciliationLandingPageV2Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CompanyAccount $bank;

    private CompanyAccount $cash;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->shop = Shop::factory()->create(['name' => 'Shadhuli']);
        $this->bank = CompanyAccount::query()->create(['name' => 'HDFC Bank', 'account_type' => 'bank', 'enabled' => true, 'is_default' => true]);
        $this->cash = CompanyAccount::query()->create(['name' => 'Main Cash', 'account_type' => 'cash', 'enabled' => true]);

        foreach ([['1010', 'Cash in Hand', 'asset'], ['1020', 'Bank Account', 'asset'], ['1100', 'Accounts Receivable', 'asset'], ['1300', 'Purchaser Advances', 'asset'], ['1500', 'Shop Petty Advances', 'asset'], ['2100', 'Accounts Payable', 'liability'], ['2200', 'Company Payable', 'liability'], ['4100', 'Sales Revenue', 'revenue'], ['5100', 'Purchase Expense', 'expense'], ['5900', 'Company Expense', 'expense']] as [$code, $name, $type]) {
            Account::query()->firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true]);
        }
    }

    public function test_default_page_is_transaction_first_and_excludes_raw_statement_rows(): void
    {
        $this->shopPayment('SHOP-IN-REF', 1000);
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bank->id,
            'transaction_date' => '2026-08-27',
            'direction' => 'in',
            'amount' => 1000,
            'reference' => 'RAW-BANK-ONLY',
            'source' => 'pdf_import',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', ['month' => '2026-08']));

        $response->assertOk()
            ->assertSeeText('Transaction Queue')
            ->assertSeeText('Needs Review')
            ->assertSeeText('Record Transaction')
            ->assertSeeText('Manage Statements')
            ->assertSeeText('Shop Payment')
            ->assertSeeText('SHOP-IN-REF')
            ->assertDontSeeText('Quick Actions')
            ->assertDontSeeText('Selected Statement')
            ->assertDontSeeText('What is this transaction?')
            ->assertDontSeeText('Reconciled History')
            ->assertDontSeeText('RAW-BANK-ONLY');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', ['workspace' => 'needs_reconciliation', 'month' => '2026-08']))
            ->assertOk()
            ->assertSeeText('Transaction Queue')
            ->assertDontSeeText('Needs Reconciliation');
    }

    public function test_direction_type_status_account_period_search_and_pagination_filters_work(): void
    {
        $this->shopPayment('SHOP-IN-REF', 1000);
        $this->directSale('DIRECT-IN-REF', 1100);
        $this->companyAccounting('income', 'OTHER-IN-REF', 1200);
        $purchaserFunding = $this->purchaserFunding('PURCH-OUT-REF', 1300);
        $this->vendorSettlement('VENDOR-OUT-REF', 1400);
        $this->companyPayable('PAYABLE-OUT-REF', 1500);
        $this->companyAccounting('expense', 'EXP-OUT-REF', 1600);
        $this->pettyFunding('PETTY-OUT-REF', 1700);
        $this->payroll('PAYROLL-OUT-REF', 1800);
        $this->markReconciled($purchaserFunding, 'MATCHED-PURCH-STMT');

        for ($i = 1; $i <= 26; $i++) {
            $this->shopPayment('PAGE-'.$i, 2000 + $i);
        }

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', ['month' => '2026-08', 'direction' => 'in']))
            ->assertOk()
            ->assertSeeText('Shop Payments')
            ->assertSeeText('Direct Sales')
            ->assertSeeText('Other Income')
            ->assertDontSeeText('Purchaser Funding');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', ['month' => '2026-08', 'direction' => 'out']))
            ->assertOk()
            ->assertSeeText('Purchaser Funding')
            ->assertSeeText('Vendor Payments')
            ->assertSeeText('Company Payables')
            ->assertSeeText('Expenses')
            ->assertSeeText('Petty Funding')
            ->assertSeeText('Payroll')
            ->assertDontSeeText('Direct Sales');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', ['month' => '2026-08', 'direction' => 'out', 'type' => 'vendor_payment']))
            ->assertOk()
            ->assertSeeText('VENDOR-OUT-REF')
            ->assertDontSeeText('PURCH-OUT-REF');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', ['month' => '2026-08', 'status' => 'RECONCILED']))
            ->assertOk()
            ->assertSeeText('✓ Reconciled')
            ->assertSeeText('MATCHED-PURCH-STMT')
            ->assertDontSeeText('SHOP-IN-REF');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', ['month' => '2026-08', 'status' => 'SUGGESTED']))
            ->assertOk()
            ->assertSeeText('No ERP transactions for this filter.');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', ['month' => '2026-08', 'company_account_id' => $this->cash->id]))
            ->assertOk()
            ->assertSeeText('No ERP transactions for this filter.')
            ->assertDontSeeText('SHOP-IN-REF');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', ['month' => '2026-08', 'search' => 'EXP-OUT-REF']))
            ->assertOk()
            ->assertSeeText('EXP-OUT-REF')
            ->assertDontSeeText('SHOP-IN-REF');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', ['month' => '2026-07']))
            ->assertOk()
            ->assertDontSeeText('SHOP-IN-REF');

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', ['month' => '2026-08']))
            ->assertOk()
            ->assertSee('page=2', false);
    }

    public function test_statement_workspace_and_existing_purchaser_reconcile_action_still_work_without_accounting_mutation(): void
    {
        $credit = $this->purchaserFunding('PURCH-RECON-ACTION', 25000);
        $statement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bank->id,
            'transaction_date' => '2026-08-27',
            'direction' => 'out',
            'amount' => 25000,
            'reference' => 'BANK-STMT-PURCH',
            'source' => 'imported',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        $journalCount = JournalEntry::query()->count();
        $bankBalance = (float) $this->bank->fresh()->current_balance;

        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', ['workspace' => 'statements', 'month' => '2026-08']))
            ->assertOk()
            ->assertSeeText('Statement Management')
            ->assertDontSeeText('Quick Actions')
            ->assertSeeText('BANK-STMT-PURCH');

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.purchasers.funding.match-statement', [
            'purchaser' => $credit->purchaser->public_uuid,
            'credit' => $credit->id,
        ]), ['statement_entry_id' => $statement->id])->assertRedirect();

        $this->assertSame($journalCount, JournalEntry::query()->count());
        $this->assertSame($bankBalance, (float) $this->bank->fresh()->current_balance);
        $this->assertTrue($statement->fresh()->is_finalized);
    }

    public function test_unauthorized_user_is_blocked(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get(route('admin.cashbook.finance.reconciliation'))
            ->assertRedirect();
    }

    public function test_suggestions_require_an_eligible_unique_candidate_and_preserve_ambiguity(): void
    {
        $this->statement('in', 9100, '2026-08-27', $this->bank, 'EXACT-ONE');
        $this->statement('in', 9200, '2026-08-27', $this->bank, 'EXACT-TWO-A');
        $this->statement('in', 9200, '2026-08-27', $this->bank, 'EXACT-TWO-B');
        $this->statement('out', 9300, '2026-08-26', $this->bank, 'LIKELY-ONE');
        $this->statement('in', 9400, '2026-08-27', $this->cash, 'WRONG-ACCOUNT');
        $this->statement('out', 9500, '2026-08-27', $this->bank, 'WRONG-DIRECTION');
        $this->statement('in', 9601, '2026-08-27', $this->bank, 'WRONG-AMOUNT');

        $suggestions = app(ReconciliationAutoMatchSuggestionService::class)->suggest(collect([
            $this->suggestionRow('in', 9100, 'UNIQUE-REF'),
            $this->suggestionRow('in', 9200, 'AMBIGUOUS-REF'),
            $this->suggestionRow('out', 9300, 'LIKELY-REF'),
            $this->suggestionRow('in', 9400, 'ACCOUNT-REF'),
            $this->suggestionRow('in', 9500, 'DIRECTION-REF'),
            $this->suggestionRow('in', 9600, 'AMOUNT-REF'),
        ]), 10);

        $this->assertSame('SUGGESTED', $suggestions[0]->reconciliation_status);
        $this->assertSame('high', $suggestions[0]->suggestion['confidence']);
        $this->assertSame('NEEDS_REVIEW', $suggestions[1]->reconciliation_status);
        $this->assertSame('SUGGESTED', $suggestions[2]->reconciliation_status);
        $this->assertSame('likely', $suggestions[2]->suggestion['confidence']);
        $this->assertSame('NO_MATCH', $suggestions[3]->suggestion['status']);
        $this->assertSame('NO_MATCH', $suggestions[4]->suggestion['status']);
        $this->assertSame('NO_MATCH', $suggestions[5]->suggestion['status']);
    }

    public function test_suggested_tab_uses_the_same_auto_match_read_model(): void
    {
        $this->directSale('SUGGESTED-DIRECT-SALE', 9700);
        $this->statement('in', 9700, '2026-08-27', $this->bank, 'SUGGESTED-STATEMENT');

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.reconciliation', ['month' => '2026-08', 'status' => 'SUGGESTED']))
            ->assertOk()
            ->assertSeeText('SUGGESTED-DIRECT-SALE')
            ->assertSeeText('HIGH Confidence')
            ->assertSeeText('Confirm')
            ->assertSeeText('Change Match');
    }

    public function test_find_match_renders_eligible_statement_candidates_for_a_specific_transaction(): void
    {
        $this->directSale('FIND-MATCH-DIRECT-SALE', 9800);
        $this->statement('in', 9800, '2026-08-27', $this->bank, 'FIND-MATCH-STATEMENT');
        $rows = app(ReconciliationTransactionQuery::class)->paginate(
            Request::create('/admin/cashbook/finance/reconciliation', 'GET', ['month' => '2026-08']),
            '2026-08-01',
            '2026-08-31',
        );
        $transaction = $rows->firstWhere('reference', 'FIND-MATCH-DIRECT-SALE');

        $this->assertNotNull($transaction);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.reconciliation', [
                'workspace' => 'needs_reconciliation',
                'find_kind' => 'journal',
                'find_ref' => $transaction->source_ref,
                'month' => '2026-08',
            ]))
            ->assertOk()
            ->assertSeeText('Best Matches')
            ->assertSeeText('FIND-MATCH-STATEMENT')
            ->assertSeeText('Match');
    }

    public function test_suggested_single_confirm_reuses_canonical_match_without_new_journal_or_balance_changes(): void
    {
        $this->directSale('SINGLE-CONFIRM', 9900);
        $statement = $this->statement('in', 9900, '2026-08-27', $this->bank, 'SINGLE-CONFIRM-STATEMENT');
        $journalCount = JournalEntry::query()->count();
        $balance = (float) $this->bank->fresh()->current_balance;

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.confirm-suggestion', $statement), [
                'candidate_ref' => $this->transactionReference('SINGLE-CONFIRM'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Suggested statement match confirmed.');

        $this->assertTrue($statement->fresh()->is_finalized);
        $this->assertSame($journalCount, JournalEntry::query()->count());
        $this->assertSame($balance, (float) $this->bank->fresh()->current_balance);
    }

    public function test_bulk_confirm_keeps_valid_matches_when_a_selected_statement_is_stale(): void
    {
        $this->directSale('BULK-CONFIRM-A', 9910);
        $this->directSale('BULK-CONFIRM-B', 9920);
        $available = $this->statement('in', 9910, '2026-08-27', $this->bank, 'BULK-AVAILABLE');
        $stale = $this->statement('in', 9920, '2026-08-27', $this->bank, 'BULK-STALE');
        $stale->update(['is_finalized' => true, 'status' => 'reconciled']);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.confirm-suggestions'), [
                'matches' => [
                    ['statement_uuid' => $available->public_uuid, 'candidate_ref' => $this->transactionReference('BULK-CONFIRM-A')],
                    ['statement_uuid' => $stale->public_uuid, 'candidate_ref' => $this->transactionReference('BULK-CONFIRM-B')],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Confirmed 1 suggested match.')
            ->assertSessionHas('reconciliation_failures');

        $this->assertTrue($available->fresh()->is_finalized);
        $this->assertSame('reconciled', $stale->fresh()->status);
    }

    private function transactionReference(string $reference): string
    {
        $rows = app(ReconciliationTransactionQuery::class)->paginate(
            Request::create('/admin/cashbook/finance/reconciliation', 'GET', ['month' => '2026-08']),
            '2026-08-01',
            '2026-08-31',
        );
        $transaction = $rows->firstWhere('reference', $reference);

        $this->assertNotNull($transaction);

        return $transaction->source_ref;
    }

    private function suggestionRow(string $direction, float $amount, string $reference): object
    {
        return (object) [
            'reconciliation_status' => 'NEEDS_REVIEW',
            'company_account_id' => $this->bank->id,
            'company_account_name' => $this->bank->name,
            'direction' => $direction,
            'amount' => $amount,
            'transaction_date' => '2026-08-27',
            'reference' => $reference,
            'party_name' => 'Test Party',
            'description' => 'Test transaction',
        ];
    }

    private function statement(string $direction, float $amount, string $date, CompanyAccount $account, string $reference): CompanyAccountStatementEntry
    {
        return CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $account->id,
            'transaction_date' => $date,
            'direction' => $direction,
            'amount' => $amount,
            'reference' => $reference,
            'source' => 'imported',
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);
    }

    private function shopPayment(string $reference, float $amount): ShopInvoicePaymentRequest
    {
        return ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_amount' => $amount,
            'floating_amount' => $amount,
            'reconciled_amount' => 0,
            'payment_method' => 'bank_transfer',
            'payment_reference' => $reference,
            'payment_date' => '2026-08-27',
            'status' => 'pending',
            'reconciliation_status' => 'floating',
        ]);
    }

    private function directSale(string $reference, float $amount): DirectCompanySale
    {
        $sale = DirectCompanySale::query()->create([
            'request_uuid' => (string) Str::uuid(),
            'business_date' => '2026-08-27',
            'customer_name' => 'Walk In Customer',
            'sale_status' => 'confirmed',
            'amount' => $amount,
            'payment_method' => 'bank',
            'company_account_id' => $this->bank->id,
            'reference' => $reference,
            'created_by' => $this->admin->id,
            'reconciliation_status' => 'pending',
            'is_finalized' => false,
        ]);
        $sale->update(['journal_entry_id' => $this->journal(DirectCompanySale::class, $sale->id, 'direct-company-sale', 'in', $amount, $reference)->id]);

        return $sale->fresh();
    }

    private function companyAccounting(string $type, string $reference, float $amount): CompanyAccountingEntry
    {
        $account = Account::query()->where('type', $type === 'income' ? 'revenue' : 'expense')->firstOrFail();
        $category = CompanyAccountingCategory::query()->create(['type' => $type, 'name' => $reference, 'account_id' => $account->id, 'is_active' => true]);
        $entry = CompanyAccountingEntry::query()->create([
            'company_accounting_category_id' => $category->id,
            'company_account_id' => $this->bank->id,
            'type' => $type,
            'business_date' => '2026-08-27',
            'payment_mode' => 'bank',
            'amount' => $amount,
            'reference' => $reference,
            'description' => $reference,
            'status' => 'final',
            'created_by' => $this->admin->id,
        ]);
        $entry->update(['journal_entry_id' => $this->journal(CompanyAccountingEntry::class, $entry->id, 'final', $type === 'income' ? 'in' : 'out', $amount, $reference)->id]);

        return $entry->fresh();
    }

    private function purchaserFunding(string $reference, float $amount): PurchaserCredit
    {
        $purchaser = User::factory()->create(['name' => 'Purchaser '.$reference]);
        $purchaser->assignRole('purchaser');
        $credit = PurchaserCredit::query()->create([
            'purchaser_id' => $purchaser->id,
            'type' => 'in',
            'amount' => $amount,
            'description' => $reference,
            'payment_source' => 'Bank',
            'company_account_id' => $this->bank->id,
            'reference' => $reference,
            'business_date' => '2026-08-27',
            'created_by' => $this->admin->id,
        ]);
        $this->journal(PurchaserCredit::class, $credit->id, 'purchaser_funding', 'out', $amount, $reference);

        return $credit->fresh('purchaser');
    }

    private function vendorSettlement(string $reference, float $amount): VendorSettlement
    {
        $supplier = Supplier::factory()->create(['name' => 'Vendor '.$reference]);
        $settlement = VendorSettlement::query()->create([
            'supplier_id' => $supplier->id,
            'actual_payment_amount' => $amount,
            'settlement_discount_amount' => 0,
            'vendor_advance_used_amount' => 0,
            'new_vendor_advance_amount' => 0,
            'company_account_id' => $this->bank->id,
            'payment_method' => 'Bank',
            'payment_date' => '2026-08-27',
            'reference' => $reference,
            'status' => 'approved',
            'reconciliation_status' => 'pending',
            'is_finalized' => false,
            'created_by' => $this->admin->id,
        ]);
        $settlement->update(['journal_entry_id' => $this->journal(VendorSettlement::class, $settlement->id, 'vendor-settlement:'.$settlement->id, 'out', $amount, $reference)->id]);

        return $settlement->fresh();
    }

    private function companyPayable(string $reference, float $amount): CompanyPayableSettlement
    {
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-27',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);
        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $this->shop->id,
            'type' => 'expense',
            'name' => $reference,
            'is_active' => true,
        ]);
        $line = ShopAccountingEntryLine::query()->create([
            'shop_accounting_entry_id' => $entry->id,
            'shop_accounting_category_id' => $category->id,
            'type' => 'expense',
            'amount' => $amount,
            'description' => $reference,
        ]);
        $settlement = CompanyPayableSettlement::query()->create([
            'shop_accounting_entry_line_id' => $line->id,
            'shop_id' => $this->shop->id,
            'settlement_type' => CompanyPayableSettlement::TypeDirectCompanyPayment,
            'amount' => $amount,
            'settlement_date' => '2026-08-27',
            'payment_account_id' => $this->bank->id,
            'reference' => $reference,
            'created_by' => $this->admin->id,
        ]);
        $settlement->update(['journal_entry_id' => $this->journal(CompanyPayableSettlement::class, $settlement->id, 'direct_company_payment', 'out', $amount, $reference)->id]);

        return $settlement->fresh();
    }

    private function pettyFunding(string $reference, float $amount): ShopLedgerTransaction
    {
        $type = LedgerEntryType::query()->create(['code' => $reference, 'name' => $reference, 'category' => 'expense', 'active' => true]);
        $transaction = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-27',
            'entry_type_id' => $type->id,
            'amount' => $amount,
            'direction' => 'in',
            'funding_source' => 'company',
            'petty_direction' => 'in',
            'settlement_direction' => 'none',
            'company_pending_direction' => 'out',
            'company_account_id' => $this->bank->id,
            'status' => 'approved',
            'notes' => $reference,
        ]);
        $this->journal(ShopLedgerTransaction::class, $transaction->id, 'shop_petty_funding', 'out', $amount, $reference);

        return $transaction->fresh();
    }

    private function payroll(string $reference, float $amount): PayrollPayment
    {
        $employee = Employee::factory()->create(['name' => 'Employee '.$reference]);
        $run = PayrollRun::query()->first() ?? PayrollRun::factory()->create();
        $item = PayrollRunItem::factory()->create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id]);
        $payment = PayrollPayment::query()->create([
            'payroll_run_id' => $run->id,
            'payroll_run_item_id' => $item->id,
            'employee_id' => $employee->id,
            'shop_id' => $this->shop->id,
            'company_account_id' => $this->bank->id,
            'reference' => $reference,
            'paid_by' => $this->admin->id,
            'paid_on' => '2026-08-27',
            'amount' => $amount,
            'payment_method' => 'bank',
            'payment_type' => 'full',
            'fund_source' => 'company_bank',
            'notes' => $reference,
        ]);
        $payment->update(['journal_entry_id' => $this->journal(PayrollPayment::class, $payment->id, 'salary_payment', 'out', $amount, $reference)->id]);

        return $payment->fresh();
    }

    private function journal(string $sourceType, int $sourceId, string $sourceEvent, string $direction, float $amount, string $reference): JournalEntry
    {
        $journal = JournalEntry::query()->create([
            'entry_date' => '2026-08-27',
            'reference' => $reference,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_event' => $sourceEvent,
            'description' => $reference,
            'created_by' => $this->admin->id,
        ]);
        JournalTransaction::query()->create([
            'journal_entry_id' => $journal->id,
            'account_id' => Account::query()->where('code', $direction === 'in' ? '1020' : '5100')->value('id'),
            'type' => 'debit',
            'amount' => $amount,
        ]);
        JournalTransaction::query()->create([
            'journal_entry_id' => $journal->id,
            'account_id' => Account::query()->where('code', $direction === 'in' ? '4100' : '1020')->value('id'),
            'type' => 'credit',
            'amount' => $amount,
        ]);

        return $journal;
    }

    private function markReconciled(PurchaserCredit $credit, string $reference): void
    {
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bank->id,
            'journal_entry_id' => JournalEntry::query()->where('source_type', PurchaserCredit::class)->where('source_id', $credit->id)->value('id'),
            'transaction_date' => '2026-08-27',
            'direction' => 'out',
            'amount' => $credit->amount,
            'reference' => $reference,
            'source' => 'imported',
            'source_type' => PurchaserCredit::class,
            'source_id' => $credit->id,
            'status' => 'reconciled',
            'matched_amount' => $credit->amount,
            'is_finalized' => true,
            'finalized_at' => now(),
        ]);
    }
}
