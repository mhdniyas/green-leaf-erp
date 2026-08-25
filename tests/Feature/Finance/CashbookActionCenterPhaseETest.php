<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\CompanyAccountingEntry;
use App\Models\CompanyPayableSettlement;
use App\Models\DirectCompanySale;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\PayrollPayment;
use App\Models\PurchaserCredit;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Models\VendorSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookActionCenterPhaseETest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CompanyAccount $bankCompanyAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->bankCompanyAccount = CompanyAccount::query()->create([
            'name' => 'Phase E Bank',
            'account_type' => 'bank',
            'bank_name' => 'Test Bank',
            'enabled' => true,
        ]);

        $this->seedAccounts();
    }

    #[DataProvider('inCandidateProvider')]
    public function test_unmatched_in_matches_existing_pending_transaction(string $sourceType, string $sourceEvent, string $expectedLabel): void
    {
        $journalEntry = $this->journal($sourceType, $sourceEvent, 'in', 750.00, 'PHASE-E-IN');
        $statement = $this->statement('in', 750.00, 'PHASE-E-IN');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.reconciliation', ['classify_statement' => $statement->public_uuid, 'company_account_uuid' => $this->bankCompanyAccount->public_uuid]))
            ->assertOk()
            ->assertSee('Match Existing Transaction')
            ->assertSee($expectedLabel)
            ->assertSee('PHASE-E-IN');

        $candidateRef = $this->candidateRefFromResponse($response->getContent());
        $this->assertNotSame((string) $journalEntry->id, $candidateRef);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.match-existing', $statement), [
                'candidate_ref' => $candidateRef,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertMatchedOnce($statement->fresh(), $journalEntry, $sourceType);
    }

    #[DataProvider('outCandidateProvider')]
    public function test_unmatched_out_matches_existing_pending_transaction(string $sourceType, string $sourceEvent, string $expectedLabel): void
    {
        $journalEntry = $this->journal($sourceType, $sourceEvent, 'out', 900.00, 'PHASE-E-OUT');
        $statement = $this->statement('out', 900.00, 'PHASE-E-OUT');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.reconciliation', ['classify_statement' => $statement->public_uuid, 'company_account_uuid' => $this->bankCompanyAccount->public_uuid]))
            ->assertOk()
            ->assertSee('Match Existing Transaction')
            ->assertSee($expectedLabel)
            ->assertSee('PHASE-E-OUT');

        $candidateRef = $this->candidateRefFromResponse($response->getContent());
        $this->assertNotSame((string) $journalEntry->id, $candidateRef);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.match-existing', $statement), [
                'candidate_ref' => $candidateRef,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertMatchedOnce($statement->fresh(), $journalEntry, $sourceType);
    }

    public function test_match_existing_rejects_wrong_direction_wrong_account_amount_mismatch_finalized_and_already_linked(): void
    {
        $outJournal = $this->journal(VendorSettlement::class, 'vendor_settlement', 'out', 500.00, 'RULE-OUT');
        $inStatement = $this->statement('in', 500.00, 'RULE-IN');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.match-existing', $inStatement), [
                'candidate_ref' => $this->secureJournalEntryKey($outJournal),
            ])
            ->assertSessionHasErrors();

        $cashStatement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => CompanyAccount::query()->create(['name' => 'Phase E Cash', 'account_type' => 'cash', 'enabled' => true])->id,
            'transaction_date' => '2026-08-22',
            'value_date' => '2026-08-22',
            'direction' => 'out',
            'amount' => 500.00,
            'reference' => 'WRONG-ACCOUNT',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.match-existing', $cashStatement), [
                'candidate_ref' => $this->secureJournalEntryKey($outJournal),
            ])
            ->assertSessionHasErrors();

        $amountMismatch = $this->statement('out', 400.00, 'WRONG-AMOUNT');
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.match-existing', $amountMismatch), [
                'candidate_ref' => $this->secureJournalEntryKey($outJournal),
            ])
            ->assertSessionHasErrors();

        $finalizedJournal = $this->journal(VendorSettlement::class, 'vendor_settlement', 'out', 300.00, 'FINALIZED');
        CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'journal_entry_id' => $finalizedJournal->id,
            'transaction_date' => '2026-08-22',
            'value_date' => '2026-08-22',
            'direction' => 'out',
            'amount' => 300.00,
            'reference' => 'FINALIZED',
            'status' => 'reconciled',
            'is_finalized' => true,
            'matched_amount' => 300.00,
            'imported_by' => $this->admin->id,
        ]);
        $newStatement = $this->statement('out', 300.00, 'FINALIZED-RETRY');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.match-existing', $newStatement), [
                'candidate_ref' => $this->secureJournalEntryKey($finalizedJournal),
            ])
            ->assertSessionHasErrors();

        $alreadyLinkedStatement = $this->statement('out', 250.00, 'LINKED');
        $alreadyLinkedStatement->update(['journal_entry_id' => $outJournal->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.match-existing', $alreadyLinkedStatement), [
                'candidate_ref' => $this->secureJournalEntryKey($outJournal),
            ])
            ->assertSessionHasErrors();
    }

    public function test_duplicate_submit_reuses_statement_and_does_not_duplicate_journal_or_source_movement(): void
    {
        $journalEntry = $this->journal(PurchaserCredit::class, 'purchaser_funding', 'out', 1200.00, 'DUPLICATE-SAFE');
        $systemPendingMovement = CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'journal_entry_id' => $journalEntry->id,
            'transaction_date' => '2026-08-22',
            'value_date' => '2026-08-22',
            'direction' => 'out',
            'amount' => 1200.00,
            'reference' => 'SYSTEM-PENDING',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'source_type' => PurchaserCredit::class,
            'source_id' => 1,
            'imported_by' => $this->admin->id,
        ]);
        $importedStatement = $this->statement('out', 1200.00, 'DUPLICATE-SAFE');

        $payload = ['candidate_ref' => $this->secureJournalEntryKey($journalEntry)];

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.match-existing', $importedStatement), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.match-existing', $importedStatement->fresh()), $payload)
            ->assertSessionHasErrors();

        $this->assertSame(1, JournalEntry::query()->whereKey($journalEntry->id)->count());
        $this->assertSame(2, CompanyAccountStatementEntry::query()->count());
        $this->assertTrue((bool) $importedStatement->fresh()->is_finalized);
        $this->assertSame('superseded', $systemPendingMovement->fresh()->status);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => 'DUPLICATE-SAFE']))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee('Purchaser Funding')
            ->assertSee('1,200.00');
    }

    public function test_match_existing_candidate_search_includes_amount(): void
    {
        $journalEntry = $this->journal(PurchaserCredit::class, 'purchaser_funding', 'out', 100000.00, 'PURCH-FUND-SEARCH');
        $statement = $this->statement('out', 100000.00, 'NEFT-SEARCH');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.reconciliation', [
                'classify_statement' => $statement->public_uuid,
                'company_account_uuid' => $this->bankCompanyAccount->public_uuid,
                'search' => '100000',
            ]))
            ->assertOk()
            ->assertSee('Match Existing Transaction')
            ->assertSee('Purchaser Funding')
            ->assertSee('PURCH-FUND-SEARCH');

        $candidateRef = $this->candidateRefFromResponse($response->getContent());
        $this->assertNotSame((string) $journalEntry->id, $candidateRef);
    }

    public static function inCandidateProvider(): array
    {
        return [
            'shop payment' => [ShopInvoicePaymentRequest::class, 'payment_request', 'Shop Payment'],
            'direct company sale' => [DirectCompanySale::class, 'direct_company_sale', 'Direct Company Sale'],
            'other income' => [CompanyAccountingEntry::class, 'final', 'Other Income'],
        ];
    }

    public static function outCandidateProvider(): array
    {
        return [
            'vendor settlement' => [VendorSettlement::class, 'vendor_settlement', 'Vendor Settlement'],
            'purchaser funding' => [PurchaserCredit::class, 'purchaser_funding', 'Purchaser Funding'],
            'shop petty funding' => [ShopLedgerTransaction::class, 'company_to_petty', 'Shop Petty Funding'],
            'other expense' => [CompanyAccountingEntry::class, 'final', 'Other Expense'],
            'salary payment' => [PayrollPayment::class, 'salary_payment', 'Salary Payment'],
            'salary advance' => [PayrollPayment::class, 'salary_advance', 'Salary Advance'],
            'company payable' => [CompanyPayableSettlement::class, 'company_payable', 'Company Payable'],
        ];
    }

    private function assertMatchedOnce(CompanyAccountStatementEntry $statement, JournalEntry $journalEntry, string $sourceType): void
    {
        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, JournalEntry::query()->whereKey($journalEntry->id)->count());
        $this->assertSame($journalEntry->id, $statement->journal_entry_id);
        $this->assertSame($sourceType, $statement->source_type);
        $this->assertTrue((bool) $statement->is_finalized);
        $this->assertSame('reconciled', $statement->status);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $journalEntry->reference]))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee(number_format((float) $statement->amount, 2));
    }

    private function statement(string $direction, float $amount, string $reference): CompanyAccountStatementEntry
    {
        return CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $this->bankCompanyAccount->id,
            'transaction_date' => '2026-08-22',
            'value_date' => '2026-08-22',
            'direction' => $direction,
            'amount' => $amount,
            'reference' => $reference,
            'narration' => 'Imported Phase E statement',
            'source' => 'bank_import',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);
    }

    private function journal(string $sourceType, string $sourceEvent, string $direction, float $amount, string $reference): JournalEntry
    {
        $journalEntry = JournalEntry::query()->create([
            'entry_date' => '2026-08-22',
            'reference' => $reference,
            'description' => $reference.' existing pending transaction',
            'source_type' => $sourceType,
            'source_id' => random_int(1000, 9999),
            'source_event' => $sourceEvent,
            'created_by' => $this->admin->id,
        ]);

        if ($direction === 'in') {
            JournalTransaction::query()->create(['journal_entry_id' => $journalEntry->id, 'account_id' => $this->account('1020')->id, 'type' => 'debit', 'amount' => $amount]);
            JournalTransaction::query()->create(['journal_entry_id' => $journalEntry->id, 'account_id' => $this->account('4100')->id, 'type' => 'credit', 'amount' => $amount]);
        } else {
            JournalTransaction::query()->create(['journal_entry_id' => $journalEntry->id, 'account_id' => $this->account('5900')->id, 'type' => 'debit', 'amount' => $amount]);
            JournalTransaction::query()->create(['journal_entry_id' => $journalEntry->id, 'account_id' => $this->account('1020')->id, 'type' => 'credit', 'amount' => $amount]);
        }

        return $journalEntry->fresh(['transactions.account', 'statementEntries']);
    }

    private function secureJournalEntryKey(JournalEntry $journalEntry): string
    {
        return rtrim(strtr(base64_encode(Crypt::encryptString('journal-entry:'.$journalEntry->getKey())), '+/', '-_'), '=');
    }

    private function candidateRefFromResponse(string $content): string
    {
        preg_match('/name="candidate_ref" value="([^"]+)"/', $content, $matches);

        $this->assertArrayHasKey(1, $matches, 'Expected rendered match-existing candidate_ref input.');

        return $matches[1];
    }

    private function account(string $code): Account
    {
        return Account::query()->where('code', $code)->firstOrFail();
    }

    private function seedAccounts(): void
    {
        foreach ([
            ['code' => '1010', 'name' => 'Cash on Hand', 'type' => 'asset'],
            ['code' => '1020', 'name' => 'Bank Account', 'type' => 'asset'],
            ['code' => '1300', 'name' => 'Purchaser Advances', 'type' => 'asset'],
            ['code' => '1500', 'name' => 'Shop Petty Advances', 'type' => 'asset'],
            ['code' => '1600', 'name' => 'Employee Advances', 'type' => 'asset'],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '2200', 'name' => 'Company Payable to Shops', 'type' => 'liability'],
            ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue'],
            ['code' => '5900', 'name' => 'Other Expense', 'type' => 'expense'],
        ] as $account) {
            Account::query()->firstOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'is_active' => true,
                    'parent_id' => null,
                ],
            );
        }
    }
}
