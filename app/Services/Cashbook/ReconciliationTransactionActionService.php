<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\CompanyAccountingEntry;
use App\Services\Finance\CompanyMainAccountService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ReconciliationTransactionActionService
{
    public function __construct(private readonly CompanyMainAccountService $companyMainAccountService) {}

    /**
     * @param Collection<int, object> $transactions
     * @return Collection<int, object>
     */
    public function addAvailableActions(Collection $transactions): Collection
    {
        return $transactions->map(function (object $transaction): object {
            $transaction->available_action = $this->availableAction($transaction);

            return $transaction;
        });
    }

    /** @return array{type:string,label:string}|null */
    private function availableAction(object $transaction): ?array
    {
        if (
            $transaction->source_type === CompanyAccountingEntry::class
            && $transaction->reconciliation_status !== 'RECONCILED'
            && ($transaction->source_record_status ?? null) === CompanyAccountingEntry::StatusFinal
        ) {
            return ['type' => 'reverse', 'label' => 'Reverse Transaction'];
        }

        return null;
    }

    public function reverseCompanyAccountingEntry(int $sourceId, int $actorId, string $reason): CompanyAccountingEntry
    {
        return DB::transaction(function () use ($sourceId, $actorId, $reason): CompanyAccountingEntry {
            $entry = CompanyAccountingEntry::query()
                ->whereKey($sourceId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($entry->status !== CompanyAccountingEntry::StatusFinal) {
                throw new RuntimeException('This transaction cannot be reversed.');
            }

            $isReconciled = CompanyAccountStatementEntry::query()
                ->where('source_type', CompanyAccountingEntry::class)
                ->where('source_id', $entry->id)
                ->where('is_finalized', true)
                ->exists();

            if ($isReconciled) {
                throw new RuntimeException('Unmatch this transaction before reversing it.');
            }

            return $this->companyMainAccountService->reverseEntry($entry, $actorId, $reason);
        }, attempts: 3);
    }
}
