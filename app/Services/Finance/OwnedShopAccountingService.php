<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopAccountingInvoice;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopPettyCashExpense;
use App\Models\ShopStaffPayment;
use Illuminate\Database\Eloquent\Builder;
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

        $businessDate = Carbon::parse($payload['business_date']);
        $lines = $payload['lines'] ?? [];
        $openingCash = $this->previousClosingBalance($shop, $businessDate);
        $closingCash = $this->calculatedClosingCash($shop, $businessDate, $openingCash, $lines);
        $status = $payload['submission_action'] === 'submit' ? 'submitted' : 'draft';
        $entry = $this->saveEntry($shop, [
            'business_date' => $businessDate->toDateString(),
            'status' => $status,
            'opening_cash' => $openingCash,
            'closing_cash' => $closingCash,
            'notes' => $payload['notes'] ?? null,
            'lines' => $lines,
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

    /**
     * @param  array<int, array{shop_accounting_category_id:int, amount: float|int|string, description?: string|null}>  $lines
     */
    private function calculatedClosingCash(Shop $shop, Carbon $businessDate, float $openingCash, array $lines): float
    {
        $categoryIds = collect($lines)
            ->pluck('shop_accounting_category_id')
            ->map(fn ($categoryId): int => (int) $categoryId)
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            return round($openingCash + $this->shopCashMovementForDate($shop, $businessDate) - $this->approvedDeliveryBillTotalForDate($shop, $businessDate), 2);
        }

        $categories = ShopAccountingCategory::query()
            ->whereIn('id', $categoryIds->toArray())
            ->where('is_active', true)
            ->where(function ($query) use ($shop): void {
                $query->whereNull('shop_id')
                    ->orWhere('shop_id', $shop->id);
            })
            ->get()
            ->keyBy('id');

        $cashMovement = collect($lines)->sum(function (array $line) use ($categories): float {
            $category = $categories->get((int) $line['shop_accounting_category_id']);

            if (! $category instanceof ShopAccountingCategory || ! (bool) $category->cash_effect) {
                return 0.0;
            }

            $amount = round((float) $line['amount'], 2);

            return $category->type === 'income' ? $amount : -$amount;
        });

        return round($openingCash + $this->shopCashMovementForDate($shop, $businessDate) + (float) $cashMovement - $this->approvedDeliveryBillTotalForDate($shop, $businessDate), 2);
    }

    public function previousClosingBalance(Shop $shop, Carbon $businessDate): float
    {
        $previousEntryDate = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '<', $businessDate->toDateString())
            ->max('business_date');
        $previousShopCashDate = ShopCredit::query()
            ->approved()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '<', $businessDate->toDateString())
            ->max('business_date');
        $previousDeliveryBillDate = $this->approvedDeliveryBillQuery($shop)
            ->whereDate('business_date', '<', $businessDate->toDateString())
            ->max('business_date');
        $previousDate = collect([$previousEntryDate, $previousShopCashDate, $previousDeliveryBillDate])
            ->filter()
            ->map(fn (string $date): string => Carbon::parse($date)->toDateString())
            ->sort()
            ->last();

        if ($previousDate === null) {
            return 0.0;
        }

        return $this->closingBalanceForDate($shop, Carbon::parse($previousDate));
    }

    public function closingBalanceForDate(Shop $shop, Carbon $businessDate): float
    {
        $entries = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $businessDate->toDateString())
            ->with('lines')
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            return round($this->previousClosingBalance($shop, $businessDate) + $this->shopCashMovementForDate($shop, $businessDate) - $this->approvedDeliveryBillTotalForDate($shop, $businessDate), 2);
        }

        if ($entries->every(fn (ShopAccountingEntry $entry): bool => $entry->lines->isEmpty())) {
            $shopCashMovement = $this->shopCashMovementForDate($shop, $businessDate) - $this->approvedDeliveryBillTotalForDate($shop, $businessDate);

            if (abs($shopCashMovement) > 0.009) {
                $openingBalance = round((float) ($entries->first()?->opening_cash ?? $this->previousClosingBalance($shop, $businessDate)), 2);

                return round($openingBalance + $shopCashMovement, 2);
            }

            return round((float) ($entries->last()?->closing_cash ?? 0.0), 2);
        }

        $openingBalance = round((float) ($entries->first()?->opening_cash ?? 0.0), 2);
        $shopCashMovement = $this->shopCashMovementForDate($shop, $businessDate);
        $approvedDeliveryBill = $this->approvedDeliveryBillTotalForDate($shop, $businessDate);
        $cashMovement = round((float) $entries->sum(function (ShopAccountingEntry $entry): float {
            $cashCredit = (float) $entry->lines
                ->where('type', 'income')
                ->where('cash_effect', true)
                ->sum('amount');
            $cashDebit = (float) $entry->lines
                ->where('type', 'expense')
                ->where('cash_effect', true)
                ->sum('amount');

            return $cashCredit - $cashDebit;
        }), 2);

        return round($openingBalance + $shopCashMovement + $cashMovement - $approvedDeliveryBill, 2);
    }

    public function syncStoredClosingBalanceForDate(Shop $shop, Carbon $businessDate, int $userId): ShopAccountingEntry
    {
        return DB::transaction(function () use ($shop, $businessDate, $userId): ShopAccountingEntry {
            $entries = ShopAccountingEntry::query()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', $businessDate->toDateString())
                ->with('lines')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $openingBalance = $this->previousClosingBalance($shop, $businessDate);
            $runningClosing = round($openingBalance + $this->shopCashMovementForDate($shop, $businessDate) - $this->approvedDeliveryBillTotalForDate($shop, $businessDate), 2);

            if ($entries->isEmpty()) {
                return ShopAccountingEntry::query()->create([
                    'shop_id' => $shop->id,
                    'business_date' => $businessDate->toDateString(),
                    'status' => 'draft',
                    'opening_cash' => $openingBalance,
                    'closing_cash' => $runningClosing,
                    'notes' => 'Auto-created to keep shop running balance in sync.',
                    'created_by' => $userId,
                ]);
            }

            $latestEntry = $entries->last();

            $entries->each(function (ShopAccountingEntry $entry, int $index) use (&$runningClosing, $openingBalance, $userId): void {
                $cashCredit = (float) $entry->lines
                    ->where('type', 'income')
                    ->where('cash_effect', true)
                    ->sum('amount');
                $cashDebit = (float) $entry->lines
                    ->where('type', 'expense')
                    ->where('cash_effect', true)
                    ->sum('amount');

                $runningClosing = round($runningClosing + $cashCredit - $cashDebit, 2);

                $entry->forceFill([
                    'opening_cash' => $index === 0 ? $openingBalance : $entry->opening_cash,
                    'closing_cash' => $runningClosing,
                    'updated_by' => $userId,
                ])->save();
            });

            return $latestEntry->fresh(['lines.category', 'shop', 'createdBy', 'updatedBy', 'submittedBy', 'reviewedBy']);
        });
    }

    public function syncStoredClosingBalancesFromDate(Shop $shop, Carbon $businessDate, int $userId, ?Carbon $throughDate = null): void
    {
        if (! $shop->isOwnedAccountingEnabled()) {
            return;
        }

        $startDate = $businessDate->copy()->startOfDay();
        $endDate = ($throughDate ?? $this->latestRunningBalanceActivityDate($shop, $startDate))
            ->copy()
            ->startOfDay();

        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy();
        }

        $activityDates = $this->runningBalanceActivityDates($shop, $startDate, $endDate);

        if ($activityDates->isEmpty()) {
            $activityDates = collect([$startDate->toDateString()]);
        }

        $activityDates
            ->sort()
            ->values()
            ->each(function (string $activityDate) use ($shop, $userId): void {
                $this->syncStoredClosingBalanceForDate($shop, Carbon::parse($activityDate), $userId);
            });
    }

    /**
     * @return array{
     *     opening_balance:float,
     *     cash_credit:float,
     *     non_cash_income:float,
     *     cash_debit:float,
     *     non_cash_debit:float,
     *     approved_delivery_bill:float,
     *     cash_given_to_shop:float,
     *     payment_to_company:float,
     *     shop_cash_credit:float,
     *     total_income:float,
     *     total_debit:float,
     *     expected_closing:float,
     *     entered_closing:?float,
     *     difference:?float,
     *     owner_funded:float
     * }
     */
    public function receiptSummary(?ShopAccountingEntry $entry, float $fallbackOpeningBalance = 0.0, float $shopCashMovement = 0.0, float $approvedDeliveryBill = 0.0): array
    {
        $lines = $entry?->lines ?? collect();
        $openingBalance = round((float) ($entry?->opening_cash ?? $fallbackOpeningBalance), 2);
        $enteredClosing = $entry?->closing_cash !== null ? round((float) $entry->closing_cash, 2) : null;

        $cashCredit = round((float) $lines
            ->filter(fn (ShopAccountingEntryLine $line): bool => $line->type === 'income' && (bool) $line->cash_effect)
            ->sum('amount'), 2);
        $nonCashIncome = round((float) $lines
            ->filter(fn (ShopAccountingEntryLine $line): bool => $line->type === 'income' && ! (bool) $line->cash_effect)
            ->sum('amount'), 2);
        $cashDebit = round((float) $lines
            ->filter(fn (ShopAccountingEntryLine $line): bool => $line->type === 'expense' && (bool) $line->cash_effect)
            ->sum('amount'), 2);
        $nonCashDebit = round((float) $lines
            ->filter(fn (ShopAccountingEntryLine $line): bool => $line->type === 'expense' && ! (bool) $line->cash_effect)
            ->sum('amount'), 2);
        $shopCashMovement = round($shopCashMovement, 2);
        $approvedDeliveryBill = round($approvedDeliveryBill, 2);
        $cashGivenToShop = max(0.0, $shopCashMovement);
        $paymentToCompany = abs(min(0.0, $shopCashMovement));
        $cashDebitWithBills = round($cashDebit + $approvedDeliveryBill, 2);
        $expectedClosing = round($openingBalance + $cashGivenToShop - $paymentToCompany + $cashCredit - $cashDebitWithBills, 2);

        return [
            'opening_balance' => $openingBalance,
            'cash_credit' => $cashCredit,
            'non_cash_income' => $nonCashIncome,
            'cash_debit' => $cashDebitWithBills,
            'non_cash_debit' => $nonCashDebit,
            'approved_delivery_bill' => $approvedDeliveryBill,
            'cash_given_to_shop' => $cashGivenToShop,
            'payment_to_company' => $paymentToCompany,
            'shop_cash_credit' => $shopCashMovement,
            'total_income' => round($cashCredit + $nonCashIncome, 2),
            'total_debit' => round($cashDebitWithBills + $nonCashDebit, 2),
            'expected_closing' => $expectedClosing,
            'entered_closing' => $enteredClosing,
            'difference' => $enteredClosing === null ? null : round($enteredClosing - $expectedClosing, 2),
            'owner_funded' => $enteredClosing !== null && $enteredClosing < 0 ? abs($enteredClosing) : 0.0,
        ];
    }

    /**
     * @return array{
     *     opening_balance:float,
     *     cash_credit:float,
     *     non_cash_income:float,
     *     cash_debit:float,
     *     non_cash_debit:float,
     *     approved_delivery_bill:float,
     *     cash_given_to_shop:float,
     *     payment_to_company:float,
     *     shop_cash_credit:float,
     *     total_income:float,
     *     total_debit:float,
     *     expected_closing:float,
     *     entered_closing:?float,
     *     difference:?float,
     *     owner_funded:float
     * }
     */
    public function receiptSummaryForDate(Shop $shop, Carbon $businessDate): array
    {
        $entries = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $businessDate->toDateString())
            ->with('lines')
            ->orderBy('id')
            ->get();
        $openingBalance = $this->previousClosingBalance($shop, $businessDate);
        $cashMovement = $this->shopCashMovementBreakdownForDate($shop, $businessDate);
        $approvedDeliveryBill = $this->approvedDeliveryBillTotalForDate($shop, $businessDate);
        $lines = $entries->flatMap(fn (ShopAccountingEntry $entry): Collection => $entry->lines);
        $cashCredit = round((float) $lines
            ->filter(fn (ShopAccountingEntryLine $line): bool => $line->type === 'income' && (bool) $line->cash_effect)
            ->sum('amount'), 2);
        $nonCashIncome = round((float) $lines
            ->filter(fn (ShopAccountingEntryLine $line): bool => $line->type === 'income' && ! (bool) $line->cash_effect)
            ->sum('amount'), 2);
        $cashDebit = round((float) $lines
            ->filter(fn (ShopAccountingEntryLine $line): bool => $line->type === 'expense' && (bool) $line->cash_effect)
            ->sum('amount'), 2);
        $nonCashDebit = round((float) $lines
            ->filter(fn (ShopAccountingEntryLine $line): bool => $line->type === 'expense' && ! (bool) $line->cash_effect)
            ->sum('amount'), 2);
        $cashDebitWithBills = round($cashDebit + $approvedDeliveryBill, 2);
        $expectedClosing = round($openingBalance + $cashMovement['cash_given_to_shop'] - $cashMovement['payment_to_company'] + $cashCredit - $cashDebitWithBills, 2);
        $hasDayActivity = $entries->isNotEmpty()
            || $cashMovement['cash_given_to_shop'] > 0
            || $cashMovement['payment_to_company'] > 0
            || $approvedDeliveryBill > 0;
        $enteredClosing = $hasDayActivity ? $this->closingBalanceForDate($shop, $businessDate) : null;

        return [
            'opening_balance' => round($openingBalance, 2),
            'cash_credit' => $cashCredit,
            'non_cash_income' => $nonCashIncome,
            'cash_debit' => $cashDebitWithBills,
            'non_cash_debit' => $nonCashDebit,
            'approved_delivery_bill' => round($approvedDeliveryBill, 2),
            'cash_given_to_shop' => $cashMovement['cash_given_to_shop'],
            'payment_to_company' => $cashMovement['payment_to_company'],
            'shop_cash_credit' => $cashMovement['net'],
            'total_income' => round($cashCredit + $nonCashIncome, 2),
            'total_debit' => round($cashDebitWithBills + $nonCashDebit, 2),
            'expected_closing' => $expectedClosing,
            'entered_closing' => $enteredClosing,
            'difference' => $enteredClosing === null ? null : round($enteredClosing - $expectedClosing, 2),
            'owner_funded' => $enteredClosing !== null && $enteredClosing < 0 ? abs($enteredClosing) : 0.0,
        ];
    }

    private function shopCashMovementForDate(Shop $shop, Carbon $businessDate): float
    {
        return round((float) ShopCredit::query()
            ->approved()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $businessDate->toDateString())
            ->get()
            ->sum(fn (ShopCredit $credit): float => $credit->shopSignedAmount()), 2);
    }

    /**
     * @return array{cash_given_to_shop:float,payment_to_company:float,net:float}
     */
    public function shopCashMovementBreakdownForDate(Shop $shop, Carbon $businessDate): array
    {
        $credits = ShopCredit::query()
            ->approved()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $businessDate->toDateString())
            ->get();
        $cashGivenToShop = round((float) $credits
            ->where('type', 'in')
            ->sum('amount'), 2);
        $paymentToCompany = round((float) $credits
            ->where('type', 'out')
            ->sum('amount'), 2);

        return [
            'cash_given_to_shop' => $cashGivenToShop,
            'payment_to_company' => $paymentToCompany,
            'net' => round($cashGivenToShop - $paymentToCompany, 2),
        ];
    }

    public function approvedDeliveryBillTotalForDate(Shop $shop, Carbon $businessDate): float
    {
        return round((float) $this->approvedDeliveryBillQuery($shop)
            ->whereDate('business_date', $businessDate->toDateString())
            ->sum('final_total'), 2);
    }

    private function latestRunningBalanceActivityDate(Shop $shop, Carbon $startDate): Carbon
    {
        $latestEntryDate = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '>=', $startDate->toDateString())
            ->max('business_date');
        $latestShopCashDate = ShopCredit::query()
            ->approved()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '>=', $startDate->toDateString())
            ->max('business_date');
        $latestDeliveryBillDate = $this->approvedDeliveryBillQuery($shop)
            ->whereDate('business_date', '>=', $startDate->toDateString())
            ->max('business_date');
        $latestDate = collect([$latestEntryDate, $latestShopCashDate, $latestDeliveryBillDate, today()->toDateString()])
            ->filter()
            ->map(fn (string $date): string => Carbon::parse($date)->toDateString())
            ->sort()
            ->last();

        return Carbon::parse($latestDate ?? $startDate->toDateString());
    }

    /**
     * @return Collection<int, string>
     */
    private function runningBalanceActivityDates(Shop $shop, Carbon $startDate, Carbon $endDate): Collection
    {
        return ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '>=', $startDate->toDateString())
            ->whereDate('business_date', '<=', $endDate->toDateString())
            ->pluck('business_date')
            ->map(fn ($businessDate): string => Carbon::parse($businessDate)->toDateString())
            ->merge(ShopCredit::query()
                ->approved()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', '>=', $startDate->toDateString())
                ->whereDate('business_date', '<=', $endDate->toDateString())
                ->pluck('business_date')
                ->map(fn ($businessDate): string => Carbon::parse($businessDate)->toDateString()))
            ->merge($this->approvedDeliveryBillQuery($shop)
                ->whereDate('business_date', '>=', $startDate->toDateString())
                ->whereDate('business_date', '<=', $endDate->toDateString())
                ->pluck('business_date')
                ->map(fn ($businessDate): string => Carbon::parse($businessDate)->toDateString()))
            ->unique()
            ->values();
    }

    /**
     * @return array{count:int,amount:float}
     */
    public function pendingDeliveryBillApprovalSummary(Shop $shop): array
    {
        $query = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->where('final_total', '>', 0)
            ->whereNot(function (Builder $query): void {
                $this->approvedDeliveryBillScope($query);
            });

        return [
            'count' => (int) $query->count(),
            'amount' => round((float) $query->sum('final_total'), 2),
        ];
    }

    private function approvedDeliveryBillQuery(Shop $shop): Builder
    {
        return ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->where('final_total', '>', 0)
            ->where(function (Builder $query): void {
                $this->approvedDeliveryBillScope($query);
            });
    }

    private function approvedDeliveryBillScope(Builder $query): void
    {
        $query
            ->whereIn('delivery_status', ['received_full', 'approved_after_discrepancy'])
            ->orWhereIn('status', ['finalized', 'payment_pending', 'paid'])
            ->orWhereIn('payment_status', ['partially_paid', 'paid']);
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
            ->approved()
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
        $pettyPayrollPayments = ShopStaffPayment::query()
            ->where('shop_id', $shop->id)
            ->where('fund_source', 'petty_cash')
            ->whereDate('paid_on', '<=', $endDate)
            ->with('employee')
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get();

        $creditByDate = $pettyCredits->groupBy(fn (ShopCredit $credit): string => $credit->business_date?->toDateString() ?? today()->toDateString());
        $expenseByDate = $pettyExpenses->keyBy(fn (ShopPettyCashExpense $expense): string => $expense->business_date?->toDateString() ?? today()->toDateString());
        $payrollPaymentByDate = $pettyPayrollPayments->groupBy(fn (ShopStaffPayment $payment): string => $payment->paid_on?->toDateString() ?? today()->toDateString());

        $allDates = $creditByDate->keys()
            ->merge($expenseByDate->keys())
            ->merge($payrollPaymentByDate->keys())
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
            $payrollPayments = $payrollPaymentByDate->get($date, collect());
            $manualExpenseAmount = $expense instanceof ShopPettyCashExpense ? round((float) $expense->amount, 2) : 0.0;
            $payrollExpenseAmount = round((float) $payrollPayments->sum('amount'), 2);
            $expenseAmount = round($manualExpenseAmount + $payrollExpenseAmount, 2);

            $runningBalance = round($runningBalance + $adminCash - $expenseAmount, 2);

            if (Carbon::parse($date)->lt($startDate) || Carbon::parse($date)->gt($endDate)) {
                continue;
            }

            $adminCashLabel = $dayCredits
                ->map(fn (ShopCredit $credit): string => ($credit->creator?->name ?? 'Admin').' - Rs. '.number_format((float) $credit->amount, 2))
                ->implode(', ');
            $payrollPaymentLabel = $payrollPayments
                ->map(fn (ShopStaffPayment $payment): string => str($payment->payment_type)->headline().' '.$payment->employee?->name.' - Rs. '.number_format((float) $payment->amount, 2))
                ->implode(', ');

            $rows->push([
                'date' => $date,
                'admin_cash' => $adminCash,
                'admin_cash_label' => $adminCashLabel,
                'expense' => $expenseAmount,
                'expense_source' => $payrollExpenseAmount > 0 ? 'salary' : ($expense instanceof ShopPettyCashExpense ? $expense->source : null),
                'expense_updated_at' => $expense instanceof ShopPettyCashExpense ? $expense->updated_at : null,
                'payroll_expense' => $payrollExpenseAmount,
                'payroll_expense_label' => $payrollPaymentLabel,
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
            ->orderByRaw("CASE status WHEN 'recheck_required' THEN 0 WHEN 'submitted' THEN 1 WHEN 'draft' THEN 2 WHEN 'approved' THEN 3 ELSE 4 END")
            ->latest('id')
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
