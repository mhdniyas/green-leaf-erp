<?php

declare(strict_types=1);

namespace App\Services\ShopInvoices;

use App\Enums\Inventory\ProductGrade;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\User;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopCreditNoteService
{
    public function __construct(
        private readonly ?InvoiceCashbookProjectionService $invoiceCashbookProjectionService = null,
        private readonly ?OwnedShopAccountingService $ownedShopAccountingService = null,
        private readonly ?StockLedgerService $stockLedgerService = null,
    ) {}

    private function cashbookProjectionService(): InvoiceCashbookProjectionService
    {
        return $this->invoiceCashbookProjectionService ?? app(InvoiceCashbookProjectionService::class);
    }

    private function ownedAccountingService(): OwnedShopAccountingService
    {
        return $this->ownedShopAccountingService ?? app(OwnedShopAccountingService::class);
    }

    private function getStockLedgerService(): StockLedgerService
    {
        return $this->stockLedgerService ?? app(StockLedgerService::class);
    }

    /**
     * Approve a Credit Note draft and post its financial ledger adjustment.
     *
     * @throws ValidationException
     */
    public function approve(
        ShopCreditNote $creditNote,
        User|int $admin,
        ?string $approvalNote = null,
        bool $fromDeliveryReview = false,
    ): ShopCreditNote {
        $adminUser = $admin instanceof User ? $admin : User::find($admin);
        $isAdmin = $adminUser && (
            $adminUser->hasRole('admin')
            || $adminUser->hasRole('super_admin')
            || (method_exists($adminUser, 'isMainAdmin') && $adminUser->isMainAdmin())
        );

        $isAuthorizedDeliveryReviewer = $fromDeliveryReview && $adminUser && (
            $isAdmin
            || $adminUser->hasRole('purchase')
            || $adminUser->can('purchasing.order.approve')
        );

        if (! $isAdmin && ! $isAuthorizedDeliveryReviewer) {
            throw ValidationException::withMessages([
                'approved_by' => 'Only authorized administrators can approve credit notes.',
            ]);
        }

        if ($creditNote->isApproved()) {
            throw ValidationException::withMessages([
                'status' => 'Credit note is already approved.',
            ]);
        }

        if ($creditNote->isVoided()) {
            throw ValidationException::withMessages([
                'status' => 'Voided credit notes cannot be approved.',
            ]);
        }

        if (! $creditNote->isDraft()) {
            throw ValidationException::withMessages([
                'status' => "Cannot approve credit note with status '{$creditNote->status->value}'.",
            ]);
        }

        if ($creditNote->inventory_action === ShopCreditNoteInventoryAction::DeductFromStock) {
            throw ValidationException::withMessages([
                'inventory_action' => "Inventory action '{$creditNote->inventory_action->label()}' is not supported in this step.",
            ]);
        }

        $creditNote->loadMissing('items');
        foreach ($creditNote->items as $item) {
            if ($item->inventory_action === ShopCreditNoteInventoryAction::DeductFromStock) {
                throw ValidationException::withMessages([
                    'inventory_action' => "Credit note contains items with action '{$item->inventory_action->label()}', which is not supported in this step.",
                ]);
            }

            if (in_array($item->inventory_action, [ShopCreditNoteInventoryAction::ReturnToStock, ShopCreditNoteInventoryAction::WriteOffWastage], true) && (float) $item->adjusted_quantity <= 0.0001) {
                throw ValidationException::withMessages([
                    'adjusted_quantity' => "Quantity must be greater than zero for item #{$item->id} with action '{$item->inventory_action->label()}'.",
                ]);
            }
        }

        return DB::transaction(function () use ($creditNote, $adminUser, $approvalNote): ShopCreditNote {
            /** @var ShopCreditNote $lockedCreditNote */
            $lockedCreditNote = ShopCreditNote::query()
                ->with(['items', 'invoice.items', 'shop'])
                ->whereKey($creditNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCreditNote->isApproved()) {
                throw ValidationException::withMessages([
                    'status' => 'Credit note is already approved.',
                ]);
            }

            if (! $lockedCreditNote->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => "Cannot approve credit note with status '{$lockedCreditNote->status->value}'.",
                ]);
            }

            $creditNoteDate = $lockedCreditNote->credit_note_date?->toDateString() ?? today()->toDateString();
            $periodClosed = ShopAccountingPeriodClosure::query()
                ->where('shop_id', $lockedCreditNote->shop_id)
                ->whereDate('period_start', '<=', $creditNoteDate)
                ->whereDate('period_end', '>=', $creditNoteDate)
                ->exists();

            if ($periodClosed) {
                throw ValidationException::withMessages([
                    'credit_note_date' => 'Cannot approve credit note in a closed accounting period.',
                ]);
            }

            /** @var ShopInvoice $invoice */
            $invoice = ShopInvoice::query()
                ->with('items')
                ->whereKey($lockedCreditNote->shop_invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Concurrency safe: revalidate cumulative limits for each credit item
            foreach ($lockedCreditNote->items as $index => $creditItem) {
                $invoiceItem = $invoice->items->firstWhere('id', $creditItem->shop_invoice_item_id);
                if (! $invoiceItem) {
                    throw ValidationException::withMessages([
                        "items.{$index}" => "Original invoice item #{$creditItem->shop_invoice_item_id} no longer exists.",
                    ]);
                }

                if ($creditItem->direction === ShopCreditNoteDirection::Credit) {
                    $cumulative = $this->cumulativeAdjustmentsForInvoiceItem($invoiceItem, excludeCreditNoteId: $lockedCreditNote->id);
                    $remainingQty = $cumulative['active']['remaining_creditable_quantity'];
                    $remainingAmt = $cumulative['active']['remaining_creditable_amount'];

                    if ((float) $creditItem->adjusted_quantity > ($remainingQty + 0.0001)) {
                        throw ValidationException::withMessages([
                            "items.{$index}.adjusted_quantity" => "Requested credit quantity ({$creditItem->adjusted_quantity}) for item #{$invoiceItem->id} exceeds current remaining creditable quantity ({$remainingQty}).",
                        ]);
                    }

                    if ((float) $creditItem->line_credit_amount > ($remainingAmt + 0.01)) {
                        throw ValidationException::withMessages([
                            "items.{$index}.line_credit_amount" => "Requested credit amount (₹{$creditItem->line_credit_amount}) for item #{$invoiceItem->id} exceeds current remaining creditable amount (₹{$remainingAmt}).",
                        ]);
                    }
                }
            }

            $noteText = $lockedCreditNote->notes;
            if (filled($approvalNote)) {
                $noteText = filled($noteText)
                    ? trim((string) $noteText)."\nApproval note: ".trim($approvalNote)
                    : 'Approval note: '.trim($approvalNote);
            }

            $lockedCreditNote->forceFill([
                'status' => ShopCreditNoteStatus::Approved,
                'approved_by' => $adminUser->id,
                'approved_at' => now(),
                'notes' => $noteText,
            ])->save();

            // Physical stock return & damage write-off integration
            foreach ($lockedCreditNote->items as $item) {
                if ($item->inventory_action === ShopCreditNoteInventoryAction::ReturnToStock) {
                    $this->getStockLedgerService()->restoreStockForCreditNoteItem($item, $lockedCreditNote, $adminUser->id);
                } elseif ($item->inventory_action === ShopCreditNoteInventoryAction::WriteOffWastage) {
                    $this->getStockLedgerService()->recordCreditNoteDamageItem($item, $lockedCreditNote, $adminUser->id);
                }
            }

            // Financial Posting: sync to cashbook ledger without mutating the original invoice
            $this->cashbookProjectionService()->syncCreditNote($lockedCreditNote, $adminUser->id);

            // Sync owned shop accounting closing balances if applicable
            if ($lockedCreditNote->shop instanceof Shop && $lockedCreditNote->shop->isOwnedAccountingEnabled()) {
                $businessDate = Carbon::parse($lockedCreditNote->credit_note_date?->toDateString() ?? today()->toDateString());
                $this->ownedAccountingService()->syncStoredClosingBalancesFromDate(
                    $lockedCreditNote->shop,
                    $businessDate,
                    $adminUser->id,
                );
            }

            return $lockedCreditNote->fresh(['items.product', 'items.invoiceItem', 'invoice', 'shop']);
        });
    }

    /**
     * Create an independent Credit Note draft referencing an original shop invoice.
     *
     * @param  array{
     *     credit_note_date?: string|null,
     *     reason_type?: string|ShopCreditNoteReason|null,
     *     direction?: string|ShopCreditNoteDirection|null,
     *     inventory_action?: string|ShopCreditNoteInventoryAction|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         shop_invoice_item_id: int,
     *         product_id?: int|null,
     *         product_grade?: string|null,
     *         unit?: string|null,
     *         adjusted_quantity?: float|int|string|null,
     *         adjusted_unit_price?: float|int|string|null,
     *         revised_quantity?: float|int|string|null,
     *         revised_unit_price?: float|int|string|null,
     *         line_credit_amount?: float|int|string|null,
     *         direction?: string|ShopCreditNoteDirection|null,
     *         inventory_action?: string|ShopCreditNoteInventoryAction|null,
     *         notes?: string|null,
     *     }>
     * }  $payload
     *
     * @throws ValidationException
     */
    public function createDraft(
        ShopInvoice $invoice,
        array $payload,
        int $creatorId,
    ): ShopCreditNote {
        $creator = User::find($creatorId);
        $fromDeliveryReview = (bool) ($payload['from_delivery_review'] ?? false)
            || (isset($payload['notes']) && str_starts_with((string) $payload['notes'], 'Delivery review '));

        if ($creator && $creator->hasRole('purchase') && ! $fromDeliveryReview) {
            throw ValidationException::withMessages([
                'creator' => 'Purchasers cannot arbitrarily create credit notes outside of delivery review.',
            ]);
        }

        if (empty($payload['items']) || ! is_array($payload['items'])) {
            throw ValidationException::withMessages([
                'items' => 'At least one adjustment item must be provided.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $payload, $creatorId): ShopCreditNote {
            /** @var ShopInvoice $lockedInvoice */
            $lockedInvoice = ShopInvoice::query()
                ->with(['items.product', 'shop'])
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $date = isset($payload['credit_note_date']) && filled($payload['credit_note_date'])
                ? Carbon::parse((string) $payload['credit_note_date'])->toDateString()
                : ($lockedInvoice->business_date?->toDateString() ?? today()->toDateString());

            $periodClosed = ShopAccountingPeriodClosure::query()
                ->where('shop_id', $lockedInvoice->shop_id)
                ->whereDate('period_start', '<=', $date)
                ->whereDate('period_end', '>=', $date)
                ->exists();

            if ($periodClosed) {
                throw ValidationException::withMessages([
                    'credit_note_date' => 'Cannot create credit note in a closed accounting period.',
                ]);
            }

            $reasonType = match (true) {
                isset($payload['reason_type']) && $payload['reason_type'] instanceof ShopCreditNoteReason => $payload['reason_type'],
                isset($payload['reason_type']) && is_string($payload['reason_type']) => ShopCreditNoteReason::tryFrom($payload['reason_type']) ?? ShopCreditNoteReason::Other,
                default => ShopCreditNoteReason::PriceCorrection,
            };

            $headerInventoryAction = match (true) {
                isset($payload['inventory_action']) && $payload['inventory_action'] instanceof ShopCreditNoteInventoryAction => $payload['inventory_action'],
                isset($payload['inventory_action']) && is_string($payload['inventory_action']) => ShopCreditNoteInventoryAction::tryFrom($payload['inventory_action']) ?? ShopCreditNoteInventoryAction::None,
                default => ShopCreditNoteInventoryAction::None,
            };

            $explicitDirection = match (true) {
                isset($payload['direction']) && $payload['direction'] instanceof ShopCreditNoteDirection => $payload['direction'],
                isset($payload['direction']) && is_string($payload['direction']) => ShopCreditNoteDirection::tryFrom($payload['direction']) ?? throw ValidationException::withMessages(['direction' => 'Invalid credit note direction.']),
                default => null,
            };

            $invoiceItemsById = $lockedInvoice->items->keyBy('id');
            $preparedItems = [];
            $totalCredits = 0.0;
            $totalDebits = 0.0;
            $intraRequestCredits = [];

            foreach ($payload['items'] as $index => $itemPayload) {
                $itemId = (int) ($itemPayload['shop_invoice_item_id'] ?? 0);
                $invoiceItem = $invoiceItemsById->get($itemId);

                if (! $invoiceItem) {
                    throw ValidationException::withMessages([
                        "items.{$index}.shop_invoice_item_id" => "Invoice item #{$itemId} does not belong to invoice {$lockedInvoice->invoice_number}.",
                    ]);
                }

                if (isset($itemPayload['product_id']) && (int) $itemPayload['product_id'] !== (int) $invoiceItem->product_id) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => "Product ID {$itemPayload['product_id']} does not match invoice item product ID {$invoiceItem->product_id}.",
                    ]);
                }

                if (isset($itemPayload['product_grade'])) {
                    $expectedGrade = ProductGrade::tryResolve($invoiceItem->product_grade) ?? ProductGrade::GradeA;
                    $givenGrade = ProductGrade::tryResolve($itemPayload['product_grade']);

                    if ($givenGrade !== $expectedGrade) {
                        throw ValidationException::withMessages([
                            "items.{$index}.product_grade" => "Product grade '{$itemPayload['product_grade']}' does not match invoice item grade '{$invoiceItem->product_grade}'.",
                        ]);
                    }
                }

                if (isset($itemPayload['unit'])) {
                    $normalizedGiven = ProductUnit::normalizeUnit((string) $itemPayload['unit']);
                    $normalizedExpected = ProductUnit::normalizeUnit((string) ($invoiceItem->price_unit ?: $invoiceItem->unit));

                    if ($normalizedGiven !== $normalizedExpected) {
                        throw ValidationException::withMessages([
                            "items.{$index}.unit" => "Unit '{$itemPayload['unit']}' is incompatible with invoice item unit '{$invoiceItem->unit}'.",
                        ]);
                    }
                }

                $origQty = (float) ($invoiceItem->delivered_qty ?? $invoiceItem->approved_qty ?? 0);
                $origPrice = (float) $invoiceItem->unit_price;
                $origTotal = round((float) ($invoiceItem->final_line_total ?? $invoiceItem->line_subtotal ?? ($origQty * $origPrice)), 2);

                $cumulative = $this->cumulativeAdjustmentsForInvoiceItem($invoiceItem);
                $intraQtyCredited = (float) ($intraRequestCredits[$invoiceItem->id]['qty'] ?? 0.0);
                $intraAmtCredited = (float) ($intraRequestCredits[$invoiceItem->id]['amt'] ?? 0.0);

                $currentEffectiveQty = round(max(0.0, $cumulative['active']['effective_quantity'] - $intraQtyCredited), 4);
                $currentEffectiveAmt = round($cumulative['active']['effective_line_total'] - $intraAmtCredited, 2);
                $remainingCreditableQty = round(max(0.0, $cumulative['active']['remaining_creditable_quantity'] - $intraQtyCredited), 4);
                $remainingCreditableAmt = round(max(0.0, $cumulative['active']['remaining_creditable_amount'] - $intraAmtCredited), 2);

                // Revision style input (specifying revised quantity / price)
                if (array_key_exists('revised_quantity', $itemPayload) || array_key_exists('revised_unit_price', $itemPayload)) {
                    $revisedQty = array_key_exists('revised_quantity', $itemPayload) && $itemPayload['revised_quantity'] !== null
                        ? round((float) $itemPayload['revised_quantity'], 4)
                        : $currentEffectiveQty;
                    $revisedPrice = array_key_exists('revised_unit_price', $itemPayload) && $itemPayload['revised_unit_price'] !== null
                        ? round((float) $itemPayload['revised_unit_price'], 2)
                        : $origPrice;

                    if ($revisedQty < 0) {
                        throw ValidationException::withMessages([
                            "items.{$index}.revised_quantity" => 'Revised quantity cannot be negative.',
                        ]);
                    }
                    if ($revisedPrice < 0) {
                        throw ValidationException::withMessages([
                            "items.{$index}.revised_unit_price" => 'Revised price cannot be negative.',
                        ]);
                    }

                    $revisedTotal = round($revisedQty * $revisedPrice, 2);
                    $difference = round($revisedTotal - $currentEffectiveAmt, 2);

                    if (abs($difference) < 0.0001 && abs($revisedQty - $currentEffectiveQty) < 0.0001 && abs($revisedPrice - $origPrice) < 0.001) {
                        throw ValidationException::withMessages([
                            "items.{$index}" => "Invoice item #{$itemId} is already revised to {$currentEffectiveQty} by existing credit notes. No additional adjustment needed.",
                        ]);
                    }

                    if ($difference < 0) {
                        $lineDirection = ShopCreditNoteDirection::Credit;
                        $lineAmount = abs($difference);
                    } else {
                        $lineDirection = ShopCreditNoteDirection::Debit;
                        $lineAmount = $difference;
                    }

                    $adjustedQty = round(abs($currentEffectiveQty - $revisedQty), 4);
                    $adjustedPrice = $revisedPrice > 0 ? $revisedPrice : $origPrice;
                } else {
                    // Direct adjustment specification
                    if (! array_key_exists('adjusted_quantity', $itemPayload) && ! array_key_exists('line_credit_amount', $itemPayload)) {
                        throw ValidationException::withMessages([
                            "items.{$index}" => "Either adjusted_quantity or revised_quantity must be provided for invoice item #{$itemId}.",
                        ]);
                    }

                    $adjustedQty = round((float) ($itemPayload['adjusted_quantity'] ?? 0), 4);
                    $adjustedPrice = array_key_exists('adjusted_unit_price', $itemPayload) && $itemPayload['adjusted_unit_price'] !== null
                        ? round((float) $itemPayload['adjusted_unit_price'], 2)
                        : $origPrice;

                    if ($adjustedQty < 0) {
                        throw ValidationException::withMessages([
                            "items.{$index}.adjusted_quantity" => 'Adjusted quantity cannot be negative.',
                        ]);
                    }
                    if ($adjustedPrice < 0) {
                        throw ValidationException::withMessages([
                            "items.{$index}.adjusted_unit_price" => 'Adjusted price cannot be negative.',
                        ]);
                    }

                    $lineDirection = match (true) {
                        isset($itemPayload['direction']) && $itemPayload['direction'] instanceof ShopCreditNoteDirection => $itemPayload['direction'],
                        isset($itemPayload['direction']) && is_string($itemPayload['direction']) => ShopCreditNoteDirection::tryFrom($itemPayload['direction']) ?? throw ValidationException::withMessages(["items.{$index}.direction" => 'Invalid direction.']),
                        $explicitDirection !== null => $explicitDirection,
                        default => ShopCreditNoteDirection::Credit,
                    };

                    $lineAmount = array_key_exists('line_credit_amount', $itemPayload) && $itemPayload['line_credit_amount'] !== null
                        ? round((float) $itemPayload['line_credit_amount'], 2)
                        : round($adjustedQty * $adjustedPrice, 2);

                    if ($lineAmount <= 0.0 && $adjustedQty <= 0.0) {
                        throw ValidationException::withMessages([
                            "items.{$index}" => "Adjustment amount or quantity must be greater than zero for invoice item #{$itemId}.",
                        ]);
                    }

                    if ($lineDirection === ShopCreditNoteDirection::Credit) {
                        if ($adjustedQty > ($remainingCreditableQty + 0.0001)) {
                            throw ValidationException::withMessages([
                                "items.{$index}.adjusted_quantity" => "Requested credit quantity ({$adjustedQty}) for item #{$itemId} exceeds remaining creditable quantity ({$remainingCreditableQty}). Original: {$origQty}, Already Credited: {$cumulative['active']['net_quantity_credited']}.",
                            ]);
                        }

                        if ($lineAmount > ($remainingCreditableAmt + 0.01)) {
                            throw ValidationException::withMessages([
                                "items.{$index}.line_credit_amount" => 'Requested credit amount (₹'.number_format($lineAmount, 2).") for item #{$itemId} exceeds remaining creditable amount (₹".number_format($remainingCreditableAmt, 2).'). Original: ₹'.number_format($origTotal, 2).', Already Credited: ₹'.number_format($cumulative['active']['net_amount_credited'], 2).'.',
                            ]);
                        }
                    }
                }

                $itemInventoryAction = match (true) {
                    isset($itemPayload['inventory_action']) && $itemPayload['inventory_action'] instanceof ShopCreditNoteInventoryAction => $itemPayload['inventory_action'],
                    isset($itemPayload['inventory_action']) && is_string($itemPayload['inventory_action']) => ShopCreditNoteInventoryAction::tryFrom($itemPayload['inventory_action']) ?? ShopCreditNoteInventoryAction::None,
                    default => $headerInventoryAction,
                };

                if ($lineDirection === ShopCreditNoteDirection::Credit) {
                    $totalCredits += $lineAmount;
                    $intraRequestCredits[$invoiceItem->id]['qty'] = ($intraRequestCredits[$invoiceItem->id]['qty'] ?? 0.0) + $adjustedQty;
                    $intraRequestCredits[$invoiceItem->id]['amt'] = ($intraRequestCredits[$invoiceItem->id]['amt'] ?? 0.0) + $lineAmount;
                } else {
                    $totalDebits += $lineAmount;
                    $intraRequestCredits[$invoiceItem->id]['qty'] = ($intraRequestCredits[$invoiceItem->id]['qty'] ?? 0.0) - $adjustedQty;
                    $intraRequestCredits[$invoiceItem->id]['amt'] = ($intraRequestCredits[$invoiceItem->id]['amt'] ?? 0.0) - $lineAmount;
                }

                $preparedItems[] = [
                    'shop_invoice_item_id' => $invoiceItem->id,
                    'product_id' => $invoiceItem->product_id,
                    'product_grade' => (string) ($invoiceItem->product_grade ?? 'A'),
                    'product_name' => (string) ($invoiceItem->product?->name ?? $invoiceItem->product_name),
                    'unit' => (string) $invoiceItem->unit,
                    'price_unit' => $invoiceItem->price_unit ? (string) $invoiceItem->price_unit : null,
                    'original_quantity' => $origQty,
                    'original_unit_price' => $origPrice,
                    'original_line_total' => $origTotal,
                    'adjusted_quantity' => $adjustedQty,
                    'adjusted_unit_price' => $adjustedPrice,
                    'line_credit_amount' => $lineAmount,
                    'direction' => $lineDirection,
                    'inventory_action' => $itemInventoryAction,
                    'notes' => $itemPayload['notes'] ?? null,
                ];
            }

            $totalCredits = round($totalCredits, 2);
            $totalDebits = round($totalDebits, 2);

            if ($explicitDirection === ShopCreditNoteDirection::Credit && $totalCredits <= 0.0001 && $totalDebits > 0.0001) {
                throw ValidationException::withMessages([
                    'direction' => 'Document direction "credit" conflicts with debit-only line adjustments.',
                ]);
            }

            if ($explicitDirection === ShopCreditNoteDirection::Debit && $totalDebits <= 0.0001 && $totalCredits > 0.0001) {
                throw ValidationException::withMessages([
                    'direction' => 'Document direction "debit" conflicts with credit-only line adjustments.',
                ]);
            }

            $headerDirection = $explicitDirection ?? ($totalDebits > $totalCredits ? ShopCreditNoteDirection::Debit : ShopCreditNoteDirection::Credit);
            $netAmount = round(abs($totalCredits - $totalDebits), 2);
            $grossSubtotal = round($totalCredits + $totalDebits, 2);
            $totalCreditAmount = $netAmount > 0.0001 ? $netAmount : $grossSubtotal;

            $creditNoteNumber = $this->generateUniqueCreditNoteNumber($lockedInvoice);

            $creditNote = ShopCreditNote::query()->create([
                'credit_note_number' => $creditNoteNumber,
                'shop_id' => $lockedInvoice->shop_id,
                'shop_invoice_id' => $lockedInvoice->id,
                'credit_note_date' => $date,
                'direction' => $headerDirection,
                'reason_type' => $reasonType,
                'status' => ShopCreditNoteStatus::Draft,
                'inventory_action' => $headerInventoryAction,
                'subtotal' => $grossSubtotal,
                'total_credit_amount' => $totalCreditAmount,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $creatorId,
            ]);

            foreach ($preparedItems as $itemData) {
                $creditNote->items()->create($itemData);
            }

            return $creditNote->fresh(['items.product', 'items.invoiceItem', 'invoice', 'shop']);
        });
    }

    /**
     * Calculate existing cumulative adjustments on an invoice item, broken down by status.
     *
     * @return array{
     *     approved: array{quantity_credited: float, quantity_debited: float, net_quantity: float, amount_credited: float, amount_debited: float, net_amount: float},
     *     draft: array{quantity_credited: float, quantity_debited: float, net_quantity: float, amount_credited: float, amount_debited: float, net_amount: float},
     *     voided: array{quantity_credited: float, quantity_debited: float, net_quantity: float, amount_credited: float, amount_debited: float, net_amount: float},
     *     active: array{net_quantity_credited: float, net_amount_credited: float, effective_quantity: float, effective_line_total: float, remaining_creditable_quantity: float, remaining_creditable_amount: float}
     * }
     */
    public function cumulativeAdjustmentsForInvoiceItem(
        ShopInvoiceItem $invoiceItem,
        ?int $excludeCreditNoteId = null,
    ): array {
        $adjustments = ShopCreditNoteItem::query()
            ->where('shop_invoice_item_id', $invoiceItem->id)
            ->when($excludeCreditNoteId, fn ($q) => $q->where('shop_credit_note_id', '!=', $excludeCreditNoteId))
            ->with('creditNote')
            ->get();

        $categories = [
            'approved' => ['quantity_credited' => 0.0, 'quantity_debited' => 0.0, 'net_quantity' => 0.0, 'amount_credited' => 0.0, 'amount_debited' => 0.0, 'net_amount' => 0.0],
            'draft' => ['quantity_credited' => 0.0, 'quantity_debited' => 0.0, 'net_quantity' => 0.0, 'amount_credited' => 0.0, 'amount_debited' => 0.0, 'net_amount' => 0.0],
            'voided' => ['quantity_credited' => 0.0, 'quantity_debited' => 0.0, 'net_quantity' => 0.0, 'amount_credited' => 0.0, 'amount_debited' => 0.0, 'net_amount' => 0.0],
        ];

        foreach ($adjustments as $adj) {
            $statusKey = match ($adj->creditNote?->status) {
                ShopCreditNoteStatus::Approved => 'approved',
                ShopCreditNoteStatus::Voided => 'voided',
                default => 'draft',
            };

            $qty = (float) $adj->adjusted_quantity;
            $amt = (float) $adj->line_credit_amount;

            if ($adj->direction === ShopCreditNoteDirection::Credit) {
                $categories[$statusKey]['quantity_credited'] += $qty;
                $categories[$statusKey]['amount_credited'] += $amt;
                $categories[$statusKey]['net_quantity'] += $qty;
                $categories[$statusKey]['net_amount'] += $amt;
            } else {
                $categories[$statusKey]['quantity_debited'] += $qty;
                $categories[$statusKey]['amount_debited'] += $amt;
                $categories[$statusKey]['net_quantity'] -= $qty;
                $categories[$statusKey]['net_amount'] -= $amt;
            }
        }

        foreach ($categories as $k => $vals) {
            foreach ($vals as $field => $val) {
                $categories[$k][$field] = round($val, 4);
            }
        }

        $activeNetQtyCredited = round($categories['approved']['net_quantity'] + $categories['draft']['net_quantity'], 4);
        $activeNetAmtCredited = round($categories['approved']['net_amount'] + $categories['draft']['net_amount'], 2);

        $originalQty = (float) ($invoiceItem->delivered_qty ?? $invoiceItem->approved_qty ?? 0);
        $originalPrice = (float) $invoiceItem->unit_price;
        $originalTotal = round((float) ($invoiceItem->final_line_total ?? $invoiceItem->line_subtotal ?? ($originalQty * $originalPrice)), 2);

        $effectiveQty = round(max(0.0, $originalQty - $activeNetQtyCredited), 4);
        $effectiveTotal = round($originalTotal - $activeNetAmtCredited, 2);

        $remainingCreditableQty = round(max(0.0, $originalQty - $activeNetQtyCredited), 4);
        $remainingCreditableAmt = round(max(0.0, $originalTotal - $activeNetAmtCredited), 2);

        return [
            'approved' => $categories['approved'],
            'draft' => $categories['draft'],
            'voided' => $categories['voided'],
            'active' => [
                'net_quantity_credited' => $activeNetQtyCredited,
                'net_amount_credited' => $activeNetAmtCredited,
                'effective_quantity' => $effectiveQty,
                'effective_line_total' => $effectiveTotal,
                'remaining_creditable_quantity' => $remainingCreditableQty,
                'remaining_creditable_amount' => $remainingCreditableAmt,
            ],
        ];
    }

    /**
     * Generate concurrency-safe unique credit note number for the given invoice.
     */
    public function generateUniqueCreditNoteNumber(ShopInvoice $invoice): string
    {
        $invoice->loadMissing('shop');

        $dateStr = ($invoice->business_date ?? today())->format('Ymd');
        $shopCode = strtoupper((string) ($invoice->shop?->code ?? sprintf('SHOP%04d', $invoice->shop_id)));
        $base = sprintf('SCN-%s-%s', $dateStr, $shopCode);

        $count = ShopCreditNote::query()
            ->where('shop_invoice_id', $invoice->id)
            ->lockForUpdate()
            ->count();

        $sequence = $count + 1;
        $candidate = sprintf('%s-%02d', $base, $sequence);

        while (ShopCreditNote::query()->where('credit_note_number', $candidate)->exists()) {
            $sequence++;
            $candidate = sprintf('%s-%02d', $base, $sequence);
        }

        return $candidate;
    }
}
