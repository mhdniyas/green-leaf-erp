<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Models\Purchasing\PurchaserBusinessDaySubmission;
use App\Models\Purchasing\PurchaserBusinessDaySubmissionItem;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayReconciliationService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitPurchaserBusinessDayAction
{
    public function __construct(
        private readonly PurchaserBusinessDayReconciliationService $reconciliationService,
        private readonly PurchaserBusinessDayService $businessDayService,
    ) {}

    /**
     * Submit a purchaser's business day verification and create an immutable snapshot.
     * Allowed even if pending bills remain (coverage < 100%).
     * Does NOT mutate inventory, stock movements, GRNs, invoices, or advance matches.
     *
     * @throws ValidationException
     */
    public function execute(
        int $purchaserUserId,
        string|\DateTimeInterface $businessDate,
        ?int $warehouseId = null,
        ?string $note = null,
        ?int $actorUserId = null
    ): PurchaserBusinessDaySubmission {
        $actorUserId ??= $purchaserUserId;
        $dateStr = is_string($businessDate) ? $businessDate : $businessDate->format('Y-m-d');

        // 1. Authorization check: Purchaser can only submit their own day (or admin/manager)
        $actor = User::query()->findOrFail($actorUserId);
        if ($actorUserId !== $purchaserUserId) {
            $isAuthorizedAdmin = $actor->hasAnyRole(['admin', 'manager', 'accounts', 'accountant']);
            if (! $isAuthorizedAdmin) {
                throw ValidationException::withMessages([
                    'purchaser_user_id' => 'You are not authorized to submit a business day for another purchaser.',
                ]);
            }
        }

        // 2. Duplicate submission check: Prevent duplicate submissions for (purchaser, date)
        $existingSubmission = PurchaserBusinessDaySubmission::query()
            ->where('purchaser_user_id', $purchaserUserId)
            ->whereDate('business_date', $dateStr)
            ->first();

        if ($existingSubmission !== null) {
            throw ValidationException::withMessages([
                'business_date' => "Business day ({$dateStr}) has already been submitted.",
            ]);
        }

        // 3. Recalculate Phase 2 reconciliation for target purchaser on date
        $reconciliation = $this->reconciliationService->calculateReconciliation($purchaserUserId, $dateStr, $warehouseId);

        $productRows = collect($reconciliation['products'] ?? []);
        if ($productRows->isEmpty()) {
            throw ValidationException::withMessages([
                'reconciliation' => "No physical warehouse receipts found for {$reconciliation['purchaser_name']} on {$dateStr}.",
            ]);
        }

        // 4. Validate absence of blocking conditions (unit mismatches)
        $hasUnitMismatch = $productRows->contains('unit_mismatch', true);
        if ($hasUnitMismatch) {
            throw ValidationException::withMessages([
                'reconciliation' => 'Cannot submit business day because unit mismatches exist. Fix unit configurations first.',
            ]);
        }

        // 5. Atomic DB Transaction for creating immutable submission header + items
        return DB::transaction(function () use (
            $purchaserUserId,
            $dateStr,
            $warehouseId,
            $note,
            $actorUserId,
            $reconciliation,
            $productRows
        ): PurchaserBusinessDaySubmission {
            $pendingCount = $productRows->where('pending_qty', '>', 0.0001)->count();
            $excessCount = $productRows->where('excess_qty', '>', 0.0001)->count();

            /** @var PurchaserBusinessDaySubmission $submission */
            $submission = PurchaserBusinessDaySubmission::query()->create([
                'purchaser_user_id' => $purchaserUserId,
                'business_date' => $dateStr,
                'warehouse_id' => $warehouseId,
                'submitted_received_summary' => $reconciliation['total_received_qty'],
                'submitted_billed_summary' => $reconciliation['total_billed_qty'],
                'submitted_pending_summary' => $reconciliation['total_pending_qty'],
                'submitted_excess_summary' => $reconciliation['total_excess_qty'],
                'submission_coverage_percentage' => $reconciliation['coverage_percentage'],
                'pending_products_count' => $pendingCount,
                'unit_mismatch_products_count' => 0,
                'excess_products_count' => $excessCount,
                'has_mixed_units' => $reconciliation['has_mixed_units'],
                'status' => 'submitted',
                'note' => filled($note) ? trim((string) $note) : null,
                'submitted_by' => $actorUserId,
                'submitted_at' => now(),
            ]);

            foreach ($productRows as $row) {
                $statusAtSubmission = ($row['pending_qty'] <= 0.0001)
                    ? 'fully_covered'
                    : (($row['billed_qty'] > 0.0001) ? 'partially_covered' : 'pending');

                PurchaserBusinessDaySubmissionItem::query()->create([
                    'submission_id' => $submission->id,
                    'product_id' => (int) $row['product_id'],
                    'warehouse_id' => $warehouseId,
                    'product_name' => (string) $row['product_name'],
                    'sku' => filled($row['sku'] ?? null) ? (string) $row['sku'] : null,
                    'unit' => (string) ($row['unit'] ?? 'kg'),
                    'ownership_source' => 'allotment',
                    'received_qty' => round((float) $row['received_qty'], 3),
                    'billed_qty' => round((float) $row['billed_qty'], 3),
                    'pending_qty' => round((float) $row['pending_qty'], 3),
                    'excess_qty' => round((float) ($row['excess_qty'] ?? 0), 3),
                    'coverage_percentage' => round((float) $row['coverage_percentage'], 2),
                    'unit_mismatch' => (bool) ($row['unit_mismatch'] ?? false),
                    'is_fully_covered' => (bool) ($row['is_fully_covered'] ?? false),
                    'status_at_submission' => $statusAtSubmission,
                    'item_details_snapshot' => $row['items'] ?? null,
                ]);
            }

            return $submission->fresh(['items.product', 'purchaser', 'submittedBy']) ?? $submission;
        });
    }
}
