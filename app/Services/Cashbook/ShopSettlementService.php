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
                'is_company_payable' => true,
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

            // Ensure exactly one settlement is marked as is_company_payable
            $hasPayable = ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('is_company_payable', true)->exists();
            if (! $hasPayable) {
                $target = ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('relation_type', 'default_company_payable')->first()
                    ?? ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('enabled', true)->orderBy('display_order')->first();
                $target?->update(['is_company_payable' => true]);
            }
        });
    }

    /** @return Collection<int, ShopCashbookRelation> */
    public function settlements(int $shopId, bool $enabledOnly = false): Collection
    {
        return ShopCashbookRelation::query()
            ->where('shop_id', $shopId)
            ->when($enabledOnly, fn ($query) => $query->where('enabled', true))
            ->with(['items.setting.entryType', 'items.headerGroup.allowedProducts', 'items.sourceSettlement'])
            ->orderBy('display_order')->orderBy('id')->get();
    }

    public function getCompanyPayableSettlement(int $shopId): ?ShopCashbookRelation
    {
        return ShopCashbookRelation::query()
            ->where('shop_id', $shopId)
            ->where('is_company_payable', true)
            ->where('enabled', true)
            ->with('items.setting.entryType')
            ->first()
            ?? ShopCashbookRelation::query()
                ->where('shop_id', $shopId)
                ->where('is_company_payable', true)
                ->with('items.setting.entryType')
                ->first()
            ?? ShopCashbookRelation::query()
                ->where('shop_id', $shopId)
                ->where('relation_type', 'default_company_payable')
                ->where('enabled', true)
                ->with('items.setting.entryType')
                ->first()
            ?? ShopCashbookRelation::query()
                ->where('shop_id', $shopId)
                ->where('enabled', true)
                ->with('items.setting.entryType')
                ->first();
    }

    /**
     * @param  array{name: string, enabled: bool|string|int, is_company_payable?: bool|string|int, items: array<int, array{setting_id: int|string, role: string}>}  $data
     */
    public function save(ShopLedgerProfile $profile, array $data, ?ShopCashbookRelation $relation = null): ShopCashbookRelation
    {
        return DB::transaction(function () use ($profile, $data, $relation): ShopCashbookRelation {
            ShopLedgerProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $relation = $relation === null
                ? new ShopCashbookRelation(['shop_id' => $profile->shop_id, 'relation_type' => 'formula', 'display_order' => (int) ShopCashbookRelation::where('shop_id', $profile->shop_id)->max('display_order') + 1])
                : ShopCashbookRelation::where('shop_id', $profile->shop_id)->whereKey($relation->id)->lockForUpdate()->firstOrFail();
            $before = $relation->exists ? $relation->only(['name', 'enabled', 'is_company_payable']) + ['items' => $relation->items()->get(['shop_ledger_entry_setting_id', 'role'])->toArray()] : null;

            $markAsPayable = ! empty($data['is_company_payable']);
            if ($markAsPayable) {
                ShopCashbookRelation::where('shop_id', $profile->shop_id)
                    ->where('id', '!=', $relation->id)
                    ->update(['is_company_payable' => false]);
                $relation->is_company_payable = true;
            } elseif (isset($data['is_company_payable']) && ! $data['is_company_payable']) {
                $relation->is_company_payable = false;
                $hasOther = ShopCashbookRelation::where('shop_id', $profile->shop_id)
                    ->where('id', '!=', $relation->id)
                    ->where('is_company_payable', true)
                    ->exists();
                if (! $hasOther) {
                    $other = ShopCashbookRelation::where('shop_id', $profile->shop_id)
                        ->where('id', '!=', $relation->id)
                        ->where('enabled', true)
                        ->first();
                    if ($other) {
                        $other->update(['is_company_payable' => true]);
                    } else {
                        $relation->is_company_payable = true;
                    }
                }
            } elseif (! $relation->exists && ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('is_company_payable', true)->doesntExist()) {
                $relation->is_company_payable = true;
            }

            $relation->fill(['name' => $data['name'], 'enabled' => $data['enabled']])->save();
            $relation->items()->delete();
            $relation->items()->createMany(collect($data['items'])->values()->map(fn (array $item, int $index): array => [
                'shop_ledger_entry_setting_id' => ! empty($item['setting_id']) ? (int) $item['setting_id'] : null,
                'header_group_id' => ! empty($item['header_group_id']) ? (int) $item['header_group_id'] : null,
                'header_mode' => $item['header_mode'] ?? 'all_categories',
                'source_settlement_id' => ! empty($item['source_settlement_id']) ? (int) $item['source_settlement_id'] : null,
                'role' => $item['role'],
                'display_order' => $index,
            ])->all());

            activity('cashbook_settlement')->performedOn($relation)->withProperties([
                'shop_id' => $profile->shop_id,
                'before' => $before,
                'after' => $relation->only(['name', 'enabled', 'is_company_payable']) + ['items' => $relation->items()->get(['shop_ledger_entry_setting_id', 'role'])->toArray()],
            ])->log($before === null ? 'Settlement created' : 'Settlement updated');

            return $relation;
        });
    }

    /**
     * Authoritative Company Payable calculation based on the dynamic Company Payable settlement formula
     * minus verified payments received up to the period/date.
     *
     * @return array<string, mixed>
     */
    public function calculateCompanyPayable(int $shopId, string $startDate, string $endDate, bool $cumulative = false): array
    {
        $payableRelation = $this->getCompanyPayableSettlement($shopId);

        $dateConstraintStart = $cumulative ? '2020-01-01' : $startDate;
        $dateConstraintEnd = $endDate;

        $amounts = [];
        if ($payableRelation) {
            $totals = ShopLedgerTransaction::query()
                ->where('shop_id', $shopId)
                ->whereBetween('business_date', [$dateConstraintStart, $dateConstraintEnd])
                ->whereIn('status', ['posted', 'approved'])
                ->whereNull('voided_at')
                ->selectRaw('entry_type_id, SUM(amount) as total')
                ->groupBy('entry_type_id')
                ->pluck('total', 'entry_type_id');

            foreach ($payableRelation->items as $item) {
                if ($item->setting && (int) $item->setting->shop_id === $shopId) {
                    $amounts[$item->shop_ledger_entry_setting_id] = (float) ($totals[$item->setting->entry_type_id] ?? 0);
                }
            }
        }

        $formulaCalc = $payableRelation ? $this->calculator->calculate($payableRelation, $amounts) : [
            'grossAdditions' => 0.0,
            'grossDeductions' => 0.0,
            'netSettlement' => 0.0,
            'items' => [],
        ];

        // Verified payments (shop_paid_company transactions that are active and posted/approved)
        $paymentTransactions = ShopLedgerTransaction::query()
            ->with(['entryType', 'companyAccount'])
            ->where('shop_id', $shopId)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'shop_paid_company'))
            ->whereBetween('business_date', [$dateConstraintStart, $dateConstraintEnd])
            ->whereIn('status', ['posted', 'approved'])
            ->whereNull('voided_at')
            ->orderBy('business_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $verifiedPaymentsTotal = round((float) $paymentTransactions->sum('amount'), 2);
        $formulaNet = round((float) ($formulaCalc['netSettlement'] ?? 0.0), 2);
        $remainingPayable = round($formulaNet - $verifiedPaymentsTotal, 2);

        $paymentsList = $paymentTransactions->map(function (ShopLedgerTransaction $tx): array {
            return [
                'id' => $tx->id,
                'amount' => (float) $tx->amount,
                'business_date' => $tx->business_date?->toDateString(),
                'payment_method' => $tx->companyAccount?->account_type === 'cash' ? 'Cash' : ($tx->companyAccount?->name ?? 'Direct Payment'),
                'notes' => $tx->notes ?? 'Payment Received',
                'status' => $tx->status,
                'reference' => $tx->reference_id ? '#'.$tx->reference_id : null,
                'company_account' => $tx->companyAccount?->name,
            ];
        })->values()->all();

        return [
            'relation_id' => $payableRelation?->id,
            'public_uuid' => $payableRelation?->public_uuid,
            'name' => $payableRelation?->name ?? 'Company Payable',
            'is_company_payable' => true,
            'enabled' => (bool) ($payableRelation?->enabled ?? true),
            'grossAdditions' => (float) ($formulaCalc['grossAdditions'] ?? 0.0),
            'grossDeductions' => (float) ($formulaCalc['grossDeductions'] ?? 0.0),
            'netSettlement' => $formulaNet,
            'formula_net' => $formulaNet,
            'items' => $formulaCalc['items'] ?? [],
            'category_breakdown' => $formulaCalc['items'] ?? [],
            'verified_payments_total' => $verifiedPaymentsTotal,
            'verified_payments_received' => $verifiedPaymentsTotal,
            'remaining_company_payable' => $remainingPayable,
            'payments' => $paymentsList,
        ];
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
        $allSettings = ShopLedgerEntrySetting::where('shop_id', $shopId)->get();
        foreach ($allSettings as $setting) {
            $amounts[$setting->id] = (float) ($totals[$setting->entry_type_id] ?? 0);
        }

        $companyPayableSummary = $this->calculateCompanyPayable($shopId, $startDate, $endDate);

        return $relations->map(function (ShopCashbookRelation $relation) use (&$amounts, $companyPayableSummary): array {
            $res = $this->calculator->calculate($relation, $amounts);
            $amounts['settlement_'.$relation->id] = $res['netSettlement'];
            $isPayable = (bool) $relation->is_company_payable || $relation->relation_type === 'default_company_payable';
            $res['is_company_payable'] = $isPayable;
            if ($isPayable) {
                $res['verified_payments_total'] = $companyPayableSummary['verified_payments_total'];
                $res['remaining_company_payable'] = $companyPayableSummary['remaining_company_payable'];
                $res['payments'] = $companyPayableSummary['payments'];
            }

            return $res;
        })->all();
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
