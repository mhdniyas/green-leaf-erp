<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\DTOs\Finance\JournalEntryData;
use App\Models\Account;
use App\Models\ContractWorkerPayment;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\User;
use App\Services\Finance\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContractWorkerPaymentService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    /**
     * @param  array{worker_name: string, work_type?: string|null, worked_on: string, paid_on: string, amount: float|int|string, payment_method: string, notes?: string|null}  $payload
     */
    public function record(array $payload, User $actor, ?Shop $shop = null): ContractWorkerPayment
    {
        return DB::transaction(function () use ($payload, $actor, $shop): ContractWorkerPayment {
            $payment = ContractWorkerPayment::query()->create([
                'shop_id' => $shop?->id,
                'paid_by' => $actor->id,
                'worker_name' => trim((string) $payload['worker_name']),
                'work_type' => filled($payload['work_type'] ?? null) ? trim((string) $payload['work_type']) : null,
                'worked_on' => Carbon::parse((string) $payload['worked_on'])->toDateString(),
                'paid_on' => Carbon::parse((string) $payload['paid_on'])->toDateString(),
                'amount' => round((float) $payload['amount'], 2),
                'payment_method' => (string) $payload['payment_method'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $journalEntry = $this->recordJournal($payment, $actor);
            $payment->forceFill(['journal_entry_id' => $journalEntry->id])->save();

            return $payment->fresh(['shop', 'journalEntry.transactions.account', 'paidBy']);
        });
    }

    private function recordJournal(ContractWorkerPayment $payment, User $actor): JournalEntry
    {
        $contractExpenseAccount = Account::query()->firstOrCreate(
            ['code' => '5710'],
            [
                'name' => 'Contract Labour Expense',
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
                reference: sprintf('CONTRACT-PAY-%s', $payment->id),
                description: 'Contract work payment to '.$payment->worker_name,
                lines: [
                    [
                        'account_id' => (int) $contractExpenseAccount->id,
                        'type' => 'debit',
                        'amount' => (float) $payment->amount,
                    ],
                    [
                        'account_id' => (int) $cashOrBankAccount->id,
                        'type' => 'credit',
                        'amount' => (float) $payment->amount,
                    ],
                ],
                sourceType: ContractWorkerPayment::class,
                sourceId: $payment->id,
                sourceEvent: 'payment',
            ),
            $actor->id,
        );
    }
}
