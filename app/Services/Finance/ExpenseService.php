<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\DTOs\Finance\ExpenseData;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Repositories\Finance\ExpenseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepository $repository,
        private readonly JournalService $journalService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $accountId = null
    ): LengthAwarePaginator {
        return $this->repository->paginateFiltered($perPage, $startDate, $endDate, $accountId);
    }

    public function create(ExpenseData $data, int $userId): Expense
    {
        return DB::transaction(function () use ($data, $userId) {
            /** @var Expense $expense */
            $expense = $this->repository->create(array_merge($data->toArray(), [
                'recorded_by' => $userId,
            ]));

            // Post journal entry
            $this->journalService->recordExpense($expense);

            return $expense;
        });
    }

    public function update(Expense $expense, ExpenseData $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {
            // Delete old journal entries
            $ref = $expense->reference ?? "EXP-{$expense->id}";
            JournalEntry::where('reference', $ref)->delete();

            // Update expense
            /** @var Expense $expense */
            $expense = $this->repository->update($expense, $data->toArray());

            // Post new journal entry
            $this->journalService->recordExpense($expense);

            return $expense;
        });
    }

    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $ref = $expense->reference ?? "EXP-{$expense->id}";
            JournalEntry::where('reference', $ref)->delete();

            $this->repository->delete($expense);
        });
    }
}
