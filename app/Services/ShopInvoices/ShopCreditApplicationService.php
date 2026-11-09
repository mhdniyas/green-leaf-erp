<?php

declare(strict_types=1);

namespace App\Services\ShopInvoices;

use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopCreditApplication;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopCreditApplicationService
{
    public function __construct(
        private readonly ?ShopFinalBillService $finalBillService = null,
    ) {}

    private function getFinalBillService(): ShopFinalBillService
    {
        return $this->finalBillService ?? app(ShopFinalBillService::class);
    }

    /**
     * Check if a user is authorized to apply shop credit notes to invoices.
     */
    public function isAuthorizedToApplyCredit(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return (method_exists($user, 'isMainAdmin') && $user->isMainAdmin())
            || $user->hasRole('admin')
            || $user->hasRole('super_admin')
            || $user->hasRole('accounts')
            || $user->can('accounting.invoice.approve')
            || $user->can('accounting.entry.create')
            || $user->can('sales.payment.record');
    }

    /**
     * Check if a user is authorized to reverse shop credit applications.
     */
    public function isAuthorizedToReverseApplication(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return (method_exists($user, 'isMainAdmin') && $user->isMainAdmin())
            || $user->hasRole('admin')
            || $user->hasRole('super_admin')
            || $user->hasRole('accounts')
            || $user->can('accounting.invoice.approve')
            || $user->can('finance.journals.reverse');
    }

    /**
     * Apply a finalized shop-level credit note to a specific outstanding shop invoice.
     *
     * @throws ValidationException
     */
    public function applyCredit(
        ShopCreditNote|int $creditNote,
        ShopInvoice|int $invoice,
        float $amount,
        User|int $actor,
        ?string $notes = null,
        ?CarbonInterface $appliedAt = null,
        ?string $idempotencyKey = null,
    ): ShopCreditApplication {
        $actorUser = $actor instanceof User ? $actor : User::find($actor);

        if (! $this->isAuthorizedToApplyCredit($actorUser)) {
            throw ValidationException::withMessages([
                'applied_by' => 'User is not authorized to apply shop credit notes.',
            ]);
        }

        $creditNoteInstance = $creditNote instanceof ShopCreditNote
            ? $creditNote
            : ShopCreditNote::findOrFail($creditNote);

        $invoiceInstance = $invoice instanceof ShopInvoice
            ? $invoice
            : ShopInvoice::findOrFail($invoice);

        $amount = round($amount, 2);

        if ($amount <= 0.0) {
            throw ValidationException::withMessages([
                'amount' => 'Credit application amount must be greater than zero.',
            ]);
        }

        if (! $creditNoteInstance->isShopLevelMode()) {
            throw ValidationException::withMessages([
                'shop_credit_note_id' => 'Only shop-level credit notes can be applied to invoices.',
            ]);
        }

        if ($creditNoteInstance->isVoided()) {
            throw ValidationException::withMessages([
                'shop_credit_note_id' => 'Voided credit notes cannot be applied.',
            ]);
        }

        if (! $creditNoteInstance->isApproved()) {
            throw ValidationException::withMessages([
                'shop_credit_note_id' => 'Credit note must be approved/finalized before it can be applied.',
            ]);
        }

        if ($invoiceInstance->status === 'cancelled') {
            throw ValidationException::withMessages([
                'shop_invoice_id' => 'Cancelled invoices cannot receive credit applications.',
            ]);
        }

        if (! $invoiceInstance->isFinalized()) {
            throw ValidationException::withMessages([
                'shop_invoice_id' => 'Invoice must be finalized before applying credit.',
            ]);
        }

        if ((int) $creditNoteInstance->shop_id !== (int) $invoiceInstance->shop_id) {
            throw ValidationException::withMessages([
                'shop_invoice_id' => 'Target invoice must belong to the same shop as the credit note.',
            ]);
        }

        $applicationDate = ($appliedAt ?? today())->toDateString();
        $isClosed = ShopAccountingPeriodClosure::query()
            ->where('shop_id', $creditNoteInstance->shop_id)
            ->whereDate('period_start', '<=', $applicationDate)
            ->whereDate('period_end', '>=', $applicationDate)
            ->exists();

        if ($isClosed) {
            throw ValidationException::withMessages([
                'applied_at' => 'Cannot apply credit in a closed accounting period.',
            ]);
        }

        return DB::transaction(function () use (
            $creditNoteInstance,
            $invoiceInstance,
            $amount,
            $actorUser,
            $notes,
            $appliedAt,
            $applicationDate,
            $idempotencyKey
        ): ShopCreditApplication {
            // Deterministic row locking: Lock Credit Note row first, then Lock Invoice row
            /** @var ShopCreditNote $lockedCreditNote */
            $lockedCreditNote = ShopCreditNote::query()
                ->whereKey($creditNoteInstance->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var ShopInvoice $lockedInvoice */
            $lockedInvoice = ShopInvoice::query()
                ->whereKey($invoiceInstance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedCreditNote->isShopLevelMode()) {
                throw ValidationException::withMessages([
                    'shop_credit_note_id' => 'Only shop-level credit notes can be applied to invoices.',
                ]);
            }

            if (! $lockedCreditNote->isApproved()) {
                throw ValidationException::withMessages([
                    'shop_credit_note_id' => 'Credit note must be approved/finalized before it can be applied.',
                ]);
            }

            if ($lockedCreditNote->isVoided()) {
                throw ValidationException::withMessages([
                    'shop_credit_note_id' => 'Voided credit notes cannot be applied.',
                ]);
            }

            if ((int) $lockedCreditNote->shop_id !== (int) $lockedInvoice->shop_id) {
                throw ValidationException::withMessages([
                    'shop_invoice_id' => 'Target invoice must belong to the same shop as the credit note.',
                ]);
            }

            $isClosed = ShopAccountingPeriodClosure::query()
                ->where('shop_id', $lockedCreditNote->shop_id)
                ->whereDate('period_start', '<=', $applicationDate)
                ->whereDate('period_end', '>=', $applicationDate)
                ->exists();

            if ($isClosed) {
                throw ValidationException::withMessages([
                    'applied_at' => 'Cannot apply credit in a closed accounting period.',
                ]);
            }

            // Idempotency check 1: Explicit token matching
            if ($idempotencyKey !== null) {
                /** @var ShopCreditApplication|null $existing */
                $existing = ShopCreditApplication::query()
                    ->where('shop_credit_note_id', $lockedCreditNote->id)
                    ->where('shop_invoice_id', $lockedInvoice->id)
                    ->whereNull('reversed_at')
                    ->where('notes', 'like', "%[idempotency:{$idempotencyKey}]%")
                    ->first();

                if ($existing) {
                    return $existing->fresh(['creditNote', 'invoice', 'shop', 'appliedByUser']);
                }
            } else {
                // Idempotency check 2: Rapid un-keyed double-click protection (exact same parameters within 5 seconds)
                /** @var ShopCreditApplication|null $recentDuplicate */
                $recentDuplicate = ShopCreditApplication::query()
                    ->where('shop_credit_note_id', $lockedCreditNote->id)
                    ->where('shop_invoice_id', $lockedInvoice->id)
                    ->where('amount', $amount)
                    ->where('applied_by', $actorUser->id)
                    ->where('notes', $notes)
                    ->whereNull('reversed_at')
                    ->where('created_at', '>=', now()->subSeconds(5))
                    ->first();

                if ($recentDuplicate) {
                    return $recentDuplicate->fresh(['creditNote', 'invoice', 'shop', 'appliedByUser']);
                }
            }

            // Recalculate available unapplied credit under database lock
            $alreadyApplied = (float) ShopCreditApplication::query()
                ->where('shop_credit_note_id', $lockedCreditNote->id)
                ->whereNull('reversed_at')
                ->lockForUpdate()
                ->sum('amount');

            $availableCredit = round(max(0.00, (float) $lockedCreditNote->total_credit_amount - $alreadyApplied), 2);

            if ($amount > ($availableCredit + 0.0001)) {
                throw ValidationException::withMessages([
                    'amount' => "Application amount (₹{$amount}) exceeds available unapplied credit (₹{$availableCredit}).",
                ]);
            }

            // Recalculate target invoice effective remaining balance under database lock
            $finalBill = $this->getFinalBillService()->calculate($lockedInvoice);
            $targetOutstanding = round($finalBill->remainingBalance, 2);

            if ($targetOutstanding <= 0.0001) {
                throw ValidationException::withMessages([
                    'shop_invoice_id' => 'Target invoice is already fully settled.',
                ]);
            }

            if ($amount > ($targetOutstanding + 0.0001)) {
                throw ValidationException::withMessages([
                    'amount' => "Application amount (₹{$amount}) exceeds invoice effective outstanding balance (₹{$targetOutstanding}).",
                ]);
            }

            $finalNotes = $notes;
            if ($idempotencyKey !== null) {
                $finalNotes = filled($finalNotes)
                    ? trim((string) $finalNotes)." [idempotency:{$idempotencyKey}]"
                    : "[idempotency:{$idempotencyKey}]";
            }

            $application = ShopCreditApplication::create([
                'shop_credit_note_id' => $lockedCreditNote->id,
                'shop_invoice_id' => $lockedInvoice->id,
                'shop_id' => $lockedCreditNote->shop_id,
                'amount' => $amount,
                'applied_at' => $appliedAt ?? now(),
                'applied_by' => $actorUser->id,
                'notes' => $finalNotes,
            ]);

            return $application->fresh(['creditNote', 'invoice', 'shop', 'appliedByUser']);
        });
    }

    /**
     * Apply a finalized shop-level credit note across multiple named invoices.
     *
     * @param  array<int, array{invoice_id: int|ShopInvoice, amount: float, notes?: string|null}>  $allocations
     * @return Collection<int, ShopCreditApplication>
     *
     * @throws ValidationException
     */
    public function applyCreditToMany(
        ShopCreditNote|int $creditNote,
        array $allocations,
        User|int $actor,
        ?CarbonInterface $appliedAt = null,
        ?string $idempotencyKey = null,
    ): Collection {
        if (empty($allocations)) {
            throw ValidationException::withMessages([
                'allocations' => 'At least one invoice allocation must be provided.',
            ]);
        }

        $actorUser = $actor instanceof User ? $actor : User::find($actor);

        if (! $this->isAuthorizedToApplyCredit($actorUser)) {
            throw ValidationException::withMessages([
                'applied_by' => 'User is not authorized to apply shop credit notes.',
            ]);
        }

        $creditNoteInstance = $creditNote instanceof ShopCreditNote
            ? $creditNote
            : ShopCreditNote::findOrFail($creditNote);

        $applicationDate = ($appliedAt ?? today())->toDateString();
        $isClosed = ShopAccountingPeriodClosure::query()
            ->where('shop_id', $creditNoteInstance->shop_id)
            ->whereDate('period_start', '<=', $applicationDate)
            ->whereDate('period_end', '>=', $applicationDate)
            ->exists();

        if ($isClosed) {
            throw ValidationException::withMessages([
                'applied_at' => 'Cannot apply credit in a closed accounting period.',
            ]);
        }

        $targetInvoiceIds = [];
        $totalRequestedAmount = 0.0;

        foreach ($allocations as $index => $alloc) {
            $invId = $alloc['invoice_id'] instanceof ShopInvoice ? $alloc['invoice_id']->id : (int) $alloc['invoice_id'];
            $amt = round((float) $alloc['amount'], 2);

            if ($amt <= 0.0) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.amount" => 'Allocation amount must be greater than zero.',
                ]);
            }

            $totalRequestedAmount += $amt;
            $targetInvoiceIds[$invId] = true;
        }

        $uniqueInvoiceIds = array_keys($targetInvoiceIds);
        sort($uniqueInvoiceIds);

        return DB::transaction(function () use (
            $creditNoteInstance,
            $uniqueInvoiceIds,
            $allocations,
            $totalRequestedAmount,
            $actorUser,
            $appliedAt,
            $idempotencyKey
        ): Collection {
            // Deterministic row locking: Credit Note first
            /** @var ShopCreditNote $lockedCreditNote */
            $lockedCreditNote = ShopCreditNote::query()
                ->whereKey($creditNoteInstance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedCreditNote->isShopLevelMode()) {
                throw ValidationException::withMessages([
                    'shop_credit_note_id' => 'Only shop-level credit notes can be applied to invoices.',
                ]);
            }

            if (! $lockedCreditNote->isApproved()) {
                throw ValidationException::withMessages([
                    'shop_credit_note_id' => 'Credit note must be approved/finalized before it can be applied.',
                ]);
            }

            if ($lockedCreditNote->isVoided()) {
                throw ValidationException::withMessages([
                    'shop_credit_note_id' => 'Voided credit notes cannot be applied.',
                ]);
            }

            // Idempotency check: Token matching for batch applications
            if ($idempotencyKey !== null) {
                $existingBatch = ShopCreditApplication::query()
                    ->where('shop_credit_note_id', $lockedCreditNote->id)
                    ->where('notes', 'like', "%[idempotency:{$idempotencyKey}-%")
                    ->whereNull('reversed_at')
                    ->get();

                if ($existingBatch->isNotEmpty()) {
                    return $existingBatch;
                }
            }

            // Lock all target invoices in ascending order
            $lockedInvoices = ShopInvoice::query()
                ->whereIn('id', $uniqueInvoiceIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($uniqueInvoiceIds as $invId) {
                if (! $lockedInvoices->has($invId)) {
                    throw ValidationException::withMessages([
                        'allocations' => "Target invoice #{$invId} does not exist.",
                    ]);
                }

                $inv = $lockedInvoices->get($invId);
                if ((int) $inv->shop_id !== (int) $lockedCreditNote->shop_id) {
                    throw ValidationException::withMessages([
                        'allocations' => "Target invoice #{$invId} does not belong to the same shop as the credit note.",
                    ]);
                }

                if ($inv->status === 'cancelled') {
                    throw ValidationException::withMessages([
                        'allocations' => "Target invoice #{$invId} is cancelled.",
                    ]);
                }

                if (! $inv->isFinalized()) {
                    throw ValidationException::withMessages([
                        'allocations' => "Target invoice #{$invId} is not finalized.",
                    ]);
                }
            }

            // Recalculate available unapplied credit under lock
            $alreadyApplied = (float) ShopCreditApplication::query()
                ->where('shop_credit_note_id', $lockedCreditNote->id)
                ->whereNull('reversed_at')
                ->lockForUpdate()
                ->sum('amount');

            $availableCredit = round(max(0.00, (float) $lockedCreditNote->total_credit_amount - $alreadyApplied), 2);

            if ($totalRequestedAmount > ($availableCredit + 0.0001)) {
                throw ValidationException::withMessages([
                    'amount' => "Total allocation amount (₹{$totalRequestedAmount}) exceeds available unapplied credit (₹{$availableCredit}).",
                ]);
            }

            $createdApplications = collect();
            $runningAvailableCredit = $availableCredit;

            foreach ($allocations as $index => $alloc) {
                $invId = $alloc['invoice_id'] instanceof ShopInvoice ? $alloc['invoice_id']->id : (int) $alloc['invoice_id'];
                $amt = round((float) $alloc['amount'], 2);
                $notes = $alloc['notes'] ?? null;

                $inv = $lockedInvoices->get($invId);

                // Check remaining balance for this invoice
                $finalBill = $this->getFinalBillService()->calculate($inv);
                $invoiceOutstanding = round($finalBill->remainingBalance, 2);

                if ($amt > ($invoiceOutstanding + 0.0001)) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => "Allocation amount (₹{$amt}) exceeds invoice #{$invId} outstanding balance (₹{$invoiceOutstanding}).",
                    ]);
                }

                if ($amt > ($runningAvailableCredit + 0.0001)) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => "Allocation amount (₹{$amt}) exceeds remaining available credit (₹{$runningAvailableCredit}).",
                    ]);
                }

                $finalNotes = $notes;
                if ($idempotencyKey !== null) {
                    $finalNotes = filled($finalNotes)
                        ? trim((string) $finalNotes)." [idempotency:{$idempotencyKey}-{$invId}]"
                        : "[idempotency:{$idempotencyKey}-{$invId}]";
                }

                $app = ShopCreditApplication::create([
                    'shop_credit_note_id' => $lockedCreditNote->id,
                    'shop_invoice_id' => $inv->id,
                    'shop_id' => $lockedCreditNote->shop_id,
                    'amount' => $amt,
                    'applied_at' => $appliedAt ?? now(),
                    'applied_by' => $actorUser->id,
                    'notes' => $finalNotes,
                ]);

                $runningAvailableCredit = round($runningAvailableCredit - $amt, 2);
                $createdApplications->push($app);
            }

            return $createdApplications;
        });
    }

    /**
     * Safely reverses an existing active credit application.
     * Restores unapplied credit to the Credit Note and restores effective payable on the target invoice.
     *
     * @throws ValidationException
     */
    public function reverseCreditApplication(
        ShopCreditApplication|int $application,
        User|int $actor,
        string $reason,
        ?CarbonInterface $reversedAt = null,
    ): ShopCreditApplication {
        $actorUser = $actor instanceof User ? $actor : User::find($actor);

        if (! $this->isAuthorizedToReverseApplication($actorUser)) {
            throw ValidationException::withMessages([
                'reversed_by' => 'User is not authorized to reverse credit applications.',
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reversal_reason' => 'A valid reversal reason is required.',
            ]);
        }

        $applicationInstance = $application instanceof ShopCreditApplication
            ? $application
            : ShopCreditApplication::findOrFail($application);

        if ($applicationInstance->isReversed()) {
            throw ValidationException::withMessages([
                'shop_credit_application_id' => 'Credit application is already reversed.',
            ]);
        }

        $reversalDate = ($reversedAt ?? today())->toDateString();
        $appliedDate = $applicationInstance->applied_at?->toDateString()
            ?? $applicationInstance->created_at?->toDateString()
            ?? today()->toDateString();

        $reversalPeriodClosed = ShopAccountingPeriodClosure::query()
            ->where('shop_id', $applicationInstance->shop_id)
            ->whereDate('period_start', '<=', $reversalDate)
            ->whereDate('period_end', '>=', $reversalDate)
            ->exists();

        if ($reversalPeriodClosed) {
            throw ValidationException::withMessages([
                'reversed_at' => 'Cannot reverse credit application in a closed accounting period.',
            ]);
        }

        $appliedPeriodClosed = ShopAccountingPeriodClosure::query()
            ->where('shop_id', $applicationInstance->shop_id)
            ->whereDate('period_start', '<=', $appliedDate)
            ->whereDate('period_end', '>=', $appliedDate)
            ->exists();

        if ($appliedPeriodClosed) {
            throw ValidationException::withMessages([
                'reversed_at' => 'Cannot reverse credit application originally applied in a closed accounting period. Compensating adjustment required.',
            ]);
        }

        return DB::transaction(function () use (
            $applicationInstance,
            $actorUser,
            $reason,
            $reversedAt,
            $reversalDate,
            $appliedDate
        ): ShopCreditApplication {
            // Deterministic resource locking: Credit Note -> Invoice -> Credit Application
            /** @var ShopCreditNote $lockedCreditNote */
            $lockedCreditNote = ShopCreditNote::query()
                ->whereKey($applicationInstance->shop_credit_note_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var ShopInvoice $lockedInvoice */
            $lockedInvoice = ShopInvoice::query()
                ->whereKey($applicationInstance->shop_invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var ShopCreditApplication $lockedApplication */
            $lockedApplication = ShopCreditApplication::query()
                ->whereKey($applicationInstance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApplication->isReversed()) {
                throw ValidationException::withMessages([
                    'shop_credit_application_id' => 'Credit application is already reversed.',
                ]);
            }

            $reversalPeriodClosed = ShopAccountingPeriodClosure::query()
                ->where('shop_id', $lockedCreditNote->shop_id)
                ->whereDate('period_start', '<=', $reversalDate)
                ->whereDate('period_end', '>=', $reversalDate)
                ->exists();

            if ($reversalPeriodClosed) {
                throw ValidationException::withMessages([
                    'reversed_at' => 'Cannot reverse credit application in a closed accounting period.',
                ]);
            }

            $appliedPeriodClosed = ShopAccountingPeriodClosure::query()
                ->where('shop_id', $lockedCreditNote->shop_id)
                ->whereDate('period_start', '<=', $appliedDate)
                ->whereDate('period_end', '>=', $appliedDate)
                ->exists();

            if ($appliedPeriodClosed) {
                throw ValidationException::withMessages([
                    'reversed_at' => 'Cannot reverse credit application originally applied in a closed accounting period. Compensating adjustment required.',
                ]);
            }

            $lockedApplication->forceFill([
                'reversed_at' => $reversedAt ?? now(),
                'reversed_by' => $actorUser->id,
                'reversal_reason' => trim($reason),
            ])->save();

            return $lockedApplication->fresh(['creditNote', 'invoice', 'shop', 'appliedByUser', 'reversedByUser']);
        });
    }

    /**
     * Check if a user is authorized to cancel shop-level credit notes.
     */
    public function isAuthorizedToCancelCreditNote(?User $user): bool
    {
        return app(ShopCreditNoteService::class)->isAuthorizedToCancelCreditNote($user);
    }

    /**
     * Safely cancel a finalized shop-level credit note and reverse its financial effects.
     *
     * @throws ValidationException
     */
    public function cancelShopLevelCreditNote(
        ShopCreditNote|int $creditNote,
        User|int $actor,
        string $reason,
        ?CarbonInterface $cancelledAt = null,
    ): ShopCreditNote {
        return app(ShopCreditNoteService::class)->cancelShopLevelCreditNote(
            $creditNote,
            $actor,
            $reason,
            $cancelledAt,
        );
    }
}
