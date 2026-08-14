<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopLedgerCollectionGroup;
use App\Models\Cashbook\ShopLedgerEntrySetting;
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
        return ShopLedgerCollectionGroup::query()
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->with('entryTypes.entryType')
            ->orderBy('display_order')
            ->get();
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
        if (! $group instanceof ShopLedgerCollectionGroup) {
            throw ValidationException::withMessages([
                'collection_group_id' => 'Collection group is not configured for this shop.',
            ]);
        }

        $this->ensureShopLedgerSettings($shopId, $group);

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

    public function deleteCollectionGroup(int $shopId, int $referenceId): array
    {
        $rows = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->where('reference_type', 'collection_group')
            ->where('reference_id', $referenceId)
            ->get();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'reference_id' => 'Collection group entries not found.',
            ]);
        }

        foreach ($rows as $row) {
            if (! $row->canBeEditedByShopOwner()) {
                throw ValidationException::withMessages([
                    'reference_id' => 'Approved collection entries can only be changed from admin cashbook.',
                ]);
            }
        }

        $firstDate = $rows->first()->business_date->toDateString();
        $this->dailyLedgerService->assertDayOpen($shopId, $firstDate);

        DB::transaction(function () use ($rows) {
            foreach ($rows as $tx) {
                $tx->children()->delete();
                $tx->delete();
            }
        });

        return [
            'snapshot' => $this->dailyLedgerService->dailySummary($shopId, $firstDate),
        ];
    }


    private function ensureShopLedgerSettings(int $shopId, ShopLedgerCollectionGroup $group): void
    {
        foreach ($group->entryTypes as $line) {
            $entryType = $line->entryType;
            if (! $entryType) {
                continue;
            }

            ShopLedgerEntrySetting::firstOrCreate(
                [
                    'shop_id' => $shopId,
                    'entry_type_id' => $entryType->id,
                ],
                [
                    'version' => 1,
                    'effective_from' => '2026-01-01',
                    'effective_to' => null,
                    'enabled' => true,
                    'default_funding_source' => 'none',
                    'allowed_funding_sources' => ['none'],
                    'include_in_sales' => $line->role === 'income',
                    'include_in_income' => $line->role === 'income',
                    'include_in_expense' => $line->role === 'expense',
                    'include_in_pl' => true,
                    'include_in_payable' => true,
                    'settlement_behavior' => 'none',
                    'petty_behavior' => 'none',
                    'company_pending_behavior' => 'none',
                    'generates_secondary_entry' => false,
                    'secondary_entry_type_id' => null,
                    'secondary_amount_mode' => 'same_amount',
                    'secondary_amount_value' => null,
                    'display_order' => (int) $line->display_order,
                ]
            );
        }
    }
}
