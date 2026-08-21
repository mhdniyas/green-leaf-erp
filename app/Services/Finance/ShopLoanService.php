<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Shop;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopLoanCategorySetting;
use App\Models\ShopLoanEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopLoanService
{
    /**
     * @return Collection<int, ShopLoanCategorySetting>
     */
    public function settingsForShop(Shop $shop): Collection
    {
        return ShopLoanCategorySetting::query()
            ->where('shop_id', $shop->id)
            ->with('category')
            ->get();
    }

    /**
     * @param  array<int, string>  $categoryEffects
     * @param  array<int, mixed>  $defaultDailyAmounts
     */
    public function syncCategorySettings(Shop $shop, array $categoryEffects, array $defaultDailyAmounts = []): void
    {
        $availableCategories = app(OwnedShopAccountingService::class)
            ->availableCategoriesForShop($shop)
            ->keyBy(fn ($category): int => (int) $category->id);

        DB::transaction(function () use ($shop, $categoryEffects, $defaultDailyAmounts, $availableCategories): void {
            ShopLoanCategorySetting::query()
                ->where('shop_id', $shop->id)
                ->delete();

            foreach ($categoryEffects as $categoryId => $effect) {
                $categoryId = (int) $categoryId;
                $category = $availableCategories->get($categoryId);

                if ($effect !== ShopLoanCategorySetting::EffectUseLoan || $category === null || $category->type !== 'expense') {
                    continue;
                }

                ShopLoanCategorySetting::query()->create([
                    'shop_id' => $shop->id,
                    'shop_accounting_category_id' => $categoryId,
                    'effect' => ShopLoanCategorySetting::EffectUseLoan,
                    'default_daily_amount' => round(max(0, (float) ($defaultDailyAmounts[$categoryId] ?? 0)), 2),
                ]);
            }
        });
    }

    public function recordCashMovement(Shop $shop, string $type, Carbon $businessDate, float $amount, string $title, ?string $description, int $userId): ShopLoanEntry
    {
        if (! in_array($type, [ShopLoanEntry::TypeCashGiven, ShopLoanEntry::TypeRepayment], true)) {
            throw ValidationException::withMessages(['type' => 'Invalid loan movement type.']);
        }

        return ShopLoanEntry::query()->create([
            'shop_id' => $shop->id,
            'type' => $type,
            'business_date' => $businessDate->toDateString(),
            'amount' => round($amount, 2),
            'title' => $title,
            'description' => $description,
            'status' => 'approved',
            'created_by' => $userId,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function ledgerRows(Shop $shop, bool $includePending = true, ?Carbon $endDate = null): Collection
    {
        $endDate ??= today();

        $manualRows = collect(ShopLoanEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '<=', $endDate->toDateString())
            ->with('creator')
            ->get()
            ->map(fn (ShopLoanEntry $entry): array => [
                'date' => $entry->business_date?->toDateString() ?? today()->toDateString(),
                'category' => $entry->typeLabel(),
                'title' => $entry->title,
                'description' => $entry->description,
                'amount' => round(abs((float) $entry->amount), 2),
                'signed_amount' => round($entry->loanSignedAmount(), 2),
                'status' => $entry->status,
                'source' => 'loan_entry',
                'source_id' => $entry->id,
                'sort_id' => $entry->id,
            ])->all());

        $lineRows = collect(ShopAccountingEntryLine::query()
            ->where('is_loan_entry', true)
            ->where('type', 'expense')
            ->whereHas('entry', function (Builder $query) use ($shop, $includePending, $endDate): void {
                $query
                    ->where('shop_id', $shop->id)
                    ->whereDate('business_date', '<=', $endDate->toDateString())
                    ->whereIn('status', $includePending ? ['submitted', 'recheck_required', 'approved', 'finalized'] : ['approved', 'finalized']);
            })
            ->with(['entry', 'category'])
            ->get()
            ->map(fn (ShopAccountingEntryLine $line): array => [
                'date' => $line->entry?->business_date?->toDateString() ?? today()->toDateString(),
                'category' => $line->category?->name ?? 'Loan category',
                'title' => $line->description ?: 'Paid from loan',
                'description' => 'Paid from loan',
                'amount' => round((float) $line->amount, 2),
                'signed_amount' => -1 * round((float) $line->amount, 2),
                'status' => $line->entry?->status ?? 'submitted',
                'source' => 'cashbook_line',
                'source_id' => $line->id,
                'sort_id' => $line->id,
            ])->all());

        $runningBalance = 0.0;

        return $manualRows
            ->merge($lineRows)
            ->sortBy([
                ['date', 'asc'],
                ['sort_id', 'asc'],
            ])
            ->values()
            ->map(function (array $row) use (&$runningBalance): array {
                if (in_array($row['status'], ['approved', 'finalized'], true)) {
                    $runningBalance = round($runningBalance + (float) $row['signed_amount'], 2);
                    $row['balance'] = $runningBalance;
                    $row['pending_balance'] = null;
                } else {
                    $row['balance'] = $runningBalance;
                    $row['pending_balance'] = round($runningBalance + (float) $row['signed_amount'], 2);
                }

                return $row;
            })
            ->sortByDesc('date')
            ->values();
    }

    public function approvedBalance(Shop $shop, ?Carbon $endDate = null): float
    {
        return round((float) $this->ledgerRows($shop, false, $endDate)
            ->sum(fn (array $row): float => (float) $row['signed_amount']), 2);
    }

    public function isLoanCategory(Shop $shop, int $categoryId): bool
    {
        return ShopLoanCategorySetting::query()
            ->where('shop_id', $shop->id)
            ->where('shop_accounting_category_id', $categoryId)
            ->exists();
    }
}
