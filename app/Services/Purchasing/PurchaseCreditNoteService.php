<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Enums\Purchasing\PurchaseCreditStatus;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseCreditNote;
use App\Models\PurchaseCreditNoteItem;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Models\Warehouse;
use App\Services\Finance\JournalService;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseCreditNoteService
{
    public function __construct(
        private readonly StockLedgerService $stockLedgerService,
        private readonly JournalService $journalService,
    ) {}

    /**
     * Create and record a new Purchase Credit Note.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function create(array $payload, User $actor): PurchaseCreditNote
    {
        return DB::transaction(function () use ($payload, $actor): PurchaseCreditNote {
            // 1. Validate Supplier
            $supplierId = (int) ($payload['supplier_id'] ?? 0);
            $supplier = Supplier::query()->whereKey($supplierId)->lockForUpdate()->first();
            if (! $supplier instanceof Supplier) {
                throw ValidationException::withMessages(['supplier_id' => 'Valid supplier is required.']);
            }

            // 2. Validate linked PurchaseInvoice (optional)
            $invoice = null;
            $purchaseInvoiceId = ! empty($payload['purchase_invoice_id']) ? (int) $payload['purchase_invoice_id'] : null;
            if ($purchaseInvoiceId !== null) {
                $invoice = PurchaseInvoice::query()->whereKey($purchaseInvoiceId)->lockForUpdate()->first();
                if (! $invoice instanceof PurchaseInvoice) {
                    throw ValidationException::withMessages(['purchase_invoice_id' => 'Selected purchase invoice not found.']);
                }

                if ((int) $invoice->supplier_id !== $supplier->id) {
                    throw ValidationException::withMessages(['purchase_invoice_id' => 'Purchase invoice does not belong to the selected supplier.']);
                }
            }

            // 3. Resolve Enums
            $reason = $payload['reason'] instanceof PurchaseCreditReason
                ? $payload['reason']
                : (PurchaseCreditReason::tryFrom((string) $payload['reason']) ?? PurchaseCreditReason::Return);

            $moneyEffect = $payload['money_effect'] instanceof PurchaseCreditMoneyEffect
                ? $payload['money_effect']
                : (PurchaseCreditMoneyEffect::tryFrom((string) $payload['money_effect']) ?? PurchaseCreditMoneyEffect::VendorCredit);

            $inventoryAction = $payload['inventory_action'] instanceof PurchaseCreditInventoryAction
                ? $payload['inventory_action']
                : (PurchaseCreditInventoryAction::tryFrom((string) $payload['inventory_action']) ?? PurchaseCreditInventoryAction::Out);

            // 4. Validate Company Account for Money In / Money Out
            $companyAccount = null;
            $companyAccountId = ! empty($payload['company_account_id']) ? (int) $payload['company_account_id'] : null;
            if (in_array($moneyEffect, [PurchaseCreditMoneyEffect::MoneyIn, PurchaseCreditMoneyEffect::MoneyOut], true)) {
                if ($companyAccountId === null) {
                    throw ValidationException::withMessages(['company_account_id' => 'Company account is required for money in/out refunds.']);
                }

                $companyAccount = CompanyAccount::query()->whereKey($companyAccountId)->lockForUpdate()->first();
                if (! $companyAccount instanceof CompanyAccount) {
                    throw ValidationException::withMessages(['company_account_id' => 'Selected company account not found.']);
                }
            }

            // 5. Validate Warehouse for Inventory Actions
            $warehouseId = ! empty($payload['warehouse_id']) ? (int) $payload['warehouse_id'] : null;
            if (in_array($inventoryAction, [PurchaseCreditInventoryAction::Out, PurchaseCreditInventoryAction::In], true) && $warehouseId === null) {
                throw ValidationException::withMessages(['warehouse_id' => 'Warehouse is required for inventory movements.']);
            }

            if ($warehouseId !== null) {
                $warehouseExists = Warehouse::query()->whereKey($warehouseId)->exists();
                if (! $warehouseExists) {
                    throw ValidationException::withMessages(['warehouse_id' => 'Selected warehouse not found.']);
                }
            }

            // 6. Validate Line Items
            $itemsData = $payload['items'] ?? [];
            if (! is_array($itemsData) || empty($itemsData)) {
                throw ValidationException::withMessages(['items' => 'At least one line item is required for a purchase credit note.']);
            }

            $creditDate = ! empty($payload['credit_date'])
                ? Carbon::parse((string) $payload['credit_date'])
                : today();

            $deductStockForDamage = (bool) ($payload['deduct_stock_for_damage'] ?? true);
            $allowNegativeStock = (bool) ($payload['allow_negative_stock'] ?? false);
            $creditNoteNumber = PurchaseCreditNote::generateCreditNoteNumber($creditDate);

            // Compute gross total
            $totalAmount = 0.0;
            $sanitizedItems = [];
            $allowedProductIds = $supplier->relatedProductIds($invoice?->id);

            foreach ($itemsData as $index => $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $product = Product::find($productId);
                if (! $product) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => "Product at line {$index} is invalid."]);
                }

                if (! in_array($productId, $allowedProductIds, true)) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => "Product {$product->name} is not associated with this vendor."]);
                }

                $qty = round((float) ($item['quantity'] ?? 0.0), 3);
                if ($qty <= 0.0) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => "Quantity for {$product->name} must be greater than zero."]);
                }

                $unitPrice = round((float) ($item['unit_price'] ?? 0.0), 2);
                if ($unitPrice < 0.0) {
                    throw ValidationException::withMessages(["items.{$index}.unit_price" => "Unit price for {$product->name} cannot be negative."]);
                }

                $lineTotal = round($qty * $unitPrice, 2);
                $totalAmount = round($totalAmount + $lineTotal, 2);

                $grade = $item['grade'] ?? $item['product_grade'] ?? 'A';
                $resolvedGrade = $grade instanceof ProductGrade ? $grade : (ProductGrade::tryResolve((string) $grade) ?? ProductGrade::GradeA);

                $itemInventoryAction = ! empty($item['inventory_action'])
                    ? ($item['inventory_action'] instanceof PurchaseCreditInventoryAction
                        ? $item['inventory_action']
                        : (PurchaseCreditInventoryAction::tryFrom((string) $item['inventory_action']) ?? $inventoryAction))
                    : $inventoryAction;

                $sanitizedItems[] = [
                    'product_id' => $productId,
                    'product_name' => $product->name,
                    'purchaser_cart_item_id' => ! empty($item['purchaser_cart_item_id']) ? (int) $item['purchaser_cart_item_id'] : null,
                    'grade' => $resolvedGrade,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'inventory_action' => $itemInventoryAction,
                    'wastage_reason' => $item['wastage_reason'] ?? WastageReason::Unsold,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            if ($totalAmount <= 0.0) {
                throw ValidationException::withMessages(['total_amount' => 'Total credit note amount must be greater than zero.']);
            }

            // Check stock availability if negative stock is not explicitly approved
            if (! $allowNegativeStock && $warehouseId !== null) {
                $stockCheck = $this->checkStockAvailability(
                    warehouseId: $warehouseId,
                    items: $sanitizedItems,
                    inventoryAction: $inventoryAction,
                    deductStockForDamage: $deductStockForDamage,
                );

                if ($stockCheck['has_shortage']) {
                    if (function_exists('session') && request()->hasSession()) {
                        session()->flash('negative_stock_shortages', $stockCheck['shortages']);
                    }

                    throw ValidationException::withMessages([
                        'allow_negative_stock' => 'Requested credit quantities exceed available warehouse stock. Confirmation required to allow negative stock.',
                    ]);
                }
            }

            // 7. Calculate Invoice Allocation and Vendor Advance
            $appliedToInvoice = 0.0;
            if ($moneyEffect === PurchaseCreditMoneyEffect::VendorCredit && $invoice instanceof PurchaseInvoice) {
                $outstanding = (float) $invoice->remainingBalance();
                $appliedToInvoice = min($totalAmount, max(0.0, $outstanding));
            }

            $excessAdvance = round(max(0.0, $totalAmount - $appliedToInvoice), 2);

            // 8. Create PurchaseCreditNote Record
            /** @var PurchaseCreditNote $creditNote */
            $creditNote = PurchaseCreditNote::query()->create([
                'public_uuid' => (string) Str::uuid(),
                'credit_note_number' => $creditNoteNumber,
                'supplier_id' => $supplier->id,
                'purchase_invoice_id' => $invoice?->id,
                'credit_date' => $creditDate->toDateString(),
                'reason' => $reason,
                'money_effect' => $moneyEffect,
                'inventory_action' => $inventoryAction,
                'status' => PurchaseCreditStatus::Active,
                'company_account_id' => $companyAccountId,
                'warehouse_id' => $warehouseId,
                'wastage_reason' => $reason === PurchaseCreditReason::DamageWastage ? WastageReason::TransitDamage->value : null,
                'deduct_stock_for_damage' => $deductStockForDamage,
                'allowed_negative_stock' => $allowNegativeStock,
                'total_amount' => $totalAmount,
                'applied_to_invoice_amount' => $appliedToInvoice,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            // 9. Process Inventory Actions & Create Line Items
            $inventoryCostTotal = 0.0;

            foreach ($sanitizedItems as $itemRow) {
                $primaryMovementId = null;
                $wastageEntryId = null;
                $lineInvAction = $itemRow['inventory_action'];
                $itemNotes = "Purchase Credit Note — PCN: {$creditNoteNumber}; NoteId: {$creditNote->id};";

                if ($lineInvAction === PurchaseCreditInventoryAction::Out && $warehouseId !== null) {
                    $movements = $this->stockLedgerService->recordPurchaseCreditOut(
                        productId: $itemRow['product_id'],
                        warehouseId: $warehouseId,
                        grade: $itemRow['grade'],
                        quantity: $itemRow['quantity'],
                        userId: $actor->id,
                        notes: $itemNotes,
                        purchaseInvoiceId: $invoice?->id,
                        allowNegativeStock: $allowNegativeStock,
                        fallbackUnitCost: $itemRow['unit_price'],
                    );

                    $primaryMovementId = $movements->first()?->id;
                    $invCostForLine = $movements->sum(fn ($m) => (float) $m->quantity * (float) $m->cost_per_unit);
                    $inventoryCostTotal = round($inventoryCostTotal + $invCostForLine, 2);
                } elseif ($lineInvAction === PurchaseCreditInventoryAction::In && $warehouseId !== null) {
                    $movement = $this->stockLedgerService->recordPurchaseCreditIn(
                        productId: $itemRow['product_id'],
                        warehouseId: $warehouseId,
                        grade: $itemRow['grade'],
                        quantity: $itemRow['quantity'],
                        unitPrice: $itemRow['unit_price'],
                        userId: $actor->id,
                        notes: $itemNotes,
                    );

                    $primaryMovementId = $movement->id;
                    $inventoryCostTotal = round($inventoryCostTotal + ($itemRow['quantity'] * $itemRow['unit_price']), 2);
                } elseif ($lineInvAction === PurchaseCreditInventoryAction::Damage) {
                    $damageResult = $this->stockLedgerService->recordPurchaseCreditDamage(
                        productId: $itemRow['product_id'],
                        warehouseId: $warehouseId,
                        grade: $itemRow['grade'],
                        quantity: $itemRow['quantity'],
                        costPerKg: $itemRow['unit_price'],
                        userId: $actor->id,
                        reason: $itemRow['wastage_reason'] instanceof WastageReason ? $itemRow['wastage_reason'] : WastageReason::Unsold,
                        notes: $itemNotes,
                        deductStock: $deductStockForDamage && $warehouseId !== null,
                        wastageDate: $creditDate->toDateString(),
                        purchaseInvoiceId: $invoice?->id,
                        allowNegativeStock: $allowNegativeStock,
                    );

                    $wastageEntryId = $damageResult['wastage']->id;
                    $primaryMovementId = $damageResult['movements']->first()?->id;
                    if ($deductStockForDamage && $damageResult['movements']->isNotEmpty()) {
                        $invCostForLine = $damageResult['movements']->sum(fn ($m) => (float) $m->quantity * (float) $m->cost_per_unit);
                        $inventoryCostTotal = round($inventoryCostTotal + $invCostForLine, 2);
                    }
                }

                PurchaseCreditNoteItem::query()->create([
                    'purchase_credit_note_id' => $creditNote->id,
                    'purchaser_cart_item_id' => $itemRow['purchaser_cart_item_id'],
                    'product_id' => $itemRow['product_id'],
                    'grade' => $itemRow['grade']->value,
                    'quantity' => $itemRow['quantity'],
                    'unit_price' => $itemRow['unit_price'],
                    'line_total' => $itemRow['line_total'],
                    'inventory_action' => $lineInvAction,
                    'stock_movement_id' => $primaryMovementId,
                    'wastage_entry_id' => $wastageEntryId,
                ]);
            }

            // 10. Process Money Effect: VendorAdvance or CompanyAccountStatementEntry
            $vendorAdvance = null;
            if ($moneyEffect === PurchaseCreditMoneyEffect::VendorCredit && $excessAdvance > 0.0) {
                $vendorAdvance = VendorAdvance::query()->create([
                    'supplier_id' => $supplier->id,
                    'source_settlement_id' => null,
                    'amount_original' => $excessAdvance,
                    'amount_remaining' => $excessAdvance,
                    'business_date' => $creditDate->toDateString(),
                    'payment_method' => $payload['payment_method'] ?? 'Vendor Credit',
                    'reference' => $creditNoteNumber,
                    'notes' => "Excess credit note amount from PCN #{$creditNoteNumber}",
                    'status' => 'open',
                    'journal_entry_id' => null,
                    'created_by' => $actor->id,
                ]);

                $creditNote->update(['vendor_advance_id' => $vendorAdvance->id]);
            }

            $statementEntry = null;
            if ($companyAccount instanceof CompanyAccount) {
                $direction = $moneyEffect === PurchaseCreditMoneyEffect::MoneyIn ? 'in' : 'out';
                $statementEntry = CompanyAccountStatementEntry::query()->create([
                    'company_account_id' => $companyAccount->id,
                    'public_uuid' => (string) Str::uuid(),
                    'transaction_date' => $creditDate->toDateString(),
                    'value_date' => $creditDate->toDateString(),
                    'direction' => $direction,
                    'amount' => $totalAmount,
                    'reference' => $creditNoteNumber,
                    'narration' => "Purchase Credit Note {$creditNoteNumber} from {$supplier->name}",
                    'source' => 'purchase_credit_note',
                    'source_type' => PurchaseCreditNote::class,
                    'source_id' => $creditNote->id,
                    'counterpart_type' => Supplier::class,
                    'counterpart_id' => $supplier->id,
                    'status' => 'finalized',
                    'is_finalized' => true,
                    'finalized_at' => now(),
                    'matched_amount' => $totalAmount,
                    'notes' => $payload['notes'] ?? null,
                    'imported_by' => $actor->id,
                ]);

                if ($direction === 'in') {
                    $companyAccount->increment('current_balance', $totalAmount);
                } else {
                    $companyAccount->decrement('current_balance', $totalAmount);
                }
            }

            // 11. Record Balanced Double-Entry Journal Entry
            $journalEntry = $this->journalService->recordPurchaseCreditNote(
                creditNote: $creditNote,
                inventoryCostTotal: $inventoryCostTotal,
                actor: $actor,
            );

            $creditNote->update(['journal_entry_id' => $journalEntry->id]);

            if ($vendorAdvance instanceof VendorAdvance) {
                $vendorAdvance->update(['journal_entry_id' => $journalEntry->id]);
            }

            if ($statementEntry instanceof CompanyAccountStatementEntry) {
                $statementEntry->update(['journal_entry_id' => $journalEntry->id]);
            }

            if ($allowNegativeStock && function_exists('activity')) {
                activity('purchase_credit_notes')
                    ->performedOn($creditNote)
                    ->causedBy($actor)
                    ->withProperties([
                        'credit_note_number' => $creditNoteNumber,
                        'supplier_id' => $supplier->id,
                        'warehouse_id' => $warehouseId,
                        'allowed_negative_stock' => true,
                    ])
                    ->log("Purchase Credit Note {$creditNoteNumber} created with allowed negative stock.");
            }

            return $creditNote->fresh([
                'items.product',
                'supplier',
                'purchaseInvoice',
                'companyAccount',
                'warehouse',
                'vendorAdvance',
                'journalEntry.transactions.account',
            ]);
        }, attempts: 3);
    }

    /**
     * Void an active Purchase Credit Note and cleanly reverse all stock, financial, and accounting effects.
     *
     * @throws ValidationException
     */
    public function void(PurchaseCreditNote $creditNote, string $reason, User $actor): PurchaseCreditNote
    {
        $trimmedReason = trim($reason);
        if (mb_strlen($trimmedReason) < 3) {
            throw ValidationException::withMessages(['reason' => 'A valid void reason of at least 3 characters is required.']);
        }

        return DB::transaction(function () use ($creditNote, $trimmedReason, $actor): PurchaseCreditNote {
            /** @var PurchaseCreditNote $lockedNote */
            $lockedNote = PurchaseCreditNote::query()
                ->whereKey($creditNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedNote->isVoided()) {
                throw ValidationException::withMessages(['status' => 'This purchase credit note is already voided.']);
            }

            // 1. Verify Vendor Advance has not been utilized
            if ($lockedNote->vendor_advance_id !== null) {
                $advance = VendorAdvance::query()
                    ->whereKey($lockedNote->vendor_advance_id)
                    ->lockForUpdate()
                    ->first();

                if ($advance instanceof VendorAdvance) {
                    if ((float) $advance->amount_remaining < ((float) $advance->amount_original - 0.009)) {
                        throw ValidationException::withMessages([
                            'status' => 'Cannot void this credit note because the associated vendor advance has already been utilized in settlement(s). Please reverse the settlement allocations first.',
                        ]);
                    }

                    $advance->delete();
                }
            }

            // 2. Reverse Company Account Statement Entry & Balance
            if ($lockedNote->company_account_id !== null) {
                $statements = CompanyAccountStatementEntry::query()
                    ->where('source_type', PurchaseCreditNote::class)
                    ->where('source_id', $lockedNote->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($statements as $stmt) {
                    $companyAccount = CompanyAccount::query()
                        ->whereKey($stmt->company_account_id)
                        ->lockForUpdate()
                        ->first();

                    if ($lockedNote->journal_entry_id === null && $companyAccount instanceof CompanyAccount) {
                        if (in_array($stmt->direction, ['in', 'credit'], true)) {
                            $companyAccount->decrement('current_balance', (float) $stmt->amount);
                        } elseif (in_array($stmt->direction, ['out', 'debit'], true)) {
                            $companyAccount->increment('current_balance', (float) $stmt->amount);
                        }
                    }

                    $stmt->update([
                        'status' => 'reversed',
                        'is_finalized' => false,
                        'finalized_at' => null,
                        'matched_amount' => 0,
                        'notes' => trim(($stmt->notes ?? '')." [Reversed: PCN #{$lockedNote->credit_note_number} voided by {$actor->name}: {$trimmedReason}]"),
                    ]);
                }
            }

            // 3. Reverse Inventory Movements & Wastage Entries
            $this->stockLedgerService->reversePurchaseCreditNoteMovements(
                creditNote: $lockedNote,
                userId: $actor->id,
                reason: $trimmedReason,
            );

            // 4. Reverse Journal Entry
            if ($lockedNote->journal_entry_id !== null) {
                $journalEntry = JournalEntry::find($lockedNote->journal_entry_id);
                if ($journalEntry instanceof JournalEntry) {
                    $this->journalService->correctJournalEntry(
                        journalEntry: $journalEntry,
                        data: [
                            'reason' => "Voided Purchase Credit Note #{$lockedNote->credit_note_number}: {$trimmedReason}",
                            'amount' => 0,
                            'confirm_shortfall' => true,
                        ],
                        actor: $actor,
                    );
                }
            }

            // 5. Update Status to Voided
            $lockedNote->update([
                'status' => PurchaseCreditStatus::Voided,
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $trimmedReason,
            ]);

            return $lockedNote->fresh([
                'items.product',
                'supplier',
                'purchaseInvoice',
                'companyAccount',
                'warehouse',
                'journalEntry.transactions.account',
            ]);
        }, attempts: 3);
    }

    /**
     * Get Purchase Credit Notes for a Supplier, optionally filtered by date range.
     *
     * @return Collection<int, PurchaseCreditNote>
     */
    public function getForSupplier(
        Supplier|int $supplier,
        ?string $startDate = null,
        ?string $endDate = null,
    ): Collection {
        $supplierId = $supplier instanceof Supplier ? $supplier->id : $supplier;

        return PurchaseCreditNote::query()
            ->with(['items.product', 'purchaseInvoice', 'companyAccount', 'warehouse', 'createdBy'])
            ->where('supplier_id', $supplierId)
            ->when($startDate !== null && $endDate !== null, function (Builder $q) use ($startDate, $endDate): void {
                $q->forPeriod($startDate, $endDate);
            })
            ->orderByDesc('credit_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Get Purchase Credit Summary KPIs for a Supplier.
     *
     * @return array{
     *     total_credit_notes_count: int,
     *     total_credit_amount: float,
     *     applied_to_invoices_amount: float,
     *     remaining_advance_amount: float,
     *     voided_count: int
     * }
     */
    public function getSummaryForSupplier(
        Supplier|int $supplier,
        ?string $startDate = null,
        ?string $endDate = null,
    ): array {
        $supplierId = $supplier instanceof Supplier ? $supplier->id : $supplier;

        $baseQuery = PurchaseCreditNote::query()
            ->where('supplier_id', $supplierId)
            ->when($startDate !== null && $endDate !== null, function (Builder $q) use ($startDate, $endDate): void {
                $q->forPeriod($startDate, $endDate);
            });

        $activeQuery = (clone $baseQuery)->active();

        $totalCreditAmount = round((float) $activeQuery->sum('total_amount'), 2);
        $appliedToInvoicesAmount = round((float) $activeQuery->sum('applied_to_invoice_amount'), 2);
        $remainingAdvanceAmount = round(max(0.0, $totalCreditAmount - $appliedToInvoicesAmount), 2);
        $totalCount = (clone $baseQuery)->active()->count();
        $voidedCount = (clone $baseQuery)->where('status', PurchaseCreditStatus::Voided->value)->count();

        return [
            'total_credit_notes_count' => $totalCount,
            'total_credit_amount' => $totalCreditAmount,
            'applied_to_invoices_amount' => $appliedToInvoicesAmount,
            'remaining_advance_amount' => $remainingAdvanceAmount,
            'voided_count' => $voidedCount,
        ];
    }

    /**
     * Check warehouse stock availability for the given credit note items.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{has_shortage: bool, shortages: array<int, array<string, mixed>>}
     */
    public function checkStockAvailability(
        int $warehouseId,
        array $items,
        string|PurchaseCreditInventoryAction $inventoryAction = PurchaseCreditInventoryAction::Out,
        bool $deductStockForDamage = true,
    ): array {
        $defaultAction = $inventoryAction instanceof PurchaseCreditInventoryAction
            ? $inventoryAction
            : (PurchaseCreditInventoryAction::tryFrom((string) $inventoryAction) ?? PurchaseCreditInventoryAction::Out);

        $demands = [];
        foreach ($items as $item) {
            $lineAction = ! empty($item['inventory_action'])
                ? ($item['inventory_action'] instanceof PurchaseCreditInventoryAction
                    ? $item['inventory_action']
                    : (PurchaseCreditInventoryAction::tryFrom((string) $item['inventory_action']) ?? $defaultAction))
                : $defaultAction;

            $willDeductStock = $lineAction === PurchaseCreditInventoryAction::Out
                || ($lineAction === PurchaseCreditInventoryAction::Damage && $deductStockForDamage);

            if (! $willDeductStock) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $qty = round((float) ($item['quantity'] ?? 0.0), 3);
            if ($productId <= 0 || $qty <= 0.0) {
                continue;
            }

            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            $grade = $item['grade'] ?? $item['product_grade'] ?? 'A';
            $resolvedGrade = $grade instanceof ProductGrade ? $grade : (ProductGrade::tryResolve((string) $grade) ?? ProductGrade::GradeA);
            $key = "{$productId}_{$resolvedGrade->value}";

            if (! isset($demands[$key])) {
                $demands[$key] = [
                    'product_id' => $productId,
                    'product_name' => $product->name,
                    'grade' => $resolvedGrade->value,
                    'resolved_grade' => $resolvedGrade,
                    'requested_qty' => 0.0,
                ];
            }

            $demands[$key]['requested_qty'] = round($demands[$key]['requested_qty'] + $qty, 3);
        }

        $shortages = [];
        foreach ($demands as $demand) {
            $available = (float) $this->stockLedgerService->availableStockForProduct(
                productId: $demand['product_id'],
                warehouseId: $warehouseId,
                grade: $demand['resolved_grade'],
            );
            $available = round($available, 3);

            if ($demand['requested_qty'] > $available) {
                $shortages[] = [
                    'product_id' => $demand['product_id'],
                    'product_name' => $demand['product_name'],
                    'grade' => $demand['grade'],
                    'available_qty' => $available,
                    'requested_qty' => $demand['requested_qty'],
                    'shortage_qty' => round($demand['requested_qty'] - $available, 3),
                    'resulting_stock' => round($available - $demand['requested_qty'], 3),
                ];
            }
        }

        return [
            'has_shortage' => ! empty($shortages),
            'shortages' => $shortages,
        ];
    }
}
