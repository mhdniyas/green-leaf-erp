<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\DTOs\Finance\JournalEntryData;
use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\EmployeeAdvanceRequest;
use App\Models\JournalEntry;
use App\Models\PayrollPayment;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Finance\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PayrollPaymentService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly CompanyPaymentReconciliationService $companyPaymentReconciliationService,
    ) {}

    public function record(
        PayrollRunItem $payrollRunItem,
        float $amount,
        string $paymentMethod,
        string $paymentType,
        Carbon $paidOn,
        User $actor,
        ?string $notes = null,
        ?Shop $shop = null,
        string $fundSource = 'company_cash',
        ?int $advanceRequestId = null,
        bool $allowAdvanceOverage = false,
        ?int $companyAccountId = null,
        ?string $reference = null,
        ?string $requestUuid = null,
    ): PayrollPayment {
        if ($requestUuid !== null && trim($requestUuid) !== '') {
            $existingPayment = PayrollPayment::query()
                ->where('request_uuid', $requestUuid)
                ->first();

            if ($existingPayment instanceof PayrollPayment) {
                $this->validateExistingPaymentFingerprint(
                    $existingPayment,
                    $payrollRunItem,
                    $amount,
                    $paymentType,
                    $shop,
                    $fundSource,
                    $companyAccountId,
                    $advanceRequestId,
                );

                return $existingPayment->fresh(['employee', 'shop', 'payrollRun', 'payrollRunItem.payrollRun', 'journalEntry.transactions.account', 'cashbookMovement.companyAccount', 'paidBy']);
            }
        }

        return DB::transaction(function () use ($payrollRunItem, $amount, $paymentMethod, $paymentType, $paidOn, $actor, $notes, $shop, $fundSource, $advanceRequestId, $allowAdvanceOverage, $companyAccountId, $reference, $requestUuid): PayrollPayment {
            $payrollRunItem = PayrollRunItem::query()
                ->with(['payrollRun', 'employee', 'payments'])
                ->whereKey($payrollRunItem->id)
                ->lockForUpdate()
                ->firstOrFail();
            $payrollRunItem->payments()->lockForUpdate()->get();

            if ($requestUuid !== null && trim($requestUuid) !== '') {
                $existingPayment = PayrollPayment::query()
                    ->where('request_uuid', $requestUuid)
                    ->lockForUpdate()
                    ->first();

                if ($existingPayment instanceof PayrollPayment) {
                    $this->validateExistingPaymentFingerprint(
                        $existingPayment,
                        $payrollRunItem,
                        $amount,
                        $paymentType,
                        $shop,
                        $fundSource,
                        $companyAccountId,
                        $advanceRequestId,
                    );

                    return $existingPayment->fresh(['employee', 'shop', 'payrollRun', 'payrollRunItem.payrollRun', 'journalEntry.transactions.account', 'cashbookMovement.companyAccount', 'paidBy']);
                }
            }

            if ($companyAccountId === null) {
                throw ValidationException::withMessages([
                    'company_account_uuid' => 'Select the company cash or bank account used for this payment.',
                ]);
            }

            $companyAccount = CompanyAccount::query()
                ->whereKey($companyAccountId)
                ->where('enabled', true)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($companyAccount->account_type, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages([
                    'company_account_uuid' => 'Salary payments can use only enabled company cash or bank accounts.',
                ]);
            }

            $accountPaymentMethod = $companyAccount->account_type === 'cash' ? 'cash' : 'bank';
            if ($paymentMethod !== $accountPaymentMethod) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Payment method must match the selected company account type.',
                ]);
            }

            $amount = round($amount, 2);
            if ($amount <= 0.0) {
                throw new RuntimeException('Payment amount is outside the remaining Green Leaf salary balance.');
            }

            if ($paymentType === 'advance' && $advanceRequestId !== null) {
                $this->validateAdvancePaymentAmount($advanceRequestId, $amount);
            } else {
                $remainingAmount = $payrollRunItem->remainingGreenLeafAmount();

                if ($amount > $remainingAmount && ! $allowAdvanceOverage) {
                    throw new RuntimeException('Payment amount is outside the remaining Green Leaf salary balance.');
                }
            }

            $payment = PayrollPayment::query()->create([
                'payroll_run_id' => $payrollRunItem->payroll_run_id,
                'payroll_run_item_id' => $payrollRunItem->id,
                'employee_id' => $payrollRunItem->employee_id,
                'shop_id' => $shop?->id,
                'company_account_id' => $companyAccount->id,
                'employee_advance_request_id' => $advanceRequestId,
                'request_uuid' => $requestUuid,
                'reference' => filled($reference) ? trim((string) $reference) : null,
                'paid_by' => $actor->id,
                'paid_on' => $paidOn->toDateString(),
                'amount' => $amount,
                'payment_method' => $accountPaymentMethod,
                'payment_type' => $paymentType,
                'fund_source' => $fundSource,
                'notes' => $notes,
            ]);

            $journalEntry = $this->recordPaymentJournal($payment, $actor);

            $payment->forceFill([
                'journal_entry_id' => $journalEntry->id,
            ])->save();

            $this->companyPaymentReconciliationService->createStatementEntry([
                'company_account_id' => $companyAccount->id,
                'journal_entry_id' => $journalEntry->id,
                'request_uuid' => $requestUuid,
                'transaction_date' => $paidOn->toDateString(),
                'value_date' => $paidOn->toDateString(),
                'direction' => 'out',
                'amount' => $amount,
                'reference' => filled($reference) ? trim((string) $reference) : sprintf('PAYROLL-PAY-%s', $payment->id),
                'narration' => $this->paymentDescription($payment),
                'source' => $paymentType === 'advance' ? 'salary_advance' : 'salary_payment',
                'source_type' => PayrollPayment::class,
                'source_id' => $payment->id,
                'notes' => $notes,
            ], (int) $actor->id);

            if ($paymentType === 'advance' && $advanceRequestId !== null) {
                EmployeeAdvanceRequest::query()
                    ->whereKey($advanceRequestId)
                    ->whereNull('payroll_payment_id')
                    ->update(['payroll_payment_id' => $payment->id]);
            }

            return $payment->fresh(['employee', 'shop', 'payrollRun', 'payrollRunItem.payrollRun', 'journalEntry.transactions.account', 'cashbookMovement.companyAccount', 'paidBy']);
        });
    }

    public function recordSalaryFromStatement(PayrollRunItem $payrollRunItem, CompanyAccountStatementEntry $statement, User $actor, ?string $notes = null): PayrollPayment
    {
        return $this->recordFromStatement(
            payrollRunItem: $payrollRunItem,
            statement: $statement,
            actor: $actor,
            paymentType: 'partial',
            notes: $notes,
        );
    }

    public function recordAdvanceFromStatement(EmployeeAdvanceRequest $advanceRequest, CompanyAccountStatementEntry $statement, User $actor, ?string $notes = null): PayrollPayment
    {
        return DB::transaction(function () use ($advanceRequest, $statement, $actor, $notes): PayrollPayment {
            $advanceRequest = EmployeeAdvanceRequest::query()
                ->with(['employee', 'shopStaffPayment.payrollRunItem'])
                ->whereKey($advanceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($advanceRequest->status !== 'approved') {
                throw ValidationException::withMessages([
                    'advance_request_id' => 'Only approved salary advance requests can be paid from a statement.',
                ]);
            }

            if ($advanceRequest->payroll_payment_id !== null) {
                throw ValidationException::withMessages([
                    'advance_request_id' => 'This salary advance already has a company-funded payout.',
                ]);
            }

            $payrollRunItem = $advanceRequest->shopStaffPayment?->payrollRunItem;
            if (! $payrollRunItem instanceof PayrollRunItem) {
                throw ValidationException::withMessages([
                    'advance_request_id' => 'This salary advance has no payroll item available for company payout.',
                ]);
            }

            $payment = $this->recordFromStatement(
                payrollRunItem: $payrollRunItem,
                statement: $statement,
                actor: $actor,
                paymentType: 'advance',
                notes: $notes,
                advanceRequestId: (int) $advanceRequest->id,
                allowAdvanceOverage: true,
            );

            $advanceRequest->forceFill(['payroll_payment_id' => $payment->id])->save();

            return $payment;
        }, attempts: 3);
    }

    private function recordFromStatement(
        PayrollRunItem $payrollRunItem,
        CompanyAccountStatementEntry $statement,
        User $actor,
        string $paymentType,
        ?string $notes = null,
        ?int $advanceRequestId = null,
        bool $allowAdvanceOverage = false,
    ): PayrollPayment {
        return DB::transaction(function () use ($payrollRunItem, $statement, $actor, $paymentType, $notes, $advanceRequestId, $allowAdvanceOverage): PayrollPayment {
            $statement = CompanyAccountStatementEntry::query()
                ->with('companyAccount')
                ->whereKey($statement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($statement->is_finalized || $statement->status !== 'unmatched' || $statement->journal_entry_id !== null || $statement->source_type !== null || $statement->direction !== 'out') {
                throw ValidationException::withMessages(['statement' => 'This statement row cannot be classified for this HR payment.']);
            }

            $companyAccount = $statement->companyAccount;
            if (! $companyAccount instanceof CompanyAccount || ! $companyAccount->enabled || ! in_array($companyAccount->account_type, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages(['statement' => 'Statement company account is not valid for HR payment.']);
            }

            $payrollRunItem = PayrollRunItem::query()
                ->with(['payrollRun', 'employee', 'payments'])
                ->whereKey($payrollRunItem->id)
                ->lockForUpdate()
                ->firstOrFail();
            $payrollRunItem->payments()->lockForUpdate()->get();

            if ($paymentType !== 'advance' && $payrollRunItem->payrollRun?->status !== 'finalized') {
                throw ValidationException::withMessages([
                    'payroll_run_item_id' => 'Only finalized payroll items can be paid from a statement.',
                ]);
            }

            $amount = round((float) $statement->amount, 2);
            if ($amount <= 0.0) {
                throw ValidationException::withMessages(['statement' => 'Statement amount must be greater than zero.']);
            }

            if ($paymentType === 'advance' && $advanceRequestId !== null) {
                $this->validateAdvancePaymentAmount($advanceRequestId, $amount);
            } else {
                $remainingAmount = $payrollRunItem->remainingGreenLeafAmount();

                if ($amount > $remainingAmount && ! $allowAdvanceOverage) {
                    throw ValidationException::withMessages([
                        'payroll_run_item_id' => 'Statement amount is greater than the remaining Green Leaf salary balance.',
                    ]);
                }
            }

            $paymentMethod = $companyAccount->account_type === 'cash' ? 'cash' : 'bank';
            $payment = PayrollPayment::query()->create([
                'payroll_run_id' => $payrollRunItem->payroll_run_id,
                'payroll_run_item_id' => $payrollRunItem->id,
                'employee_id' => $payrollRunItem->employee_id,
                'shop_id' => null,
                'company_account_id' => $companyAccount->id,
                'employee_advance_request_id' => $advanceRequestId,
                'request_uuid' => (string) $statement->public_uuid,
                'reference' => filled($statement->reference) ? trim((string) $statement->reference) : null,
                'paid_by' => $actor->id,
                'paid_on' => $statement->transaction_date?->toDateString() ?? today()->toDateString(),
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_type' => $paymentType,
                'fund_source' => 'company_cash',
                'notes' => $notes,
            ]);

            $journalEntry = $this->recordPaymentJournal($payment, $actor);

            $payment->forceFill([
                'journal_entry_id' => $journalEntry->id,
            ])->save();

            $statement->update([
                'journal_entry_id' => $journalEntry->id,
                'source' => $paymentType === 'advance' ? 'salary_advance' : 'salary_payment',
                'source_type' => PayrollPayment::class,
                'source_id' => $payment->id,
                'reference' => $statement->reference ?: sprintf('PAYROLL-PAY-%s', $payment->id),
                'narration' => $statement->narration ?: $this->paymentDescription($payment),
                'notes' => $notes,
            ]);

            $this->companyPaymentReconciliationService->reconcileStatementJournal(
                $statement,
                $journalEntry,
                $amount,
                (int) $actor->id,
            );

            return $payment->fresh(['employee', 'shop', 'payrollRun', 'payrollRunItem.payrollRun', 'journalEntry.transactions.account', 'cashbookMovement.companyAccount', 'paidBy']);
        }, attempts: 3);
    }

    private function recordPaymentJournal(PayrollPayment $payment, User $actor): JournalEntry
    {
        $payment->loadMissing(['employee', 'payrollRun']);

        $debitAccount = $payment->payment_type === 'advance'
            ? $this->account('1600', 'Employee Advances', 'asset')
            : $this->account('2300', 'Salary Payable', 'liability');
        $cashOrBankAccount = Account::query()->firstOrCreate(
            ['code' => $payment->payment_method === 'cash' ? '1010' : '1020'],
            [
                'name' => $payment->payment_method === 'cash' ? 'Cash on Hand' : 'Bank Account',
                'type' => 'asset',
                'is_active' => true,
                'parent_id' => null,
            ],
        );

        return $this->journalService->createEntry(
            new JournalEntryData(
                entryDate: $payment->paid_on->format('Y-m-d'),
                reference: sprintf('PAYROLL-PAY-%s', $payment->id),
                description: $this->paymentDescription($payment),
                lines: [
                    [
                        'account_id' => (int) $debitAccount->id,
                        'type' => 'debit',
                        'amount' => (float) $payment->amount,
                    ],
                    [
                        'account_id' => (int) $cashOrBankAccount->id,
                        'type' => 'credit',
                        'amount' => (float) $payment->amount,
                    ],
                ],
                sourceType: PayrollPayment::class,
                sourceId: $payment->id,
                sourceEvent: $payment->payment_type === 'advance' ? 'salary_advance' : 'salary_payment',
            ),
            $actor->id,
        );
    }

    private function paymentDescription(PayrollPayment $payment): string
    {
        $typeLabel = $payment->payment_type === 'advance' ? 'Salary advance' : 'Salary payment';
        $sourceLabel = match ($payment->fund_source) {
            'petty_cash' => ' from shop cash balance',
            'sales_income' => ' from shop sales income',
            default => '',
        };

        return $typeLabel.' to '.$payment->employee->name.$sourceLabel.' for '.$payment->payrollRun->period_start->format('F Y');
    }

    private function validateAdvancePaymentAmount(int $advanceRequestId, float $amount): void
    {
        $advanceRequest = EmployeeAdvanceRequest::query()
            ->whereKey($advanceRequestId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($advanceRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'advance_request' => 'Only approved salary advance requests can be paid.',
            ]);
        }

        $alreadyPaid = PayrollPayment::query()
            ->where('employee_advance_request_id', $advanceRequest->id)
            ->lockForUpdate()
            ->sum('amount');
        $remainingApproved = round((float) $advanceRequest->approved_amount - (float) $alreadyPaid, 2);

        if ($amount > $remainingApproved) {
            throw ValidationException::withMessages([
                'amount' => 'The salary advance payment cannot exceed the remaining approved advance amount.',
            ]);
        }
    }

    private function validateExistingPaymentFingerprint(
        PayrollPayment $existingPayment,
        PayrollRunItem $payrollRunItem,
        float $amount,
        string $paymentType,
        ?Shop $shop,
        string $fundSource,
        ?int $companyAccountId,
        ?int $advanceRequestId,
    ): void {
        $expectedShopId = $shop?->id ?? $payrollRunItem->shop_id;
        $existingShopId = $existingPayment->shop_id;

        if (
            (int) $existingPayment->employee_id !== (int) $payrollRunItem->employee_id
            || (int) $existingPayment->payroll_run_item_id !== (int) $payrollRunItem->id
            || ($expectedShopId !== null && (int) $existingShopId !== (int) $expectedShopId)
            || abs((float) $existingPayment->amount - round($amount, 2)) > 0.009
            || $existingPayment->payment_type !== $paymentType
            || $existingPayment->fund_source !== $fundSource
            || ($companyAccountId !== null && (int) $existingPayment->company_account_id !== (int) $companyAccountId)
            || ($advanceRequestId !== null && (int) $existingPayment->employee_advance_request_id !== (int) $advanceRequestId)
        ) {
            throw ValidationException::withMessages([
                'request_uuid' => 'This request UUID has already been used for a different payment.',
            ]);
        }
    }

    private function account(string $code, string $name, string $type): Account
    {
        return Account::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'is_active' => true,
                'parent_id' => null,
            ],
        );
    }
}
