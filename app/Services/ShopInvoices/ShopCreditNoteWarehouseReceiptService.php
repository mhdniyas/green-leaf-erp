<?php

declare(strict_types=1);

namespace App\Services\ShopInvoices;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteLineType;
use App\Enums\ShopInvoice\ShopCreditNoteReceiptStatus;
use App\Models\Product;
use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopCreditNoteReceipt;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
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

        // Idempotency: exact key match
        if (filled($idempotencyKey)) {
            $existing = ShopCreditNoteReceipt::query()
                ->where('shop_credit_note_item_id', $itemInstance->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof ShopCreditNoteReceipt) {
                return $existing->fresh(['creditNote', 'item', 'product', 'warehouse', 'stockMovement', 'receiver']);
            }
        }

        $expectedQuantity = round((float) $itemInstance->adjusted_quantity, 4);
        $alreadyReceived = round((float) $itemInstance->receipts()->whereIn('status', ['verified', 'damaged'])->whereNull('reversed_at')->sum('received_quantity'), 4);
        $remainingExpected = round(max(0.0, $expectedQuantity - $alreadyReceived), 4);

        if ($quantity > ($remainingExpected + 0.0001)) {
            throw ValidationException::withMessages([
                'received_quantity' => "Received quantity ({$quantity} kg) exceeds remaining expected return quantity ({$remainingExpected} kg) for this item.",
            ]);
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

            if (filled($idempotencyKey)) {
                $existing = ShopCreditNoteReceipt::query()
                    ->where('shop_credit_note_item_id', $lockedItem->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing instanceof ShopCreditNoteReceipt) {
                    return $existing->fresh(['creditNote', 'item', 'product', 'warehouse', 'wastageEntry', 'receiver']);
                }
            }

            if ($lockedItem->inventory_action !== ShopCreditNoteInventoryAction::ReturnToStock) {
                throw ValidationException::withMessages([
                    'inventory_action' => 'Only items marked for return to stock can be physically received into the warehouse.',
                ]);
            }

            $expectedQuantity = round((float) $lockedItem->adjusted_quantity, 4);
            $alreadyReceived = round((float) $lockedItem->receipts()->whereIn('status', ['verified', 'damaged'])->whereNull('reversed_at')->lockForUpdate()->sum('received_quantity'), 4);
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
     * Record physical warehouse receipt of goods that arrived damaged or unsellable.
     * Records an authoritative WastageEntry for Damage & Loss reporting.
     * Does NOT create any stock movements (damaged goods never enter saleable inventory, and are not deducted twice).
     *
     * @throws ValidationException
     */
    public function receiveDamagedReturn(
        ShopCreditNoteItem|int $item,
        float $quantity,
        WastageReason|string $damageReason,
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

        $warehouseInstance = $warehouse instanceof Warehouse
            ? $warehouse
            : Warehouse::findOrFail($warehouse);

        $quantity = round($quantity, 4);
        if ($quantity <= 0.0001) {
            throw ValidationException::withMessages([
                'received_quantity' => 'Received quantity must be greater than zero.',
            ]);
        }

        // Idempotency: exact key match
        if (filled($idempotencyKey)) {
            $existing = ShopCreditNoteReceipt::query()
                ->where('shop_credit_note_item_id', $itemInstance->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof ShopCreditNoteReceipt) {
                return $existing->fresh(['creditNote', 'item', 'product', 'warehouse', 'wastageEntry', 'receiver']);
            }
        }

        $expectedQuantity = round((float) $itemInstance->adjusted_quantity, 4);
        $alreadyReceived = round((float) $itemInstance->receipts()->whereIn('status', ['verified', 'damaged'])->whereNull('reversed_at')->sum('received_quantity'), 4);
        $remainingExpected = round(max(0.0, $expectedQuantity - $alreadyReceived), 4);

        if ($quantity > ($remainingExpected + 0.0001)) {
            throw ValidationException::withMessages([
                'received_quantity' => "Received quantity ({$quantity} kg) exceeds remaining expected return quantity ({$remainingExpected} kg) for this item.",
            ]);
        }

        $resolvedReason = match (true) {
            $damageReason instanceof WastageReason => $damageReason,
            is_string($damageReason) => WastageReason::tryFrom(strtolower(trim($damageReason))) ?? WastageReason::TransitDamage,
            default => WastageReason::TransitDamage,
        };

        $receivedTimestamp = $receivedAt !== null ? Carbon::instance($receivedAt) : now();

        return DB::transaction(function () use (
            $creditNote,
            $itemInstance,
            $warehouseInstance,
            $resolvedReason,
            $quantity,
            $actorUser,
            $receivedTimestamp,
            $remarks,
            $idempotencyKey
        ): ShopCreditNoteReceipt {
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

            if (filled($idempotencyKey)) {
                $existing = ShopCreditNoteReceipt::query()
                    ->where('shop_credit_note_item_id', $lockedItem->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing instanceof ShopCreditNoteReceipt) {
                    return $existing->fresh(['creditNote', 'item', 'product', 'warehouse', 'wastageEntry', 'receiver']);
                }
            }

            $expectedQuantity = round((float) $lockedItem->adjusted_quantity, 4);
            $alreadyReceived = round((float) $lockedItem->receipts()->whereIn('status', ['verified', 'damaged'])->whereNull('reversed_at')->lockForUpdate()->sum('received_quantity'), 4);
            $remainingExpected = round(max(0.0, $expectedQuantity - $alreadyReceived), 4);

            if ($quantity > ($remainingExpected + 0.0001)) {
                throw ValidationException::withMessages([
                    'received_quantity' => "Received quantity ({$quantity} kg) exceeds remaining expected return quantity ({$remainingExpected} kg).",
                ]);
            }

            $orderItem = $lockedItem->invoiceItem?->orderItem;
            $outMovement = StockMovement::query()
                ->where('shop_order_item_id', $orderItem?->id)
                ->where('type', StockMovementType::Out->value)
                ->latest('id')
                ->first();

            $batchId = $outMovement?->batch_id;
            $costPerUnit = (float) ($outMovement?->cost_per_unit ?? $lockedItem->credit_rate ?? 0.0);

            $sourceInvoice = $lockedItem->sourceInvoice ?? $lockedItem->invoiceItem?->invoice;
            $order = $orderItem?->order ?? $sourceInvoice?->order;
            $shop = $lockedCreditNote->shop ?? $sourceInvoice?->shop;

            // Authoritative WastageEntry for Damage & Loss reporting
            $wastage = WastageEntry::create([
                'product_id' => $lockedItem->product_id,
                'batch_id' => $batchId,
                'recorded_by' => $actorUser->id,
                'grade' => $lockedItem->product_grade ?? 'A',
                'quantity' => $quantity,
                'cost_per_kg' => $costPerUnit,
                'reason' => $resolvedReason,
                'wastage_date' => $receivedTimestamp->toDateString(),
                'notes' => "Warehouse Damaged Return — SCN: {$lockedCreditNote->credit_note_number}; NoteItem: {$lockedItem->id}; Invoice: {$sourceInvoice?->invoice_number}; Order: {$order?->order_number}; Shop: {$shop?->name}; Warehouse: {$warehouseInstance->code}; Reason: {$resolvedReason->label()}; ".trim((string) $remarks),
            ]);

            // Receipt record without stock movement (damaged goods never enter saleable stock)
            $receipt = ShopCreditNoteReceipt::create([
                'shop_credit_note_id' => $lockedCreditNote->id,
                'shop_credit_note_item_id' => $lockedItem->id,
                'product_id' => $lockedItem->product_id,
                'warehouse_id' => $warehouseInstance->id,
                'stock_movement_id' => null,
                'wastage_entry_id' => $wastage->id,
                'received_grade' => $lockedItem->product_grade ?? 'A',
                'received_quantity' => $quantity,
                'received_by' => $actorUser->id,
                'received_at' => $receivedTimestamp,
                'status' => 'damaged',
                'remarks' => $remarks,
                'idempotency_key' => $idempotencyKey,
            ]);

            $newTotalReceived = round($alreadyReceived + $quantity, 4);
            $newStatus = ($newTotalReceived >= ($expectedQuantity - 0.0001))
                ? ShopCreditNoteReceiptStatus::ReceivedAndVerified
                : ShopCreditNoteReceiptStatus::PartiallyReceived;

            $auditLine = "Received {$quantity} kg DAMAGED ({$resolvedReason->label()}) in {$warehouseInstance->code} on ".$receivedTimestamp->toDateTimeString()." by {$actorUser->name}";
            if (filled($remarks)) {
                $auditLine .= ' ('.trim($remarks).')';
            }
            $notesText = filled($lockedItem->physical_receipt_notes)
                ? trim((string) $lockedItem->physical_receipt_notes)."\n".$auditLine
                : $auditLine;

            $lockedItem->forceFill([
                'received_quantity' => $newTotalReceived,
                'receipt_status' => $newStatus->value,
                'physical_receipt_notes' => $notesText,
            ])->save();

            return $receipt->fresh(['creditNote', 'item', 'product', 'warehouse', 'wastageEntry', 'receiver']);
        });
    }

    /**
     * Reconcile / reverse a previously verified warehouse return receipt.
     * Decreases stock in the exact warehouse, grade, product, and batch via compensating stock adjustment.
     * Decoupled from finance: does not alter credit note amount or Cashbook ledger.
     *
     * @throws ValidationException
     */
    public function reversePhysicalReturn(
        ShopCreditNoteReceipt|int $receipt,
        User|int $actor,
        string $reason,
        ?CarbonInterface $reversedAt = null,
    ): ShopCreditNoteReceipt {
        $actorUser = $actor instanceof User ? $actor : User::find($actor);

        if (! $this->isAuthorizedToReceive($actorUser)) {
            throw ValidationException::withMessages([
                'reversed_by' => 'User is not authorized to reverse warehouse receipts.',
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reversal_reason' => 'A valid reversal reason is required.',
            ]);
        }

        $receiptInstance = $receipt instanceof ShopCreditNoteReceipt
            ? $receipt
            : ShopCreditNoteReceipt::findOrFail($receipt);

        if (! $receiptInstance->isVerified() && ! $receiptInstance->isDamaged()) {
            throw ValidationException::withMessages([
                'receipt' => 'Only verified or damaged receipts can be reversed.',
            ]);
        }

        if ($receiptInstance->isReversed()) {
            throw ValidationException::withMessages([
                'receipt' => 'This warehouse return receipt has already been reversed.',
            ]);
        }

        $creditNote = $receiptInstance->creditNote;
        if (! $creditNote instanceof ShopCreditNote) {
            throw ValidationException::withMessages([
                'shop_credit_note_id' => 'Credit note for this receipt could not be found.',
            ]);
        }

        if ($creditNote->isVoided()) {
            throw ValidationException::withMessages([
                'credit_note' => 'Cannot reverse receipts for an already voided credit note.',
            ]);
        }

        $reversedTimestamp = $reversedAt !== null ? Carbon::instance($reversedAt) : now();
        $reversalDate = $reversedTimestamp->toDateString();

        $reversalPeriodClosed = ShopAccountingPeriodClosure::query()
            ->where('shop_id', $creditNote->shop_id)
            ->whereDate('period_start', '<=', $reversalDate)
            ->whereDate('period_end', '>=', $reversalDate)
            ->exists();

        if ($reversalPeriodClosed) {
            throw ValidationException::withMessages([
                'reversed_at' => 'Cannot reverse warehouse receipt in a closed accounting period.',
            ]);
        }

        return DB::transaction(function () use (
            $receiptInstance,
            $actorUser,
            $reason,
            $reversedTimestamp,
            $reversalDate
        ): ShopCreditNoteReceipt {
            /** @var ShopCreditNote $lockedCreditNote */
            $lockedCreditNote = ShopCreditNote::query()
                ->whereKey($receiptInstance->shop_credit_note_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCreditNote->isVoided()) {
                throw ValidationException::withMessages([
                    'credit_note' => 'Cannot reverse receipts for an already voided credit note.',
                ]);
            }

            if (! $lockedCreditNote->isApproved()) {
                throw ValidationException::withMessages([
                    'credit_note' => 'Credit note must be approved to reverse return receipts.',
                ]);
            }

            $reversalPeriodClosed = ShopAccountingPeriodClosure::query()
                ->where('shop_id', $lockedCreditNote->shop_id)
                ->whereDate('period_start', '<=', $reversalDate)
                ->whereDate('period_end', '>=', $reversalDate)
                ->exists();

            if ($reversalPeriodClosed) {
                throw ValidationException::withMessages([
                    'reversed_at' => 'Cannot reverse warehouse receipt in a closed accounting period.',
                ]);
            }

            /** @var ShopCreditNoteItem $lockedItem */
            $lockedItem = ShopCreditNoteItem::query()
                ->whereKey($receiptInstance->shop_credit_note_item_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var ShopCreditNoteReceipt $lockedReceipt */
            $lockedReceipt = ShopCreditNoteReceipt::query()
                ->whereKey($receiptInstance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReceipt->isReversed()) {
                throw ValidationException::withMessages([
                    'receipt' => 'This warehouse return receipt has already been reversed.',
                ]);
            }

            if (! $lockedReceipt->isVerified() && ! $lockedReceipt->isDamaged()) {
                throw ValidationException::withMessages([
                    'receipt' => 'Only verified or damaged receipts can be reversed.',
                ]);
            }

            if ($lockedReceipt->isDamaged()) {
                if ($lockedReceipt->wastage_entry_id) {
                    $wastage = WastageEntry::find($lockedReceipt->wastage_entry_id);
                    if ($wastage) {
                        $voidNote = "\n[Receipt #{$lockedReceipt->id} Reversed on ".$reversedTimestamp->toDateTimeString()." by {$actorUser->name}: ".trim($reason).']';
                        $wastage->notes = trim((string) $wastage->notes).$voidNote;
                        $wastage->save();
                        $wastage->delete();
                    }
                }

                $lockedReceipt->forceFill([
                    'reversed_at' => $reversedTimestamp,
                    'reversed_by' => $actorUser->id,
                    'reversal_reason' => trim($reason),
                ])->save();
            } else {
                $origMovement = $lockedReceipt->stockMovement;
                $batchId = $origMovement?->batch_id;

                if ($batchId !== null) {
                    StockBatch::query()->whereKey($batchId)->lockForUpdate()->first();
                }

                $qtyToReverse = (float) $lockedReceipt->received_quantity;
                $warehouseId = (int) $lockedReceipt->warehouse_id;
                $productId = (int) $lockedReceipt->product_id;
                $grade = (string) $lockedReceipt->received_grade;

                // Check stock availability in the specific batch if batch exists
                if ($batchId !== null) {
                    $batchAvailable = (float) StockMovement::query()
                        ->where('batch_id', $batchId)
                        ->where('grade', $grade)
                        ->selectRaw('SUM(CASE WHEN type IN (?, ?) THEN quantity WHEN type IN (?, ?, ?, ?) THEN -quantity ELSE 0 END) as available_quantity', [
                            StockMovementType::In->value,
                            StockMovementType::SaleReversal->value,
                            StockMovementType::Out->value,
                            StockMovementType::Wastage->value,
                            StockMovementType::Sale->value,
                            StockMovementType::Adjustment->value,
                        ])
                        ->value('available_quantity') ?? 0.0;

                    if ($batchAvailable < ($qtyToReverse - 0.0001)) {
                        throw ValidationException::withMessages([
                            'stock_availability' => "Cannot reverse warehouse receipt #{$lockedReceipt->id}. The returned stock ({$qtyToReverse} kg of Grade {$grade}) has already been consumed or dispatched from batch #{$batchId} (Available in batch: {$batchAvailable} kg).",
                        ]);
                    }
                }

                // Check warehouse-level available stock for that product and grade
                $whAvailable = (float) StockMovement::query()
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $productId)
                    ->where('grade', $grade)
                    ->selectRaw('SUM(CASE WHEN type IN (?, ?) THEN quantity WHEN type IN (?, ?, ?, ?) THEN -quantity ELSE 0 END) as available_quantity', [
                        StockMovementType::In->value,
                        StockMovementType::SaleReversal->value,
                        StockMovementType::Out->value,
                        StockMovementType::Wastage->value,
                        StockMovementType::Sale->value,
                        StockMovementType::Adjustment->value,
                    ])
                    ->value('available_quantity') ?? 0.0;

                if ($whAvailable < ($qtyToReverse - 0.0001)) {
                    throw ValidationException::withMessages([
                        'stock_availability' => "Cannot reverse warehouse receipt #{$lockedReceipt->id}. Insufficient warehouse stock available to reverse return ({$qtyToReverse} kg of Grade {$grade} required, {$whAvailable} kg available in warehouse #{$warehouseId}).",
                    ]);
                }

                // Create compensating negative stock movement (StockMovementType::Adjustment)
                $compensatingMovement = new StockMovement([
                    'batch_id' => $batchId,
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'created_by' => $actorUser->id,
                    'grade' => $grade,
                    'type' => StockMovementType::Adjustment->value,
                    'quantity' => $qtyToReverse,
                    'cost_per_unit' => (float) ($origMovement?->cost_per_unit ?? 0.0),
                    'shop_order_item_id' => $lockedItem->invoiceItem?->shop_order_item_id,
                    'notes' => "Reversal of Credit Note Return Receipt #{$lockedReceipt->id} — SCN: {$lockedCreditNote->credit_note_number}; NoteItem: {$lockedItem->id}; Reason: ".trim($reason),
                ]);
                $compensatingMovement->created_at = $reversedTimestamp;
                $compensatingMovement->save();

                // Update receipt record
                $lockedReceipt->forceFill([
                    'reversal_stock_movement_id' => $compensatingMovement->id,
                    'reversed_at' => $reversedTimestamp,
                    'reversed_by' => $actorUser->id,
                    'reversal_reason' => trim($reason),
                ])->save();
            }

            // Re-evaluate cumulative item state
            $newReceivedQuantity = round((float) $lockedItem->receipts()
                ->whereIn('status', ['verified', 'damaged'])
                ->whereNull('reversed_at')
                ->sum('received_quantity'), 4);

            $expectedQuantity = round((float) $lockedItem->adjusted_quantity, 4);

            if ($newReceivedQuantity >= ($expectedQuantity - 0.0001)) {
                $newStatus = ShopCreditNoteReceiptStatus::ReceivedAndVerified;
            } elseif ($newReceivedQuantity > 0.0001) {
                $newStatus = ShopCreditNoteReceiptStatus::PartiallyReceived;
            } else {
                $hasDispute = $lockedItem->receipts()->where('status', 'disputed')->exists();
                $newStatus = $hasDispute ? ShopCreditNoteReceiptStatus::Disputed : ShopCreditNoteReceiptStatus::PendingReceipt;
            }

            $latestActiveReceipt = $lockedItem->receipts()
                ->whereIn('status', ['verified', 'damaged'])
                ->whereNull('reversed_at')
                ->latest('id')
                ->first();

            $latestGrade = $latestActiveReceipt?->received_grade ?? ($newReceivedQuantity > 0 ? $lockedItem->received_grade : null);

            $auditLine = "Reversed receipt #{$lockedReceipt->id} (".($lockedReceipt->isDamaged() ? 'DAMAGED ' : '')."{$lockedReceipt->received_quantity} kg of Grade {$lockedReceipt->received_grade}) on ".$reversedTimestamp->toDateTimeString()." by {$actorUser->name} (Reason: ".trim($reason).')';
            $notesText = filled($lockedItem->physical_receipt_notes)
                ? trim((string) $lockedItem->physical_receipt_notes)."\n".$auditLine
                : $auditLine;

            $lockedItem->forceFill([
                'received_quantity' => $newReceivedQuantity,
                'received_grade' => $latestGrade,
                'receipt_status' => $newStatus->value,
                'physical_receipt_notes' => $notesText,
            ])->save();

            return $lockedReceipt->fresh(['creditNote', 'item', 'product', 'warehouse', 'stockMovement', 'reversalStockMovement', 'receiver', 'reversedByUser']);
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
