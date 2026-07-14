<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\DTOs\Finance\JournalEntryData;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\PayrollPayment;
use App\Models\PayrollRunItem;
use App\Models\User;
use App\Services\Finance\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollPaymentService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    public function record(PayrollRunItem $payrollRunItem, float $amount, string $paymentMethod, string $paymentType, Carbon $paidOn, User $actor, ?string $notes = null): PayrollPayment
    {
        $payrollRunItem->loadMissing(['payrollRun', 'employee', 'payments']);

        $remainingAmount = $payrollRunItem->remainingAmount();

        if ($amount <= 0 || $amount > $remainingAmount) {
            throw new RuntimeException('Payment amount is outside the remaining salary balance.');
        }

        return DB::transaction(function () use ($payrollRunItem, $amount, $paymentMethod, $paymentType, $paidOn, $actor, $notes): PayrollPayment {
            $payment = PayrollPayment::query()->create([
                'payroll_run_id' => $payrollRunItem->payroll_run_id,
                'payroll_run_item_id' => $payrollRunItem->id,
                'employee_id' => $payrollRunItem->employee_id,
                'paid_by' => $actor->id,
                'paid_on' => $paidOn->toDateString(),
                'amount' => round($amount, 2),
                'payment_method' => $paymentMethod,
                'payment_type' => $paymentType,
                'notes' => $notes,
            ]);

            $journalEntry = $this->recordPaymentJournal($payment, $actor);

            $payment->forceFill([
                'journal_entry_id' => $journalEntry->id,
            ])->save();

            return $payment->fresh(['employee', 'payrollRun', 'payrollRunItem.payrollRun', 'journalEntry.transactions.account', 'paidBy']);
        });
    }

    private function recordPaymentJournal(PayrollPayment $payment, User $actor): JournalEntry
    {
        $payment->loadMissing(['employee', 'payrollRun']);

        $salaryExpenseAccount = Account::query()->firstOrCreate(
            ['code' => '5700'],
            [
                'name' => 'Salaries Expense',
                'type' => 'expense',
                'is_active' => true,
                'parent_id' => null,
            ],
        );
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
                description: 'Salary payment to '.$payment->employee->name.' for '.$payment->payrollRun->period_start->format('F Y'),
                lines: [
                    [
                        'account_id' => (int) $salaryExpenseAccount->id,
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
                sourceEvent: 'payment',
            ),
            $actor->id,
        );
    }
}
