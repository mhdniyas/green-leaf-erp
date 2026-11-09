<?php

declare(strict_types=1);

namespace App\Services\ShopInvoices;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteLineType;
use App\Enums\ShopInvoice\ShopCreditNoteReceiptStatus;
use App\Models\Product;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopCreditNoteReceipt;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopCreditNoteWarehouseReceiptService
{
    /**
     * Check if a user is authorized to verify physical warehouse returns.
     */
    public function isAuthorizedToReceive(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return (method_exists($user, 'isMainAdmin') && $user->isMainAdmin())
            || $user->hasRole('admin')
            || $user->hasRole('super_admin')
            || $user->hasRole('warehouse_receiver')
            || $user->hasRole('warehouse')
            || $user->hasRole('accounts')
            || $user->can('warehouse.receive.confirm')
            || $user->can('inventory.sorting.process');
    }

    /**
     * Verify physical warehouse return of goods for a finalized credit note item.
     * Increases stock for the actually received grade and warehouse only after confirmation.
     *
     * @throws ValidationException
     */
    public function receivePhysicalReturn(
        ShopCreditNoteItem|int $item,
        float $quantity,
        ProductGrade|string $grade,
        Warehouse|int $warehouse,
        User|int $actor,
        ?CarbonInterface $receivedAt = null,
        ?string $remarks = null,
        ?string $idempotencyKey = null,
    ): ShopCreditNoteReceipt {
        $actorUser = $actor instanceof User ? $actor : User::find($actor);

        if (! $this->isAuthorizedToReceive($actorUser)) {
            throw ValidationException::withMessages([
                'received_by' => 'User is not authorized to verify warehouse receipts.',
            ]);
        }

        $itemInstance = $item instanceof ShopCreditNoteItem
            ? $item
            : ShopCreditNoteItem::findOrFail($item);

        $creditNote = $itemInstance->creditNote;

        if (! $creditNote instanceof ShopCreditNote) {
            throw ValidationException::withMessages([
                'shop_credit_note_id' => 'Credit note for this item could not be found.',
            ]);
        }

        if (! $creditNote->isShopLevelMode()) {
            throw ValidationException::withMessages([
                'accounting_mode' => 'Only shop-level credit notes support decoupled warehouse receipt verification.',
            ]);
        }

        if ($creditNote->isVoided()) {
            throw ValidationException::withMessages([
                'credit_note' => 'Cannot receive goods for a voided credit note.',
            ]);
        }

        if (! $creditNote->isApproved()) {
            throw ValidationException::withMessages([
                'credit_note' => 'Credit note must be finalized before physical warehouse receipt can be verified.',
            ]);
        }

        if ($itemInstance->line_type === ShopCreditNoteLineType::CommercialDiscount) {
            throw ValidationException::withMessages([
                'line_type' => 'Commercial discount lines cannot be physically received into a warehouse.',
            ]);
        }

        if ($itemInstance->inventory_action !== ShopCreditNoteInventoryAction::ReturnToStock) {
            throw ValidationException::withMessages([
                'inventory_action' => 'Only items marked for return to stock can be physically received into the warehouse. Financial-only adjustments cannot create inventory.',
            ]);
        }

        $warehouseInstance = $warehouse instanceof Warehouse
            ? $warehouse
            : Warehouse::findOrFail($warehouse);

        $receivedGrade = ProductGrade::tryResolve($grade);
        if ($receivedGrade === null) {
            throw ValidationException::withMessages([
                'received_grade' => 'A valid product grade (A, B, or C) is required for physical warehouse receipt.',
            ]);
        }

        $quantity = round($quantity, 4);
        if ($quantity <= 0.0001) {
            throw ValidationException::withMessages([
                'received_quantity' => 'Received quantity must be greater than zero.',
            ]);
        }

        $expectedQuantity = round((float) $itemInstance->adjusted_quantity, 4);
        $alreadyReceived = round((float) $itemInstance->receipts()->where('status', 'verified')->sum('received_quantity'), 4);
        $remainingExpected = round(max(0.0, $expectedQuantity - $alreadyReceived), 4);

        if ($quantity > ($remainingExpected + 0.0001)) {
            throw ValidationException::withMessages([
                'received_quantity' => "Received quantity ({$quantity} kg) exceeds remaining expected return quantity ({$remainingExpected} kg) for this item.",
            ]);
        }

        // Idempotency: exact key match
        if (filled($idempotencyKey)) {
            $existing = ShopCreditNoteReceipt::query()
                ->where('shop_credit_note_item_id', $itemInstance->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof ShopCreditNoteReceipt) {
                return $existing->fresh(['creditNote', 'item', 'product', 'warehouse', 'stockMovement', 'receiver']);
            }
        } else {
            // Rapid double-submission protection within 3 seconds
            $recentSameReceipt = ShopCreditNoteReceipt::query()
                ->where('shop_credit_note_item_id', $itemInstance->id)
                ->where('warehouse_id', $warehouseInstance->id)
                ->where('received_grade', $receivedGrade->value)
                ->where('received_quantity', $quantity)
                ->where('received_by', $actorUser->id)
                ->where('created_at', '>=', now()->subSeconds(3))
                ->first();

            if ($recentSameReceipt instanceof ShopCreditNoteReceipt) {
                return $recentSameReceipt->fresh(['creditNote', 'item', 'product', 'warehouse', 'stockMovement', 'receiver']);
            }
        }

        $receivedTimestamp = $receivedAt !== null ? Carbon::instance($receivedAt) : now();

        return DB::transaction(function () use (
            $creditNote,
            $itemInstance,
            $warehouseInstance,
            $receivedGrade,
            $quantity,
            $actorUser,
            $receivedTimestamp,
            $remarks,
            $idempotencyKey
        ): ShopCreditNoteReceipt {
            // Pessimistic locking: Credit Note -> Item
            /** @var ShopCreditNote $lockedCreditNote */
            $lockedCreditNote = ShopCreditNote::query()
                ->whereKey($creditNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCreditNote->isVoided()) {
                throw ValidationException::withMessages([
                    'credit_note' => 'Cannot receive goods for a voided credit note.',
                ]);
            }

            if (! $lockedCreditNote->isApproved()) {
                throw ValidationException::withMessages([
                    'credit_note' => 'Credit note must be finalized before physical warehouse receipt can be verified.',
                ]);
            }

            /** @var ShopCreditNoteItem $lockedItem */
            $lockedItem = ShopCreditNoteItem::query()
                ->whereKey($itemInstance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->inventory_action !== ShopCreditNoteInventoryAction::ReturnToStock) {
                throw ValidationException::withMessages([
                    'inventory_action' => 'Only items marked for return to stock can be physically received into the warehouse.',
                ]);
            }

            $expectedQuantity = round((float) $lockedItem->adjusted_quantity, 4);
            $alreadyReceived = round((float) $lockedItem->receipts()->where('status', 'verified')->lockForUpdate()->sum('received_quantity'), 4);
            $remainingExpected = round(max(0.0, $expectedQuantity - $alreadyReceived), 4);

            if ($quantity > ($remainingExpected + 0.0001)) {
                throw ValidationException::withMessages([
                    'received_quantity' => "Received quantity ({$quantity} kg) exceeds remaining expected return quantity ({$remainingExpected} kg).",
                ]);
            }

            // Resolve or create receiving batch in target warehouse
            $batch = $this->resolveReceivingBatch(
                (int) $lockedItem->product_id,
                (int) $warehouseInstance->id,
                $receivedGrade,
                $lockedCreditNote,
                $lockedItem,
                $actorUser->id,
            );

            $costPerUnit = (float) ($batch->cost_per_kg > 0 ? $batch->cost_per_kg : ($lockedItem->invoiceItem?->orderItem?->unit_cost ?? $lockedItem->credit_rate ?? 0.0));

            // Positive StockMovement for the actual received grade and warehouse
            $stockMovement = StockMovement::create([
                'batch_id' => $batch->id,
                'product_id' => $lockedItem->product_id,
                'warehouse_id' => $warehouseInstance->id,
                'created_by' => $actorUser->id,
                'grade' => $receivedGrade->value,
                'type' => StockMovementType::SaleReversal->value,
                'quantity' => $quantity,
                'cost_per_unit' => $costPerUnit,
                'shop_order_item_id' => $lockedItem->invoiceItem?->shop_order_item_id,
                'notes' => "Credit Note Return — SCN: {$lockedCreditNote->credit_note_number}; NoteItem: {$lockedItem->id}; Grade: {$receivedGrade->value}; Qty: {$quantity}; Warehouse: {$warehouseInstance->code}",
            ]);

            // Create individual auditable receipt event
            $receipt = ShopCreditNoteReceipt::create([
                'shop_credit_note_id' => $lockedCreditNote->id,
                'shop_credit_note_item_id' => $lockedItem->id,
                'product_id' => $lockedItem->product_id,
                'warehouse_id' => $warehouseInstance->id,
                'stock_movement_id' => $stockMovement->id,
                'received_grade' => $receivedGrade->value,
                'received_quantity' => $quantity,
                'received_by' => $actorUser->id,
                'received_at' => $receivedTimestamp,
                'status' => 'verified',
                'remarks' => $remarks,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Update cumulative received quantity and receipt status
            $newTotalReceived = round($alreadyReceived + $quantity, 4);
            $newStatus = ($newTotalReceived >= ($expectedQuantity - 0.0001))
                ? ShopCreditNoteReceiptStatus::ReceivedAndVerified
                : ShopCreditNoteReceiptStatus::PartiallyReceived;

            $auditLine = "Received {$quantity} kg ({$receivedGrade->value}) in {$warehouseInstance->code} on ".$receivedTimestamp->toDateTimeString()." by {$actorUser->name}";
            if (filled($remarks)) {
                $auditLine .= ' ('.trim($remarks).')';
            }
            $notesText = filled($lockedItem->physical_receipt_notes)
                ? trim((string) $lockedItem->physical_receipt_notes)."\n".$auditLine
                : $auditLine;

            $lockedItem->forceFill([
                'received_quantity' => $newTotalReceived,
                'received_grade' => $receivedGrade->value,
                'receipt_status' => $newStatus->value,
                'physical_receipt_notes' => $notesText,
            ])->save();

            return $receipt->fresh(['creditNote', 'item', 'product', 'warehouse', 'stockMovement', 'receiver']);
        });
    }

    /**
     * Record a dispute or failure to deliver physical goods without creating stock movements.
     *
     * @throws ValidationException
     */
    public function disputePhysicalReturn(
        ShopCreditNoteItem|int $item,
        User|int $actor,
        string $reason,
        ?CarbonInterface $disputedAt = null,
    ): ShopCreditNoteReceipt {
        $actorUser = $actor instanceof User ? $actor : User::find($actor);

        if (! $this->isAuthorizedToReceive($actorUser)) {
            throw ValidationException::withMessages([
                'received_by' => 'User is not authorized to verify warehouse receipts.',
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'A valid dispute reason is required.',
            ]);
        }

        $itemInstance = $item instanceof ShopCreditNoteItem
            ? $item
            : ShopCreditNoteItem::findOrFail($item);

        $creditNote = $itemInstance->creditNote;

        if (! $creditNote instanceof ShopCreditNote || ! $creditNote->isApproved() || $creditNote->isVoided()) {
            throw ValidationException::withMessages([
                'credit_note' => 'Only active finalized credit notes can have disputed physical returns.',
            ]);
        }

        if ($itemInstance->inventory_action !== ShopCreditNoteInventoryAction::ReturnToStock) {
            throw ValidationException::withMessages([
                'inventory_action' => 'Only return-to-stock items can have physical return disputes recorded.',
            ]);
        }

        $disputedTimestamp = $disputedAt !== null ? Carbon::instance($disputedAt) : now();

        return DB::transaction(function () use (
            $creditNote,
            $itemInstance,
            $actorUser,
            $reason,
            $disputedTimestamp
        ): ShopCreditNoteReceipt {
            /** @var ShopCreditNoteItem $lockedItem */
            $lockedItem = ShopCreditNoteItem::query()
                ->whereKey($itemInstance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $receipt = ShopCreditNoteReceipt::create([
                'shop_credit_note_id' => $creditNote->id,
                'shop_credit_note_item_id' => $lockedItem->id,
                'product_id' => $lockedItem->product_id,
                'warehouse_id' => Warehouse::first()?->id ?? 1,
                'stock_movement_id' => null,
                'received_grade' => $lockedItem->product_grade,
                'received_quantity' => 0.0000,
                'received_by' => $actorUser->id,
                'received_at' => $disputedTimestamp,
                'status' => 'disputed',
                'remarks' => trim($reason),
            ]);

            $auditLine = 'Disputed on '.$disputedTimestamp->toDateTimeString()." by {$actorUser->name}: ".trim($reason);
            $notesText = filled($lockedItem->physical_receipt_notes)
                ? trim((string) $lockedItem->physical_receipt_notes)."\n".$auditLine
                : $auditLine;

            $statusToSet = ((float) $lockedItem->received_quantity <= 0.0001)
                ? ShopCreditNoteReceiptStatus::Disputed
                : $lockedItem->receipt_status;

            $lockedItem->forceFill([
                'receipt_status' => $statusToSet->value,
                'physical_receipt_notes' => $notesText,
            ])->save();

            return $receipt->fresh(['creditNote', 'item', 'product', 'receiver']);
        });
    }

    /**
     * Resolve or create an active sorted batch in the receiving warehouse for the specified product and grade.
     */
    private function resolveReceivingBatch(
        int $productId,
        int $warehouseId,
        ProductGrade $grade,
        ShopCreditNote $creditNote,
        ShopCreditNoteItem $item,
        int $userId,
    ): StockBatch {
        // Priority 1: Active sorted batch matching product, warehouse, and received grade
        $batch = StockBatch::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('purchase_grade', $grade->value)
            ->where('status', BatchStatus::Sorted->value)
            ->where('warehouse_receive_pending', false)
            ->whereNull('deleted_at')
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if ($batch instanceof StockBatch) {
            return $batch;
        }

        // Priority 2: Any active sorted batch in this warehouse for this product
        $batch = StockBatch::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', BatchStatus::Sorted->value)
            ->where('warehouse_receive_pending', false)
            ->whereNull('deleted_at')
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if ($batch instanceof StockBatch) {
            return $batch;
        }

        // Priority 3: Create dedicated return batch for this warehouse, product, and grade
        $warehouse = Warehouse::find($warehouseId);
        $whCode = $warehouse?->code ?? "WH{$warehouseId}";
        $ref = "RET-{$whCode}-{$productId}-{$grade->value}-".today()->format('Ymd');

        $costPerKg = (float) ($item->invoiceItem?->orderItem?->unit_cost
            ?? $item->credit_rate
            ?? 0.0);

        return StockBatch::create([
            'reference' => $ref,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'purchase_grade' => $grade->value,
            'grading_mode' => 'fixed_purchase_grade',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'total_kg' => 0.0,
            'cost_per_kg' => $costPerKg,
            'received_at' => today()->toDateString(),
            'sorted_at' => now(),
            'created_by' => $userId,
            'notes' => "Return batch for SCN {$creditNote->credit_note_number}",
        ]);
    }
}
