<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ShopSettlementService
{
    public function __construct(private readonly RelationSettlementCalculator $calculator) {}

    public function ensureDefaults(ShopLedgerProfile|Shop $profileOrShop): void
    {
        $profile = $profileOrShop instanceof Shop
            ? ShopLedgerProfile::query()->where('shop_id', (int) $profileOrShop->id)->first()
            : $profileOrShop;

        if (! $profile) {
            return;
        }
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

            // Ensure exactly one settlement is marked as is_net_balance
            $hasNetBalance = ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('is_net_balance', true)->exists();
            if (! $hasNetBalance) {
                $target = ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('relation_type', 'default_balance')->first()
                    ?? ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('name', 'like', '%Balance%')->first()
                    ?? ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('enabled', true)->orderBy('display_order')->first();
                $target?->update(['is_net_balance' => true]);
            }

            // Ensure exactly one settlement is marked as is_payment_payable
            $hasPaymentPayable = ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('is_payment_payable', true)->exists();
            if (! $hasPaymentPayable) {
                $target = ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('is_company_payable', true)->first()
                    ?? ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('relation_type', 'default_company_payable')->first()
                    ?? ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('enabled', true)->orderBy('display_order')->first();
                $target?->update(['is_payment_payable' => true]);
            }

            // Ensure exactly one settlement is marked as is_payment_paid
            $hasPaymentPaid = ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('is_payment_paid', true)->exists();
            if (! $hasPaymentPaid) {
                $paid = ShopCashbookRelation::query()->firstOrCreate([
                    'shop_id' => $profile->shop_id,
                    'relation_type' => 'default_payment_paid',
                ], [
                    'name' => 'Paid (Company Received)',
                    'enabled' => true,
                    'is_payment_paid' => true,
                    'display_order' => 10,
                ]);

                if ($paid->wasRecentlyCreated) {
                    // Seed with direct company payment items and shop_paid_company if available
                    $paidSettings = $settings->filter(function (ShopLedgerEntrySetting $s): bool {
                        $code = $s->entryType?->code;
                        $isDirectBank = (bool) $s->company_account_id;

                        return $isDirectBank || $code === 'shop_paid_company';
                    });

                    $paid->items()->createMany($paidSettings->values()->map(fn (ShopLedgerEntrySetting $s, int $index): array => [
                        'shop_ledger_entry_setting_id' => $s->id,
                        'role' => 'add',
                        'display_order' => $index,
                    ])->all());
                } else {
                    $paid->update(['is_payment_paid' => true]);
                }
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

    public function getDefaultPaymentPayable(int $shopId): ?ShopCashbookRelation
    {
        return ShopCashbookRelation::query()
            ->where('shop_id', $shopId)
            ->where('is_payment_payable', true)
            ->where('enabled', true)
            ->with('items.setting.entryType')
            ->first()
            ?? ShopCashbookRelation::query()
                ->where('shop_id', $shopId)
                ->where('is_payment_payable', true)
                ->with('items.setting.entryType')
                ->first()
            ?? $this->getCompanyPayableSettlement($shopId);
    }

    public function getDefaultPaymentPaid(int $shopId): ?ShopCashbookRelation
    {
        return ShopCashbookRelation::query()
            ->where('shop_id', $shopId)
            ->where('is_payment_paid', true)
            ->where('enabled', true)
            ->with('items.setting.entryType')
            ->first()
            ?? ShopCashbookRelation::query()
                ->where('shop_id', $shopId)
                ->where('is_payment_paid', true)
                ->with('items.setting.entryType')
                ->first()
            ?? ShopCashbookRelation::query()
                ->where('shop_id', $shopId)
                ->where('relation_type', 'default_payment_paid')
                ->where('enabled', true)
                ->with('items.setting.entryType')
                ->first()
            ?? ShopCashbookRelation::query()
                ->where('shop_id', $shopId)
                ->where('name', 'like', '%Paid%')
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

            if (! empty($data['is_net_balance'])) {
                ShopCashbookRelation::where('shop_id', $profile->shop_id)
                    ->where('id', '!=', $relation->id)
                    ->update(['is_net_balance' => false]);
                $relation->is_net_balance = true;
            } elseif (isset($data['is_net_balance']) && ! $data['is_net_balance']) {
                $relation->is_net_balance = false;
            }

            if (! empty($data['is_payment_payable'])) {
                ShopCashbookRelation::where('shop_id', $profile->shop_id)
                    ->where('id', '!=', $relation->id)
                    ->update(['is_payment_payable' => false]);
                $relation->is_payment_payable = true;
            } elseif (isset($data['is_payment_payable']) && ! $data['is_payment_payable']) {
                $relation->is_payment_payable = false;
            }

            if (! empty($data['is_payment_paid'])) {
                ShopCashbookRelation::where('shop_id', $profile->shop_id)
                    ->where('id', '!=', $relation->id)
                    ->update(['is_payment_paid' => false]);
                $relation->is_payment_paid = true;
            } elseif (isset($data['is_payment_paid']) && ! $data['is_payment_paid']) {
                $relation->is_payment_paid = false;
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

        $relationsMap = $relations->keyBy('id');
        $computed = [];
        $visiting = [];

        $computeRelation = function (ShopCashbookRelation $relation) use (&$computeRelation, &$computed, &$visiting, &$amounts, $relationsMap, $companyPayableSummary): array {
            $rId = (int) $relation->id;
            if (isset($computed[$rId])) {
                return $computed[$rId];
            }
            if (isset($visiting[$rId])) {
                return [
                    'relation_id' => $relation->id,
                    'public_uuid' => $relation->public_uuid,
                    'name' => $relation->name,
                    'relation_type' => $relation->relation_type,
                    'is_company_payable' => (bool) $relation->is_company_payable || $relation->relation_type === 'default_company_payable',
                    'enabled' => (bool) $relation->enabled,
                    'grossAdditions' => 0.0,
                    'grossDeductions' => 0.0,
                    'netSettlement' => 0.0,
                    'items' => [],
                ];
            }
            $visiting[$rId] = true;

            foreach ($relation->items as $item) {
                $sourceId = $item->source_settlement_id ? (int) $item->source_settlement_id : null;
                if ($sourceId !== null && isset($relationsMap[$sourceId])) {
                    $sourceRelation = $relationsMap[$sourceId];
                    $sourceRes = $computeRelation($sourceRelation);
                    $amounts['settlement_'.$sourceId] = $sourceRes['netSettlement'];
                }
            }

            $res = $this->calculator->calculate($relation, $amounts);
            $amounts['settlement_'.$relation->id] = $res['netSettlement'];
            $isPayable = (bool) $relation->is_company_payable || $relation->relation_type === 'default_company_payable';
            $res['is_company_payable'] = $isPayable;
            if ($isPayable) {
                $res['verified_payments_total'] = $companyPayableSummary['verified_payments_total'];
                $res['remaining_company_payable'] = $companyPayableSummary['remaining_company_payable'];
                $res['payments'] = $companyPayableSummary['payments'];
            }

            unset($visiting[$rId]);
            $computed[$rId] = $res;

            return $res;
        };

        return $relations->map(fn (ShopCashbookRelation $relation): array => $computeRelation($relation))->all();
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

    public function setNetBalance(ShopLedgerProfile $profile, ShopCashbookRelation $relation): void
    {
        DB::transaction(function () use ($profile, $relation): void {
            ShopCashbookRelation::where('shop_id', $profile->shop_id)
                ->update(['is_net_balance' => false]);

            ShopCashbookRelation::where('shop_id', $profile->shop_id)
                ->whereKey($relation->id)
                ->update(['is_net_balance' => true]);

            activity('cashbook_settlement')->performedOn($relation)->withProperties([
                'shop_id' => $profile->shop_id,
                'relation_name' => $relation->name,
            ])->log('Settlement marked as Net Balance');
        });
    }

    public function setDefaultPaymentPayable(ShopLedgerProfile $profile, ShopCashbookRelation $relation): void
    {
        DB::transaction(function () use ($profile, $relation): void {
            ShopCashbookRelation::where('shop_id', $profile->shop_id)
                ->update(['is_payment_payable' => false]);

            ShopCashbookRelation::where('shop_id', $profile->shop_id)
                ->whereKey($relation->id)
                ->update(['is_payment_payable' => true]);

            activity('cashbook_settlement')->performedOn($relation)->withProperties([
                'shop_id' => $profile->shop_id,
                'relation_name' => $relation->name,
            ])->log('Settlement marked as Default Payment Payable');
        });
    }

    public function setDefaultPaymentPaid(ShopLedgerProfile $profile, ShopCashbookRelation $relation): void
    {
        DB::transaction(function () use ($profile, $relation): void {
            ShopCashbookRelation::where('shop_id', $profile->shop_id)
                ->update(['is_payment_paid' => false]);

            ShopCashbookRelation::where('shop_id', $profile->shop_id)
                ->whereKey($relation->id)
                ->update(['is_payment_paid' => true]);

            activity('cashbook_settlement')->performedOn($relation)->withProperties([
                'shop_id' => $profile->shop_id,
                'relation_name' => $relation->name,
            ])->log('Settlement marked as Default Payment Paid');
        });
    }

    /**
     * Get the configured payment settlement relation for a shop.
     */
    public function getPaymentSettlement(ShopLedgerProfile|Shop|int $shop): ?ShopCashbookRelation
    {
        $shopId = $shop instanceof Shop ? (int) $shop->id : ($shop instanceof ShopLedgerProfile ? (int) $shop->shop_id : (int) $shop);
        $profile = $shop instanceof ShopLedgerProfile ? $shop : ShopLedgerProfile::query()->where('shop_id', $shopId)->first();
        if ($profile) {
            $this->ensureDefaults($profile);
        }

        $config = $profile ? $profile->getPaymentConfiguration() : [];

        $settlementId = $config['payment_settlement_id'] ?? null;
        if ($settlementId) {
            $relation = ShopCashbookRelation::query()->where('shop_id', $shopId)->where('id', $settlementId)->first();
            if ($relation) {
                return $relation;
            }
        }

        // Fallback: Check relation marked is_payment_payable = true
        $relation = ShopCashbookRelation::query()->where('shop_id', $shopId)->where('is_payment_payable', true)->first();
        if ($relation) {
            return $relation;
        }

        // Safety fallback: First available relation for shop
        return ShopCashbookRelation::query()->where('shop_id', $shopId)->first();
    }

    /**
     * Resolve target allocation categories configured inside a given settlement relation.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, category: string, role: string, setting_id: int, entry_type_id: int}>
     */
    public function resolveSettlementAllocationTargets(?ShopCashbookRelation $relation): \Illuminate\Support\Collection
    {
        if (! $relation) {
            return collect();
        }

        $relation->loadMissing([
            'items.setting.entryType',
            'items.headerGroup.entrySettings.entryType',
            'items.sourceSettlement.items.setting.entryType',
        ]);

        $targets = collect();

        foreach ($relation->items as $item) {
            if ($item->setting) {
                $setting = $item->setting;
                $categoryType = strtolower((string) ($setting->entryType?->category ?? ''));
                $isExpense = $categoryType === 'expense' || $item->role === 'subtract' || (bool) $setting->include_in_expense;

                if ($isExpense) {
                    $targets->push([
                        'id' => (int) $setting->id,
                        'name' => $setting->displayName(),
                        'category' => $setting->entryType?->category ?? 'expense',
                        'role' => $item->role ?? 'subtract',
                        'setting_id' => (int) $setting->id,
                        'entry_type_id' => (int) $setting->entry_type_id,
                    ]);
                }
            } elseif ($item->headerGroup) {
                foreach ($item->headerGroup->entrySettings as $setting) {
                    $categoryType = strtolower((string) ($setting->entryType?->category ?? ''));
                    $isExpense = $categoryType === 'expense' || $item->role === 'subtract' || (bool) $setting->include_in_expense;

                    if ($isExpense) {
                        $targets->push([
                            'id' => (int) $setting->id,
                            'name' => $setting->displayName(),
                            'category' => $setting->entryType?->category ?? 'expense',
                            'role' => $item->role ?? 'subtract',
                            'setting_id' => (int) $setting->id,
                            'entry_type_id' => (int) $setting->entry_type_id,
                        ]);
                    }
                }
            } elseif ($item->sourceSettlement) {
                $subTargets = $this->resolveSettlementAllocationTargets($item->sourceSettlement);
                $targets = $targets->merge($subTargets);
            }
        }

        return $targets->unique('id')->values();
    }

    /**
     * Resolve the active Auto Allocation target categories configured under PAYABLE for a shop.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, category: string, role: string, setting_id: int, entry_type_id: int}>
     */
    public function resolvePayableAllocationTargets(ShopLedgerProfile|Shop|int $shop): \Illuminate\Support\Collection
    {
        $shopId = $shop instanceof Shop ? (int) $shop->id : ($shop instanceof ShopLedgerProfile ? (int) $shop->shop_id : (int) $shop);
        $profile = $shop instanceof ShopLedgerProfile ? $shop : ShopLedgerProfile::query()->where('shop_id', $shopId)->first();
        if ($profile) {
            $this->ensureDefaults($profile);
        }

        $config = $profile ? $profile->getPaymentConfiguration() : [];
        $payableConfig = $config['payable'] ?? ['source' => 'settlement', 'category_ids' => [], 'settlement_id' => null];
        $payableSource = $payableConfig['source'] ?? 'settlement';

        if ($payableSource === 'categories') {
            $categoryIds = array_values(array_map('intval', (array) ($payableConfig['category_ids'] ?? [])));
            if (! empty($categoryIds)) {
                $settings = ShopLedgerEntrySetting::with('entryType')
                    ->where('shop_id', $shopId)
                    ->whereIn('id', $categoryIds)
                    ->get();

                return $settings->map(function (ShopLedgerEntrySetting $setting): array {
                    return [
                        'id' => (int) $setting->id,
                        'name' => $setting->displayName(),
                        'category' => $setting->entryType?->category ?? 'expense',
                        'role' => $setting->payable_direction === 'minus' ? 'subtract' : 'add',
                        'setting_id' => (int) $setting->id,
                        'entry_type_id' => (int) $setting->entry_type_id,
                    ];
                })->values();
            }
        }

        // Source is 'settlement' or fallback
        $settlementId = ! empty($payableConfig['settlement_id']) ? (int) $payableConfig['settlement_id'] : null;
        $relation = $settlementId ? ShopCashbookRelation::query()->where('shop_id', $shopId)->where('id', $settlementId)->first() : null;

        if (! $relation) {
            $relation = $this->getDefaultPaymentPayable($shopId);
        }

        return $this->resolveSettlementAllocationTargets($relation);
    }

    /**
     * Save the Payments configuration (Payment Settlement mapping, Payable, and Sales Collections).
     *
     * @param  array{
     *     payment_settlement_id?: ?int,
     *     payable?: array{source?: string, category_ids?: array<int, int>, settlement_id?: ?int},
     *     sales_collections?: array{source?: string, direct_category_ids?: array<int, int>, cash_category_ids?: array<int, int>, settlement_id?: ?int},
     *     direct_to_company?: array{source?: string, category_ids?: array<int, int>, settlement_id?: ?int},
     *     paid?: array{source?: string, category_ids?: array<int, int>, settlement_id?: ?int}
     * }  $config
     */
    public function savePaymentConfiguration(ShopLedgerProfile $profile, array $config): void
    {
        DB::transaction(function () use ($profile, $config): void {
            $payableInput = $config['payable'] ?? [];
            $payableSource = ($payableInput['source'] ?? '') === 'categories' ? 'categories' : 'settlement';
            $payableSettlementId = ! empty($payableInput['settlement_id']) ? (int) $payableInput['settlement_id'] : null;

            $paymentSettlementId = ! empty($config['payment_settlement_id']) ? (int) $config['payment_settlement_id'] : $payableSettlementId;

            $salesInput = $config['sales_collections'] ?? [];
            $directInput = $config['direct_to_company'] ?? $config['paid'] ?? [];

            $salesSource = ($salesInput['source'] ?? ($directInput['source'] ?? 'settlement')) === 'categories' ? 'categories' : 'settlement';
            $salesDirectIds = array_values(array_map('intval', (array) ($salesInput['direct_category_ids'] ?? ($directInput['category_ids'] ?? []))));
            $salesCashIds = array_values(array_map('intval', (array) ($salesInput['cash_category_ids'] ?? [])));
            $salesSettlementId = ! empty($salesInput['settlement_id']) ? (int) $salesInput['settlement_id'] : (! empty($directInput['settlement_id']) ? (int) $directInput['settlement_id'] : null);

            $directClean = [
                'source' => $salesSource,
                'category_ids' => $salesDirectIds,
                'settlement_id' => $salesSettlementId,
            ];

            $salesClean = [
                'source' => $salesSource,
                'direct_category_ids' => $salesDirectIds,
                'cash_category_ids' => $salesCashIds,
                'category_ids' => $salesDirectIds,
                'settlement_id' => $salesSettlementId,
            ];

            $cleanConfig = [
                'payment_settlement_id' => $paymentSettlementId,
                'payable' => [
                    'source' => $payableSource,
                    'category_ids' => array_values(array_map('intval', (array) ($payableInput['category_ids'] ?? []))),
                    'settlement_id' => $payableSettlementId,
                ],
                'sales_collections' => $salesClean,
                'direct_to_company' => $directClean,
                'paid' => $directClean,
            ];

            $profile->update(['payment_configuration' => $cleanConfig]);

            // Sync is_payment_payable flag on relations
            if ($cleanConfig['payable']['source'] === 'settlement' && $cleanConfig['payable']['settlement_id']) {
                ShopCashbookRelation::where('shop_id', $profile->shop_id)->update(['is_payment_payable' => false]);
                ShopCashbookRelation::where('shop_id', $profile->shop_id)->whereKey($cleanConfig['payable']['settlement_id'])->update(['is_payment_payable' => true]);
            }

            if ($cleanConfig['direct_to_company']['source'] === 'settlement' && $cleanConfig['direct_to_company']['settlement_id']) {
                ShopCashbookRelation::where('shop_id', $profile->shop_id)->update(['is_payment_paid' => false]);
                ShopCashbookRelation::where('shop_id', $profile->shop_id)->whereKey($cleanConfig['direct_to_company']['settlement_id'])->update(['is_payment_paid' => true]);
            }

            activity('cashbook_settings')->performedOn($profile)->withProperties([
                'shop_id' => $profile->shop_id,
                'payment_configuration' => $cleanConfig,
            ])->log('Updated shop payments configuration');
        });
    }

    /**
     * Calculate shop payments:
     * - Payable: Expenses configured for payment/allocation
     * - Sales Collections: Direct to Company (Paytm/Card/UPI) + Cash in Shop
     * - Direct to Company: Bank-connected collections (auto received)
     * - Manual Payments: Cash / Shop Balance manually sent later to company
     * - Shop Balance: Opening + Money Kept by Shop - Expenses Paid - Manual Received
     *
     * @return array{
     *     payable: float,
     *     direct_to_company: float,
     *     paid: float,
     *     total_sales: float,
     *     cash_in_shop: float,
     *     shop_collections: float,
     *     sales_collections: array{total_sales: float, direct_to_company: float, cash_in_shop: float, items: array<int, array<string, mixed>>},
     *     sales_collection_items: array<int, array<string, mixed>>,
     *     cash_collection_items: array<int, array<string, mixed>>,
     *     manual_payments: array{total: float, received: float, pending: float, requests: \Illuminate\Support\Collection<int, ShopInvoicePaymentRequest>},
     *     manual_total: float,
     *     manual_received: float,
     *     manual_pending: float,
     *     manual_requests: \Illuminate\Support\Collection<int, ShopInvoicePaymentRequest>,
     *     shop_balance: float,
     *     payable_source: string,
     *     sales_source: string,
     *     direct_to_company_source: string,
     *     paid_source: string,
     *     payable_relation: ?ShopCashbookRelation,
     *     direct_relation: ?ShopCashbookRelation,
     *     paid_relation: ?ShopCashbookRelation,
     *     payable_items: array<int, array<string, mixed>>,
     *     direct_to_company_items: array<int, array<string, mixed>>,
     *     direct_items: array<int, array<string, mixed>>,
     *     paid_items: array<int, array<string, mixed>>,
     *     expense_payables: array<int, array<string, mixed>>,
     *     settled_expenses: array<int, array<string, mixed>>
     * }
     */
    public function calculateShopPayments(int $shopId, string $startDate, string $endDate): array
    {
        $profile = ShopLedgerProfile::where('shop_id', $shopId)->first();
        $config = $profile?->getPaymentConfiguration() ?? [
            'payable' => ['source' => 'settlement', 'category_ids' => [], 'settlement_id' => null],
            'sales_collections' => ['source' => 'settlement', 'direct_category_ids' => [], 'cash_category_ids' => [], 'settlement_id' => null],
            'direct_to_company' => ['source' => 'settlement', 'category_ids' => [], 'settlement_id' => null],
            'paid' => ['source' => 'settlement', 'category_ids' => [], 'settlement_id' => null],
        ];

        $totals = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereIn('status', ['posted', 'approved'])
            ->whereNull('voided_at')
            ->selectRaw('entry_type_id, SUM(amount) as total')
            ->groupBy('entry_type_id')
            ->pluck('total', 'entry_type_id');

        $amounts = [];
        $settings = ShopLedgerEntrySetting::with(['entryType', 'companyAccount'])->where('shop_id', $shopId)->get();
        foreach ($settings as $setting) {
            $amounts[$setting->id] = (float) ($totals[$setting->entry_type_id] ?? 0);
        }

        // 1. Calculate Payable (expenses configured for payment/allocation)
        $payableConfig = $config['payable'];
        $payableSource = $payableConfig['source'];
        $payableRelation = null;
        $payableItems = [];
        $payable = 0.0;

        if ($payableSource === 'categories') {
            $categoryIds = array_map('intval', (array) ($payableConfig['category_ids'] ?? []));
            $selectedSettings = $settings->whereIn('id', $categoryIds);
            foreach ($selectedSettings as $s) {
                $amt = round((float) ($amounts[$s->id] ?? 0.0), 2);
                $payable += $amt;
                $payableItems[] = [
                    'id' => $s->id,
                    'entry_setting_id' => $s->id,
                    'name' => $s->displayName(),
                    'code' => $s->entryType?->code ?? '',
                    'category' => $s->entryType?->category ?? '',
                    'amount' => $amt,
                    'role' => 'add',
                ];
            }
            $payable = round($payable, 2);
        } else {
            $settlementId = $payableConfig['settlement_id'] ?? null;
            if ($settlementId) {
                $payableRelation = ShopCashbookRelation::where('shop_id', $shopId)->where('id', $settlementId)->first();
            }
            if (! $payableRelation) {
                $payableRelation = $this->getDefaultPaymentPayable($shopId);
            }
            $payableCalc = $payableRelation ? $this->calculator->calculate($payableRelation, $amounts) : [
                'netSettlement' => 0.0,
                'items' => [],
            ];
            $payable = round((float) ($payableCalc['netSettlement'] ?? 0.0), 2);
            $payableItems = $payableCalc['items'] ?? [];
        }

        // 2. Calculate Sales Collections (Direct to Company + Cash in Shop)
        $salesConfig = $config['sales_collections'] ?? [];
        $directConfig = $config['direct_to_company'] ?? $config['paid'];
        $salesSource = $salesConfig['source'] ?? ($directConfig['source'] ?? 'settlement');
        $salesDirectIds = array_map('intval', (array) ($salesConfig['direct_category_ids'] ?? ($directConfig['category_ids'] ?? [])));
        $salesCashIds = array_map('intval', (array) ($salesConfig['cash_category_ids'] ?? []));

        $directRelation = null;
        $directItems = [];
        $cashItems = [];
        $directToCompany = 0.0;
        $cashInShop = 0.0;

        if ($salesSource === 'categories') {
            // Direct to Company
            $selectedDirectSettings = $settings->whereIn('id', $salesDirectIds);
            foreach ($selectedDirectSettings as $s) {
                // Enforce Cash rule: Pure cash categories stay with shop and are excluded
                $isCash = $s->company_account_id === null && (
                    str_contains(strtolower($s->displayName()), 'cash')
                    || str_contains(strtolower($s->entryType?->code ?? ''), 'cash')
                    || strtolower((string) ($s->default_funding_source ?? '')) === 'shop_cash'
                );
                if ($isCash) {
                    continue;
                }

                $amt = round((float) ($amounts[$s->id] ?? 0.0), 2);
                $directToCompany += $amt;
                $bankName = $s->companyAccount?->bank_name ?: $s->companyAccount?->name;
                $directItems[] = [
                    'id' => $s->id,
                    'entry_setting_id' => $s->id,
                    'name' => $s->displayName(),
                    'code' => $s->entryType?->code ?? '',
                    'category' => $s->entryType?->category ?? '',
                    'bank_name' => $bankName,
                    'is_direct' => true,
                    'collection_type' => 'direct',
                    'destination_label' => $bankName ? 'Direct to Company · '.$bankName : 'Direct to Company',
                    'amount' => $amt,
                    'role' => 'add',
                ];
            }
            $directToCompany = round($directToCompany, 2);

            // Cash / Shop Collections
            if (! empty($salesCashIds)) {
                $selectedCashSettings = $settings->whereIn('id', $salesCashIds);
            } else {
                $selectedCashSettings = $settings->filter(function ($s) {
                    return $s->company_account_id === null && (
                        str_contains(strtolower($s->displayName()), 'cash')
                        || str_contains(strtolower($s->entryType?->code ?? ''), 'cash')
                        || in_array(strtolower((string) ($s->default_funding_source ?? '')), ['shop_cash', 'cash', 'sales'], true)
                        || in_array(strtolower((string) ($s->entryType?->category ?? '')), ['income', 'sales'], true)
                    );
                });
            }

            foreach ($selectedCashSettings as $s) {
                $amt = round((float) ($amounts[$s->id] ?? 0.0), 2);
                $cashInShop += $amt;
                $cashItems[] = [
                    'id' => $s->id,
                    'entry_setting_id' => $s->id,
                    'name' => $s->displayName(),
                    'code' => $s->entryType?->code ?? '',
                    'category' => $s->entryType?->category ?? '',
                    'bank_name' => null,
                    'is_direct' => false,
                    'collection_type' => 'cash',
                    'destination_label' => 'Stays with Shop',
                    'amount' => $amt,
                    'role' => 'add',
                ];
            }
            $cashInShop = round($cashInShop, 2);
        } else {
            $settlementId = $salesConfig['settlement_id'] ?? ($directConfig['settlement_id'] ?? null);
            if ($settlementId) {
                $directRelation = ShopCashbookRelation::where('shop_id', $shopId)->where('id', $settlementId)->first();
            }
            if (! $directRelation) {
                $directRelation = $this->getDefaultPaymentPaid($shopId);
            }
            $directCalc = $directRelation ? $this->calculator->calculate($directRelation, $amounts) : [
                'netSettlement' => 0.0,
                'items' => [],
            ];
            $directToCompany = round((float) ($directCalc['netSettlement'] ?? 0.0), 2);
            $rawItems = $directCalc['items'] ?? [];
            foreach ($rawItems as $item) {
                $settingId = $item['setting_id'] ?? $item['shop_ledger_entry_setting_id'] ?? null;
                $settingObj = $settingId ? $settings->firstWhere('id', $settingId) : null;
                $bankName = $settingObj?->companyAccount?->bank_name ?: $settingObj?->companyAccount?->name;
                $item['bank_name'] = $bankName;
                $item['is_direct'] = true;
                $item['collection_type'] = 'direct';
                $item['destination_label'] = $bankName ? 'Direct to Company · '.$bankName : 'Direct to Company';
                $directItems[] = $item;
            }

            // Cash in shop for settlement mode: auto-detect cash categories
            $cashSettings = $settings->filter(function ($s) {
                return $s->company_account_id === null && (
                    str_contains(strtolower($s->displayName()), 'cash')
                    || str_contains(strtolower($s->entryType?->code ?? ''), 'cash')
                    || in_array(strtolower((string) ($s->default_funding_source ?? '')), ['shop_cash', 'cash', 'sales'], true)
                    || in_array(strtolower((string) ($s->entryType?->category ?? '')), ['income', 'sales'], true)
                );
            });
            foreach ($cashSettings as $s) {
                $amt = round((float) ($amounts[$s->id] ?? 0.0), 2);
                $cashInShop += $amt;
                $cashItems[] = [
                    'id' => $s->id,
                    'entry_setting_id' => $s->id,
                    'name' => $s->displayName(),
                    'code' => $s->entryType?->code ?? '',
                    'category' => $s->entryType?->category ?? '',
                    'bank_name' => null,
                    'is_direct' => false,
                    'collection_type' => 'cash',
                    'destination_label' => 'Stays with Shop',
                    'amount' => $amt,
                    'role' => 'add',
                ];
            }
            $cashInShop = round($cashInShop, 2);
        }

        $totalSales = round($directToCompany + $cashInShop, 2);
        $salesCollectionItems = array_merge($directItems, $cashItems);

        // 3. Calculate Manual Payments (Cash / Shop Balance manually sent later to company)
        $manualRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shopId)
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($startDate, $endDate): void {
                $query->whereBetween('payment_date', [$startDate, $endDate])
                    ->orWhereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
            })
            ->latest('id')
            ->get();

        $manualReceived = round((float) $manualRequests->where('status', 'approved')->sum(function (ShopInvoicePaymentRequest $r): float {
            return (float) ($r->reconciled_amount > 0 ? $r->reconciled_amount : $r->requested_amount);
        }), 2);
        $manualPending = round((float) $manualRequests->where('status', 'pending')->sum('requested_amount'), 2);
        $manualTotal = round($manualReceived + $manualPending, 2);

        // 4. Calculate Total Collections & Money Kept by Shop
        $totalCollections = (float) ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereIn('status', ['posted', 'approved'])
            ->whereNull('voided_at')
            ->where(function ($q): void {
                $q->where('direction', 'income')
                    ->orWhere('affects_income', true)
                    ->orWhere('affects_sales', true);
            })
            ->sum('amount');
        $moneyKeptByShop = max(0.0, round($totalCollections - $directToCompany, 2));

        // 5. Calculate Expenses Paid from Shop Balance
        $expensesPaid = (float) ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereIn('status', ['posted', 'approved'])
            ->whereNull('voided_at')
            ->where('direction', 'expense')
            ->whereIn('funding_source', ['shop_cash', 'shop_balance', 'bank'])
            ->sum('amount');
        $expensesPaid = round($expensesPaid, 2);

        // 6. Calculate Opening Shop Position
        $previousSnapshot = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $shopId)
            ->where('business_date', '<', $startDate)
            ->orderByDesc('business_date')
            ->first();
        $openingShopPosition = (float) ($previousSnapshot?->closing_shop_position ?? 0.0);

        // 7. Calculate Correct Shop Balance: Money Kept by Shop - (Expenses Paid + Manual Remittances Received)
        $shopBalance = round($openingShopPosition + $moneyKeptByShop - $expensesPaid - $manualReceived, 2);

        // 8. Build Expense Payables & Settled Expenses breakdown grouped by configured PAYABLE category
        $expensePayables = [];
        $settledExpenses = [];

        $payableTargets = $this->resolvePayableAllocationTargets($shopId);
        $targetEntryTypeIds = $payableTargets->pluck('entry_type_id')->filter()->unique()->values()->all();

        if (! empty($targetEntryTypeIds)) {
            $expenseTransactions = ShopLedgerTransaction::query()
                ->with(['entryType'])
                ->where('shop_id', $shopId)
                ->whereIn('entry_type_id', $targetEntryTypeIds)
                ->whereBetween('business_date', [$startDate, $endDate])
                ->whereIn('status', ['posted', 'approved'])
                ->whereNull('voided_at')
                ->where('direction', 'expense')
                ->whereNull('company_account_id')
                ->orderBy('business_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $txIds = $expenseTransactions->pluck('id')->all();
            $allocationsByTx = [];
            if (! empty($txIds)) {
                $allocationsByTx = ShopPaymentLedgerAllocation::query()
                    ->where('shop_id', $shopId)
                    ->whereIn('shop_ledger_transaction_id', $txIds)
                    ->selectRaw('shop_ledger_transaction_id, SUM(amount) as total_allocated')
                    ->groupBy('shop_ledger_transaction_id')
                    ->pluck('total_allocated', 'shop_ledger_transaction_id')
                    ->all();
            }

            foreach ($payableTargets as $target) {
                $entryTypeId = (int) $target['entry_type_id'];
                $categoryTxs = $expenseTransactions->where('entry_type_id', $entryTypeId);

                $totalRecorded = round((float) $categoryTxs->sum('amount'), 2);
                if ($totalRecorded <= 0.0) {
                    continue;
                }

                $totalPaid = round((float) $categoryTxs->sum(function ($tx) use ($allocationsByTx): float {
                    return (float) ($allocationsByTx[$tx->id] ?? 0.0);
                }), 2);

                $remainingAmt = max(0.0, round($totalRecorded - $totalPaid, 2));
                $settingId = (int) ($target['setting_id'] ?? $target['id']);
                $categoryName = (string) $target['name'];

                // 1. Add to Expense Payables if category has any remaining unpaid balance
                if ($remainingAmt > 0.001) {
                    $status = $totalPaid > 0 ? 'partial' : 'unpaid';
                    $expensePayables[] = [
                        'id' => $settingId,
                        'entry_setting_id' => $settingId,
                        'entry_type_id' => $entryTypeId,
                        'name' => $categoryName,
                        'category' => $target['category'] ?? 'expense',
                        'amount' => $totalRecorded,
                        'recorded_amount' => $totalRecorded,
                        'paid_amount' => $totalPaid,
                        'remaining_amount' => $remainingAmt,
                        'status' => $status,
                        'status_label' => $status === 'partial' ? 'Partial' : 'Unpaid',
                        'role' => $target['role'] ?? 'subtract',
                        'transaction_count' => $categoryTxs->count(),
                    ];
                }

                // 2. Add to Settled Expenses for fully settled transactions in this category
                $settledTxs = $categoryTxs->filter(function ($tx) use ($allocationsByTx): bool {
                    $rec = round((float) $tx->amount, 2);
                    $alloc = round((float) ($allocationsByTx[$tx->id] ?? 0.0), 2);

                    return ($rec - $alloc) <= 0.001;
                });

                if ($settledTxs->isNotEmpty()) {
                    $settledAmount = round((float) $settledTxs->sum('amount'), 2);
                    $settledExpenses[] = [
                        'id' => $settingId,
                        'entry_setting_id' => $settingId,
                        'entry_type_id' => $entryTypeId,
                        'name' => $categoryName,
                        'category' => $target['category'] ?? 'expense',
                        'amount' => $settledAmount,
                        'recorded_amount' => $settledAmount,
                        'settled_amount' => $settledAmount,
                        'paid_amount' => $settledAmount,
                        'remaining_amount' => 0.0,
                        'status' => 'paid',
                        'status_label' => 'Settled',
                        'role' => $target['role'] ?? 'subtract',
                        'settled_count' => $settledTxs->count(),
                        'transaction_count' => $settledTxs->count(),
                    ];
                }
            }
        }

        return [
            'payable' => $payable,
            'direct_to_company' => $directToCompany,
            'paid' => $directToCompany,
            'total_sales' => $totalSales,
            'cash_in_shop' => $cashInShop,
            'shop_collections' => $cashInShop,
            'sales_collections' => [
                'total_sales' => $totalSales,
                'direct_to_company' => $directToCompany,
                'cash_in_shop' => $cashInShop,
                'items' => $salesCollectionItems,
            ],
            'sales_collection_items' => $salesCollectionItems,
            'cash_collection_items' => $cashItems,
            'money_kept_by_shop' => $moneyKeptByShop,
            'expenses_paid' => $expensesPaid,
            'manual_payments' => [
                'total' => $manualTotal,
                'received' => $manualReceived,
                'pending' => $manualPending,
                'requests' => $manualRequests,
            ],
            'manual_total' => $manualTotal,
            'manual_received' => $manualReceived,
            'manual_pending' => $manualPending,
            'manual_requests' => $manualRequests,
            'shop_balance' => $shopBalance,
            'payable_source' => $payableSource,
            'sales_source' => $salesSource,
            'direct_to_company_source' => $salesSource,
            'paid_source' => $salesSource,
            'payable_relation' => $payableRelation,
            'direct_relation' => $directRelation,
            'paid_relation' => $directRelation,
            'payable_items' => $payableItems,
            'expense_payables' => $expensePayables,
            'settled_expenses' => $settledExpenses,
            'direct_items' => $directItems,
            'direct_to_company_items' => $directItems,
            'paid_items' => $directItems,
        ];
    }

    /**
     * @param  array<int, string|int>  $relationIdentifiers  Array of public_uuid or id
     */
    public function reorder(ShopLedgerProfile $profile, array $relationIdentifiers): void
    {
        DB::transaction(function () use ($profile, $relationIdentifiers): void {
            foreach ($relationIdentifiers as $index => $identifier) {
                ShopCashbookRelation::where('shop_id', $profile->shop_id)
                    ->where(function ($query) use ($identifier) {
                        if (is_numeric($identifier)) {
                            $query->where('id', (int) $identifier);
                        } else {
                            $query->where('public_uuid', (string) $identifier);
                        }
                    })
                    ->update(['display_order' => $index + 1]);
            }
        });
    }
}
