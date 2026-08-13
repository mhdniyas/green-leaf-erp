<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\PresetCollectionGroup;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollectionGroupPostingService
{
    public function __construct(
        private readonly DailyLedgerService $dailyLedgerService,
    ) {}

    public function groupsForShop(int $shopId): Collection
    {
        $profile = ShopLedgerProfile::query()
            ->where('shop_id', $shopId)
            ->with('preset.collectionGroups.entryTypes.entryType')
            ->first();

        return $profile?->preset?->collectionGroups
            ?->where('enabled', true)
            ->values() ?? collect();
    }

    public function summaries(Collection $transactions): array
    {
        return $transactions
            ->filter(fn (ShopLedgerTransaction $transaction): bool => $transaction->reference_type === 'collection_group' && $transaction->reference_id !== null)
            ->groupBy('reference_id')
            ->map(function (Collection $rows, int|string $referenceId): array {
                $income = (float) $rows
                    ->filter(fn (ShopLedgerTransaction $transaction): bool => $transaction->direction === 'income' || $transaction->entryType?->category === 'income')
                    ->sum('amount');
                $expense = (float) $rows
                    ->filter(fn (ShopLedgerTransaction $transaction): bool => $transaction->direction === 'expense' || $transaction->entryType?->category === 'expense')
                    ->sum('amount');
                $first = $rows->sortBy('id')->first();

                return [
                    'reference_id' => (int) $referenceId,
                    'business_date' => $first?->business_date?->toDateString(),
                    'name' => str((string) ($first?->notes ?: 'Collection'))->before(' collection')->title()->toString(),
                    'income' => round($income, 2),
                    'expense' => round($expense, 2),
                    'net' => round($income - $expense, 2),
                    'lines' => $rows->values(),
                ];
            })
            ->values()
            ->all();
    }

    public function record(int $shopId, string $businessDate, int $groupId, array $amounts, int $userId, ?string $notes = null): array
    {
        $group = $this->groupsForShop($shopId)->firstWhere('id', $groupId);
        if (! $group instanceof PresetCollectionGroup) {
            throw ValidationException::withMessages([
                'collection_group_id' => 'Collection group is not configured for this shop.',
            ]);
        }

        $lines = $group->entryTypes
            ->sortBy('display_order')
            ->map(function ($line) use ($amounts): ?array {
                $amount = round((float) ($amounts[$line->entry_type_id] ?? 0), 2);
                if ($amount <= 0 && ! $line->required) {
                    return null;
                }

                if ($amount <= 0 && $line->required) {
                    throw ValidationException::withMessages([
                        'collection_lines' => "{$line->entryType->name} amount is required.",
                    ]);
                }

                return [
                    'entry_type_code' => $line->entryType->code,
                    'amount' => $amount,
                ];
            })
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'collection_lines' => 'Enter at least one collection amount.',
            ]);
        }

        return DB::transaction(function () use ($shopId, $businessDate, $group, $lines, $userId, $notes): array {
            $posted = [];
            $referenceId = null;

            foreach ($lines as $line) {
                $result = $this->dailyLedgerService->recordEntry([
                    'shop_id' => $shopId,
                    'business_date' => $businessDate,
                    'entry_type_code' => $line['entry_type_code'],
                    'amount' => $line['amount'],
                    'entered_by' => $userId,
                    'notes' => $notes ?: "{$group->name} collection",
                ]);

                $transaction = $result['transaction'];
                $referenceId ??= (int) $transaction->id;
                $transaction->update([
                    'reference_type' => 'collection_group',
                    'reference_id' => $referenceId,
                ]);
                $posted[] = $transaction->fresh('entryType');
            }

            return [
                'transactions' => $posted,
                'snapshot' => $this->dailyLedgerService->dailySummary($shopId, $businessDate),
            ];
        });
    }
}
