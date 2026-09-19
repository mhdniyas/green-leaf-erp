<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\PurchaseFinanceAccountingOpening;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class PurchaseFinanceAccountingOpeningService
{
    public const DEFAULT_ACCOUNTING_START_DATE = '2026-09-01';

    /**
     * Get the active accounting opening record effective for a purchaser or vendor on or before the given date.
     */
    public function getOpeningForDate(
        ?int $purchaserId,
        ?int $supplierId,
        string|Carbon $date,
        string $accountScope = 'purchaser'
    ): ?PurchaseFinanceAccountingOpening {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        $query = PurchaseFinanceAccountingOpening::query()
            ->where('account_scope', $accountScope)
            ->whereDate('accounting_start_date', '<=', $dateStr);

        if ($accountScope === 'purchaser' && $purchaserId !== null) {
            $query->where('purchaser_id', $purchaserId);
        } elseif ($accountScope === 'vendor' && $supplierId !== null) {
            $query->where('supplier_id', $supplierId);
        }

        return $query->orderByDesc('accounting_start_date')->first();
    }

    /**
     * Get the earliest accounting start date configured for a purchaser, vendor, or global scope.
     */
    public function getAccountingStartDate(
        ?int $purchaserId = null,
        ?int $supplierId = null,
        string $accountScope = 'purchaser'
    ): ?string {
        $query = PurchaseFinanceAccountingOpening::query()->where('account_scope', $accountScope);

        if ($accountScope === 'purchaser' && $purchaserId !== null) {
            $query->where('purchaser_id', $purchaserId);
        } elseif ($accountScope === 'vendor' && $supplierId !== null) {
            $query->where('supplier_id', $supplierId);
        }

        $opening = $query->orderBy('accounting_start_date')->first();

        return $opening?->accounting_start_date?->toDateString() ?? self::DEFAULT_ACCOUNTING_START_DATE;
    }

    /**
     * Determine whether the given date is in the historical pre-opening period.
     */
    public function isPreOpening(
        ?int $purchaserId,
        ?int $supplierId,
        string|Carbon $date,
        string $accountScope = 'purchaser'
    ): bool {
        $startDate = $this->getAccountingStartDate($purchaserId, $supplierId, $accountScope);
        if ($startDate === null) {
            return false;
        }

        $dateStr = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return $dateStr < $startDate;
    }

    /**
     * Determine whether an entire month string (Y-m) is in the historical pre-opening period.
     */
    public function isPreOpeningMonth(
        ?int $purchaserId,
        ?int $supplierId,
        string $month,
        string $accountScope = 'purchaser'
    ): bool {
        $startDate = $this->getAccountingStartDate($purchaserId, $supplierId, $accountScope);
        if ($startDate === null) {
            return false;
        }

        $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        return $monthEnd < $startDate;
    }

    /**
     * Create or update a purchaser accounting opening record idempotently.
     *
     * @param  array{
     *     opening_balance?: float|int|string,
     *     opening_balance_direction?: string,
     *     opening_credit_balance?: float|int|string,
     *     opening_credit_outstanding?: float|int|string,
     *     opening_advance_credit?: float|int|string,
     *     notes?: ?string
     * }  $attributes
     */
    public function setPurchaserOpening(
        User|int $purchaser,
        string $startDate = self::DEFAULT_ACCOUNTING_START_DATE,
        array $attributes = [],
        ?int $userId = null
    ): PurchaseFinanceAccountingOpening {
        $purchaserId = $purchaser instanceof User ? (int) $purchaser->id : (int) $purchaser;
        $dateStr = Carbon::parse($startDate)->toDateString();

        return DB::transaction(function () use ($purchaserId, $dateStr, $attributes, $userId): PurchaseFinanceAccountingOpening {
            $opening = PurchaseFinanceAccountingOpening::query()
                ->where('account_scope', 'purchaser')
                ->where('purchaser_id', $purchaserId)
                ->whereDate('accounting_start_date', $dateStr)
                ->lockForUpdate()
                ->first();

            $data = [
                'opening_balance' => (float) ($attributes['opening_balance'] ?? 0.0),
                'opening_balance_direction' => (string) ($attributes['opening_balance_direction'] ?? 'settled'),
                'opening_credit_balance' => (float) ($attributes['opening_credit_balance'] ?? 0.0),
                'opening_credit_outstanding' => (float) ($attributes['opening_credit_outstanding'] ?? 0.0),
                'opening_advance_credit' => (float) ($attributes['opening_advance_credit'] ?? 0.0),
                'notes' => $attributes['notes'] ?? 'September 2026 Purchaser Opening — Continuous Credit & Zero Settlement',
                'created_by' => $userId,
            ];

            if ($opening instanceof PurchaseFinanceAccountingOpening) {
                $opening->update($data);

                return $opening->fresh();
            }

            return PurchaseFinanceAccountingOpening::query()->create(array_merge([
                'purchaser_id' => $purchaserId,
                'supplier_id' => null,
                'account_scope' => 'purchaser',
                'accounting_start_date' => $dateStr,
            ], $data));
        });
    }

    /**
     * Create or update a vendor accounting opening record idempotently.
     *
     * @param  array{
     *     opening_balance?: float|int|string,
     *     opening_balance_direction?: string,
     *     opening_credit_balance?: float|int|string,
     *     opening_credit_outstanding?: float|int|string,
     *     opening_advance_credit?: float|int|string,
     *     notes?: ?string
     * }  $attributes
     */
    public function setVendorOpening(
        Supplier|int $supplier,
        string $startDate = self::DEFAULT_ACCOUNTING_START_DATE,
        array $attributes = [],
        ?int $userId = null
    ): PurchaseFinanceAccountingOpening {
        $supplierId = $supplier instanceof Supplier ? (int) $supplier->id : (int) $supplier;
        $dateStr = Carbon::parse($startDate)->toDateString();

        return DB::transaction(function () use ($supplierId, $dateStr, $attributes, $userId): PurchaseFinanceAccountingOpening {
            $opening = PurchaseFinanceAccountingOpening::query()
                ->where('account_scope', 'vendor')
                ->where('supplier_id', $supplierId)
                ->whereDate('accounting_start_date', $dateStr)
                ->lockForUpdate()
                ->first();

            $data = [
                'opening_balance' => (float) ($attributes['opening_balance'] ?? 0.0),
                'opening_balance_direction' => (string) ($attributes['opening_balance_direction'] ?? 'settled'),
                'opening_credit_balance' => (float) ($attributes['opening_credit_balance'] ?? 0.0),
                'opening_credit_outstanding' => (float) ($attributes['opening_credit_outstanding'] ?? 0.0),
                'opening_advance_credit' => (float) ($attributes['opening_advance_credit'] ?? 0.0),
                'notes' => $attributes['notes'] ?? 'September 2026 Vendor Opening — Zero Outstanding Cutoff',
                'created_by' => $userId,
            ];

            if ($opening instanceof PurchaseFinanceAccountingOpening) {
                $opening->update($data);

                return $opening->fresh();
            }

            return PurchaseFinanceAccountingOpening::query()->create(array_merge([
                'purchaser_id' => null,
                'supplier_id' => $supplierId,
                'account_scope' => 'vendor',
                'accounting_start_date' => $dateStr,
            ], $data));
        });
    }
}
