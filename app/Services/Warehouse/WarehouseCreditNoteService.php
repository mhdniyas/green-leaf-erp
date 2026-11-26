<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use App\Enums\Warehouse\WarehouseCreditInventoryAction;
use App\Enums\Warehouse\WarehouseCreditNoteStatus;
use App\Enums\Warehouse\WarehouseCreditReason;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseFinanceAccountingOpening;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseCreditNote;
use App\Models\WarehouseCreditNoteItem;
use App\Models\WarehouseCreditNoteItemMovement;
use App\Services\Finance\JournalService;
use App\Services\Finance\PurchaseFinanceAccountingOpeningService;
use App\Services\Inventory\StockLedgerService;
use App\Support\CashbookAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WarehouseCreditNoteService
{
    public function __construct(
        private readonly StockLedgerService $stockLedgerService,
        private readonly JournalService $journalService,
    ) {}

    /**
     * Create a Draft Warehouse Credit Note with zero inventory impact.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function createDraft(array $payload, User $actor): WarehouseCreditNote
    {
        $warehouseId = (int) ($payload['warehouse_id'] ?? 0);
        $this->assertAuthorizedForWarehouse($actor, CashbookAccess::WarehouseCreditNotesManage, $warehouseId);

        $warehouse = Warehouse::query()->whereKey($warehouseId)->first();
        if (! $warehouse instanceof Warehouse) {
            throw ValidationException::withMessages(['warehouse_id' => 'Selected warehouse not found.']);
        }

        $reason = $payload['reason'] instanceof WarehouseCreditReason
            ? $payload['reason']
            : WarehouseCreditReason::tryFrom((string) ($payload['reason'] ?? ''));
        if (! $reason instanceof WarehouseCreditReason) {
            throw ValidationException::withMessages(['reason' => 'A valid credit note reason is required.']);
        }

        $businessDate = ! empty($payload['business_date'])
            ? Carbon::parse((string) $payload['business_date'])->toDateString()
            : today()->toDateString();

        $sanitizedItems = $this->sanitizeItems($payload['items'] ?? []);

        return DB::transaction(function () use ($warehouse, $businessDate, $reason, $payload, $actor, $sanitizedItems): WarehouseCreditNote {
            $totalAmount = round(array_sum(array_column($sanitizedItems, 'line_total')), 2);

            /** @var WarehouseCreditNote $creditNote */
            $creditNote = WarehouseCreditNote::query()->create([
                'public_uuid' => (string) Str::uuid(),
                'warehouse_id' => $warehouse->id,
                'business_date' => $businessDate,
                'reason' => $reason,
                'total_amount' => $totalAmount,
                'remarks' => $payload['remarks'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($sanitizedItems as $row) {
                $creditNote->items()->create($row);
            }

            return $creditNote->fresh(['items.product', 'warehouse']);
        });
    }

    /**
     * Update a Draft Warehouse Credit Note with zero inventory impact.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function updateDraft(WarehouseCreditNote $creditNote, array $payload, User $actor): WarehouseCreditNote
    {
        $this->assertAuthorizedForWarehouse($actor, CashbookAccess::WarehouseCreditNotesManage, (int) $creditNote->warehouse_id);

        return DB::transaction(function () use ($creditNote, $payload, $actor): WarehouseCreditNote {
            /** @var WarehouseCreditNote $locked */
            $locked = WarehouseCreditNote::query()->whereKey($creditNote->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft warehouse credit notes can be updated.',
                ]);
            }

            $warehouseId = isset($payload['warehouse_id']) ? (int) $payload['warehouse_id'] : (int) $locked->warehouse_id;
            $this->assertAuthorizedForWarehouse($actor, CashbookAccess::WarehouseCreditNotesManage, $warehouseId);

            $warehouse = Warehouse::query()->whereKey($warehouseId)->first();
            if (! $warehouse instanceof Warehouse) {
                throw ValidationException::withMessages(['warehouse_id' => 'Selected warehouse not found.']);
            }

            $reason = isset($payload['reason'])
                ? ($payload['reason'] instanceof WarehouseCreditReason
                    ? $payload['reason']
                    : WarehouseCreditReason::tryFrom((string) $payload['reason']))
                : $locked->reason;
            if (! $reason instanceof WarehouseCreditReason) {
                throw ValidationException::withMessages(['reason' => 'A valid credit note reason is required.']);
            }

            $businessDate = ! empty($payload['business_date'])
                ? Carbon::parse((string) $payload['business_date'])->toDateString()
                : $locked->business_date->toDateString();

            $sanitizedItems = $this->sanitizeItems($payload['items'] ?? []);
            $totalAmount = round(array_sum(array_column($sanitizedItems, 'line_total')), 2);

            $locked->update([
                'warehouse_id' => $warehouse->id,
                'business_date' => $businessDate,
                'reason' => $reason,
                'total_amount' => $totalAmount,
                'remarks' => array_key_exists('remarks', $payload) ? $payload['remarks'] : $locked->remarks,
            ]);

            $locked->items()->delete();
            foreach ($sanitizedItems as $row) {
                $locked->items()->create($row);
            }

            return $locked->fresh(['items.product', 'warehouse']);
        });
    }

    /**
     * Delete a Draft Warehouse Credit Note. Active and Voided notes cannot be deleted.
     *
     * @throws ValidationException
     */
    public function deleteDraft(WarehouseCreditNote $creditNote, User $actor): void
    {
        $this->assertAuthorizedForWarehouse($actor, CashbookAccess::WarehouseCreditNotesManage, (int) $creditNote->warehouse_id);

        DB::transaction(function () use ($creditNote): void {
            /** @var WarehouseCreditNote $locked */
            $locked = WarehouseCreditNote::query()->whereKey($creditNote->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft warehouse credit notes can be deleted.',
                ]);
            }

            $locked->delete();
        });
    }

    /**
     * Atomically finalize a Draft Warehouse Credit Note:
     * validates authorization/period/items, applies exact-grade FIFO inventory movements,
     * computes authoritative valuation, posts a balanced JournalEntry, and transitions to Active.
     *
     * @throws ValidationException
     */
    public function finalize(WarehouseCreditNote $creditNote, User $actor): WarehouseCreditNote
    {
        $this->assertAuthorizedForWarehouse($actor, CashbookAccess::WarehouseCreditNotesManage, (int) $creditNote->warehouse_id);

        return DB::transaction(function () use ($creditNote, $actor): WarehouseCreditNote {
            /** @var WarehouseCreditNote $locked */
            $locked = WarehouseCreditNote::query()
                ->with(['items.product', 'warehouse'])
                ->whereKey($creditNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertAuthorizedForWarehouse($actor, CashbookAccess::WarehouseCreditNotesManage, (int) $locked->warehouse_id);

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft warehouse credit notes can be finalized.',
                ]);
            }

            if ($locked->journal_entry_id !== null || $locked->itemMovements()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Inventory or journal entries have already been posted for this credit note.',
                ]);
            }

            $businessDate = $locked->business_date?->toDateString() ?? today()->toDateString();
            $this->assertOpenAccountingPeriod($businessDate, 'business_date');

            if ($locked->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'At least one line item is required to finalize a warehouse credit note.',
                ]);
            }

            foreach ($locked->items as $index => $item) {
                if (! $item->product instanceof Product) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => "Product at line {$index} is invalid."]);
                }

                $grade = $item->grade instanceof ProductGrade ? $item->grade : ProductGrade::tryResolve((string) $item->grade);
                if (! $grade instanceof ProductGrade || $grade === ProductGrade::Damage) {
                    throw ValidationException::withMessages(["items.{$index}.grade" => "Valid grade (A, B, C, or Mixed/U) is required for {$item->product->name}."]);
                }

                $this->resolveUnitConversion($item->product, $item->unit, $index);

                if ((float) $item->quantity <= 0.0 || (float) $item->base_qty <= 0.0) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => "Quantity for {$item->product->name} must be greater than zero."]);
                }
            }

            if (empty($locked->credit_note_number)) {
                $locked->credit_note_number = WarehouseCreditNote::generateCreditNoteNumber($businessDate);
                $locked->save();
            }

            $this->applyInventoryForFinalization($locked, $actor);

            $locked->refresh()->loadMissing(['items', 'warehouse']);
            $journalEntry = $this->journalService->recordWarehouseCreditNote($locked, $actor);

            $locked->update([
                'status' => WarehouseCreditNoteStatus::Active,
                'journal_entry_id' => $journalEntry->id,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
            ]);

            return $locked->fresh([
                'items.product',
                'items.movements',
                'warehouse',
                'journalEntry.transactions.account',
                'createdBy',
                'finalizedBy',
            ]);
        }, attempts: 3);
    }

    /**
     * Atomically void an Active Warehouse Credit Note:
     * reverses exact stock movements and wastage entries, reverses the JournalEntry,
     * and transitions status to Voided.
     *
     * @throws ValidationException
     */
    public function void(WarehouseCreditNote $creditNote, string $reason, User $actor): WarehouseCreditNote
    {
        $trimmedReason = trim($reason);
        if (mb_strlen($trimmedReason) < 3) {
            throw ValidationException::withMessages([
                'void_reason' => 'A valid void reason of at least 3 characters is required.',
            ]);
        }

        $this->assertAuthorizedForWarehouse($actor, CashbookAccess::WarehouseCreditNotesVoid, (int) $creditNote->warehouse_id);

        return DB::transaction(function () use ($creditNote, $trimmedReason, $actor): WarehouseCreditNote {
            /** @var WarehouseCreditNote $locked */
            $locked = WarehouseCreditNote::query()
                ->whereKey($creditNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertAuthorizedForWarehouse($actor, CashbookAccess::WarehouseCreditNotesVoid, (int) $locked->warehouse_id);

            if ($locked->isVoided()) {
                throw ValidationException::withMessages([
                    'status' => 'This warehouse credit note is already voided.',
                ]);
            }

            if (! $locked->isActive()) {
                throw ValidationException::withMessages([
                    'status' => 'Only active warehouse credit notes can be voided.',
                ]);
            }

            $businessDate = $locked->business_date?->toDateString() ?? today()->toDateString();
            $this->assertOpenAccountingPeriod($businessDate, 'business_date');
            $this->assertOpenAccountingPeriod(now()->toDateString(), 'voided_at');

            $this->reverseInventoryForVoid($locked, $actor, $trimmedReason);

            if ($locked->journal_entry_id !== null) {
                $journalEntry = JournalEntry::query()->whereKey($locked->journal_entry_id)->lockForUpdate()->first();
                if ($journalEntry instanceof JournalEntry) {
                    $this->journalService->correctJournalEntry(
                        journalEntry: $journalEntry,
                        data: [
                            'reason' => "Voided Warehouse Credit Note #{$locked->credit_note_number}: {$trimmedReason}",
                            'amount' => 0,
                            'confirm_shortfall' => true,
                        ],
                        actor: $actor,
                    );
                }
            }

            $locked->update([
                'status' => WarehouseCreditNoteStatus::Voided,
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $trimmedReason,
            ]);

            return $locked->fresh([
                'items.product',
                'items.movements.reversalStockMovement',
                'warehouse',
                'journalEntry.transactions.account',
                'voidedBy',
            ]);
        }, attempts: 3);
    }

    /**
     * Apply inventory movements for a Draft Warehouse Credit Note inside the finalization transaction.
     *
     * @return array{total_amount: float, fifo_cost_total: float}
     *
     * @throws ValidationException
     */
    private function applyInventoryForFinalization(WarehouseCreditNote $creditNote, User $actor): array
    {
        $this->assertAuthorizedForWarehouse($actor, CashbookAccess::WarehouseCreditNotesManage, (int) $creditNote->warehouse_id);

        return DB::transaction(function () use ($creditNote, $actor): array {
            /** @var WarehouseCreditNote $locked */
            $locked = WarehouseCreditNote::query()
                ->with('items')
                ->whereKey($creditNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft warehouse credit notes can apply inventory.',
                ]);
            }

            if ($locked->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'At least one line item is required.',
                ]);
            }

            if ($locked->itemMovements()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Inventory movements have already been recorded for this credit note.',
                ]);
            }

            if (empty($locked->credit_note_number)) {
                $locked->credit_note_number = WarehouseCreditNote::generateCreditNoteNumber($locked->business_date);
                $locked->save();
            }

            $documentTotal = 0.0;
            $totalFifoCost = 0.0;
            $wastageReason = $this->resolveWastageReason($locked->reason);
            $businessDate = $locked->business_date->toDateString();

            foreach ($locked->items as $item) {
                $baseQty = round((float) $item->base_qty, 3);
                if ($baseQty <= 0.0) {
                    throw ValidationException::withMessages(['items' => 'Item base quantity must be greater than zero.']);
                }

                $itemNotes = "Warehouse Credit Note — WCN: {$locked->credit_note_number}; ItemId: {$item->id}";
                $action = $item->inventory_action;

                if ($action === WarehouseCreditInventoryAction::In) {
                    $rate = round((float) $item->valuation_rate, 4);
                    $movement = $this->stockLedgerService->recordWarehouseCreditIn(
                        productId: (int) $item->product_id,
                        warehouseId: (int) $locked->warehouse_id,
                        grade: $item->grade,
                        baseQuantity: $baseQty,
                        costPerBaseUnit: $rate,
                        userId: $actor->id,
                        notes: $itemNotes,
                        businessDate: $businessDate,
                    );

                    $lineAmount = round($baseQty * $rate, 2);
                    $this->linkItemMovement($item, $movement);

                    $item->update([
                        'valuation_rate' => $rate,
                        'line_total' => $lineAmount,
                        'fifo_cost_total' => $lineAmount,
                    ]);

                    $documentTotal = round($documentTotal + $lineAmount, 2);
                    $totalFifoCost = round($totalFifoCost + $lineAmount, 2);
                } elseif ($action === WarehouseCreditInventoryAction::Out) {
                    $movements = $this->stockLedgerService->recordWarehouseCreditOut(
                        productId: (int) $item->product_id,
                        warehouseId: (int) $locked->warehouse_id,
                        grade: $item->grade,
                        baseQuantity: $baseQty,
                        userId: $actor->id,
                        notes: $itemNotes,
                    );

                    $fifoCost = round((float) $movements->sum(
                        fn (StockMovement $m): float => (float) $m->quantity * (float) $m->cost_per_unit
                    ), 2);
                    $effectiveRate = round($fifoCost / $baseQty, 4);

                    foreach ($movements as $movement) {
                        $this->linkItemMovement($item, $movement);
                    }

                    $item->update([
                        'valuation_rate' => $effectiveRate,
                        'line_total' => $fifoCost,
                        'fifo_cost_total' => $fifoCost,
                    ]);

                    $documentTotal = round($documentTotal + $fifoCost, 2);
                    $totalFifoCost = round($totalFifoCost + $fifoCost, 2);
                } elseif ($action === WarehouseCreditInventoryAction::Damage) {
                    $damageResult = $this->stockLedgerService->recordWarehouseCreditDamage(
                        productId: (int) $item->product_id,
                        warehouseId: (int) $locked->warehouse_id,
                        grade: $item->grade,
                        baseQuantity: $baseQty,
                        userId: $actor->id,
                        reason: $wastageReason,
                        notes: $itemNotes,
                        wastageDate: $businessDate,
                    );

                    $fifoCost = $damageResult['fifo_cost_total'];
                    $effectiveRate = round($fifoCost / $baseQty, 4);

                    foreach ($damageResult['movements'] as $movement) {
                        $this->linkItemMovement($item, $movement);
                    }

                    $item->update([
                        'valuation_rate' => $effectiveRate,
                        'line_total' => $fifoCost,
                        'fifo_cost_total' => $fifoCost,
                        'wastage_entry_id' => $damageResult['wastage']->id,
                    ]);

                    $documentTotal = round($documentTotal + $fifoCost, 2);
                    $totalFifoCost = round($totalFifoCost + $fifoCost, 2);
                }
            }

            $locked->update(['total_amount' => $documentTotal]);

            return [
                'total_amount' => $documentTotal,
                'fifo_cost_total' => $totalFifoCost,
            ];
        }, attempts: 3);
    }

    /**
     * Reverse inventory movements for an Active Warehouse Credit Note inside the void transaction.
     *
     * @throws ValidationException
     */
    private function reverseInventoryForVoid(WarehouseCreditNote $creditNote, User $actor, string $reason): void
    {
        $trimmedReason = trim($reason);
        if (mb_strlen($trimmedReason) < 3) {
            throw ValidationException::withMessages(['void_reason' => 'A valid void reason of at least 3 characters is required.']);
        }

        $this->assertAuthorizedForWarehouse($actor, CashbookAccess::WarehouseCreditNotesVoid, (int) $creditNote->warehouse_id);

        DB::transaction(function () use ($creditNote, $actor, $trimmedReason): void {
            /** @var WarehouseCreditNote $locked */
            $locked = WarehouseCreditNote::query()->whereKey($creditNote->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw ValidationException::withMessages([
                    'status' => 'Only active warehouse credit notes can reverse inventory.',
                ]);
            }

            $this->stockLedgerService->reverseWarehouseCreditNoteMovements(
                creditNote: $locked,
                userId: $actor->id,
                reason: $trimmedReason,
            );
        }, attempts: 3);
    }

    /**
     * Resolve and validate product unit conversion to base unit.
     *
     * @throws ValidationException
     */
    public function resolveUnitConversion(Product $product, ?string $unit, int $index): array
    {
        $requestedUnit = trim((string) ($unit ?: $product->unit));
        if ($requestedUnit === '') {
            throw ValidationException::withMessages([
                "items.{$index}.unit" => "Unit is required for {$product->name}.",
            ]);
        }

        $baseUnit = trim((string) $product->unit);
        if ($baseUnit !== '' && strcasecmp($requestedUnit, $baseUnit) === 0) {
            return [$baseUnit, 1.0];
        }

        $matchedUnit = $product->orderUnits()
            ->get()
            ->first(fn (ProductUnit $pu): bool => strcasecmp(trim((string) $pu->unit), $requestedUnit) === 0);

        if (! $matchedUnit instanceof ProductUnit || $matchedUnit->conversion_to_base === null || (float) $matchedUnit->conversion_to_base <= 0.0) {
            throw ValidationException::withMessages([
                "items.{$index}.unit" => "Unit '{$requestedUnit}' is not configured or has an invalid conversion for {$product->name}.",
            ]);
        }

        return [$matchedUnit->unit, round((float) $matchedUnit->conversion_to_base, 4)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $itemsData
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    private function sanitizeItems(mixed $itemsData): array
    {
        if (! is_array($itemsData) || $itemsData === []) {
            throw ValidationException::withMessages(['items' => 'At least one line item is required.']);
        }

        $sanitized = [];
        foreach (array_values($itemsData) as $index => $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $product = Product::query()->find($productId);
            if (! $product instanceof Product) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => "Product at line {$index} is invalid."]);
            }

            $grade = $row['grade'] instanceof ProductGrade
                ? $row['grade']
                : ProductGrade::tryResolve((string) ($row['grade'] ?? ''));
            if (! $grade instanceof ProductGrade || $grade === ProductGrade::Damage) {
                throw ValidationException::withMessages(["items.{$index}.grade" => "Valid grade (A, B, C, or Mixed/U) is required for {$product->name}."]);
            }

            $action = $row['inventory_action'] instanceof WarehouseCreditInventoryAction
                ? $row['inventory_action']
                : WarehouseCreditInventoryAction::tryFrom((string) ($row['inventory_action'] ?? ''));
            if (! $action instanceof WarehouseCreditInventoryAction) {
                throw ValidationException::withMessages(["items.{$index}.inventory_action" => "Valid inventory action is required for {$product->name}."]);
            }

            [$unit, $conversion] = $this->resolveUnitConversion($product, $row['unit'] ?? null, $index);

            $quantity = round((float) ($row['quantity'] ?? 0.0), 3);
            if ($quantity <= 0.0) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => "Quantity for {$product->name} must be greater than zero."]);
            }

            $baseQty = round($quantity * $conversion, 3);
            if ($baseQty <= 0.0) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => "Base quantity for {$product->name} must be greater than zero."]);
            }

            $rate = round((float) ($row['valuation_rate'] ?? 0.0), 4);
            if ($rate < 0.0) {
                throw ValidationException::withMessages(["items.{$index}.valuation_rate" => "Valuation rate for {$product->name} cannot be negative."]);
            }

            $lineTotal = round($baseQty * $rate, 2);

            $sanitized[] = [
                'product_id' => $product->id,
                'grade' => $grade->value,
                'inventory_action' => $action->value,
                'unit' => $unit,
                'conversion_to_base' => $conversion,
                'quantity' => $quantity,
                'base_qty' => $baseQty,
                'valuation_rate' => $rate,
                'line_total' => $lineTotal,
                'fifo_cost_total' => 0.00,
                'notes' => $row['notes'] ?? null,
            ];
        }

        return $sanitized;
    }

    private function linkItemMovement(WarehouseCreditNoteItem $item, StockMovement $movement): void
    {
        WarehouseCreditNoteItemMovement::query()->create([
            'warehouse_credit_note_item_id' => $item->id,
            'stock_batch_id' => (int) $movement->batch_id,
            'stock_movement_id' => $movement->id,
            'quantity' => round((float) $movement->quantity, 3),
            'cost_per_unit' => round((float) $movement->cost_per_unit, 4),
        ]);
    }

    private function assertAuthorizedForWarehouse(User $actor, string $permission, int $warehouseId): void
    {
        if ($actor->isMainAdmin() || $actor->hasRole('admin')) {
            return;
        }

        if (! $actor->hasRole('accounts') || ! $actor->can($permission)) {
            throw ValidationException::withMessages([
                'authorization' => 'User is not authorized to perform this Warehouse Credit Note action.',
            ]);
        }

        if ($warehouseId > 0 && ! $actor->canAccessWarehouse($warehouseId)) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'User is not authorized for the selected warehouse.',
            ]);
        }
    }

    private function assertOpenAccountingPeriod(string $date, string $field = 'business_date'): void
    {
        $opening = PurchaseFinanceAccountingOpening::query()
            ->whereIn('account_scope', ['warehouse', 'global'])
            ->orderByDesc('accounting_start_date')
            ->first();

        $startDate = $opening?->accounting_start_date?->toDateString()
            ?? PurchaseFinanceAccountingOpeningService::DEFAULT_ACCOUNTING_START_DATE;

        if ($date < $startDate) {
            throw ValidationException::withMessages([
                $field => "Date {$date} precedes the accounting opening date ({$startDate}).",
            ]);
        }
    }

    private function resolveWastageReason(WarehouseCreditReason $reason): WastageReason
    {
        return match ($reason) {
            WarehouseCreditReason::Spoilage => WastageReason::Rotten,
            WarehouseCreditReason::DamageWastage => WastageReason::SortingDamage,
            WarehouseCreditReason::Shortage => WastageReason::Shrinkage,
            default => WastageReason::Unsold,
        };
    }
}
