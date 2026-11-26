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
use App\Models\PurchaseCreditNoteItemMovement;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Models\VendorSettlementAllocation;
use App\Models\VendorSettlementCreditAllocation;
use App\Models\Warehouse;
use App\Services\Finance\JournalService;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
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
        $submissionToken = isset($payload['submission_token']) ? trim((string) $payload['submission_token']) : '';

        try {
            return DB::transaction(function () use ($payload, $actor, $submissionToken): PurchaseCreditNote {
                // 0. Durable idempotency check via submission_token
                if ($submissionToken !== '') {
                    $existingByToken = PurchaseCreditNote::query()
                        ->where('submission_token', $submissionToken)
                        ->lockForUpdate()
                        ->first();
                    if ($existingByToken instanceof PurchaseCreditNote) {
                        $this->assertSubmissionTokenMatchesPayload($existingByToken, $payload);

                        return $existingByToken->fresh([
                            'items.product',
                            'supplier',
                            'purchaseInvoice',
                            'companyAccount',
                            'warehouse',
                            'vendorAdvance',
                            'journalEntry.transactions.account',
                        ]);
                    }
                }

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

                // 4. Validate Company Account & Payment Method for Money In / Money Out
                $companyAccount = null;
                $paymentMethod = null;
                $companyAccountId = ! empty($payload['company_account_id']) ? (int) $payload['company_account_id'] : null;
                if (in_array($moneyEffect, [PurchaseCreditMoneyEffect::MoneyIn, PurchaseCreditMoneyEffect::MoneyOut], true)) {
                    if ($companyAccountId === null) {
                        throw ValidationException::withMessages(['company_account_id' => 'Company account is required for money in/out refunds.']);
                    }

                    $companyAccount = CompanyAccount::query()->whereKey($companyAccountId)->lockForUpdate()->first();
                    if (! $companyAccount instanceof CompanyAccount) {
                        throw ValidationException::withMessages(['company_account_id' => 'Selected company account not found.']);
                    }

                    $derivedMethod = strtolower((string) $companyAccount->account_type) === 'cash' ? 'cash' : 'bank';
                    $submittedMethod = ! empty($payload['payment_method']) ? strtolower(trim((string) $payload['payment_method'])) : null;
                    if ($submittedMethod !== null) {
                        $submittedIsCash = $submittedMethod === 'cash';
                        $accountIsCash = $derivedMethod === 'cash';
                        if ($submittedIsCash !== $accountIsCash) {
                            throw ValidationException::withMessages([
                                'payment_method' => "Payment method '{$payload['payment_method']}' does not match selected {$companyAccount->account_type} company account.",
                            ]);
                        }
                        $paymentMethod = $submittedMethod;
                    } else {
                        $paymentMethod = $derivedMethod;
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

                    ['unit' => $resolvedUnit, 'conversion_to_base' => $conversionToBase] = $this->resolveUnitConversion(
                        $product,
                        isset($item['unit']) ? (string) $item['unit'] : null,
                        $index
                    );
                    $baseQty = round($qty * $conversionToBase, 3);
                    if ($baseQty <= 0.0) {
                        throw ValidationException::withMessages(["items.{$index}.quantity" => "Converted base quantity for {$product->name} must be greater than zero."]);
                    }
                    $baseUnitCost = round($lineTotal / $baseQty, 4);

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
                        'unit' => $resolvedUnit,
                        'conversion_to_base' => $conversionToBase,
                        'base_qty' => $baseQty,
                        'unit_price' => $unitPrice,
                        'base_unit_cost' => $baseUnitCost,
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

                // 7. Server-controlled accounting version (V1 legacy vs V2 independent) & Invoice Allocation
                $accountingVersion = config('greenleaf.purchase_credit_notes_v2_enabled', false) ? 'v2' : 'v1';

                $appliedToInvoice = 0.0;
                if ($accountingVersion === 'v1' && $moneyEffect === PurchaseCreditMoneyEffect::VendorCredit && $invoice instanceof PurchaseInvoice) {
                    $outstanding = (float) $invoice->remainingBalance();
                    $appliedToInvoice = min($totalAmount, max(0.0, $outstanding));
                }

                $excessAdvance = round(max(0.0, $totalAmount - $appliedToInvoice), 2);
                $vendorCreditNoteRef = isset($payload['vendor_credit_note_ref']) ? trim((string) $payload['vendor_credit_note_ref']) : null;
                if ($vendorCreditNoteRef === '') {
                    $vendorCreditNoteRef = null;
                }

                // 8. Create PurchaseCreditNote Record
                /** @var PurchaseCreditNote $creditNote */
                $creditNote = PurchaseCreditNote::query()->create([
                    'public_uuid' => (string) Str::uuid(),
                    'submission_token' => $submissionToken !== '' ? $submissionToken : null,
                    'credit_note_number' => $creditNoteNumber,
                    'vendor_credit_note_ref' => $vendorCreditNoteRef,
                    'supplier_id' => $supplier->id,
                    'purchase_invoice_id' => $invoice?->id,
                    'credit_date' => $creditDate->toDateString(),
                    'reason' => $reason,
                    'money_effect' => $moneyEffect,
                    'inventory_action' => $inventoryAction,
                    'status' => PurchaseCreditStatus::Active,
                    'accounting_version' => $accountingVersion,
                    'company_account_id' => $companyAccountId,
                    'payment_method' => $paymentMethod,
                    'warehouse_id' => $warehouseId,
                    'wastage_reason' => $reason === PurchaseCreditReason::DamageWastage ? WastageReason::TransitDamage->value : null,
                    'deduct_stock_for_damage' => $deductStockForDamage,
                    'allowed_negative_stock' => $allowNegativeStock,
                    'total_amount' => $totalAmount,
                    'applied_to_invoice_amount' => $appliedToInvoice,
                    'utilized_amount' => 0.00,
                    'notes' => $payload['notes'] ?? null,
                    'created_by' => $actor->id,
                ]);

                // 9. Process Inventory Actions & Create Line Items
                $inventoryCostTotal = 0.0;

                foreach ($sanitizedItems as $itemRow) {
                    $primaryMovementId = null;
                    $wastageEntryId = null;
                    $createdMovements = collect();
                    $lineInvAction = $itemRow['inventory_action'];
                    $itemNotes = "Purchase Credit Note — PCN: {$creditNoteNumber}; NoteId: {$creditNote->id};";

                    if ($lineInvAction === PurchaseCreditInventoryAction::Out && $warehouseId !== null) {
                        $movements = $this->stockLedgerService->recordPurchaseCreditOut(
                            productId: $itemRow['product_id'],
                            warehouseId: $warehouseId,
                            grade: $itemRow['grade'],
                            quantity: $itemRow['base_qty'],
                            userId: $actor->id,
                            notes: $itemNotes,
                            purchaseInvoiceId: $invoice?->id,
                            allowNegativeStock: $allowNegativeStock,
                            fallbackUnitCost: $itemRow['base_unit_cost'],
                        );

                        $createdMovements = $movements;
                        $primaryMovementId = $movements->first()?->id;
                        $invCostForLine = $movements->sum(fn ($m) => (float) $m->quantity * (float) $m->cost_per_unit);
                        $inventoryCostTotal = round($inventoryCostTotal + $invCostForLine, 2);
                    } elseif ($lineInvAction === PurchaseCreditInventoryAction::In && $warehouseId !== null) {
                        $movement = $this->stockLedgerService->recordPurchaseCreditIn(
                            productId: $itemRow['product_id'],
                            warehouseId: $warehouseId,
                            grade: $itemRow['grade'],
                            quantity: $itemRow['base_qty'],
                            unitPrice: $itemRow['base_unit_cost'],
                            userId: $actor->id,
                            notes: $itemNotes,
                        );

                        $createdMovements = collect([$movement]);
                        $primaryMovementId = $movement->id;
                        $inventoryCostTotal = round($inventoryCostTotal + $itemRow['line_total'], 2);
                    } elseif ($lineInvAction === PurchaseCreditInventoryAction::Damage) {
                        $damageResult = $this->stockLedgerService->recordPurchaseCreditDamage(
                            productId: $itemRow['product_id'],
                            warehouseId: $warehouseId,
                            grade: $itemRow['grade'],
                            quantity: $itemRow['base_qty'],
                            costPerKg: $itemRow['base_unit_cost'],
                            userId: $actor->id,
                            reason: $itemRow['wastage_reason'] instanceof WastageReason ? $itemRow['wastage_reason'] : WastageReason::Unsold,
                            notes: $itemNotes,
                            deductStock: $deductStockForDamage && $warehouseId !== null,
                            wastageDate: $creditDate->toDateString(),
                            purchaseInvoiceId: $invoice?->id,
                            allowNegativeStock: $allowNegativeStock,
                        );

                        $wastageEntryId = $damageResult['wastage']->id;
                        $createdMovements = $damageResult['movements'];
                        $primaryMovementId = $createdMovements->first()?->id;
                        if ($deductStockForDamage && $createdMovements->isNotEmpty()) {
                            $invCostForLine = $createdMovements->sum(fn ($m) => (float) $m->quantity * (float) $m->cost_per_unit);
                            $inventoryCostTotal = round($inventoryCostTotal + $invCostForLine, 2);
                        }
                    }

                    $creditNoteItem = PurchaseCreditNoteItem::query()->create([
                        'purchase_credit_note_id' => $creditNote->id,
                        'purchaser_cart_item_id' => $itemRow['purchaser_cart_item_id'],
                        'product_id' => $itemRow['product_id'],
                        'grade' => $itemRow['grade']->value,
                        'quantity' => $itemRow['quantity'],
                        'unit' => $itemRow['unit'],
                        'conversion_to_base' => $itemRow['conversion_to_base'],
                        'base_qty' => $itemRow['base_qty'],
                        'unit_price' => $itemRow['unit_price'],
                        'line_total' => $itemRow['line_total'],
                        'inventory_action' => $lineInvAction,
                        'stock_movement_id' => $primaryMovementId,
                        'wastage_entry_id' => $wastageEntryId,
                    ]);

                    foreach ($createdMovements as $movement) {
                        PurchaseCreditNoteItemMovement::query()->create([
                            'purchase_credit_note_item_id' => $creditNoteItem->id,
                            'stock_batch_id' => (int) $movement->batch_id,
                            'stock_movement_id' => (int) $movement->id,
                            'quantity' => round((float) $movement->quantity, 3),
                            'cost_per_unit' => round((float) $movement->cost_per_unit, 4),
                        ]);
                    }
                }

                // 10. Process Money Effect: VendorAdvance (V1 legacy only) or CompanyAccountStatementEntry
                $vendorAdvance = null;
                if ($accountingVersion === 'v1' && $moneyEffect === PurchaseCreditMoneyEffect::VendorCredit && $excessAdvance > 0.0) {
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

                if ($invoice instanceof PurchaseInvoice && $appliedToInvoice > 0.0) {
                    $invoice->refreshPaymentStatus();
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
        } catch (QueryException $e) {
            if ($submissionToken !== '' && ($e->getCode() === '23000' || str_contains($e->getMessage(), 'submission_token'))) {
                $existingByToken = PurchaseCreditNote::query()->where('submission_token', $submissionToken)->first();
                if ($existingByToken instanceof PurchaseCreditNote) {
                    $this->assertSubmissionTokenMatchesPayload($existingByToken, $payload);

                    return $existingByToken->fresh([
                        'items.product',
                        'supplier',
                        'purchaseInvoice',
                        'companyAccount',
                        'warehouse',
                        'vendorAdvance',
                        'journalEntry.transactions.account',
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    private function assertSubmissionTokenMatchesPayload(PurchaseCreditNote $existing, array $payload): void
    {
        $supplierId = (int) ($payload['supplier_id'] ?? 0);
        $itemsSum = round((float) collect($payload['items'] ?? [])->sum(
            fn (array $row): float => round((float) ($row['quantity'] ?? 0) * (float) ($row['unit_price'] ?? 0), 2)
        ), 2);
        $moneyEffect = $payload['money_effect'] instanceof PurchaseCreditMoneyEffect
            ? $payload['money_effect']->value
            : (string) ($payload['money_effect'] ?? PurchaseCreditMoneyEffect::VendorCredit->value);

        if (
            (int) $existing->supplier_id !== $supplierId
            || $existing->money_effect->value !== $moneyEffect
            || abs((float) $existing->total_amount - $itemsSum) > 0.02
        ) {
            throw ValidationException::withMessages([
                'submission_token' => 'This submission token has already been used for a different credit note payload.',
            ]);
        }
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

            // 0. Guard against voiding when utilized (V2) or linked invoice has subsequent settlements (V1)
            $linkedInvoice = null;
            if ($lockedNote->isV2()) {
                $hasActiveCreditAllocations = VendorSettlementCreditAllocation::query()
                    ->where('purchase_credit_note_id', $lockedNote->id)
                    ->where('is_reversed', false)
                    ->exists();

                if ($hasActiveCreditAllocations || (float) $lockedNote->utilized_amount > 0.009) {
                    throw ValidationException::withMessages([
                        'status' => 'Cannot void this purchase credit note because it has active settlement allocations. Please reverse the settlement(s) first.',
                    ]);
                }
            } elseif ($lockedNote->purchase_invoice_id !== null) {
                $linkedInvoice = PurchaseInvoice::query()
                    ->whereKey($lockedNote->purchase_invoice_id)
                    ->lockForUpdate()
                    ->first();

                if ($linkedInvoice instanceof PurchaseInvoice && (float) $lockedNote->applied_to_invoice_amount > 0.0) {
                    $hasSubsequentSettlement = VendorSettlementAllocation::query()
                        ->where('purchase_invoice_id', $linkedInvoice->id)
                        ->where('is_reversed', false)
                        ->where('created_at', '>=', $lockedNote->created_at)
                        ->exists();

                    if ($hasSubsequentSettlement) {
                        throw ValidationException::withMessages([
                            'status' => 'Cannot void this credit note because the linked purchase invoice has subsequent settlement(s). Please reverse the settlement(s) first.',
                        ]);
                    }
                }
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

            // 5. Update Status to Voided & refresh linked invoice payment_status
            $lockedNote->update([
                'status' => PurchaseCreditStatus::Voided,
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $trimmedReason,
            ]);

            if ($linkedInvoice instanceof PurchaseInvoice && (float) $lockedNote->applied_to_invoice_amount > 0.0) {
                $linkedInvoice->refreshPaymentStatus();
            }

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
        foreach ($items as $index => $item) {
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

            ['conversion_to_base' => $conversionToBase] = $this->resolveUnitConversion(
                $product,
                isset($item['unit']) ? (string) $item['unit'] : null,
                $index
            );
            $baseQty = round($qty * $conversionToBase, 3);

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

            $demands[$key]['requested_qty'] = round($demands[$key]['requested_qty'] + $baseQty, 3);
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

    /**
     * Compute a vendor's current all-time financial position using bounded aggregate queries.
     *
     * Note: Available purchase credit note advance balance is a subset of total open vendor advances
     * and must not be added to it.
     *
     * @return array{
     *     total_purchases: float,
     *     total_settled: float,
     *     outstanding_balance: float,
     *     available_credit_note_balance: float,
     *     total_open_vendor_advances: float,
     *     pending_bills_count: int,
     *     total_active_credit_notes: int,
     *     total_active_credit_note_amount: float
     * }
     */
    public function getVendorFinancialSummary(Supplier $supplier): array
    {
        $allocSub = DB::table('vendor_settlement_allocations')
            ->selectRaw('purchase_invoice_id, COALESCE(SUM(total_settled), 0) as settled_sum, COUNT(*) as alloc_count')
            ->where('is_reversed', false)
            ->groupBy('purchase_invoice_id');

        $pcnAppliedSub = DB::table('purchase_credit_notes')
            ->selectRaw('purchase_invoice_id, COALESCE(SUM(applied_to_invoice_amount), 0) as applied_sum')
            ->where('status', PurchaseCreditStatus::Active->value)
            ->whereNull('deleted_at')
            ->whereNotNull('purchase_invoice_id')
            ->groupBy('purchase_invoice_id');

        $netExpr = 'CASE WHEN (COALESCE(pi.amount, 0) - COALESCE(pi.discount_amount, 0)) > 0 THEN (COALESCE(pi.amount, 0) - COALESCE(pi.discount_amount, 0)) ELSE 0 END';
        $settledExpr = 'COALESCE(alloc.settled_sum, 0) + CASE WHEN COALESCE(alloc.alloc_count, 0) = 0 AND COALESCE(pi.paid_amount, 0) > 0 THEN COALESCE(pi.paid_amount, 0) ELSE 0 END';
        $appliedExpr = 'COALESCE(pcn.applied_sum, 0)';
        $outstandingExpr = "CASE WHEN ({$netExpr} - ({$settledExpr}) - {$appliedExpr}) > 0 THEN ({$netExpr} - ({$settledExpr}) - {$appliedExpr}) ELSE 0 END";

        $invoiceTotals = DB::table('purchase_invoices as pi')
            ->leftJoinSub($allocSub, 'alloc', fn ($join) => $join->on('alloc.purchase_invoice_id', '=', 'pi.id'))
            ->leftJoinSub($pcnAppliedSub, 'pcn', fn ($join) => $join->on('pcn.purchase_invoice_id', '=', 'pi.id'))
            ->where('pi.supplier_id', $supplier->id)
            ->where('pi.status', '!=', 'cancelled')
            ->whereNull('pi.deleted_at')
            ->selectRaw(
                "COALESCE(SUM({$netExpr}), 0) as total_purchases, ".
                "COALESCE(SUM({$settledExpr}), 0) as total_settled, ".
                "COALESCE(SUM({$outstandingExpr}), 0) as outstanding_balance, ".
                "COALESCE(SUM(CASE WHEN ({$outstandingExpr}) > 0.009 THEN 1 ELSE 0 END), 0) as pending_bills_count"
            )
            ->first();

        $advanceTotals = DB::table('vendor_advances as va')
            ->where('va.supplier_id', $supplier->id)
            ->where('va.amount_remaining', '>', 0)
            ->selectRaw(
                'COALESCE(SUM(va.amount_remaining), 0) as total_open_vendor_advances, '.
                'COALESCE(SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM purchase_credit_notes pcn_ref WHERE pcn_ref.vendor_advance_id = va.id) THEN va.amount_remaining ELSE 0 END), 0) as actual_cash_vendor_advances'
            )
            ->first();

        $totalOpenVendorAdvances = round((float) ($advanceTotals->total_open_vendor_advances ?? 0), 2);
        $actualCashVendorAdvances = round((float) ($advanceTotals->actual_cash_vendor_advances ?? 0), 2);

        $creditNoteTotals = DB::table('purchase_credit_notes as pcn')
            ->leftJoin('vendor_advances as va', 'va.id', '=', 'pcn.vendor_advance_id')
            ->where('pcn.supplier_id', $supplier->id)
            ->where('pcn.status', PurchaseCreditStatus::Active->value)
            ->whereNull('pcn.deleted_at')
            ->selectRaw(
                'COUNT(*) as total_active_credit_notes, '.
                'COALESCE(SUM(pcn.total_amount), 0) as total_active_credit_note_amount, '.
                "COALESCE(SUM(CASE WHEN pcn.accounting_version = 'v2' AND pcn.money_effect = ? THEN (CASE WHEN pcn.total_amount > pcn.utilized_amount THEN pcn.total_amount - pcn.utilized_amount ELSE 0 END) ELSE 0 END), 0) as available_v2_credit_notes, ".
                "COALESCE(SUM(CASE WHEN pcn.accounting_version != 'v2' AND va.amount_remaining > 0 THEN va.amount_remaining ELSE 0 END), 0) as available_legacy_credit_notes",
                [PurchaseCreditMoneyEffect::VendorCredit->value]
            )
            ->first();

        $availableV2Credits = round((float) ($creditNoteTotals->available_v2_credit_notes ?? 0), 2);
        $availableLegacyCredits = round((float) ($creditNoteTotals->available_legacy_credit_notes ?? 0), 2);
        $availablePurchaseCredits = round($availableV2Credits + $availableLegacyCredits, 2);
        $outstandingBalance = round((float) ($invoiceTotals->outstanding_balance ?? 0), 2);
        $netVendorPayable = round($outstandingBalance - $availablePurchaseCredits - $actualCashVendorAdvances, 2);

        return [
            'total_purchases' => round((float) ($invoiceTotals->total_purchases ?? 0), 2),
            'total_settled' => round((float) ($invoiceTotals->total_settled ?? 0), 2),
            'outstanding_balance' => $outstandingBalance,
            'available_v2_credit_notes' => $availableV2Credits,
            'available_legacy_credit_notes' => $availableLegacyCredits,
            'available_credit_note_balance' => $availablePurchaseCredits,
            'available_purchase_credits' => $availablePurchaseCredits,
            'actual_cash_vendor_advances' => $actualCashVendorAdvances,
            'total_open_vendor_advances' => $totalOpenVendorAdvances,
            'net_vendor_payable' => $netVendorPayable,
            'pending_bills_count' => (int) ($invoiceTotals->pending_bills_count ?? 0),
            'total_active_credit_notes' => (int) ($creditNoteTotals->total_active_credit_notes ?? 0),
            'total_active_credit_note_amount' => round((float) ($creditNoteTotals->total_active_credit_note_amount ?? 0), 2),
        ];
    }

    /**
     * Resolve and validate unit conversion to product base unit.
     *
     * @return array{unit: string, conversion_to_base: float}
     */
    private function resolveUnitConversion(Product $product, ?string $unit, int $index): array
    {
        $baseUnit = trim((string) ($product->unit ?: 'kg'));
        $requestedUnit = $unit !== null ? trim($unit) : $baseUnit;

        if ($requestedUnit === '' || $requestedUnit === $baseUnit) {
            return [
                'unit' => $baseUnit,
                'conversion_to_base' => 1.0,
            ];
        }

        $productUnit = $product->orderUnits()
            ->where('unit', $requestedUnit)
            ->first();

        if (! $productUnit || (float) $productUnit->conversion_to_base <= 0) {
            throw ValidationException::withMessages([
                "items.{$index}.unit" => "Invalid unit '{$requestedUnit}' for {$product->name}.",
            ]);
        }

        return [
            'unit' => $requestedUnit,
            'conversion_to_base' => (float) $productUnit->conversion_to_base,
        ];
    }
}
