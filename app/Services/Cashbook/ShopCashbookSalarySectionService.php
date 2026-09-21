<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\DTO\Cashbook\SalaryGroup;
use App\DTO\Cashbook\SalarySectionData;
use App\DTO\Cashbook\SalaryTransactionRow;
use App\Enums\Cashbook\SalaryHrTransactionType;
use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopSalaryBridgeSetting;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use Illuminate\Support\Collection;

class ShopCashbookSalarySectionService
{
    /**
     * @param  array<string, string>  $FUNDING_LABELS
     */
    private const FUNDING_LABELS = [
        'sales' => 'Sales Cash',
        'petty' => 'Petty',
        'company' => 'Company Payable',
    ];

    /**
     * Build the salary section view-model for the given shop and date range.
     *
     * - Returns transactions identified by reference_type = ShopStaffPayment::class
     * - Groups them by SalaryHrTransactionType using ShopStaffPayment.payment_type
     * - Supplies excludedTxIds / excludedSettingIds so the caller can suppress salary
     *   entries from the normal Cashbook header rendering
     * - Does NOT touch BalanceCalculator, FundingSourceEffectResolver, or payroll
     */
    public function getSalarySection(Shop $shop, string $startDate, string $endDate): SalarySectionData
    {
        // Load bridge settings to know which entry setting IDs belong to salary.
        $bridgeSettings = ShopSalaryBridgeSetting::query()
            ->where('shop_id', $shop->id)
            ->where('is_enabled', true)
            ->get()
            ->keyBy(fn (ShopSalaryBridgeSetting $b): string => $b->typeEnum()->value);

        // Collect setting IDs that the bridge maps (may include nulls for unconfigured).
        $excludedSettingIds = $bridgeSettings
            ->filter(fn (ShopSalaryBridgeSetting $b): bool => $b->shop_ledger_entry_setting_id !== null)
            ->pluck('shop_ledger_entry_setting_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        // Load salary ledger transactions for this shop and period.
        // Identity: reference_type = ShopStaffPayment::class (set by StaffPaymentCashbookProjectionService).
        $transactions = ShopLedgerTransaction::query()
            ->where('shop_id', $shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->where('status', '!=', TransactionStatus::Void->value)
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        if ($transactions->isEmpty()) {
            return new SalarySectionData(
                groups: [],
                grandTotal: 0.0,
                excludedTxIds: [],
                excludedSettingIds: $excludedSettingIds,
                fundingBreakdown: [],
            );
        }

        // Load the linked payments eagerly (one query for all reference_ids).
        /** @var Collection<int, ShopStaffPayment> $payments */
        $payments = ShopStaffPayment::query()
            ->with('employee')
            ->whereIn('id', $transactions->pluck('reference_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        $excludedTxIds = [];
        $fundingTotals = [];   // label => float

        // Group transactions by SalaryHrTransactionType derived from ShopStaffPayment.payment_type.
        /** @var array<string, SalaryTransactionRow[]> $byType */
        $byType = [];

        foreach ($transactions as $tx) {
            $excludedTxIds[] = $tx->id;

            /** @var ShopStaffPayment|null $payment */
            $payment = $payments->get($tx->reference_id);

            $hrType = $this->resolveHrType((string) ($payment?->payment_type ?? 'salary'));
            $employeeName = $payment?->employee?->name ?? '—';
            $fundingSource = (string) ($tx->funding_source ?? 'sales');
            $fundingLabel = self::FUNDING_LABELS[$fundingSource] ?? 'Sales Cash';
            $amount = (float) $tx->amount;
            $businessDate = $tx->business_date instanceof \DateTimeInterface
                ? $tx->business_date->format('Y-m-d')
                : (string) $tx->business_date;

            $byType[$hrType->value][] = new SalaryTransactionRow(
                transactionId: $tx->id,
                paymentId: $payment?->id ?? 0,
                employeeName: $employeeName,
                amount: $amount,
                businessDate: $businessDate,
                fundingSource: $fundingSource,
                fundingLabel: $fundingLabel,
                status: (string) ($tx->status ?? 'active'),
            );

            $fundingTotals[$fundingLabel] = ($fundingTotals[$fundingLabel] ?? 0.0) + $amount;
        }

        // Build SalaryGroup for each type that has transactions.
        $groups = [];
        $grandTotal = 0.0;

        foreach (SalaryHrTransactionType::cases() as $type) {
            $rows = $byType[$type->value] ?? [];

            if ($rows === []) {
                continue;
            }

            $groupTotal = array_sum(array_map(fn (SalaryTransactionRow $r): float => $r->amount, $rows));
            $grandTotal += $groupTotal;

            $groups[] = new SalaryGroup(
                type: $type,
                typeLabel: $type->label(),
                transactions: $rows,
                total: $groupTotal,
                isMapped: $bridgeSettings->has($type->value)
                    && $bridgeSettings->get($type->value)?->shop_ledger_entry_setting_id !== null,
            );
        }

        return new SalarySectionData(
            groups: $groups,
            grandTotal: $grandTotal,
            excludedTxIds: $excludedTxIds,
            excludedSettingIds: $excludedSettingIds,
            fundingBreakdown: $fundingTotals,
        );
    }

    private function resolveHrType(string $paymentType): SalaryHrTransactionType
    {
        return match ($paymentType) {
            'advance', 'salary_advance' => SalaryHrTransactionType::SalaryAdvance,
            'salary_adjustment', 'adjustment' => SalaryHrTransactionType::SalaryAdjustment,
            'advance_recovery', 'recovery' => SalaryHrTransactionType::AdvanceRecovery,
            default => SalaryHrTransactionType::Salary,
        };
    }
}
