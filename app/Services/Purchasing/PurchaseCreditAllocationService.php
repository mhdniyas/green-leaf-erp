<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Models\PurchaseCreditNote;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Models\VendorSettlementCreditAllocation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PurchaseCreditAllocationService
{
    /**
     * Allocate V2 Purchase Credit Notes to settled invoices inside an active DB transaction.
     *
     * @param  array<int, array{purchase_credit_note_id: int, amount: float, purchase_invoice_id?: int|null}>  $creditNoteItems
     * @param  array<int, float>  $invoiceCreditTargets  Map of [purchase_invoice_id => credit_allocated]
     * @param  Collection<int, PurchaseInvoice>  $lockedInvoices
     * @return Collection<int, VendorSettlementCreditAllocation>
     *
     * @throws ValidationException
     */
    public function allocateForSettlement(
        VendorSettlement $settlement,
        Supplier $supplier,
        array $creditNoteItems,
        array $invoiceCreditTargets,
        Collection $lockedInvoices,
    ): Collection {
        $normalizedCredits = collect($creditNoteItems)
            ->map(fn (array $row): array => [
                'purchase_credit_note_id' => (int) ($row['purchase_credit_note_id'] ?? 0),
                'purchase_invoice_id' => ! empty($row['purchase_invoice_id']) ? (int) $row['purchase_invoice_id'] : null,
                'amount' => round((float) ($row['amount'] ?? 0), 2),
            ])
            ->filter(fn (array $row): bool => $row['amount'] > 0.0)
            ->values();

        $totalCreditRequested = round((float) $normalizedCredits->sum('amount'), 2);
        $totalInvoiceCreditTarget = round((float) array_sum($invoiceCreditTargets), 2);

        if (abs($totalCreditRequested - $totalInvoiceCreditTarget) > 0.01) {
            throw ValidationException::withMessages([
                'credit_notes' => 'Selected Purchase Credit Note amounts must exactly match invoice credit allocations.',
            ]);
        }

        if ($normalizedCredits->isEmpty()) {
            return collect();
        }

        $pcnIds = $normalizedCredits->pluck('purchase_credit_note_id')->unique()->values()->all();
        if (in_array(0, $pcnIds, true)) {
            throw ValidationException::withMessages([
                'credit_notes' => 'Invalid Purchase Credit Note selected.',
            ]);
        }

        /** @var Collection<int, PurchaseCreditNote> $lockedNotes */
        $lockedNotes = PurchaseCreditNote::query()
            ->whereIn('id', $pcnIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($lockedNotes->count() !== count($pcnIds)) {
            throw ValidationException::withMessages([
                'credit_notes' => 'One or more selected Purchase Credit Notes could not be found.',
            ]);
        }

        // Aggregate total requested per credit note to guard against duplicate rows for the same note
        $requestedPerNote = [];
        foreach ($normalizedCredits as $row) {
            $pcnId = $row['purchase_credit_note_id'];
            $requestedPerNote[$pcnId] = round(($requestedPerNote[$pcnId] ?? 0.0) + $row['amount'], 2);
        }

        foreach ($requestedPerNote as $pcnId => $requestedAmount) {
            /** @var PurchaseCreditNote $note */
            $note = $lockedNotes->get($pcnId);

            if ((int) $note->supplier_id !== (int) $supplier->id) {
                throw ValidationException::withMessages([
                    'credit_notes' => "Credit Note {$note->credit_note_number} does not belong to {$supplier->name}.",
                ]);
            }

            if (! $note->isActive()) {
                throw ValidationException::withMessages([
                    'credit_notes' => "Credit Note {$note->credit_note_number} is not active and cannot be applied.",
                ]);
            }

            if ($note->money_effect !== PurchaseCreditMoneyEffect::VendorCredit) {
                throw ValidationException::withMessages([
                    'credit_notes' => "Credit Note {$note->credit_note_number} is not a Vendor Credit document.",
                ]);
            }

            if (! $note->isV2()) {
                throw ValidationException::withMessages([
                    'credit_notes' => "Legacy Credit Note {$note->credit_note_number} cannot be allocated via V2 credit selection.",
                ]);
            }

            $available = round(max(0.0, (float) $note->total_amount - (float) $note->utilized_amount), 2);
            if ($requestedAmount > $available + 0.009) {
                throw ValidationException::withMessages([
                    'credit_notes' => "Credit Note {$note->credit_note_number} has only ₹".number_format($available, 2).' available.',
                ]);
            }
        }

        // Track remaining target credit per invoice and remaining amount per credit note entry
        $remainingInvoiceTargets = [];
        foreach ($invoiceCreditTargets as $invId => $targetAmount) {
            $rounded = round((float) $targetAmount, 2);
            if ($rounded > 0.0) {
                if (! $lockedInvoices->has((int) $invId)) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Credit allocation target invoice is not part of this settlement.',
                    ]);
                }
                $remainingInvoiceTargets[(int) $invId] = $rounded;
            }
        }

        $createdAllocations = collect();

        // First pass: apply explicit invoice-targeted credit lines if specified
        $untargetedCredits = [];
        foreach ($normalizedCredits as $row) {
            $pcnId = $row['purchase_credit_note_id'];
            $targetInvoiceId = $row['purchase_invoice_id'];
            $amount = $row['amount'];

            if ($targetInvoiceId !== null) {
                $invRemaining = $remainingInvoiceTargets[$targetInvoiceId] ?? 0.0;
                if ($amount > $invRemaining + 0.009) {
                    throw ValidationException::withMessages([
                        'credit_notes' => 'Credit allocation exceeds target invoice credit amount.',
                    ]);
                }

                $createdAllocations->push(VendorSettlementCreditAllocation::query()->create([
                    'vendor_settlement_id' => $settlement->id,
                    'purchase_credit_note_id' => $pcnId,
                    'purchase_invoice_id' => $targetInvoiceId,
                    'amount' => $amount,
                    'is_reversed' => false,
                ]));

                $remainingInvoiceTargets[$targetInvoiceId] = round($invRemaining - $amount, 2);
            } else {
                $untargetedCredits[] = $row;
            }
        }

        // Second pass: distribute untargeted credit note amounts across remaining invoice credit targets
        foreach ($untargetedCredits as $row) {
            $pcnId = $row['purchase_credit_note_id'];
            $remainingNoteAmount = $row['amount'];

            foreach ($remainingInvoiceTargets as $invId => $invNeed) {
                if ($remainingNoteAmount <= 0.009) {
                    break;
                }
                if ($invNeed <= 0.009) {
                    continue;
                }

                $apply = round(min($remainingNoteAmount, $invNeed), 2);
                if ($apply <= 0.0) {
                    continue;
                }

                $createdAllocations->push(VendorSettlementCreditAllocation::query()->create([
                    'vendor_settlement_id' => $settlement->id,
                    'purchase_credit_note_id' => $pcnId,
                    'purchase_invoice_id' => $invId,
                    'amount' => $apply,
                    'is_reversed' => false,
                ]));

                $remainingNoteAmount = round($remainingNoteAmount - $apply, 2);
                $remainingInvoiceTargets[$invId] = round($invNeed - $apply, 2);
            }

            if ($remainingNoteAmount > 0.01) {
                throw ValidationException::withMessages([
                    'credit_notes' => 'Unable to match full selected credit amount to invoice allocations.',
                ]);
            }
        }

        // Atomically update utilized_amount on each credit note
        foreach ($requestedPerNote as $pcnId => $requestedAmount) {
            /** @var PurchaseCreditNote $note */
            $note = $lockedNotes->get($pcnId);
            $newUtilized = round((float) $note->utilized_amount + $requestedAmount, 2);
            $note->update(['utilized_amount' => $newUtilized]);
        }

        return $createdAllocations;
    }

    /**
     * Reverse all active purchase credit allocations for a settlement, restoring exact credit note balances.
     */
    public function reverseForSettlement(VendorSettlement $settlement, User $actor, string $reason): float
    {
        $activeAllocations = VendorSettlementCreditAllocation::query()
            ->where('vendor_settlement_id', $settlement->id)
            ->where('is_reversed', false)
            ->lockForUpdate()
            ->get();

        if ($activeAllocations->isEmpty()) {
            return 0.0;
        }

        $pcnIds = $activeAllocations->pluck('purchase_credit_note_id')->unique()->values()->all();
        /** @var Collection<int, PurchaseCreditNote> $lockedNotes */
        $lockedNotes = PurchaseCreditNote::query()
            ->whereIn('id', $pcnIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $totalRestored = 0.0;
        $restoredPerNote = [];

        foreach ($activeAllocations as $allocation) {
            $amount = round((float) $allocation->amount, 2);
            $allocation->reverse($actor, $reason);
            $pcnId = (int) $allocation->purchase_credit_note_id;
            $restoredPerNote[$pcnId] = round(($restoredPerNote[$pcnId] ?? 0.0) + $amount, 2);
            $totalRestored = round($totalRestored + $amount, 2);
        }

        foreach ($restoredPerNote as $pcnId => $restoredAmount) {
            $note = $lockedNotes->get($pcnId);
            if ($note instanceof PurchaseCreditNote) {
                $newUtilized = round(max(0.0, (float) $note->utilized_amount - $restoredAmount), 2);
                $note->update(['utilized_amount' => $newUtilized]);
            }
        }

        return $totalRestored;
    }
}
