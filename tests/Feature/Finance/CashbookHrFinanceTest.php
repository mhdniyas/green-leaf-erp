<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeCategory;
use App\Models\JournalEntry;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\HR\EmployeeAdvanceService;
use App\Services\HR\PayrollPaymentService;
use App\Services\HR\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookHrFinanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CompanyAccount $bankCompanyAccount;

    private CompanyAccount $cashCompanyAccount;

    private PayrollPaymentService $payrollPaymentService;

    private PayrollService $payrollService;

    private CompanyPaymentReconciliationService $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');

        $this->bankCompanyAccount = CompanyAccount::query()->create([
            'name' => 'Payroll Bank',
            'account_type' => 'bank',
            'bank_name' => 'Test Bank',
            'enabled' => true,
        ]);

        $this->cashCompanyAccount = CompanyAccount::query()->create([
            'name' => 'Payroll Cash',
            'account_type' => 'cash',
            'enabled' => true,
        ]);

        $this->seedAccounts();

        $this->payrollPaymentService = app(PayrollPaymentService::class);
        $this->payrollService = app(PayrollService::class);
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
    }

    public function test_payroll_finalization_is_accrual_only_without_company_statement_movement(): void
    {
        $payrollRunItem = $this->makePayrollRunItem(greenLeafAmount: 1200.00);

        $finalizedRun = $this->payrollService->finalize($payrollRunItem->payrollRun, (int) $this->admin->id);

        $this->assertSame(0, CompanyAccountStatementEntry::query()->count());

        $journalEntry = $finalizedRun->journalEntry->load('transactions.account');
        $this->assertTrue($journalEntry->is_balanced);
        $this->assertTrue($journalEntry->transactions->contains(fn ($transaction): bool => $transaction->type === 'debit' && $transaction->account?->code === '5700' && (float) $transaction->amount === 1200.00));
        $this->assertTrue($journalEntry->transactions->contains(fn ($transaction): bool => $transaction->type === 'credit' && $transaction->account?->code === '2300' && (float) $transaction->amount === 1200.00));
        $this->assertFalse($journalEntry->transactions->contains(fn ($transaction): bool => $transaction->type === 'credit' && in_array($transaction->account?->code, ['1010', '1020'], true)));
    }

    public function test_actual_salary_payment_creates_one_pending_out_statement_and_balanced_journal(): void
    {
        $payrollRunItem = $this->makePayrollRunItem(greenLeafAmount: 1500.00);
        $this->payrollService->finalize($payrollRunItem->payrollRun, (int) $this->admin->id);

        $payment = $this->recordSalaryPayment($payrollRunItem->fresh(), 500.00);

        $this->assertSame(1, PayrollPayment::query()->count());
        $this->assertSame(2, JournalEntry::query()->count());
        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());

        $movement = $payment->cashbookMovement()->firstOrFail();
        $this->assertSame('out', $movement->direction);
        $this->assertSame(500.00, (float) $movement->amount);
        $this->assertFalse((bool) $movement->is_finalized);
        $this->assertSame($this->bankCompanyAccount->id, $movement->company_account_id);

        $journalEntry = $payment->journalEntry->load('transactions.account');
        $this->assertTrue($journalEntry->is_balanced);
        $this->assertTrue($journalEntry->transactions->contains(fn ($transaction): bool => $transaction->type === 'debit' && $transaction->account?->code === '2300' && (float) $transaction->amount === 500.00));
        $this->assertTrue($journalEntry->transactions->contains(fn ($transaction): bool => $transaction->type === 'credit' && $transaction->account?->code === '1020' && (float) $transaction->amount === 500.00));

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $payment->journalEntry->reference]))
            ->assertOk()
            ->assertSee('0 entries')
            ->assertDontSee('Salary Payment');
    }

    public function test_reconciled_salary_payment_appears_once_in_approved_transactions(): void
    {
        $payrollRunItem = $this->makePayrollRunItem(greenLeafAmount: 1800.00);
        $this->payrollService->finalize($payrollRunItem->payrollRun, (int) $this->admin->id);
        $payment = $this->recordSalaryPayment($payrollRunItem->fresh(), 800.00);

        $this->finalizeMovement($payment);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $payment->journalEntry->reference]))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee('Salary Payment')
            ->assertSee('800.00');
    }

    public function test_duplicate_salary_payout_request_is_idempotent_and_overpayment_is_rejected(): void
    {
        $payrollRunItem = $this->makePayrollRunItem(greenLeafAmount: 1000.00);
        $this->payrollService->finalize($payrollRunItem->payrollRun, (int) $this->admin->id);
        $requestUuid = (string) Str::uuid();

        $firstPayment = $this->recordSalaryPayment($payrollRunItem->fresh(), 400.00, $requestUuid);
        $secondPayment = $this->recordSalaryPayment($payrollRunItem->fresh(), 400.00, $requestUuid);

        $this->assertTrue($firstPayment->is($secondPayment));
        $this->assertSame(1, PayrollPayment::query()->count());
        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(2, JournalEntry::query()->count());
        $this->assertSame(600.00, $payrollRunItem->fresh(['payments'])->remainingGreenLeafAmount());

        $this->expectException(RuntimeException::class);

        $this->recordSalaryPayment($payrollRunItem->fresh(), 700.00);
    }

    public function test_partial_salary_payment_follows_existing_remaining_balance_rule(): void
    {
        $payrollRunItem = $this->makePayrollRunItem(greenLeafAmount: 1000.00);
        $this->payrollService->finalize($payrollRunItem->payrollRun, (int) $this->admin->id);

        $this->recordSalaryPayment($payrollRunItem->fresh(), 300.00);
        $this->recordSalaryPayment($payrollRunItem->fresh(), 700.00);

        $this->assertSame(2, PayrollPayment::query()->count());
        $this->assertSame(2, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(0.00, $payrollRunItem->fresh(['payments'])->remainingGreenLeafAmount());
    }

    public function test_advance_review_preserves_shop_staff_payment_without_company_statement_movement(): void
    {
        $advanceRequest = $this->makePendingAdvanceRequest(approvedAmount: 600.00);

        $reviewedAdvance = app(EmployeeAdvanceService::class)->review(
            $advanceRequest,
            'approve',
            600.00,
            $this->admin,
            'Approved for shop cash payout.',
        );

        $this->assertSame('approved', $reviewedAdvance->status);
        $this->assertSame(1, ShopStaffPayment::query()->count());
        $this->assertNotNull($reviewedAdvance->shop_staff_payment_id);
        $this->assertSame(0, CompanyAccountStatementEntry::query()->count());
    }

    public function test_company_funded_advance_payout_creates_one_pending_out_statement_and_balanced_journal(): void
    {
        $advanceRequest = $this->makePendingAdvanceRequest(approvedAmount: 700.00);
        app(EmployeeAdvanceService::class)->review($advanceRequest, 'approve', 700.00, $this->admin, null);
        $payrollRunItem = PayrollRunItem::query()->where('employee_id', $advanceRequest->employee_id)->firstOrFail();

        CompanyAccountStatementEntry::query()->delete();
        JournalEntry::query()->delete();

        $payment = $this->recordAdvancePayment($payrollRunItem, $advanceRequest->fresh(), 700.00);

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, JournalEntry::query()->count());

        $movement = $payment->cashbookMovement()->firstOrFail();
        $this->assertSame('out', $movement->direction);
        $this->assertSame(700.00, (float) $movement->amount);
        $this->assertFalse((bool) $movement->is_finalized);

        $journalEntry = $payment->journalEntry->load('transactions.account');
        $this->assertTrue($journalEntry->is_balanced);
        $this->assertTrue($journalEntry->transactions->contains(fn ($transaction): bool => $transaction->type === 'debit' && $transaction->account?->code === '1600' && (float) $transaction->amount === 700.00));
        $this->assertTrue($journalEntry->transactions->contains(fn ($transaction): bool => $transaction->type === 'credit' && $transaction->account?->code === '1010' && (float) $transaction->amount === 700.00));

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $payment->journalEntry->reference]))
            ->assertOk()
            ->assertSee('0 entries')
            ->assertDontSee('Salary Advance');
    }

    public function test_reconciled_advance_payout_appears_once_in_approved_transactions_and_duplicate_is_idempotent(): void
    {
        $advanceRequest = $this->makePendingAdvanceRequest(approvedAmount: 900.00);
        app(EmployeeAdvanceService::class)->review($advanceRequest, 'approve', 900.00, $this->admin, null);
        $payrollRunItem = PayrollRunItem::query()->where('employee_id', $advanceRequest->employee_id)->firstOrFail();

        CompanyAccountStatementEntry::query()->delete();
        JournalEntry::query()->delete();
        $requestUuid = (string) Str::uuid();

        $payment = $this->recordAdvancePayment($payrollRunItem, $advanceRequest->fresh(), 900.00, $requestUuid);
        $duplicate = $this->recordAdvancePayment($payrollRunItem, $advanceRequest->fresh(), 900.00, $requestUuid);

        $this->assertTrue($payment->is($duplicate));
        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, JournalEntry::query()->count());

        $this->finalizeMovement($payment);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $payment->journalEntry->reference]))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee('Salary Advance')
            ->assertSee('900.00');

        $this->expectException(ValidationException::class);

        $this->recordAdvancePayment($payrollRunItem->fresh(), $advanceRequest->fresh(), 1.00);
    }

    public function test_payroll_recovery_of_advance_does_not_create_new_company_cash_movement(): void
    {
        $advanceRequest = $this->makePendingAdvanceRequest(approvedAmount: 500.00);
        app(EmployeeAdvanceService::class)->review($advanceRequest, 'approve', 500.00, $this->admin, null);
        $payrollRunItem = PayrollRunItem::query()->where('employee_id', $advanceRequest->employee_id)->firstOrFail();

        CompanyAccountStatementEntry::query()->delete();
        $this->recordAdvancePayment($payrollRunItem, $advanceRequest->fresh(), 500.00);
        $statementCountAfterPayout = CompanyAccountStatementEntry::query()->count();

        $this->payrollService->finalize($payrollRunItem->payrollRun, (int) $this->admin->id);

        $this->assertSame($statementCountAfterPayout, CompanyAccountStatementEntry::query()->count());
    }

    public function test_approved_transactions_route_shows_finalized_salary_and_advance(): void
    {
        $salaryItem = $this->makePayrollRunItem(greenLeafAmount: 1300.00);
        $this->payrollService->finalize($salaryItem->payrollRun, (int) $this->admin->id);
        $salaryPayment = $this->recordSalaryPayment($salaryItem->fresh(), 1300.00);
        $this->finalizeMovement($salaryPayment);

        $advanceRequest = $this->makePendingAdvanceRequest(approvedAmount: 450.00);
        app(EmployeeAdvanceService::class)->review($advanceRequest, 'approve', 450.00, $this->admin, null);
        $advanceItem = PayrollRunItem::query()->where('employee_id', $advanceRequest->employee_id)->firstOrFail();
        $advancePayment = $this->recordAdvancePayment($advanceItem, $advanceRequest->fresh(), 450.00);
        $this->finalizeMovement($advancePayment);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal'))
            ->assertOk()
            ->assertSee('Approved Transactions')
            ->assertSee('2 entries')
            ->assertSee('Salary Payment')
            ->assertSee('1,300.00')
            ->assertSee('Salary Advance')
            ->assertSee('450.00');
    }

    public function test_statement_first_salary_payment_reuses_imported_statement_and_finalizes_once(): void
    {
        $payrollRunItem = $this->makePayrollRunItem(greenLeafAmount: 1400.00);
        $this->payrollService->finalize($payrollRunItem->payrollRun, (int) $this->admin->id);
        $statement = $this->makeImportedStatement('out', 600.00, $this->bankCompanyAccount, 'BANK-SALARY-1');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-salary-payment', $statement), [
                'payroll_run_item_id' => $payrollRunItem->id,
                'notes' => 'Statement salary payout.',
            ])
            ->assertRedirect();

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, PayrollPayment::query()->count());

        $payment = PayrollPayment::query()->firstOrFail();
        $statement = $statement->fresh();
        $this->assertSame($payment->id, $statement->source_id);
        $this->assertSame(PayrollPayment::class, $statement->source_type);
        $this->assertSame('salary_payment', $statement->source);
        $this->assertTrue((bool) $statement->is_finalized);
        $this->assertSame('reconciled', $statement->status);
        $this->assertSame(600.00, (float) $payment->amount);
        $this->assertSame($this->bankCompanyAccount->id, $payment->company_account_id);

        $journalEntry = $payment->journalEntry->load('transactions.account');
        $this->assertTrue($journalEntry->is_balanced);
        $this->assertTrue($journalEntry->transactions->contains(fn ($transaction): bool => $transaction->type === 'debit' && $transaction->account?->code === '2300' && (float) $transaction->amount === 600.00));
        $this->assertTrue($journalEntry->transactions->contains(fn ($transaction): bool => $transaction->type === 'credit' && $transaction->account?->code === '1020' && (float) $transaction->amount === 600.00));

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-salary-payment', $statement), [
                'payroll_run_item_id' => $payrollRunItem->id,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, PayrollPayment::query()->count());

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $payment->journalEntry->reference]))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee('Salary Payment')
            ->assertSee('600.00');
    }

    public function test_statement_first_salary_payment_rejects_wrong_direction(): void
    {
        $payrollRunItem = $this->makePayrollRunItem(greenLeafAmount: 800.00);
        $this->payrollService->finalize($payrollRunItem->payrollRun, (int) $this->admin->id);
        $statement = $this->makeImportedStatement('in', 300.00, $this->bankCompanyAccount, 'BANK-SALARY-IN');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-salary-payment', $statement), [
                'payroll_run_item_id' => $payrollRunItem->id,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, PayrollPayment::query()->count());
        $this->assertNull($statement->fresh()->journal_entry_id);
    }

    public function test_statement_first_salary_advance_reuses_imported_statement_and_finalizes_once(): void
    {
        $advanceRequest = $this->makePendingAdvanceRequest(approvedAmount: 750.00);
        app(EmployeeAdvanceService::class)->review($advanceRequest, 'approve', 750.00, $this->admin, null);
        $statement = $this->makeImportedStatement('out', 750.00, $this->cashCompanyAccount, 'CASH-ADV-1');

        $journalCountBefore = JournalEntry::query()->count();

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-salary-advance', $statement), [
                'employee_advance_request_id' => $advanceRequest->id,
                'notes' => 'Statement advance payout.',
            ])
            ->assertRedirect();

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, PayrollPayment::query()->count());
        $this->assertSame($journalCountBefore + 1, JournalEntry::query()->count());

        $payment = PayrollPayment::query()->firstOrFail();
        $statement = $statement->fresh();
        $this->assertSame($payment->id, $statement->source_id);
        $this->assertSame(PayrollPayment::class, $statement->source_type);
        $this->assertSame('salary_advance', $statement->source);
        $this->assertTrue((bool) $statement->is_finalized);
        $this->assertSame(750.00, (float) $payment->amount);
        $this->assertSame($this->cashCompanyAccount->id, $payment->company_account_id);
        $this->assertSame($payment->id, $advanceRequest->fresh()->payroll_payment_id);

        $journalEntry = $payment->journalEntry->load('transactions.account');
        $this->assertTrue($journalEntry->is_balanced);
        $this->assertTrue($journalEntry->transactions->contains(fn ($transaction): bool => $transaction->type === 'debit' && $transaction->account?->code === '1600' && (float) $transaction->amount === 750.00));
        $this->assertTrue($journalEntry->transactions->contains(fn ($transaction): bool => $transaction->type === 'credit' && $transaction->account?->code === '1010' && (float) $transaction->amount === 750.00));

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-salary-advance', $statement), [
                'employee_advance_request_id' => $advanceRequest->id,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, PayrollPayment::query()->count());

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.journal', ['search' => $payment->journalEntry->reference]))
            ->assertOk()
            ->assertSee('1 entries')
            ->assertSee('Salary Advance')
            ->assertSee('750.00');
    }

    public function test_statement_first_salary_advance_rejects_wrong_direction(): void
    {
        $advanceRequest = $this->makePendingAdvanceRequest(approvedAmount: 250.00);
        app(EmployeeAdvanceService::class)->review($advanceRequest, 'approve', 250.00, $this->admin, null);
        $statement = $this->makeImportedStatement('in', 250.00, $this->cashCompanyAccount, 'CASH-ADV-IN');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.reconciliation.classify-salary-advance', $statement), [
                'employee_advance_request_id' => $advanceRequest->id,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, PayrollPayment::query()->count());
        $this->assertNull($statement->fresh()->journal_entry_id);
    }

    private function makePayrollRunItem(float $greenLeafAmount): PayrollRunItem
    {
        $category = EmployeeCategory::factory()->create(['staff_area' => 'office']);
        $employee = Employee::factory()->create([
            'employee_category_id' => $category->id,
            'staff_area' => 'office',
            'monthly_salary' => $greenLeafAmount,
        ]);
        $periodStart = Carbon::parse('2026-08-01');
        $payrollRun = PayrollRun::factory()->create([
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodStart->copy()->endOfMonth()->toDateString(),
            'status' => 'draft',
            'gross_amount' => $greenLeafAmount,
            'net_amount' => $greenLeafAmount,
            'generated_by' => $this->admin->id,
        ]);

        return PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $employee->id,
            'employee_category_id' => $category->id,
            'computed_amount' => $greenLeafAmount,
            'green_leaf_computed_amount' => $greenLeafAmount,
            'client_shop_computed_amount' => 0,
            'final_amount' => $greenLeafAmount,
        ])->fresh(['payrollRun', 'employee', 'payments']);
    }

    private function makePendingAdvanceRequest(float $approvedAmount): EmployeeAdvanceRequest
    {
        $shop = Shop::factory()->create([
            'accounting_mode' => 'owned',
            'accounting_enabled' => true,
        ]);
        $category = EmployeeCategory::factory()->create(['staff_area' => 'shop']);
        $employee = Employee::factory()->create([
            'employee_category_id' => $category->id,
            'staff_area' => 'shop',
            'default_shop_id' => $shop->id,
        ]);
        $rule = EmployeeAdvanceRule::factory()->create();

        return EmployeeAdvanceRequest::factory()->create([
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'employee_advance_rule_id' => $rule->id,
            'requested_by' => $this->admin->id,
            'requested_on' => '2026-08-10',
            'payroll_month' => '2026-08-01',
            'requested_amount' => $approvedAmount,
            'eligible_amount' => $approvedAmount,
            'approved_amount' => null,
            'status' => 'pending',
            'fund_source' => 'petty_cash',
            'rule_snapshot' => [
                'eligible_amount' => $approvedAmount,
                'available_amount' => $approvedAmount,
                'already_advanced_amount' => 0,
            ],
        ]);
    }

    private function recordSalaryPayment(PayrollRunItem $payrollRunItem, float $amount, ?string $requestUuid = null): PayrollPayment
    {
        return $this->payrollPaymentService->record(
            payrollRunItem: $payrollRunItem,
            amount: $amount,
            paymentMethod: 'bank',
            paymentType: 'partial',
            paidOn: Carbon::parse('2026-08-20'),
            actor: $this->admin,
            notes: 'Salary test payment.',
            companyAccountId: (int) $this->bankCompanyAccount->id,
            reference: 'SALARY-TEST',
            requestUuid: $requestUuid ?? (string) Str::uuid(),
        );
    }

    private function recordAdvancePayment(PayrollRunItem $payrollRunItem, EmployeeAdvanceRequest $advanceRequest, float $amount, ?string $requestUuid = null): PayrollPayment
    {
        return $this->payrollPaymentService->record(
            payrollRunItem: $payrollRunItem,
            amount: $amount,
            paymentMethod: 'cash',
            paymentType: 'advance',
            paidOn: Carbon::parse('2026-08-20'),
            actor: $this->admin,
            notes: 'Advance test payment.',
            fundSource: 'company_cash',
            advanceRequestId: (int) $advanceRequest->id,
            allowAdvanceOverage: true,
            companyAccountId: (int) $this->cashCompanyAccount->id,
            reference: 'ADVANCE-TEST',
            requestUuid: $requestUuid ?? (string) Str::uuid(),
        );
    }

    private function finalizeMovement(PayrollPayment $payment): void
    {
        $movement = $payment->cashbookMovement()->firstOrFail();

        $this->reconciliationService->reconcileStatementJournal(
            $movement,
            $payment->journalEntry,
            (float) $movement->amount,
            (int) $this->admin->id,
        );
    }

    private function makeImportedStatement(string $direction, float $amount, CompanyAccount $companyAccount, string $reference): CompanyAccountStatementEntry
    {
        return CompanyAccountStatementEntry::query()->create([
            'company_account_id' => $companyAccount->id,
            'transaction_date' => '2026-08-20',
            'value_date' => '2026-08-20',
            'direction' => $direction,
            'amount' => $amount,
            'reference' => $reference,
            'narration' => 'Imported HR statement row',
            'source' => 'bank_import',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'imported_by' => $this->admin->id,
        ]);
    }

    private function seedAccounts(): void
    {
        foreach ([
            ['code' => '1010', 'name' => 'Cash on Hand', 'type' => 'asset'],
            ['code' => '1020', 'name' => 'Bank Account', 'type' => 'asset'],
            ['code' => '1600', 'name' => 'Employee Advances', 'type' => 'asset'],
            ['code' => '2300', 'name' => 'Salary Payable', 'type' => 'liability'],
            ['code' => '5700', 'name' => 'Salaries Expense', 'type' => 'expense'],
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
