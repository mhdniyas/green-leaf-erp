<?php

declare(strict_types=1);

namespace App\Repositories\Finance;

use App\Models\Expense;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ExpenseRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Expense::class;
    }

    public function paginateFiltered(
        int $perPage = 15,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $accountId = null
    ): LengthAwarePaginator {
        return $this->query()
            ->with(['account', 'recordedBy'])
            ->when($startDate, fn ($q) => $q->where('expense_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('expense_date', '<=', $endDate))
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
