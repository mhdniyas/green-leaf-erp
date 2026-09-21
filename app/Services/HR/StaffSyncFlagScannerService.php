<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\StaffSyncFlag;
use App\Models\User;
use App\Services\Cashbook\StaffPaymentCashbookProjectionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StaffSyncFlagScannerService
{
    public function __construct(
        private readonly StaffPaymentCashbookProjectionService $projectionService,
        private readonly EmployeeAdvanceService $employeeAdvanceService,
    ) {}

    /**
     * Run full scan across ShopStaffPayments and Cashbook records.
     *
     * @param  array{shop_id?: ?int, employee_id?: ?int, month?: ?string, payment_type?: ?string}  $filters
     * @return array{scanned: int, open_flags: int, resolved_flags: int}
     */
    public function scanAll(array $filters = []): array
    {
        $query = ShopStaffPayment::query()
            ->with(['employee', 'shop', 'cashbookLine.entry', 'advanceRequest', 'payrollRunItem.payrollRun']);

        if (! empty($filters['shop_id'])) {
            $query->where('shop_id', (int) $filters['shop_id']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if (! empty($filters['month'])) {
            $start = Carbon::createFromFormat('Y-m', (string) $filters['month'])->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->whereDate('paid_on', '>=', $start->toDateString())
                ->whereDate('paid_on', '<=', $end->toDateString());
        }

        if (! empty($filters['payment_type'])) {
            $query->where('payment_type', (string) $filters['payment_type']);
        }

        $payments = $query->get();
        $scannedCount = $payments->count();

        foreach ($payments as $payment) {
            $this->checkPayment($payment);
        }

        // Check for orphan cashbook records
        $shopId = ! empty($filters['shop_id']) ? (int) $filters['shop_id'] : null;
        $this->checkOrphanCashbooks($shopId);

        $openCount = StaffSyncFlag::query()->open()->count();
        $resolvedCount = StaffSyncFlag::query()->resolved()->count();

        return [
            'scanned' => $scannedCount,
            'open_flags' => $openCount,
            'resolved_flags' => $resolvedCount,
        ];
    }

    /**
     * Check a single payment against History, Cashbook, and Salary/Advance state.
     *
     * @return array<int, StaffSyncFlag>
     */
    public function checkPayment(ShopStaffPayment $payment): array
    {
        $payment->loadMissing(['employee', 'shop', 'advanceRequest', 'payrollRunItem.payrollRun']);

        $masterAmount = round((float) $payment->amount, 2);
        $masterDate = $payment->paid_on ? $payment->paid_on->toDateString() : '';
        $masterType = (string) $payment->payment_type;
        $masterFundSource = (string) $payment->fund_source;
        $masterShopId = (int) $payment->shop_id;

        $detectedIssues = [];

        // 1. History Checks
        if (! $payment->shop_id || ! $payment->shop) {
            $detectedIssues[] = [
                'code' => StaffSyncFlag::CODE_SHOP_HISTORY_MISSING,
                'title' => 'Shop History Missing',
                'description' => 'Payment has no valid shop assigned and cannot appear in Shop Staff History.',
                'severity' => 'danger',
            ];
        }

        if (! $payment->employee_id || ! $payment->employee) {
            $detectedIssues[] = [
                'code' => StaffSyncFlag::CODE_HR_HISTORY_MISSING,
                'title' => 'HR Staff History Missing',
                'description' => 'Payment has no valid employee assigned and cannot appear in Admin HR Staff History.',
                'severity' => 'danger',
            ];
        }

        // 2. Cashbook Projection Checks
        $cashbookTxs = ShopLedgerTransaction::query()
            ->with(['entryType', 'shop'])
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->get();

        $activeTx = null;

        if ($cashbookTxs->isEmpty()) {
            $detectedIssues[] = [
                'code' => StaffSyncFlag::CODE_MISSING_CASHBOOK,
                'title' => 'Missing Cashbook Entry',
                'description' => "No linked Cashbook transaction found for {$masterType} payment of Rs. {$masterAmount}.",
                'severity' => 'danger',
            ];
        } elseif ($cashbookTxs->count() > 1) {
            $detectedIssues[] = [
                'code' => StaffSyncFlag::CODE_DUPLICATE_CASHBOOK,
                'title' => 'Duplicate Cashbook Entries',
                'description' => "Found {$cashbookTxs->count()} Cashbook transactions linked to payment #{$payment->id}.",
                'severity' => 'danger',
            ];
            $activeTx = $cashbookTxs->first();
        } else {
            $activeTx = $cashbookTxs->first();
            $txAmount = round((float) $activeTx->amount, 2);
            $txDate = $activeTx->business_date ? $activeTx->business_date->toDateString() : '';
            $txShopId = (int) $activeTx->shop_id;
            $txFundSource = (string) $activeTx->funding_source;

            // Amount Check
            if (abs($txAmount - $masterAmount) > 0.009) {
                $detectedIssues[] = [
                    'code' => StaffSyncFlag::CODE_CASHBOOK_AMOUNT_MISMATCH,
                    'title' => 'Cashbook Amount Mismatch',
                    'description' => "Master payment is Rs. {$masterAmount} but Cashbook entry #{$activeTx->id} has Rs. {$txAmount}.",
                    'severity' => 'danger',
                ];
            }

            // Date Check
            if ($txDate !== $masterDate) {
                $detectedIssues[] = [
                    'code' => StaffSyncFlag::CODE_CASHBOOK_DATE_MISMATCH,
                    'title' => 'Cashbook Date Mismatch',
                    'description' => "Master payment date is {$masterDate} but Cashbook business date is {$txDate}.",
                    'severity' => 'warning',
                ];
            }

            // Shop Check
            if ($txShopId !== $masterShopId) {
                $detectedIssues[] = [
                    'code' => StaffSyncFlag::CODE_CASHBOOK_SHOP_MISMATCH,
                    'title' => 'Cashbook Shop Mismatch',
                    'description' => "Master payment belongs to Shop #{$masterShopId} ({$payment->shop?->name}) but Cashbook entry is in Shop #{$txShopId}.",
                    'severity' => 'danger',
                ];
            }

            // Funding Source Check
            $canonMasterFs = $this->canonicalFundingSource($masterFundSource);
            $canonTxFs = $this->canonicalFundingSource($txFundSource);
            if ($canonMasterFs !== $canonTxFs) {
                $detectedIssues[] = [
                    'code' => StaffSyncFlag::CODE_CASHBOOK_FUND_SOURCE_MISMATCH,
                    'title' => 'Cashbook Fund Source Mismatch',
                    'description' => "Master fund source is '{$masterFundSource}' (canonical: {$canonMasterFs}) but Cashbook has '{$txFundSource}' (canonical: {$canonTxFs}).",
                    'severity' => 'warning',
                ];
            }

            // Category / Entry Type Check
            $entryCode = (string) ($activeTx->entryType?->code ?? '');
            if ($masterType === 'salary' && $entryCode !== 'salary') {
                $detectedIssues[] = [
                    'code' => StaffSyncFlag::CODE_CASHBOOK_CATEGORY_MISMATCH,
                    'title' => 'Cashbook Category Mismatch',
                    'description' => "Payment is Salary (expected ledger type 'salary') but Cashbook transaction entry type is '{$entryCode}'.",
                    'severity' => 'warning',
                ];
            } elseif ($masterType === 'advance' && $entryCode !== 'staff_advance') {
                $detectedIssues[] = [
                    'code' => StaffSyncFlag::CODE_CASHBOOK_CATEGORY_MISMATCH,
                    'title' => 'Cashbook Category Mismatch',
                    'description' => "Payment is Staff Advance (expected ledger type 'staff_advance') but Cashbook transaction entry type is '{$entryCode}'.",
                    'severity' => 'warning',
                ];
            }
        }

        // 3. Salary / Advance State Checks
        if ($masterType === 'advance' && $payment->employee_advance_request_id) {
            $advanceRequest = $payment->advanceRequest;
            if ($advanceRequest) {
                $reqApproved = round((float) ($advanceRequest->approved_amount ?? $advanceRequest->requested_amount), 2);
                if (abs($reqApproved - $masterAmount) > 0.009) {
                    $detectedIssues[] = [
                        'code' => StaffSyncFlag::CODE_ADVANCE_BALANCE_MISMATCH,
                        'title' => 'Advance Balance Mismatch',
                        'description' => "Linked Advance Request #{$advanceRequest->id} approved amount is Rs. {$reqApproved}, but payment is Rs. {$masterAmount}.",
                        'severity' => 'warning',
                    ];
                }
            }
        }

        // Build 4-way comparison details snapshot
        $detailsSnapshot = [
            'master' => [
                'id' => $payment->id,
                'amount' => $masterAmount,
                'paid_on' => $masterDate,
                'payment_type' => $masterType,
                'fund_source' => $masterFundSource,
                'shop_id' => $masterShopId,
                'shop_name' => $payment->shop?->name ?? '—',
                'employee_id' => $payment->employee_id,
                'employee_name' => $payment->employee?->name ?? '—',
                'notes' => $payment->notes,
            ],
            'shop_history' => [
                'amount' => $masterAmount,
                'status' => $payment->shop ? 'visible' : 'missing_shop',
            ],
            'hr_history' => [
                'amount' => $masterAmount,
                'status' => $payment->employee ? 'visible' : 'missing_employee',
            ],
            'cashbook' => $activeTx ? [
                'id' => $activeTx->id,
                'amount' => round((float) $activeTx->amount, 2),
                'business_date' => $activeTx->business_date ? $activeTx->business_date->toDateString() : '',
                'shop_id' => $activeTx->shop_id,
                'shop_name' => $activeTx->shop?->name ?? '—',
                'funding_source' => $activeTx->funding_source,
                'entry_type' => $activeTx->entryType?->name ?? $activeTx->entryType?->code ?? '—',
            ] : null,
            'salary_advance' => [
                'type' => $masterType,
                'status' => 'active',
                'advance_request_id' => $payment->employee_advance_request_id,
                'payroll_run_item_id' => $payment->payroll_run_item_id,
            ],
        ];

        $savedFlags = [];
        $activeCodes = [];

        foreach ($detectedIssues as $issue) {
            $activeCodes[] = $issue['code'];

            $flag = StaffSyncFlag::query()
                ->where('source_type', ShopStaffPayment::class)
                ->where('source_id', $payment->id)
                ->where('flag_code', $issue['code'])
                ->first();

            if (! $flag) {
                $flag = new StaffSyncFlag([
                    'source_type' => ShopStaffPayment::class,
                    'source_id' => $payment->id,
                    'flag_code' => $issue['code'],
                ]);
            }

            $flag->severity = $issue['severity'];
            $flag->shop_id = $payment->shop_id;
            $flag->employee_id = $payment->employee_id;
            $flag->payment_date = $payment->paid_on?->toDateString();
            $flag->status = StaffSyncFlag::STATUS_OPEN;
            $flag->title = $issue['title'];
            $flag->description = $issue['description'];
            $flag->details = $detailsSnapshot;
            $flag->resolved_at = null;
            $flag->resolved_by = null;
            $flag->save();

            $savedFlags[] = $flag;
        }

        // Auto-resolve any flags for this payment that are no longer present
        StaffSyncFlag::query()
            ->where('source_type', ShopStaffPayment::class)
            ->where('source_id', $payment->id)
            ->where('status', StaffSyncFlag::STATUS_OPEN)
            ->whereNotIn('flag_code', $activeCodes)
            ->update([
                'status' => StaffSyncFlag::STATUS_RESOLVED,
                'resolved_at' => now(),
                'resolution_notes' => 'Resolved automatically by integrity scan.',
            ]);

        return $savedFlags;
    }

    /**
     * Canonicalize funding source aliases to standard names.
     */
    public function canonicalFundingSource(?string $source): string
    {
        return match (strtolower(trim((string) $source))) {
            'sales_income', 'sales' => 'sales',
            'petty_cash', 'petty' => 'petty',
            'company', 'company_account' => 'company',
            'settlement' => 'settlement',
            'third_party' => 'third_party',
            'none' => 'none',
            default => strtolower(trim((string) $source)),
        };
    }

    /**
     * Check and flag orphan Cashbook transactions.
     */
    public function checkOrphanCashbooks(?int $shopId = null): void
    {
        $query = ShopLedgerTransaction::query()
            ->with(['shop', 'entryType'])
            ->where('reference_type', ShopStaffPayment::class)
            ->whereNotNull('reference_id')
            ->whereNotIn('reference_id', ShopStaffPayment::query()->select('id'));

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        $orphans = $query->get();
        $activeOrphanTxIds = [];

        foreach ($orphans as $orphan) {
            $activeOrphanTxIds[] = $orphan->id;
            $detailsSnapshot = [
                'cashbook' => [
                    'id' => $orphan->id,
                    'amount' => round((float) $orphan->amount, 2),
                    'business_date' => $orphan->business_date ? $orphan->business_date->toDateString() : '',
                    'shop_id' => $orphan->shop_id,
                    'shop_name' => $orphan->shop?->name ?? '—',
                    'funding_source' => $orphan->funding_source,
                    'entry_type' => $orphan->entryType?->name ?? $orphan->entryType?->code ?? '—',
                ],
                'master' => null,
            ];

            $flag = StaffSyncFlag::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->where('source_id', $orphan->id)
                ->where('flag_code', StaffSyncFlag::CODE_ORPHAN_CASHBOOK)
                ->first();

            if (! $flag) {
                $flag = new StaffSyncFlag([
                    'source_type' => ShopLedgerTransaction::class,
                    'source_id' => $orphan->id,
                    'flag_code' => StaffSyncFlag::CODE_ORPHAN_CASHBOOK,
                ]);
            }

            $flag->severity = 'danger';
            $flag->shop_id = $orphan->shop_id;
            $flag->employee_id = null;
            $flag->payment_date = $orphan->business_date?->toDateString();
            $flag->status = StaffSyncFlag::STATUS_OPEN;
            $flag->title = 'Orphan Cashbook Entry';
            $flag->description = "Cashbook transaction #{$orphan->id} (Rs. {$orphan->amount}) is linked to ShopStaffPayment #{$orphan->reference_id} which no longer exists.";
            $flag->details = $detailsSnapshot;
            $flag->resolved_at = null;
            $flag->resolved_by = null;
            $flag->save();
        }

        // Auto-resolve any orphan flags for transactions that no longer exist or are no longer orphans
        $orphanResolutionQuery = StaffSyncFlag::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('flag_code', StaffSyncFlag::CODE_ORPHAN_CASHBOOK)
            ->where('status', StaffSyncFlag::STATUS_OPEN);

        if ($shopId) {
            $orphanResolutionQuery->where('shop_id', $shopId);
        }

        if (! empty($activeOrphanTxIds)) {
            $orphanResolutionQuery->whereNotIn('source_id', $activeOrphanTxIds);
        }

        $orphanResolutionQuery->update([
            'status' => StaffSyncFlag::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolution_notes' => 'Orphan cashbook transaction confirmed resolved.',
        ]);
    }

    /**
     * Fix a flagged payment with optional Master overrides.
     *
     * @param  array{amount?: float|string, paid_on?: string, payment_type?: string, fund_source?: string, shop_id?: int, notes?: ?string}  $overrides
     * @return array{success: bool, payment: ShopStaffPayment, flags: array<int, StaffSyncFlag>}
     */
    public function fixPayment(ShopStaffPayment $payment, array $overrides = [], ?User $actor = null): array
    {
        return DB::transaction(function () use ($payment, $overrides, $actor): array {
            // 1. Apply overrides if provided
            if (! empty($overrides)) {
                $this->employeeAdvanceService->updateShopStaffPayment($payment, $overrides, $actor);
                $payment->refresh();
            }

            // 2. Synchronize Cashbook projection
            $this->projectionService->syncPayment($payment, $actor?->id);

            // 3. Recalculate Cashbook running balances for the shop and date
            $businessDate = $payment->paid_on?->toDateString() ?? today()->toDateString();
            $this->projectionService->recalculateBalancesFromDate((int) $payment->shop_id, $businessDate);

            // 4. Re-check payment integrity
            $flags = $this->checkPayment($payment);

            // 5. If clean, ensure all flags for this payment are marked resolved
            if (empty($flags)) {
                StaffSyncFlag::query()
                    ->where('source_type', ShopStaffPayment::class)
                    ->where('source_id', $payment->id)
                    ->where('status', StaffSyncFlag::STATUS_OPEN)
                    ->update([
                        'status' => StaffSyncFlag::STATUS_RESOLVED,
                        'resolved_at' => now(),
                        'resolved_by' => $actor?->id,
                        'resolution_notes' => 'Fixed and synchronized by Admin.',
                    ]);
            }

            return [
                'success' => empty($flags),
                'payment' => $payment,
                'flags' => $flags,
            ];
        });
    }

    /**
     * Build rich comparison payload for Admin review and decision screen.
     *
     * @return array<string, mixed>
     */
    public function getReviewPayload(StaffSyncFlag $flag): array
    {
        $flag->loadMissing(['employee', 'shop']);

        if ($flag->source_type === ShopLedgerTransaction::class) {
            $tx = ShopLedgerTransaction::query()->with(['entryType', 'shop'])->find($flag->source_id);
            $txAmount = $tx ? round((float) $tx->amount, 2) : null;
            $txDate = $tx?->business_date ? $tx->business_date->toDateString() : null;
            $txType = (string) ($tx?->entryType?->code ?? '');
            $txTypeName = $tx?->entryType?->name ?? ($txType === 'salary' ? 'Salary' : ($txType === 'staff_advance' ? 'Staff Advance' : $txType));
            $txFundSource = $tx?->funding_source ? (string) $tx->funding_source : null;
            $txShopName = $tx?->shop?->name ?? ($tx ? "Shop #{$tx->shop_id}" : null);

            $detectedProblems = [
                [
                    'aspect' => 'shop_staff',
                    'title' => 'Shop Staff',
                    'is_ok' => false,
                    'value' => 'MISSING',
                    'description' => 'No parent ShopStaffPayment record exists for this Cashbook entry.',
                ],
                [
                    'aspect' => 'cashbook',
                    'title' => 'Cashbook',
                    'is_ok' => false,
                    'value' => $tx ? "{$txTypeName} ₹".number_format($txAmount ?? 0, 2) : 'MISSING',
                    'description' => $tx ? "Entry #{$tx->id} on {$txDate} in {$txShopName}" : 'Transaction missing.',
                ],
                [
                    'aspect' => 'hr_history',
                    'title' => 'HR History',
                    'is_ok' => false,
                    'value' => 'MISSING',
                    'description' => 'Cannot appear in HR History without a linked Staff Payment.',
                ],
                [
                    'aspect' => 'summary',
                    'title' => 'Issue Detected',
                    'is_ok' => false,
                    'value' => 'ORPHAN_CASHBOOK',
                    'description' => 'Cashbook transaction has no parent payment record. Admin must decide whether this payment should exist or if the Cashbook entry should be removed.',
                ],
            ];

            return [
                'flag' => $flag,
                'is_orphan' => true,
                'is_missing_cashbook' => false,
                'detected_problems' => $detectedProblems,
                'existing_values' => [
                    'amount' => [
                        'shop_staff' => null,
                        'hr_history' => null,
                        'cashbook' => $txAmount,
                        'is_match' => false,
                        'prefill' => $txAmount ?? 0.0,
                    ],
                    'category' => [
                        'shop_staff' => null,
                        'hr_history' => null,
                        'cashbook' => $txTypeName,
                        'is_match' => false,
                        'prefill' => $txType === 'staff_advance' ? 'advance' : 'salary',
                    ],
                    'paid_on' => [
                        'shop_staff' => null,
                        'hr_history' => null,
                        'cashbook' => $txDate,
                        'is_match' => false,
                        'prefill' => $txDate ?? today()->toDateString(),
                    ],
                    'fund_source' => [
                        'shop_staff' => null,
                        'hr_history' => null,
                        'cashbook' => $txFundSource,
                        'canonical' => $txFundSource ? "Canonical: {$this->canonicalFundingSource($txFundSource)}" : '—',
                        'is_match' => false,
                        'prefill' => $txFundSource ?? 'sales',
                    ],
                    'shop' => [
                        'shop_staff_name' => null,
                        'hr_history_name' => null,
                        'cashbook_name' => $txShopName,
                        'is_match' => false,
                        'prefill_id' => $tx?->shop_id ?? null,
                    ],
                    'notes' => [
                        'prefill' => $tx?->notes ?? '',
                    ],
                ],
                'available_fixes' => $this->getAvailableFixes($flag),
                'related_flags' => [$flag],
                'issues_count' => 1,
                'details' => $flag->details,
            ];
        }

        // ShopStaffPayment Source
        $payment = ShopStaffPayment::query()
            ->with(['employee', 'shop', 'advanceRequest', 'payrollRunItem'])
            ->find($flag->source_id);

        if (! $payment) {
            return [
                'flag' => $flag,
                'is_orphan' => false,
                'is_missing_cashbook' => false,
                'detected_problems' => [
                    [
                        'aspect' => 'general',
                        'title' => 'Payment Missing',
                        'is_ok' => false,
                        'value' => 'Payment #'.$flag->source_id.' no longer exists.',
                        'description' => 'Payment was deleted or removed.',
                    ],
                ],
                'existing_values' => [],
                'available_fixes' => [],
                'related_flags' => [],
                'issues_count' => 0,
            ];
        }

        $masterAmount = round((float) $payment->amount, 2);
        $masterDate = $payment->paid_on ? $payment->paid_on->toDateString() : '';
        $masterType = (string) $payment->payment_type;
        $masterTypeName = $masterType === 'advance' ? 'Staff Advance' : 'Salary';
        $masterFundSource = (string) $payment->fund_source;
        $masterShopName = $payment->shop?->name ?? "Shop #{$payment->shop_id}";
        $masterEmployeeName = $payment->employee?->name ?? '—';

        $cashbookTxs = ShopLedgerTransaction::query()
            ->with(['entryType', 'shop'])
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->get();

        $activeTx = $cashbookTxs->first();
        $hasCashbook = $activeTx !== null;
        $txAmount = $hasCashbook ? round((float) $activeTx->amount, 2) : null;
        $txDate = $hasCashbook && $activeTx->business_date ? $activeTx->business_date->toDateString() : null;
        $txType = $hasCashbook ? (string) ($activeTx->entryType?->code ?? '') : null;
        $txTypeName = $hasCashbook ? ($activeTx->entryType?->name ?? ($txType === 'salary' ? 'Salary' : ($txType === 'staff_advance' ? 'Staff Advance' : $txType))) : null;
        $txFundSource = $hasCashbook ? (string) $activeTx->funding_source : null;
        $txShopName = $hasCashbook ? ($activeTx->shop?->name ?? "Shop #{$activeTx->shop_id}") : null;

        // Match calculations
        $amountMatches = $hasCashbook && abs(($txAmount ?? 0) - $masterAmount) < 0.009;
        $expectedTxCode = $masterType === 'advance' ? 'staff_advance' : 'salary';
        $categoryMatches = $hasCashbook && ($txType === $expectedTxCode);
        $dateMatches = $hasCashbook && ($txDate === $masterDate);
        $canonMasterFs = $this->canonicalFundingSource($masterFundSource);
        $canonTxFs = $hasCashbook ? $this->canonicalFundingSource($txFundSource) : null;
        $fundSourceMatches = $hasCashbook && ($canonMasterFs === $canonTxFs);
        $shopMatches = $hasCashbook && ((int) $activeTx->shop_id === (int) $payment->shop_id);

        $detectedProblems = [];

        // 1. Shop Staff status
        $detectedProblems[] = [
            'aspect' => 'shop_staff',
            'title' => 'Shop Staff',
            'is_ok' => (bool) ($payment->shop_id && $payment->shop),
            'value' => "{$masterTypeName} ₹".number_format($masterAmount, 2),
            'description' => $payment->shop ? "Shop Staff History recorded in {$masterShopName}" : 'Missing shop assignment.',
        ];

        // 2. Cashbook status
        $detectedProblems[] = [
            'aspect' => 'cashbook',
            'title' => 'Cashbook',
            'is_ok' => $hasCashbook && $amountMatches && $categoryMatches && $dateMatches && $shopMatches,
            'value' => $hasCashbook ? "{$txTypeName} ₹".number_format($txAmount ?? 0, 2) : 'MISSING',
            'description' => $hasCashbook ? "Cashbook #{$activeTx->id} in {$txShopName}" : 'No linked Cashbook transaction found.',
        ];

        // 3. HR History status
        $detectedProblems[] = [
            'aspect' => 'hr_history',
            'title' => 'HR History',
            'is_ok' => (bool) ($payment->employee_id && $payment->employee),
            'value' => "{$masterTypeName} ₹".number_format($masterAmount, 2),
            'description' => $payment->employee ? "HR record for {$masterEmployeeName}" : 'Missing employee assignment.',
        ];

        // 4. Amount comparison
        $detectedProblems[] = [
            'aspect' => 'amount',
            'title' => 'Amount',
            'is_ok' => $hasCashbook ? $amountMatches : false,
            'value' => ! $hasCashbook ? 'Cashbook Missing' : ($amountMatches ? 'Amount matches (₹'.number_format($masterAmount, 2).')' : 'Amount does not match (Shop/HR: ₹'.number_format($masterAmount, 2).' vs Cashbook: ₹'.number_format($txAmount ?? 0, 2).')'),
            'description' => $amountMatches ? 'All systems agree on amount.' : 'Discrepancy detected in amount.',
        ];

        // 5. Category comparison
        $detectedProblems[] = [
            'aspect' => 'category',
            'title' => 'Category',
            'is_ok' => $hasCashbook ? $categoryMatches : false,
            'value' => ! $hasCashbook ? 'Cashbook Missing' : ($categoryMatches ? "Category matches ({$masterTypeName})" : "Category does not match (Shop/HR: {$masterTypeName} vs Cashbook: {$txTypeName})"),
            'description' => $categoryMatches ? 'Payment classification agrees.' : 'Classification mismatch between records.',
        ];

        // 6. Date comparison
        if (! $dateMatches && $hasCashbook) {
            $detectedProblems[] = [
                'aspect' => 'date',
                'title' => 'Date',
                'is_ok' => false,
                'value' => "Date does not match (Shop/HR: {$masterDate} vs Cashbook: {$txDate})",
                'description' => 'Payment date differs from Cashbook business date.',
            ];
        }

        // 7. Fund source comparison
        if (! $fundSourceMatches && $hasCashbook) {
            $detectedProblems[] = [
                'aspect' => 'fund_source',
                'title' => 'Fund Source',
                'is_ok' => false,
                'value' => "Fund source mismatch (Shop/HR: {$masterFundSource} vs Cashbook: {$txFundSource})",
                'description' => "Canonical: {$canonMasterFs} vs {$canonTxFs}",
            ];
        }

        // 8. Shop comparison
        if (! $shopMatches && $hasCashbook) {
            $detectedProblems[] = [
                'aspect' => 'shop',
                'title' => 'Shop Location',
                'is_ok' => false,
                'value' => "Shop does not match (Shop/HR: {$masterShopName} vs Cashbook: {$txShopName})",
                'description' => 'Payment registered under different shop than Cashbook ledger.',
            ];
        }

        $canonicalNote = ($canonMasterFs === $canonTxFs)
            ? 'Both = '.ucfirst($canonMasterFs)
            : "Shop: {$masterFundSource} | Cashbook: ".($txFundSource ?? 'None');

        $relatedFlags = StaffSyncFlag::query()
            ->where('source_type', ShopStaffPayment::class)
            ->where('source_id', $payment->id)
            ->where('status', StaffSyncFlag::STATUS_OPEN)
            ->get();

        return [
            'flag' => $flag,
            'is_orphan' => false,
            'is_missing_cashbook' => ! $hasCashbook,
            'detected_problems' => $detectedProblems,
            'existing_values' => [
                'amount' => [
                    'shop_staff' => $masterAmount,
                    'hr_history' => $masterAmount,
                    'cashbook' => $txAmount,
                    'is_match' => $amountMatches,
                    'prefill' => $masterAmount,
                ],
                'category' => [
                    'shop_staff' => $masterTypeName,
                    'hr_history' => $masterTypeName,
                    'cashbook' => $txTypeName,
                    'is_match' => $categoryMatches,
                    'prefill' => $masterType,
                ],
                'paid_on' => [
                    'shop_staff' => $masterDate,
                    'hr_history' => $masterDate,
                    'cashbook' => $txDate,
                    'is_match' => $dateMatches,
                    'prefill' => $masterDate,
                ],
                'fund_source' => [
                    'shop_staff' => $masterFundSource,
                    'hr_history' => $masterFundSource,
                    'cashbook' => $txFundSource,
                    'canonical' => $canonicalNote,
                    'is_match' => $fundSourceMatches,
                    'prefill' => $masterFundSource,
                ],
                'shop' => [
                    'shop_staff_name' => $masterShopName,
                    'hr_history_name' => $masterShopName,
                    'cashbook_name' => $txShopName,
                    'is_match' => $shopMatches,
                    'prefill_id' => (int) $payment->shop_id,
                ],
                'notes' => [
                    'prefill' => $payment->notes ?? '',
                ],
            ],
            'available_fixes' => $this->getAvailableFixes($flag),
            'related_flags' => $relatedFlags,
            'issues_count' => $relatedFlags->count() > 0 ? $relatedFlags->count() : count($this->getAvailableFixes($flag)),
            'details' => $flag->details,
        ];
    }

    /**
     * Apply Admin's final decision across all linked subsystems.
     *
     * @param  array<string, mixed>  $decisionData
     * @return array{success: bool, payment?: ?ShopStaffPayment, remaining_flags_count: int, remaining_flags: array<int, StaffSyncFlag>, message: string}
     */
    public function applyAdminDecision(StaffSyncFlag $flag, array $decisionData, ?User $actor = null): array
    {
        return DB::transaction(function () use ($flag, $decisionData, $actor): array {
            // 1. Orphan Cashbook Handling
            if ($flag->source_type === ShopLedgerTransaction::class) {
                $orphanAction = $decisionData['orphan_action'] ?? $decisionData['action'] ?? 'remove_cashbook';

                if ($orphanAction === 'remove_cashbook' || in_array('remove_orphan_cashbook', (array) ($decisionData['selected_fixes'] ?? []), true)) {
                    $this->fixOrphanCashbook((int) $flag->source_id, $actor);

                    return [
                        'success' => true,
                        'remaining_flags_count' => 0,
                        'remaining_flags' => [],
                        'message' => 'Orphan Cashbook transaction removed and daily balances recalculated.',
                    ];
                }

                if ($orphanAction === 'create_payment') {
                    $employeeId = (int) ($decisionData['employee_id'] ?? 0);
                    $employee = Employee::query()->findOrFail($employeeId);
                    $shopId = (int) ($decisionData['shop_id'] ?? 0);
                    $shop = Shop::query()->findOrFail($shopId);

                    $amount = round((float) ($decisionData['amount'] ?? 0), 2);
                    $paidOn = Carbon::parse((string) ($decisionData['paid_on'] ?? today()));
                    $paymentType = (string) ($decisionData['payment_type'] ?? 'salary');
                    $fundSource = (string) ($decisionData['fund_source'] ?? 'sales');
                    $notes = filled($decisionData['notes'] ?? null) ? (string) $decisionData['notes'] : 'Restored from orphan Cashbook transaction #'.$flag->source_id;

                    // Delete the orphan transaction so recordAdminShopStaffPayment creates the fresh clean linked transaction and balances
                    $tx = ShopLedgerTransaction::query()->find($flag->source_id);
                    $orphanShopId = $tx ? (int) $tx->shop_id : $shopId;
                    $orphanDate = $tx?->business_date ? $tx->business_date->toDateString() : $paidOn->toDateString();
                    if ($tx) {
                        $tx->delete();
                        $this->projectionService->recalculateBalancesFromDate($orphanShopId, $orphanDate);
                    }

                    $payment = $this->employeeAdvanceService->recordAdminShopStaffPayment(
                        $employee,
                        $shop,
                        $amount,
                        $paymentType,
                        $fundSource,
                        $paidOn,
                        $actor ?? User::factory()->make(),
                        $notes
                    );

                    $flag->status = StaffSyncFlag::STATUS_RESOLVED;
                    $flag->resolved_at = now();
                    $flag->resolved_by = $actor?->id;
                    $flag->resolution_notes = "Restored as {$paymentType} payment #{$payment->id} for {$employee->name} by ".($actor?->name ?? 'Admin').'.';
                    $flag->save();

                    $remainingFlags = $this->checkPayment($payment);

                    return [
                        'success' => true,
                        'payment' => $payment,
                        'remaining_flags_count' => count($remainingFlags),
                        'remaining_flags' => $remainingFlags,
                        'message' => "Payment #{$payment->id} successfully created and synchronized across all records.",
                    ];
                }

                throw new \InvalidArgumentException('Invalid orphan resolution action.');
            }

            // 2. ShopStaffPayment Handling
            if ($flag->source_type === ShopStaffPayment::class) {
                $payment = ShopStaffPayment::query()->find($flag->source_id);
                if (! $payment) {
                    $flag->status = StaffSyncFlag::STATUS_RESOLVED;
                    $flag->resolved_at = now();
                    $flag->resolved_by = $actor?->id;
                    $flag->resolution_notes = 'Payment no longer exists.';
                    $flag->save();

                    return [
                        'success' => true,
                        'payment' => null,
                        'remaining_flags_count' => 0,
                        'remaining_flags' => [],
                        'message' => 'Payment no longer exists. Flag resolved.',
                    ];
                }

                $action = $decisionData['action'] ?? 'apply_final_value';

                if ($action === 'cancel_payment') {
                    $this->employeeAdvanceService->deleteShopStaffPayment($payment, $actor ?? User::factory()->make(), false);

                    StaffSyncFlag::query()
                        ->where('source_type', ShopStaffPayment::class)
                        ->where('source_id', $flag->source_id)
                        ->update([
                            'status' => StaffSyncFlag::STATUS_RESOLVED,
                            'resolved_at' => now(),
                            'resolved_by' => $actor?->id,
                            'resolution_notes' => 'Payment cancelled and removed by '.($actor?->name ?? 'Admin').'.',
                        ]);

                    return [
                        'success' => true,
                        'payment' => null,
                        'remaining_flags_count' => 0,
                        'remaining_flags' => [],
                        'message' => "Payment #{$flag->source_id} cancelled and all linked records updated.",
                    ];
                }

                // Normal 'apply_final_value' decision
                $amount = round((float) ($decisionData['amount'] ?? $payment->amount), 2);
                $paidOn = isset($decisionData['paid_on']) ? Carbon::parse((string) $decisionData['paid_on']) : ($payment->paid_on ?? today());
                $paymentType = (string) ($decisionData['payment_type'] ?? $payment->payment_type);
                $fundSource = (string) ($decisionData['fund_source'] ?? $payment->fund_source);
                $shopId = isset($decisionData['shop_id']) ? (int) $decisionData['shop_id'] : (int) $payment->shop_id;
                $notes = array_key_exists('notes', $decisionData) ? $decisionData['notes'] : $payment->notes;

                // Handle transition between advance and salary
                if ($paymentType === 'advance' && $payment->payment_type === 'salary' && ! $payment->employee_advance_request_id) {
                    $adv = EmployeeAdvanceRequest::query()->create([
                        'employee_id' => $payment->employee_id,
                        'shop_id' => $shopId,
                        'requested_by' => $actor?->id ?? $payment->paid_by,
                        'reviewed_by' => $actor?->id ?? $payment->paid_by,
                        'requested_on' => $paidOn->toDateString(),
                        'payroll_month' => $paidOn->copy()->startOfMonth()->toDateString(),
                        'requested_amount' => $amount,
                        'approved_amount' => $amount,
                        'fund_source' => $fundSource,
                        'approved_fund_source' => $fundSource,
                        'status' => 'approved',
                        'review_note' => $notes,
                        'reviewed_at' => now(),
                        'shop_staff_payment_id' => $payment->id,
                    ]);
                    $payment->employee_advance_request_id = $adv->id;
                    $payment->save();
                } elseif ($paymentType === 'salary' && $payment->payment_type === 'advance' && $payment->advanceRequest) {
                    $adv = $payment->advanceRequest;
                    $payment->employee_advance_request_id = null;
                    $payment->save();
                    $adv->delete();
                }

                $updatePayload = [
                    'amount' => $amount,
                    'paid_on' => $paidOn->toDateString(),
                    'payment_type' => $paymentType,
                    'fund_source' => $fundSource,
                    'shop_id' => $shopId,
                    'notes' => $notes,
                ];

                $this->employeeAdvanceService->updateShopStaffPayment($payment, $updatePayload, $actor ?? User::factory()->make());
                $payment->refresh();

                // Ensure Cashbook projection is 100% updated to match final decision
                $this->projectionService->syncPayment($payment, $actor?->id);
                $this->projectionService->recalculateBalancesFromDate((int) $payment->shop_id, $paidOn->toDateString());

                // Re-run scanner to verify that all systems agree
                $remainingFlags = $this->checkPayment($payment);

                if (empty($remainingFlags)) {
                    StaffSyncFlag::query()
                        ->where('source_type', ShopStaffPayment::class)
                        ->where('source_id', $payment->id)
                        ->where('status', StaffSyncFlag::STATUS_OPEN)
                        ->update([
                            'status' => StaffSyncFlag::STATUS_RESOLVED,
                            'resolved_at' => now(),
                            'resolved_by' => $actor?->id,
                            'resolution_notes' => 'Admin decision applied by '.($actor?->name ?? 'Admin').': Amount: ₹'.number_format($amount, 2).", Type: {$paymentType}, Date: {$paidOn->toDateString()}, Shop: #{$shopId}.",
                        ]);
                }

                $remainingCount = count($remainingFlags);
                $message = $remainingCount === 0
                    ? '✓ All linked systems (Shop Staff, Admin HR, Cashbook, Salary/Advance) updated and verified to match Admin final values.'
                    : "Admin values applied. {$remainingCount} ".($remainingCount === 1 ? 'discrepancy remains' : 'discrepancies remain').' and must be reviewed.';

                return [
                    'success' => $remainingCount === 0,
                    'payment' => $payment,
                    'remaining_flags_count' => $remainingCount,
                    'remaining_flags' => $remainingFlags,
                    'message' => $message,
                ];
            }

            throw new \InvalidArgumentException('Unsupported flag source.');
        });
    }

    /**
     * Get available selectable fixes for a flag.
     *
     * @return array<int, array{key: string, title: string, description: string, from?: string, to?: string, effect?: string}>
     */
    public function getAvailableFixes(StaffSyncFlag $flag): array
    {
        if ($flag->source_type === ShopLedgerTransaction::class) {
            $tx = ShopLedgerTransaction::query()->with('shop')->find($flag->source_id);
            if (! $tx) {
                return [];
            }

            $amountFormatted = number_format((float) $tx->amount, 2);
            $shopName = $tx->shop?->name ?? 'Shop';

            return [
                [
                    'key' => 'remove_orphan_cashbook',
                    'title' => 'Remove orphan Cashbook transaction',
                    'description' => "Linked ShopStaffPayment #{$tx->reference_id} no longer exists. This transaction is still affecting Cashbook.",
                    'effect' => "{$shopName} balance adjustment: +₹{$amountFormatted}",
                ],
            ];
        }

        if ($flag->source_type !== ShopStaffPayment::class) {
            return [];
        }

        $payment = ShopStaffPayment::query()
            ->with(['shop', 'employee', 'advanceRequest', 'payrollRunItem'])
            ->find($flag->source_id);

        if (! $payment) {
            return [];
        }

        $cashbookTxs = ShopLedgerTransaction::query()
            ->with(['entryType', 'shop'])
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->get();

        $fixes = [];
        $masterAmount = round((float) $payment->amount, 2);
        $masterDate = $payment->paid_on ? $payment->paid_on->toDateString() : '';
        $masterType = (string) $payment->payment_type;
        $masterFundSource = (string) $payment->fund_source;
        $masterShopName = $payment->shop?->name ?? 'Assigned Shop';

        if ($cashbookTxs->isEmpty()) {
            $fixes[] = [
                'key' => 'create_missing_cashbook',
                'title' => 'Create missing Cashbook entry from HR Master',
                'description' => 'Generate Cashbook expense entry of ₹'.number_format($masterAmount, 2)." in {$masterShopName}.",
                'from' => 'No Cashbook Entry',
                'to' => '₹'.number_format($masterAmount, 2)." ({$masterType})",
            ];
        } else {
            $activeTx = $cashbookTxs->first();
            $txAmount = round((float) $activeTx->amount, 2);
            $txDate = $activeTx->business_date ? $activeTx->business_date->toDateString() : '';
            $txFundSource = (string) $activeTx->funding_source;
            $txShopId = (int) $activeTx->shop_id;

            // 1. Category Mismatch
            $entryCode = (string) ($activeTx->entryType?->code ?? '');
            $expectedCode = $masterType === 'advance' ? 'staff_advance' : 'salary';
            $expectedName = $masterType === 'advance' ? 'Staff Advance' : 'Salary';
            $currentName = $activeTx->entryType?->name ?? ($entryCode === 'salary' ? 'Salary' : ($entryCode === 'staff_advance' ? 'Staff Advance' : $entryCode));

            if (($masterType === 'salary' && $entryCode !== 'salary') || ($masterType === 'advance' && $entryCode !== 'staff_advance')) {
                $fixes[] = [
                    'key' => 'fix_cashbook_category',
                    'title' => "Update Cashbook category: {$currentName} → {$expectedName}",
                    'description' => "Change Cashbook entry #{$activeTx->id} type to {$expectedName}.",
                    'from' => $currentName,
                    'to' => $expectedName,
                ];
            }

            // 2. Amount Mismatch
            if (abs($txAmount - $masterAmount) > 0.009) {
                $fixes[] = [
                    'key' => 'fix_cashbook_amount',
                    'title' => 'Update Cashbook amount: ₹'.number_format($txAmount, 2).' → ₹'.number_format($masterAmount, 2),
                    'description' => "Realign Cashbook entry #{$activeTx->id} amount to match HR Master.",
                    'from' => '₹'.number_format($txAmount, 2),
                    'to' => '₹'.number_format($masterAmount, 2),
                ];
            }

            // 3. Date Mismatch
            if ($txDate !== $masterDate) {
                $fixes[] = [
                    'key' => 'fix_cashbook_date',
                    'title' => "Update Cashbook date: {$txDate} → {$masterDate}",
                    'description' => "Shift Cashbook entry #{$activeTx->id} business date to match HR Master payment date.",
                    'from' => $txDate,
                    'to' => $masterDate,
                ];
            }

            // 4. Fund Source Mismatch
            $canonMasterFs = $this->canonicalFundingSource($masterFundSource);
            $canonTxFs = $this->canonicalFundingSource($txFundSource);
            if ($canonMasterFs !== $canonTxFs) {
                $fixes[] = [
                    'key' => 'fix_cashbook_fund_source',
                    'title' => "Update Cashbook fund source: {$txFundSource} → {$masterFundSource}",
                    'description' => 'Synchronize Cashbook funding source with HR Master.',
                    'from' => $txFundSource,
                    'to' => $masterFundSource,
                ];
            }

            // 5. Shop Mismatch
            if ($txShopId !== (int) $payment->shop_id) {
                $txShopName = $activeTx->shop?->name ?? "Shop #{$txShopId}";
                $fixes[] = [
                    'key' => 'fix_cashbook_shop',
                    'title' => "Update Cashbook shop: {$txShopName} → {$masterShopName}",
                    'description' => "Move Cashbook entry #{$activeTx->id} to {$masterShopName}.",
                    'from' => $txShopName,
                    'to' => $masterShopName,
                ];
            }
        }

        // 6. Advance Balance Mismatch
        if ($masterType === 'advance' && $payment->employee_advance_request_id) {
            $advanceRequest = $payment->advanceRequest;
            if ($advanceRequest) {
                $reqApproved = round((float) ($advanceRequest->approved_amount ?? $advanceRequest->requested_amount), 2);
                if (abs($reqApproved - $masterAmount) > 0.009) {
                    $fixes[] = [
                        'key' => 'recalc_advance_balance',
                        'title' => 'Recalculate Staff Advance balance',
                        'description' => "Synchronize Advance Request #{$advanceRequest->id} approved amount (₹".number_format($reqApproved, 2).') with payment (₹'.number_format($masterAmount, 2).').',
                        'from' => '₹'.number_format($reqApproved, 2),
                        'to' => '₹'.number_format($masterAmount, 2),
                    ];
                }
            }
        }

        return $fixes;
    }

    /**
     * Apply only explicitly selected fixes for a payment.
     *
     * @param  array<int, string>  $selectedFixes
     * @return array{success: bool, applied_fixes: array<int, string>, applied_count: int, remaining_flags_count: int, remaining_flags: array<int, StaffSyncFlag>, message: string}
     */
    public function applySelectiveFixes(ShopStaffPayment $payment, array $selectedFixes, ?User $actor = null): array
    {
        return DB::transaction(function () use ($payment, $selectedFixes, $actor): array {
            $appliedActions = [];
            $auditTrail = [];

            $tx = ShopLedgerTransaction::query()
                ->where('reference_type', ShopStaffPayment::class)
                ->where('reference_id', $payment->id)
                ->first();

            // 1. Fix Cashbook Category
            if (in_array('fix_cashbook_category', $selectedFixes, true) && $tx) {
                $targetCode = $payment->payment_type === 'advance' ? 'staff_advance' : 'salary';
                $targetEntryType = LedgerEntryType::query()->where('code', $targetCode)->first();
                if ($targetEntryType) {
                    $oldEntryCode = $tx->entryType?->code ?? 'unknown';
                    $tx->entry_type_id = $targetEntryType->id;
                    $tx->save();

                    $appliedActions[] = 'fix_cashbook_category';
                    $auditTrail[] = "Category changed from '{$oldEntryCode}' to '{$targetCode}' on Cashbook #{$tx->id}";
                }
            }

            // 2. Fix Cashbook Amount
            if (in_array('fix_cashbook_amount', $selectedFixes, true) && $tx) {
                $oldAmount = round((float) $tx->amount, 2);
                $newAmount = round((float) $payment->amount, 2);
                $tx->amount = $newAmount;
                $tx->save();

                $this->projectionService->recalculateBalancesFromDate((int) $tx->shop_id, $tx->business_date?->toDateString() ?? today()->toDateString());

                $appliedActions[] = 'fix_cashbook_amount';
                $auditTrail[] = "Amount changed from ₹{$oldAmount} to ₹{$newAmount} on Cashbook #{$tx->id}";
            }

            // 3. Fix Cashbook Date
            if (in_array('fix_cashbook_date', $selectedFixes, true) && $tx && $payment->paid_on) {
                $oldDate = $tx->business_date ? $tx->business_date->toDateString() : today()->toDateString();
                $newDate = $payment->paid_on->toDateString();
                $tx->business_date = $payment->paid_on;
                $tx->save();

                $minDate = min($oldDate, $newDate);
                $this->projectionService->recalculateBalancesFromDate((int) $tx->shop_id, $minDate);

                $appliedActions[] = 'fix_cashbook_date';
                $auditTrail[] = "Date changed from {$oldDate} to {$newDate} on Cashbook #{$tx->id}";
            }

            // 4. Fix Cashbook Fund Source
            if (in_array('fix_cashbook_fund_source', $selectedFixes, true) && $tx) {
                $oldFs = (string) $tx->funding_source;
                $newFs = (string) $payment->fund_source;
                $tx->funding_source = $newFs;
                $tx->save();

                $appliedActions[] = 'fix_cashbook_fund_source';
                $auditTrail[] = "Fund source changed from '{$oldFs}' to '{$newFs}' on Cashbook #{$tx->id}";
            }

            // 5. Fix Cashbook Shop
            if (in_array('fix_cashbook_shop', $selectedFixes, true) && $tx && $payment->shop_id) {
                $oldShopId = (int) $tx->shop_id;
                $newShopId = (int) $payment->shop_id;
                $date = $tx->business_date ? $tx->business_date->toDateString() : today()->toDateString();

                $tx->shop_id = $newShopId;
                $tx->save();

                $this->projectionService->recalculateBalancesFromDate($oldShopId, $date);
                $this->projectionService->recalculateBalancesFromDate($newShopId, $date);

                $appliedActions[] = 'fix_cashbook_shop';
                $auditTrail[] = "Shop changed from Shop #{$oldShopId} to Shop #{$newShopId} on Cashbook #{$tx->id}";
            }

            // 6. Create Missing Cashbook
            if (in_array('create_missing_cashbook', $selectedFixes, true)) {
                $this->projectionService->syncPayment($payment, $actor?->id);
                $this->projectionService->recalculateBalancesFromDate(
                    (int) $payment->shop_id,
                    $payment->paid_on?->toDateString() ?? today()->toDateString()
                );

                $appliedActions[] = 'create_missing_cashbook';
                $auditTrail[] = "Created Cashbook entry for Payment #{$payment->id} (₹{$payment->amount})";
            }

            // 7. Recalculate Advance Balance
            if (in_array('recalc_advance_balance', $selectedFixes, true) && $payment->advanceRequest) {
                $adv = $payment->advanceRequest;
                $oldApproved = $adv->approved_amount;
                $adv->approved_amount = $payment->amount;
                $adv->save();

                $appliedActions[] = 'recalc_advance_balance';
                $auditTrail[] = "Advance request #{$adv->id} approved amount synchronized to ₹{$payment->amount}";
            }

            // 8. Recalculate Salary / Payroll
            if (in_array('recalc_salary_payroll', $selectedFixes, true) && $payment->payrollRunItem) {
                $item = $payment->payrollRunItem;
                $item->touch();

                $appliedActions[] = 'recalc_salary_payroll';
                $auditTrail[] = "Payroll run item #{$item->id} state refreshed";
            }

            // Re-run scanner check on this payment
            $remainingFlags = $this->checkPayment($payment);

            // Audit log recording
            Log::info('Staff sync selective fixes applied by user', [
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'payment_id' => $payment->id,
                'cashbook_id' => $tx?->id,
                'applied_fixes' => $appliedActions,
                'audit_trail' => $auditTrail,
                'remaining_flags' => array_map(fn ($f) => $f->flag_code, $remainingFlags),
                'timestamp' => now()->toIso8601String(),
            ]);

            // Update resolution notes on newly resolved flags for this payment
            if (! empty($appliedActions)) {
                StaffSyncFlag::query()
                    ->where('source_type', ShopStaffPayment::class)
                    ->where('source_id', $payment->id)
                    ->where('status', StaffSyncFlag::STATUS_RESOLVED)
                    ->where('resolved_at', '>=', now()->subMinute())
                    ->update([
                        'resolved_by' => $actor?->id,
                        'resolution_notes' => 'Fixed by '.($actor?->name ?? 'Admin').': '.implode('; ', $auditTrail),
                    ]);
            }

            $appliedCount = count($appliedActions);
            $remainingCount = count($remainingFlags);

            $message = $remainingCount === 0
                ? "✓ {$appliedCount} ".($appliedCount === 1 ? 'issue' : 'issues').' fixed and verified! All records are now in sync.'
                : "✓ {$appliedCount} ".($appliedCount === 1 ? 'issue' : 'issues')." fixed. 🚩 {$remainingCount} ".($remainingCount === 1 ? 'issue' : 'issues').' still require review.';

            return [
                'success' => true,
                'applied_fixes' => $appliedActions,
                'applied_count' => $appliedCount,
                'remaining_flags_count' => $remainingCount,
                'remaining_flags' => $remainingFlags,
                'message' => $message,
            ];
        });
    }

    /**
     * Fix an orphan Cashbook transaction.
     */
    public function fixOrphanCashbook(int $transactionId, ?User $actor = null): bool
    {
        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->find($transactionId);

        if (! $tx) {
            // Flag can be marked resolved since transaction is gone
            StaffSyncFlag::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->where('source_id', $transactionId)
                ->update([
                    'status' => StaffSyncFlag::STATUS_RESOLVED,
                    'resolved_at' => now(),
                    'resolved_by' => $actor?->id,
                    'resolution_notes' => 'Orphan transaction confirmed removed.',
                ]);

            return true;
        }

        $shopId = (int) $tx->shop_id;
        $date = $tx->business_date ? $tx->business_date->toDateString() : today()->toDateString();
        $amount = round((float) $tx->amount, 2);

        DB::transaction(function () use ($tx, $shopId, $date, $amount, $actor, $transactionId): void {
            $tx->delete();
            $this->projectionService->recalculateBalancesFromDate($shopId, $date);

            Log::info('Orphan cashbook transaction removed by user', [
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'transaction_id' => $transactionId,
                'shop_id' => $shopId,
                'amount' => $amount,
                'timestamp' => now()->toIso8601String(),
            ]);

            StaffSyncFlag::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->where('source_id', $transactionId)
                ->update([
                    'status' => StaffSyncFlag::STATUS_RESOLVED,
                    'resolved_at' => now(),
                    'resolved_by' => $actor?->id,
                    'resolution_notes' => "Orphan cashbook transaction #{$transactionId} (₹{$amount}) removed by ".($actor?->name ?? 'Admin').' and shop balances recalculated.',
                ]);
        });

        return true;
    }
}
