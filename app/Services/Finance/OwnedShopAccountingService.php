<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopAccountingInvoice;
use App\Models\ShopCredit;
use App\Models\ShopPettyCashExpense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnedShopAccountingService
{
    /**
     * @return Collection<int, Shop>
     */
    public function eligibleShops(): Collection
    {
        return Shop::query()
            ->where('accounting_enabled', true)
            ->whereIn('accounting_mode', ['owned', 'partnership'])
            ->with(['ownerships.user'])
            ->orderBy('name')
            ->get();
    }

    public function isEligibleShop(Shop $shop): bool
    {
        return $shop->isOwnedAccountingEnabled();
    }

    public function ownershipPercentageTotal(Shop $shop): float
    {
        return round((float) $shop->ownerships()->sum('ownership_percent'), 2);
    }

    /**
     * @param  array<int, array{user_id?: int|null, owner_name: string, ownership_percent: float|int|string, role_label?: string|null}>  $ownerships
     */
    public function replaceOwnerships(Shop $shop, array $ownerships): void
    {
        $shop->ownerships()->delete();

        foreach ($ownerships as $ownership) {
            $shop->ownerships()->create([
                'user_id' => $ownership['user_id'] ?? null,
                'owner_name' => trim((string) $ownership['owner_name']),
                'ownership_percent' => round((float) $ownership['ownership_percent'], 2),
                'role_label' => filled($ownership['role_label'] ?? null) ? trim((string) $ownership['role_label']) : null,
            ]);
        }
    }

    /**
     * @return Collection<int, ShopAccountingCategory>
     */
    public function availableCategoriesForShop(Shop $shop): Collection
    {
        return ShopAccountingCategory::query()
            ->where('is_active', true)
            ->where(function ($query) use ($shop): void {
                $query->whereNull('shop_id')
                    ->orWhere('shop_id', $shop->id);
            })
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{
     *     business_date:string,
     *     status:string,
     *     opening_cash?: float|int|string|null,
     *     closing_cash?: float|int|string|null,
     *     notes?: string|null,
     *     lines: array<int, array{
     *         shop_accounting_category_id:int,
     *         amount: float|int|string,
     *         description?: string|null
     *     }>
     * }  $payload
     */
    public function saveEntry(Shop $shop, array $payload, int $userId, ?ShopAccountingEntry $entry = null, bool $allowApprovedEdit = false): ShopAccountingEntry
    {
        return DB::transaction(function () use ($shop, $payload, $userId, $entry, $allowApprovedEdit): ShopAccountingEntry {
            if ($entry instanceof ShopAccountingEntry && ($entry->status === 'finalized' || ($entry->status === 'approved' && ! $allowApprovedEdit))) {
                throw ValidationException::withMessages([
                    'business_date' => 'Approved or finalized entries cannot be edited. Use the correction review workflow.',
                ]);
            }

            $businessDate = Carbon::parse($payload['business_date'])->toDateString();
            $periodClosed = ShopAccountingInvoice::query()
                ->where('shop_id', $shop->id)
                ->whereIn('status', ['approved', 'paid'])
                ->whereDate('period_start', '<=', $businessDate)
                ->whereDate('period_end', '>=', $businessDate)
                ->exists();

            if ($periodClosed) {
                throw ValidationException::withMessages([
                    'business_date' => 'This accounting period is closed by an approved settlement invoice.',
                ]);
            }

            $entry ??= new ShopAccountingEntry;

            $entry->fill([
                'shop_id' => $shop->id,
                'business_date' => $businessDate,
                'status' => $payload['status'],
                'opening_cash' => $payload['opening_cash'] ?? null,
                'closing_cash' => $payload['closing_cash'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $entry->exists ? $entry->created_by : $userId,
                'updated_by' => $entry->exists ? $userId : null,
            ]);
            $entry->save();

            $categoryIds = collect($payload['lines'])
                ->pluck('shop_accounting_category_id')
                ->map(fn ($categoryId): int => (int) $categoryId)
                ->toArray();

            $categories = ShopAccountingCategory::query()
                ->whereIn('id', $categoryIds)
                ->where('is_active', true)
                ->where(function ($query) use ($shop): void {
                    $query->whereNull('shop_id')
                        ->orWhere('shop_id', $shop->id);
                })
                ->get()
                ->keyBy('id');

            if ($categories->count() !== count(array_unique($categoryIds))) {
                throw ValidationException::withMessages([
                    'lines' => 'Every accounting line must reference an active category belonging to this shop.',
                ]);
            }

            $entry->lines()->delete();

            foreach ($payload['lines'] as $line) {
                /** @var ShopAccountingCategory|null $category */
                $category = $categories->get((int) $line['shop_accounting_category_id']);

                $entry->lines()->create([
                    'shop_accounting_category_id' => $category->id,
                    'type' => $category->type,
                    'cash_effect' => $category->cash_effect,
                    'amount' => round((float) $line['amount'], 2),
                    'description' => filled($line['description'] ?? null) ? trim((string) $line['description']) : null,
                    'review_status' => null,
                    'review_note' => null,
                ]);
            }

            return $entry->fresh(['lines.category', 'shop', 'createdBy', 'updatedBy']);
        });
    }

    /**
     * @param  array{
     *     business_date:string,
     *     submission_action:string,
     *     opening_cash?: float|int|string|null,
     *     closing_cash?: float|int|string|null,
     *     notes?: string|null,
     *     shop_reply_note?: string|null,
     *     lines: array<int, array{
     *         shop_accounting_category_id:int,
     *         amount: float|int|string,
     *         description?: string|null
     *     }>
     * }  $payload
     */
    public function saveShopOwnerEntry(Shop $shop, array $payload, int $userId, ?ShopAccountingEntry $entry = null): ShopAccountingEntry
    {
        if ($entry instanceof ShopAccountingEntry && ! $entry->canBeEditedByShopOwner()) {
            throw ValidationException::withMessages([
                'business_date' => 'This entry is locked. Ask an administrator to send it through the correction review workflow.',
            ]);
        }

        $status = $payload['submission_action'] === 'submit' ? 'submitted' : 'draft';
        $entry = $this->saveEntry($shop, [
            'business_date' => $payload['business_date'],
            'status' => $status,
            'opening_cash' => $payload['opening_cash'] ?? null,
            'closing_cash' => $payload['closing_cash'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'lines' => $payload['lines'],
        ], $userId, $entry, allowApprovedEdit: true);

        $entry->forceFill([
            'submitted_by' => $status === 'submitted' ? $userId : null,
            'submitted_at' => $status === 'submitted' ? now() : null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'admin_note' => null,
            'shop_reply_note' => filled($payload['shop_reply_note'] ?? null) ? trim((string) $payload['shop_reply_note']) : null,
        ])->save();

        return $entry->fresh(['lines.category', 'shop', 'createdBy', 'updatedBy', 'submittedBy', 'reviewedBy']);
    }

    public function ensureDefaultPettyCashExpense(Shop $shop, Carbon $businessDate, ?int $userId = null): ?ShopPettyCashExpense
    {
        $amount = round((float) ($shop->default_petty_cash_amount ?? 0), 2);

        if ($amount <= 0) {
            return null;
        }

        $date = $businessDate->toDateString();
        $expense = ShopPettyCashExpense::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $date)
            ->first();

        if ($expense instanceof ShopPettyCashExpense && $expense->isManual()) {
            return $expense;
        }

        if (! $expense instanceof ShopPettyCashExpense) {
            return ShopPettyCashExpense::query()->create([
                'shop_id' => $shop->id,
                'business_date' => $date,
                'amount' => $amount,
                'source' => 'auto',
                'created_by' => $userId,
            ]);
        }

        $expense->update([
            'amount' => $amount,
            'updated_by' => $userId,
        ]);

        return $expense->fresh();
    }

    public function recordManualPettyCashExpense(Shop $shop, Carbon $businessDate, float $amount, int $userId): ShopPettyCashExpense
    {
        $date = $businessDate->toDateString();
        $expense = ShopPettyCashExpense::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $date)
            ->first();

        if (! $expense instanceof ShopPettyCashExpense) {
            return ShopPettyCashExpense::query()->create([
                'shop_id' => $shop->id,
                'business_date' => $date,
                'amount' => round($amount, 2),
                'source' => 'manual',
                'created_by' => $userId,
            ]);
        }

        $newAmount = round($amount, 2);
        $currentAmount = round((float) $expense->amount, 2);
        $changedPayload = $currentAmount !== $newAmount
            ? [
                'previous_amount' => $currentAmount,
                'amount_changed_by' => $userId,
                'amount_changed_at' => now(),
            ]
            : [];

        $expense->update([
            ...$changedPayload,
            'amount' => $newAmount,
            'source' => 'manual',
            'updated_by' => $userId,
        ]);

        return $expense->fresh();
    }

    /**
     * @return Collection<int, array{
     *     date:string,
     *     admin_cash:float,
     *     admin_cash_label:string,
     *     expense:float,
     *     expense_source:?string,
     *     expense_updated_at:?Carbon,
     *     amount_change_label:?string,
     *     balance:float
     * }>
     */
    public function pettyCashRows(Shop $shop, Carbon $startDate, Carbon $endDate, bool $includeEmptyDays = false): Collection
    {
        $pettyCredits = ShopCredit::query()
            ->where('shop_id', $shop->id)
            ->where('is_petty_cash', true)
            ->whereDate('business_date', '<=', $endDate)
            ->with('creator')
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        $pettyExpenses = ShopPettyCashExpense::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '<=', $endDate)
            ->with('amountChangedBy')
            ->orderBy('business_date')
            ->get();

        $creditByDate = $pettyCredits->groupBy(fn (ShopCredit $credit): string => $credit->business_date?->toDateString() ?? today()->toDateString());
        $expenseByDate = $pettyExpenses->keyBy(fn (ShopPettyCashExpense $expense): string => $expense->business_date?->toDateString() ?? today()->toDateString());

        $allDates = $creditByDate->keys()
            ->merge($expenseByDate->keys())
            ->unique()
            ->sort()
            ->values();

        if ($includeEmptyDays) {
            $calendarDate = $startDate->copy();

            while ($calendarDate->lte($endDate)) {
                $allDates->push($calendarDate->toDateString());
                $calendarDate->addDay();
            }

            $allDates = $allDates->unique()->sort()->values();
        }

        $runningBalance = 0.0;
        $rows = collect();

        foreach ($allDates as $date) {
            $dayCredits = $creditByDate->get($date, collect());
            $adminCash = round((float) $dayCredits->sum(
                fn (ShopCredit $credit): float => $credit->type === 'in' ? (float) $credit->amount : (float) $credit->amount * -1
            ), 2);
            $expense = $expenseByDate->get($date);
            $expenseAmount = $expense instanceof ShopPettyCashExpense ? round((float) $expense->amount, 2) : 0.0;

            $runningBalance = round($runningBalance + $adminCash - $expenseAmount, 2);

            if (Carbon::parse($date)->lt($startDate) || Carbon::parse($date)->gt($endDate)) {
                continue;
            }

            $adminCashLabel = $dayCredits
                ->map(fn (ShopCredit $credit): string => ($credit->creator?->name ?? 'Admin').' - Rs. '.number_format((float) $credit->amount, 2))
                ->implode(', ');

            $rows->push([
                'date' => $date,
                'admin_cash' => $adminCash,
                'admin_cash_label' => $adminCashLabel,
                'expense' => $expenseAmount,
                'expense_source' => $expense instanceof ShopPettyCashExpense ? $expense->source : null,
                'expense_updated_at' => $expense instanceof ShopPettyCashExpense ? $expense->updated_at : null,
                'amount_change_label' => $expense instanceof ShopPettyCashExpense && $expense->previous_amount !== null && $expense->amount_changed_at !== null
                    ? sprintf(
                        'Changed from Rs. %s to Rs. %s on %s by %s for %s',
                        number_format((float) $expense->previous_amount, 2),
                        number_format((float) $expense->amount, 2),
                        $expense->amount_changed_at->format('d M Y h:i A'),
                        $expense->amountChangedBy?->name ?? 'Shop owner',
                        Carbon::parse($date)->format('d M Y'),
                    )
                    : null,
                'balance' => $runningBalance,
            ]);
        }

        return $rows->sortByDesc('date')->values();
    }

    /**
     * @param  array<int, array{decision?: string|null, review_note?: string|null}>  $lineReviews
     */
    public function reviewEntry(ShopAccountingEntry $entry, string $decision, int $userId, ?string $adminNote = null, array $lineReviews = []): ShopAccountingEntry
    {
        if ($entry->status === 'finalized') {
            throw ValidationException::withMessages([
                'decision' => 'Finalized entries cannot be reopened. Create a reversal or adjustment entry instead.',
            ]);
        }

        if ($decision === 'approve') {
            $entry->lines()->update([
                'review_status' => 'approved',
                'review_note' => null,
            ]);
        } elseif ($decision === 'recheck') {
            $entry->lines()->update([
                'review_status' => 'recheck_required',
                'review_note' => filled($adminNote) ? trim((string) $adminNote) : null,
            ]);
        } else {
            $normalizedReviews = collect($lineReviews)
                ->mapWithKeys(fn ($review, $lineId): array => [
                    (int) $lineId => [
                        'decision' => $review['decision'] ?? null,
                        'review_note' => $review['review_note'] ?? null,
                    ],
                ]);

            $entry->lines->each(function ($line) use ($normalizedReviews): void {
                $review = $normalizedReviews->get($line->id, []);

                if (! in_array($review['decision'] ?? null, ['approve', 'recheck'], true)) {
                    return;
                }

                $line->forceFill([
                    'review_status' => $review['decision'] === 'approve' ? 'approved' : 'recheck_required',
                    'review_note' => filled($review['review_note'] ?? null) ? trim((string) $review['review_note']) : null,
                ])->save();
            });
        }

        $lineStatuses = $entry->lines()->pluck('review_status')->values();
        $reviewedStatuses = $lineStatuses->filter()->values();
        $entryStatus = match (true) {
            $reviewedStatuses->contains('recheck_required') => 'recheck_required',
            $lineStatuses->isNotEmpty() && $lineStatuses->every(fn ($status): bool => $status === 'approved') => 'approved',
            default => 'submitted',
        };

        $entry->forceFill([
            'status' => $entryStatus,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'admin_note' => filled($adminNote) ? trim((string) $adminNote) : null,
        ])->save();

        return $entry->fresh(['lines.category', 'shop', 'createdBy', 'updatedBy', 'submittedBy', 'reviewedBy']);
    }

    public function entryForDate(Shop $shop, Carbon $date): ?ShopAccountingEntry
    {
        return ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $date->toDateString())
            ->with(['lines.category', 'createdBy', 'updatedBy', 'submittedBy', 'reviewedBy'])
            ->first();
    }

    /**
     * @return array{
     *     eligible_shop_count:int,
     *     owned_shop_count:int,
     *     partnership_shop_count:int,
     *     entries_today_count:int,
     *     draft_entries_count:int,
     *     pending_review_count:int,
     *     recheck_count:int,
     *     approved_entries_count:int,
     *     total_income:float,
     *     total_expense:float,
     *     net_amount:float,
     *     invoice_count:int
     * }
     */
    public function dashboardMetrics(Carbon $date): array
    {
        $eligibleShops = $this->eligibleShops();
        $entryQuery = ShopAccountingEntry::query()
            ->whereIn('shop_id', $eligibleShops->pluck('id'))
            ->where('business_date', $date->toDateString());
        $lineTotals = ShopAccountingEntryLine::query()
            ->join('shop_accounting_entries', 'shop_accounting_entries.id', '=', 'shop_accounting_entry_lines.shop_accounting_entry_id')
            ->whereIn('shop_accounting_entries.shop_id', $eligibleShops->pluck('id'))
            ->where('shop_accounting_entries.business_date', $date->toDateString())
            ->selectRaw("COALESCE(SUM(CASE WHEN shop_accounting_entry_lines.type = 'income' THEN shop_accounting_entry_lines.amount ELSE 0 END), 0) as total_income")
            ->selectRaw("COALESCE(SUM(CASE WHEN shop_accounting_entry_lines.type = 'expense' THEN shop_accounting_entry_lines.amount ELSE 0 END), 0) as total_expense")
            ->first();

        $totalIncome = round((float) ($lineTotals?->total_income ?? 0), 2);
        $totalExpense = round((float) ($lineTotals?->total_expense ?? 0), 2);

        return [
            'eligible_shop_count' => $eligibleShops->count(),
            'owned_shop_count' => $eligibleShops->where('accounting_mode', 'owned')->count(),
            'partnership_shop_count' => $eligibleShops->where('accounting_mode', 'partnership')->count(),
            'entries_today_count' => (clone $entryQuery)->count(),
            'draft_entries_count' => (clone $entryQuery)->where('status', 'draft')->count(),
            'pending_review_count' => (clone $entryQuery)->where('status', 'submitted')->count(),
            'recheck_count' => (clone $entryQuery)->where('status', 'recheck_required')->count(),
            'approved_entries_count' => (clone $entryQuery)->where('status', 'approved')->count(),
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_amount' => round($totalIncome - $totalExpense, 2),
            'invoice_count' => ShopAccountingInvoice::query()
                ->whereIn('shop_id', $eligibleShops->pluck('id'))
                ->whereDate('created_at', $date)
                ->count(),
        ];
    }
}
