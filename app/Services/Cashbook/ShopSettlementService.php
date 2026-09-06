<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ShopSettlementService
{
    public function __construct(private readonly RelationSettlementCalculator $calculator) {}

    public function ensureDefaults(ShopLedgerProfile $profile): void
    {
        if (ShopCashbookRelation::where('shop_id', $profile->shop_id)->whereIn('relation_type', ['default_balance', 'default_income', 'default_expense', 'default_company_payable'])->distinct()->count('relation_type') === 4) {
            return;
        }

        DB::transaction(function () use ($profile): void {
            ShopLedgerProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $settings = $profile->entrySettings()->where('enabled', true)->get();

            // 1. Balance Settlement (All Income - All Expense)
            $balance = ShopCashbookRelation::query()->firstOrCreate([
                'shop_id' => $profile->shop_id,
                'relation_type' => 'default_balance',
            ], [
                'name' => 'Balance',
                'enabled' => true,
                'display_order' => -4,
            ]);

            if ($balance->wasRecentlyCreated) {
                $incomeItems = $settings->filter(fn (ShopLedgerEntrySetting $s): bool => (bool) ($s->include_in_income || $s->include_in_sales));
                $expenseItems = $settings->filter(fn (ShopLedgerEntrySetting $s): bool => (bool) $s->include_in_expense);

                $items = collect();
                foreach ($incomeItems as $setting) {
                    $items->push([
                        'shop_ledger_entry_setting_id' => $setting->id,
                        'role' => $setting->payable_direction === 'minus' ? 'subtract' : 'add',
                    ]);
                }
                foreach ($expenseItems as $setting) {
                    $items->push([
                        'shop_ledger_entry_setting_id' => $setting->id,
                        'role' => 'subtract',
                    ]);
                }

                $balance->items()->createMany($items->values()->map(fn (array $item, int $index): array => [
                    'shop_ledger_entry_setting_id' => $item['shop_ledger_entry_setting_id'],
                    'role' => $item['role'],
                    'display_order' => $index,
                ])->all());
            }

            // 2. Income Settlement (All Income)
            $income = ShopCashbookRelation::query()->firstOrCreate([
                'shop_id' => $profile->shop_id,
                'relation_type' => 'default_income',
            ], [
                'name' => 'Income',
                'enabled' => true,
                'display_order' => -3,
            ]);

            if ($income->wasRecentlyCreated) {
                $incomeItems = $settings->filter(fn (ShopLedgerEntrySetting $s): bool => (bool) ($s->include_in_income || $s->include_in_sales));

                $items = collect();
                foreach ($incomeItems as $setting) {
                    $items->push([
                        'shop_ledger_entry_setting_id' => $setting->id,
                        'role' => $setting->payable_direction === 'minus' ? 'subtract' : 'add',
                    ]);
                }

                $income->items()->createMany($items->values()->map(fn (array $item, int $index): array => [
                    'shop_ledger_entry_setting_id' => $item['shop_ledger_entry_setting_id'],
                    'role' => $item['role'],
                    'display_order' => $index,
                ])->all());
            }

            // 3. Expense Settlement (All Expense)
            $expense = ShopCashbookRelation::query()->firstOrCreate([
                'shop_id' => $profile->shop_id,
                'relation_type' => 'default_expense',
            ], [
                'name' => 'Expense',
                'enabled' => true,
                'display_order' => -2,
            ]);

            if ($expense->wasRecentlyCreated) {
                $expenseItems = $settings->filter(fn (ShopLedgerEntrySetting $s): bool => (bool) $s->include_in_expense);

                $items = collect();
                foreach ($expenseItems as $setting) {
                    $items->push([
                        'shop_ledger_entry_setting_id' => $setting->id,
                        'role' => 'add',
                    ]);
                }

                $expense->items()->createMany($items->values()->map(fn (array $item, int $index): array => [
                    'shop_ledger_entry_setting_id' => $item['shop_ledger_entry_setting_id'],
                    'role' => $item['role'],
                    'display_order' => $index,
                ])->all());
            }

            // 4. Company Payable Settlement (Shop-held Collections - Shop Cash Expenses)
            $payable = ShopCashbookRelation::query()->firstOrCreate([
                'shop_id' => $profile->shop_id,
                'relation_type' => 'default_company_payable',
            ], [
                'name' => 'Company Payable',
                'enabled' => true,
                'display_order' => -1,
            ]);

            if ($payable->wasRecentlyCreated) {
                $shopIncomeItems = $settings->filter(function (ShopLedgerEntrySetting $s): bool {
                    $isIncome = (bool) ($s->include_in_income || $s->include_in_sales);
                    $isDirectBank = (bool) $s->company_account_id;

                    return $isIncome && ! $isDirectBank;
                });

                $shopExpenseItems = $settings->filter(function (ShopLedgerEntrySetting $s): bool {
                    $isExpense = (bool) $s->include_in_expense;
                    $fundingSource = strtolower((string) ($s->default_funding_source ?? 'sales'));

                    return $isExpense && in_array($fundingSource, ['sales', 'shop_cash', ''], true);
                });

                $items = collect();
                foreach ($shopIncomeItems as $setting) {
                    $items->push([
                        'shop_ledger_entry_setting_id' => $setting->id,
                        'role' => $setting->payable_direction === 'minus' ? 'subtract' : 'add',
                    ]);
                }
                foreach ($shopExpenseItems as $setting) {
                    $items->push([
                        'shop_ledger_entry_setting_id' => $setting->id,
                        'role' => 'subtract',
                    ]);
                }

                $payable->items()->createMany($items->values()->map(fn (array $item, int $index): array => [
                    'shop_ledger_entry_setting_id' => $item['shop_ledger_entry_setting_id'],
                    'role' => $item['role'],
                    'display_order' => $index,
                ])->all());
            }
        });
    }

    /** @return Collection<int, ShopCashbookRelation> */
    public function settlements(int $shopId, bool $enabledOnly = false): Collection
    {
        return ShopCashbookRelation::query()
            ->where('shop_id', $shopId)
            ->when($enabledOnly, fn ($query) => $query->where('enabled', true))
            ->with('items.setting.entryType')
            ->orderBy('display_order')->orderBy('id')->get();
    }

    /**
     * @param  array{name: string, enabled: bool|string|int, items: array<int, array{setting_id: int|string, role: string}>}  $data
     */
    public function save(ShopLedgerProfile $profile, array $data, ?ShopCashbookRelation $relation = null): ShopCashbookRelation
    {
        return DB::transaction(function () use ($profile, $data, $relation): ShopCashbookRelation {
            ShopLedgerProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $relation = $relation === null
                ? new ShopCashbookRelation(['shop_id' => $profile->shop_id, 'relation_type' => 'formula', 'display_order' => (int) ShopCashbookRelation::where('shop_id', $profile->shop_id)->max('display_order') + 1])
                : ShopCashbookRelation::where('shop_id', $profile->shop_id)->whereKey($relation->id)->lockForUpdate()->firstOrFail();
            $before = $relation->exists ? $relation->only(['name', 'enabled']) + ['items' => $relation->items()->get(['shop_ledger_entry_setting_id', 'role'])->toArray()] : null;
            $relation->fill(['name' => $data['name'], 'enabled' => $data['enabled']])->save();
            $relation->items()->delete();
            $relation->items()->createMany(collect($data['items'])->values()->map(fn (array $item, int $index): array => [
                'shop_ledger_entry_setting_id' => $item['setting_id'],
                'role' => $item['role'],
                'display_order' => $index,
            ])->all());

            activity('cashbook_settlement')->performedOn($relation)->withProperties([
                'shop_id' => $profile->shop_id,
                'before' => $before,
                'after' => $relation->only(['name', 'enabled']) + ['items' => $relation->items()->get(['shop_ledger_entry_setting_id', 'role'])->toArray()],
            ])->log($before === null ? 'Settlement created' : 'Settlement updated');

            return $relation;
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function summary(int $shopId, string $startDate, string $endDate): array
    {
        $relations = $this->settlements($shopId, true);
        $totals = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereIn('status', ['posted', 'approved'])
            ->whereNull('voided_at')
            ->selectRaw('entry_type_id, SUM(amount) as total')
            ->groupBy('entry_type_id')->pluck('total', 'entry_type_id');
        $amounts = [];
        foreach ($relations as $relation) {
            foreach ($relation->items as $item) {
                if ($item->setting && (int) $item->setting->shop_id === $shopId) {
                    $amounts[$item->shop_ledger_entry_setting_id] = (float) ($totals[$item->setting->entry_type_id] ?? 0);
                }
            }
        }

        return $relations->map(fn (ShopCashbookRelation $relation): array => $this->calculator->calculate($relation, $amounts))->all();
    }

    public function copyToShop(ShopCashbookRelation $sourceRelation, ShopLedgerProfile $targetProfile): ShopCashbookRelation
    {
        return DB::transaction(function () use ($sourceRelation, $targetProfile): ShopCashbookRelation {
            $sourceRelation->loadMissing('items.setting.entryType');
            $targetSettings = $targetProfile->entrySettings()->with('entryType')->get();

            $targetRelation = ShopCashbookRelation::query()->firstOrNew([
                'shop_id' => $targetProfile->shop_id,
                'name' => $sourceRelation->name,
            ]);

            $targetRelation->relation_type = $sourceRelation->relation_type;
            $targetRelation->enabled = $sourceRelation->enabled;
            $targetRelation->display_order = $targetRelation->exists ? $targetRelation->display_order : (int) ShopCashbookRelation::where('shop_id', $targetProfile->shop_id)->max('display_order') + 1;
            $targetRelation->save();

            $targetRelation->items()->delete();

            $newItems = [];
            foreach ($sourceRelation->items as $index => $sourceItem) {
                $sourceEntryTypeId = $sourceItem->setting?->entry_type_id;
                $matchingSetting = $targetSettings->firstWhere('entry_type_id', $sourceEntryTypeId);
                if ($matchingSetting) {
                    $newItems[] = [
                        'shop_ledger_entry_setting_id' => $matchingSetting->id,
                        'role' => $sourceItem->role,
                        'display_order' => $index,
                    ];
                }
            }

            if (! empty($newItems)) {
                $targetRelation->items()->createMany($newItems);
            }

            activity('cashbook_settlement')->performedOn($targetRelation)->withProperties([
                'source_shop_id' => $sourceRelation->shop_id,
                'target_shop_id' => $targetProfile->shop_id,
                'relation_name' => $sourceRelation->name,
            ])->log('Settlement copied to shop');

            return $targetRelation;
        });
    }
}
