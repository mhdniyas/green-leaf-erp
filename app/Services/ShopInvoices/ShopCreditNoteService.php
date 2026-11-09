<?php

declare(strict_types=1);

namespace App\Services\ShopInvoices;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteLineType;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteReceiptStatus;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopCreditApplication;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopCreditNoteReceipt;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Inventory\StockLedgerService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopCreditNoteService
{
    public function __construct(
        private readonly ?InvoiceCashbookProjectionService $invoiceCashbookProjectionService = null,
        private readonly ?OwnedShopAccountingService $ownedShopAccountingService = null,
        private readonly ?StockLedgerService $stockLedgerService = null,
        private readonly ?ShopCreditNoteEligibilityService $eligibilityService = null,
    ) {}

    private function getEligibilityService(): ShopCreditNoteEligibilityService
    {
        return $this->eligibilityService ?? app(ShopCreditNoteEligibilityService::class);
    }

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

        if ($creditNote->isShopLevelMode()) {
            throw ValidationException::withMessages([
                'accounting_mode' => 'Shop-level credit notes cannot be approved via legacy single-invoice approval. Financial finalization is not yet activated.',
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
        return $this->getEligibilityService()->calculateItemAdjustments($invoiceItem, $excludeCreditNoteId);
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

    /**
     * Generate concurrency-safe unique draft credit note number for the given shop.
     */
    public function generateUniqueDraftNumber(Shop $shop, ?CarbonInterface $date = null): string
    {
        $dateStr = ($date ?? today())->format('Ymd');
        $shopCode = strtoupper((string) ($shop->code ?: sprintf('SHP%04d', $shop->id)));
        $base = sprintf('DRAFT-SCN-%s-%s', $dateStr, $shopCode);

        $count = ShopCreditNote::query()
            ->where('shop_id', $shop->id)
            ->where('credit_note_number', 'like', "{$base}%")
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

    /**
     * Generate concurrency-safe unique final credit note number for the given shop.
     */
    public function generateUniqueFinalCreditNoteNumber(Shop $shop, ?CarbonInterface $date = null): string
    {
        $dateStr = ($date ?? today())->format('Ymd');
        $shopCode = strtoupper((string) ($shop->code ?: sprintf('SHP%04d', $shop->id)));
        $base = sprintf('SCN-%s-%s', $dateStr, $shopCode);

        $count = ShopCreditNote::query()
            ->where('shop_id', $shop->id)
            ->where('credit_note_number', 'like', "{$base}%")
            ->where('credit_note_number', 'not like', 'DRAFT-%')
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

    /**
     * Create a multi-source Shop Credit Note draft.
     *
     * @param  array{
     *     credit_note_date?: string|null,
     *     reason_type?: string|ShopCreditNoteReason|null,
     *     inventory_action?: string|ShopCreditNoteInventoryAction|null,
     *     notes?: string|null,
     *     items?: array<int, array<string, mixed>>,
     *     discounts?: array<int, array<string, mixed>>,
     * }  $payload
     *
     * @throws ValidationException
     */
    public function createShopLevelDraft(
        Shop|int $shop,
        array $payload,
        User|int $creator,
    ): ShopCreditNote {
        $shopInstance = $shop instanceof Shop ? $shop : Shop::findOrFail($shop);
        $creatorUser = $creator instanceof User ? $creator : User::findOrFail($creator);

        if ($creatorUser->hasRole('purchase') && ! ($payload['from_delivery_review'] ?? false)) {
            throw ValidationException::withMessages([
                'creator' => 'Purchasers cannot arbitrarily create credit notes outside of delivery review.',
            ]);
        }

        $date = isset($payload['credit_note_date']) && filled($payload['credit_note_date'])
            ? Carbon::parse((string) $payload['credit_note_date'])->toDateString()
            : today()->toDateString();

        $periodClosed = ShopAccountingPeriodClosure::query()
            ->where('shop_id', $shopInstance->id)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();

        if ($periodClosed) {
            throw ValidationException::withMessages([
                'credit_note_date' => 'Cannot create credit note in a closed accounting period.',
            ]);
        }

        $parsed = $this->parseDraftLinesPayload($payload);
        $productAdjustmentLines = $parsed['product_lines'];
        $commercialDiscountLines = $parsed['discount_lines'];

        $headerReason = match (true) {
            isset($payload['reason_type']) && $payload['reason_type'] instanceof ShopCreditNoteReason => $payload['reason_type'],
            isset($payload['reason_type']) && is_string($payload['reason_type']) => ShopCreditNoteReason::tryFrom($payload['reason_type']) ?? ShopCreditNoteReason::Other,
            default => ! empty($productAdjustmentLines) ? ShopCreditNoteReason::PriceCorrection : ShopCreditNoteReason::CommercialDiscount,
        };

        $headerInventoryAction = match (true) {
            isset($payload['inventory_action']) && $payload['inventory_action'] instanceof ShopCreditNoteInventoryAction => $payload['inventory_action'],
            isset($payload['inventory_action']) && is_string($payload['inventory_action']) => ShopCreditNoteInventoryAction::tryFrom($payload['inventory_action']) ?? ShopCreditNoteInventoryAction::None,
            default => ShopCreditNoteInventoryAction::None,
        };

        return DB::transaction(function () use (
            $shopInstance,
            $creatorUser,
            $date,
            $payload,
            $productAdjustmentLines,
            $commercialDiscountLines,
            $headerReason,
            $headerInventoryAction,
        ): ShopCreditNote {
            $eligibilityResult = null;
            if (! empty($productAdjustmentLines)) {
                $eligibilityResult = $this->getEligibilityService()->validateAndLockSourceLines(
                    $shopInstance,
                    $productAdjustmentLines,
                );
            }

            $productCreditTotal = $eligibilityResult ? $eligibilityResult->totalCreditAmount : 0.00;
            $discountCreditTotal = round(array_sum(array_column($commercialDiscountLines, 'line_credit_amount')), 2);
            $totalDraftCredit = round($productCreditTotal + $discountCreditTotal, 2);

            $discountReason = ! empty($commercialDiscountLines)
                ? implode('; ', array_filter(array_column($commercialDiscountLines, 'notes')))
                : null;

            $draftNumber = $this->generateUniqueDraftNumber($shopInstance, Carbon::parse($date));

            /** @var ShopCreditNote $creditNote */
            $creditNote = ShopCreditNote::query()->create([
                'credit_note_number' => $draftNumber,
                'shop_id' => $shopInstance->id,
                'shop_invoice_id' => null,
                'credit_note_date' => $date,
                'direction' => ShopCreditNoteDirection::Credit,
                'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
                'reason_type' => $headerReason,
                'status' => ShopCreditNoteStatus::Draft,
                'inventory_action' => $headerInventoryAction,
                'discount_amount' => $discountCreditTotal,
                'discount_reason' => $discountReason,
                'subtotal' => $totalDraftCredit,
                'total_credit_amount' => $totalDraftCredit,
                'lock_version' => 1,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $creatorUser->id,
            ]);

            if ($eligibilityResult) {
                foreach ($eligibilityResult->validatedLines as $line) {
                    $creditNote->items()->create([
                        'line_type' => ShopCreditNoteLineType::ProductAdjustment,
                        'reason_type' => $line->reasonType ?? $headerReason,
                        'source_shop_invoice_id' => $line->sourceShopInvoiceId,
                        'shop_invoice_item_id' => $line->shopInvoiceItemId,
                        'product_id' => $line->productId,
                        'product_grade' => $line->productGrade,
                        'received_grade' => $line->receivedGrade,
                        'product_name' => $line->productName,
                        'unit' => $line->unit,
                        'price_unit' => $line->priceUnit,
                        'original_quantity' => $line->originalQuantity,
                        'original_unit_price' => $line->originalUnitPrice,
                        'original_line_total' => $line->originalLineTotal,
                        'adjusted_quantity' => $line->creditQuantity,
                        'adjusted_unit_price' => $line->creditRate,
                        'credit_rate' => $line->creditRate,
                        'line_credit_amount' => $line->creditAmount,
                        'direction' => $line->direction,
                        'inventory_action' => $line->inventoryAction,
                        'receipt_status' => $line->inventoryAction === ShopCreditNoteInventoryAction::ReturnToStock ? ShopCreditNoteReceiptStatus::PendingReceipt->value : ShopCreditNoteReceiptStatus::NotApplicable->value,
                        'notes' => $line->notes,
                    ]);
                }
            }

            foreach ($commercialDiscountLines as $disc) {
                $creditNote->items()->create([
                    'line_type' => ShopCreditNoteLineType::CommercialDiscount,
                    'reason_type' => $disc['reason_type'] ?? ShopCreditNoteReason::CommercialDiscount,
                    'source_shop_invoice_id' => $disc['source_shop_invoice_id'] ?? null,
                    'shop_invoice_item_id' => null,
                    'product_id' => null,
                    'product_grade' => 'A',
                    'received_grade' => null,
                    'product_name' => $disc['notes'] ?: 'Commercial Discount',
                    'unit' => null,
                    'price_unit' => null,
                    'original_quantity' => 0.0000,
                    'original_unit_price' => 0.00,
                    'original_line_total' => 0.00,
                    'adjusted_quantity' => 0.0000,
                    'adjusted_unit_price' => 0.00,
                    'credit_rate' => 0.00,
                    'line_credit_amount' => $disc['line_credit_amount'],
                    'direction' => ShopCreditNoteDirection::Credit,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                    'receipt_status' => 'not_applicable',
                    'notes' => $disc['notes'] ?? null,
                ]);
            }

            return $creditNote->fresh(['items.product', 'items.invoiceItem', 'items.sourceInvoice', 'shop']);
        });
    }

    /**
     * Update an existing Shop Credit Note draft.
     *
     * @param  array{
     *     credit_note_date?: string|null,
     *     reason_type?: string|ShopCreditNoteReason|null,
     *     inventory_action?: string|ShopCreditNoteInventoryAction|null,
     *     notes?: string|null,
     *     items?: array<int, array<string, mixed>>,
     *     discounts?: array<int, array<string, mixed>>,
     *     expected_lock_version?: int|null,
     *     expected_updated_at?: string|null,
     * }  $payload
     *
     * @throws ValidationException
     */
    public function updateShopLevelDraft(
        ShopCreditNote $creditNote,
        array $payload,
        User|int $actor,
    ): ShopCreditNote {
        if (! $creditNote->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft credit notes can be edited.',
            ]);
        }

        if (! $creditNote->isShopLevelMode()) {
            throw ValidationException::withMessages([
                'accounting_mode' => 'Legacy single-invoice credit notes cannot be edited with the shop-level draft engine.',
            ]);
        }

        if (isset($payload['expected_lock_version']) && (int) $creditNote->lock_version !== (int) $payload['expected_lock_version']) {
            throw ValidationException::withMessages([
                'concurrency' => 'This draft has been modified by another user. Please reload the draft and review recent changes before saving.',
            ]);
        }

        if (isset($payload['expected_updated_at'])) {
            $expectedTime = Carbon::parse((string) $payload['expected_updated_at'])->toISOString();
            $actualTime = $creditNote->updated_at?->toISOString();
            if ($actualTime !== $expectedTime) {
                throw ValidationException::withMessages([
                    'concurrency' => 'This draft has been modified by another user. Please reload the draft and review recent changes before saving.',
                ]);
            }
        }

        $date = isset($payload['credit_note_date']) && filled($payload['credit_note_date'])
            ? Carbon::parse((string) $payload['credit_note_date'])->toDateString()
            : ($creditNote->credit_note_date?->toDateString() ?? today()->toDateString());

        $periodClosed = ShopAccountingPeriodClosure::query()
            ->where('shop_id', $creditNote->shop_id)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();

        if ($periodClosed) {
            throw ValidationException::withMessages([
                'credit_note_date' => 'Cannot update credit note to a closed accounting period.',
            ]);
        }

        $parsed = $this->parseDraftLinesPayload($payload);
        $productAdjustmentLines = $parsed['product_lines'];
        $commercialDiscountLines = $parsed['discount_lines'];

        $headerReason = match (true) {
            isset($payload['reason_type']) && $payload['reason_type'] instanceof ShopCreditNoteReason => $payload['reason_type'],
            isset($payload['reason_type']) && is_string($payload['reason_type']) => ShopCreditNoteReason::tryFrom($payload['reason_type']) ?? $creditNote->reason_type,
            default => $creditNote->reason_type,
        };

        $headerInventoryAction = match (true) {
            isset($payload['inventory_action']) && $payload['inventory_action'] instanceof ShopCreditNoteInventoryAction => $payload['inventory_action'],
            isset($payload['inventory_action']) && is_string($payload['inventory_action']) => ShopCreditNoteInventoryAction::tryFrom($payload['inventory_action']) ?? $creditNote->inventory_action,
            default => $creditNote->inventory_action,
        };

        return DB::transaction(function () use (
            $creditNote,
            $date,
            $payload,
            $productAdjustmentLines,
            $commercialDiscountLines,
            $headerReason,
            $headerInventoryAction,
        ): ShopCreditNote {
            /** @var ShopCreditNote $lockedCreditNote */
            $lockedCreditNote = ShopCreditNote::query()
                ->whereKey($creditNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedCreditNote->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft credit notes can be edited.',
                ]);
            }

            if (isset($payload['expected_lock_version']) && (int) $lockedCreditNote->lock_version !== (int) $payload['expected_lock_version']) {
                throw ValidationException::withMessages([
                    'concurrency' => 'This draft has been modified by another user. Please reload the draft and review recent changes before saving.',
                ]);
            }

            $shop = $lockedCreditNote->shop;

            $eligibilityResult = null;
            if (! empty($productAdjustmentLines)) {
                // Pass excludeCreditNoteId: $lockedCreditNote->id so the draft's OWN items are not double counted!
                $eligibilityResult = $this->getEligibilityService()->validateAndLockSourceLines(
                    $shop,
                    $productAdjustmentLines,
                    excludeCreditNoteId: $lockedCreditNote->id,
                );
            }

            $productCreditTotal = $eligibilityResult ? $eligibilityResult->totalCreditAmount : 0.00;
            $discountCreditTotal = round(array_sum(array_column($commercialDiscountLines, 'line_credit_amount')), 2);
            $totalDraftCredit = round($productCreditTotal + $discountCreditTotal, 2);

            $discountReason = ! empty($commercialDiscountLines)
                ? implode('; ', array_filter(array_column($commercialDiscountLines, 'notes')))
                : null;

            // Delete previous items to replace with updated lines
            $lockedCreditNote->items()->delete();

            if ($eligibilityResult) {
                foreach ($eligibilityResult->validatedLines as $line) {
                    $lockedCreditNote->items()->create([
                        'line_type' => ShopCreditNoteLineType::ProductAdjustment,
                        'reason_type' => $line->reasonType ?? $headerReason,
                        'source_shop_invoice_id' => $line->sourceShopInvoiceId,
                        'shop_invoice_item_id' => $line->shopInvoiceItemId,
                        'product_id' => $line->productId,
                        'product_grade' => $line->productGrade,
                        'received_grade' => $line->receivedGrade,
                        'product_name' => $line->productName,
                        'unit' => $line->unit,
                        'price_unit' => $line->priceUnit,
                        'original_quantity' => $line->originalQuantity,
                        'original_unit_price' => $line->originalUnitPrice,
                        'original_line_total' => $line->originalLineTotal,
                        'adjusted_quantity' => $line->creditQuantity,
                        'adjusted_unit_price' => $line->creditRate,
                        'credit_rate' => $line->creditRate,
                        'line_credit_amount' => $line->creditAmount,
                        'direction' => $line->direction,
                        'inventory_action' => $line->inventoryAction,
                        'receipt_status' => $line->inventoryAction === ShopCreditNoteInventoryAction::ReturnToStock ? ShopCreditNoteReceiptStatus::PendingReceipt->value : ShopCreditNoteReceiptStatus::NotApplicable->value,
                        'notes' => $line->notes,
                    ]);
                }
            }

            foreach ($commercialDiscountLines as $disc) {
                $lockedCreditNote->items()->create([
                    'line_type' => ShopCreditNoteLineType::CommercialDiscount,
                    'reason_type' => $disc['reason_type'] ?? ShopCreditNoteReason::CommercialDiscount,
                    'source_shop_invoice_id' => $disc['source_shop_invoice_id'] ?? null,
                    'shop_invoice_item_id' => null,
                    'product_id' => null,
                    'product_grade' => 'A',
                    'received_grade' => null,
                    'product_name' => $disc['notes'] ?: 'Commercial Discount',
                    'unit' => null,
                    'price_unit' => null,
                    'original_quantity' => 0.0000,
                    'original_unit_price' => 0.00,
                    'original_line_total' => 0.00,
                    'adjusted_quantity' => 0.0000,
                    'adjusted_unit_price' => 0.00,
                    'credit_rate' => 0.00,
                    'line_credit_amount' => $disc['line_credit_amount'],
                    'direction' => ShopCreditNoteDirection::Credit,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                    'receipt_status' => 'not_applicable',
                    'notes' => $disc['notes'] ?? null,
                ]);
            }

            $lockedCreditNote->forceFill([
                'credit_note_date' => $date,
                'reason_type' => $headerReason,
                'inventory_action' => $headerInventoryAction,
                'discount_amount' => $discountCreditTotal,
                'discount_reason' => $discountReason,
                'subtotal' => $totalDraftCredit,
                'total_credit_amount' => $totalDraftCredit,
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $lockedCreditNote->notes,
                'lock_version' => $lockedCreditNote->lock_version + 1,
            ])->save();

            return $lockedCreditNote->fresh(['items.product', 'items.invoiceItem', 'items.sourceInvoice', 'shop']);
        });
    }

    /**
     * Check if a user is authorized to finalize shop-level credit notes.
     */
    public function isAuthorizedToFinalize(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return (method_exists($user, 'isMainAdmin') && $user->isMainAdmin())
            || $user->hasRole('admin')
            || $user->hasRole('super_admin')
            || $user->hasRole('accounts')
            || $user->can('accounting.invoice.approve')
            || $user->can('accounting.entry.create');
    }

    /**
     * Safely finalize a shop-level credit note draft.
     *
     * @throws ValidationException
     */
    public function finalizeShopLevelCreditNote(
        ShopCreditNote $creditNote,
        User|int $actor,
        ?string $finalizationNote = null,
    ): ShopCreditNote {
        $actorUser = $actor instanceof User ? $actor : User::find($actor);

        if (! $this->isAuthorizedToFinalize($actorUser)) {
            throw ValidationException::withMessages([
                'approved_by' => 'User is not authorized to finalize shop credit notes.',
            ]);
        }

        if (! $creditNote->isShopLevelMode()) {
            throw ValidationException::withMessages([
                'accounting_mode' => 'Only shop-level credit notes can be finalized using this method.',
            ]);
        }

        if ($creditNote->isApproved()) {
            throw ValidationException::withMessages([
                'status' => 'Credit note is already finalized.',
            ]);
        }

        if ($creditNote->isVoided()) {
            throw ValidationException::withMessages([
                'status' => 'Voided credit notes cannot be finalized.',
            ]);
        }

        if (! $creditNote->isDraft()) {
            throw ValidationException::withMessages([
                'status' => "Cannot finalize credit note with status '{$creditNote->status->value}'.",
            ]);
        }

        $creditNoteDate = $creditNote->credit_note_date?->toDateString() ?? today()->toDateString();
        $periodClosed = ShopAccountingPeriodClosure::query()
            ->where('shop_id', $creditNote->shop_id)
            ->whereDate('period_start', '<=', $creditNoteDate)
            ->whereDate('period_end', '>=', $creditNoteDate)
            ->exists();

        if ($periodClosed) {
            throw ValidationException::withMessages([
                'credit_note_date' => 'Cannot finalize credit note in a closed accounting period.',
            ]);
        }

        return DB::transaction(function () use ($creditNote, $actorUser, $finalizationNote): ShopCreditNote {
            /** @var ShopCreditNote $lockedCreditNote */
            $lockedCreditNote = ShopCreditNote::query()
                ->with(['items.invoiceItem', 'shop'])
                ->whereKey($creditNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCreditNote->isApproved()) {
                throw ValidationException::withMessages([
                    'status' => 'Credit note is already finalized.',
                ]);
            }

            if (! $lockedCreditNote->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => "Cannot finalize credit note with status '{$lockedCreditNote->status->value}'.",
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
                    'credit_note_date' => 'Cannot finalize credit note in a closed accounting period.',
                ]);
            }

            $productItems = $lockedCreditNote->items->filter(
                fn (ShopCreditNoteItem $item): bool => $item->line_type === ShopCreditNoteLineType::ProductAdjustment
                    || ($item->line_type === null && $item->shop_invoice_item_id !== null)
            );

            $discountItems = $lockedCreditNote->items->filter(
                fn (ShopCreditNoteItem $item): bool => $item->line_type === ShopCreditNoteLineType::CommercialDiscount
            );

            $eligibilityResult = null;
            if ($productItems->isNotEmpty()) {
                $payloadLines = [];
                foreach ($productItems as $item) {
                    $payloadLines[] = [
                        'source_shop_invoice_id' => $item->source_shop_invoice_id ?? $item->invoiceItem?->shop_invoice_id,
                        'shop_invoice_item_id' => $item->shop_invoice_item_id,
                        'product_id' => $item->product_id,
                        'product_grade' => $item->product_grade,
                        'unit' => $item->unit,
                        'credit_quantity' => (float) $item->adjusted_quantity,
                        'credit_rate' => (float) ($item->credit_rate ?? $item->adjusted_unit_price),
                        'credit_amount' => (float) $item->line_credit_amount,
                        'reason_type' => $item->reason_type,
                        'received_grade' => $item->received_grade,
                        'inventory_action' => $item->inventory_action,
                        'direction' => $item->direction,
                        'notes' => $item->notes,
                    ];
                }

                $eligibilityResult = $this->getEligibilityService()->validateAndLockSourceLines(
                    $lockedCreditNote->shop,
                    $payloadLines,
                    excludeCreditNoteId: $lockedCreditNote->id,
                );
            }

            $productCreditTotal = $eligibilityResult ? $eligibilityResult->totalCreditAmount : 0.00;
            $discountCreditTotal = round((float) $discountItems->sum('line_credit_amount'), 2);
            $finalCreditTotal = round($productCreditTotal + $discountCreditTotal, 2);

            if ($finalCreditTotal <= 0.0001) {
                throw ValidationException::withMessages([
                    'total_credit_amount' => 'Final credit amount must be greater than zero.',
                ]);
            }

            $draftNumber = $lockedCreditNote->credit_note_number;
            $finalNumber = str_starts_with($draftNumber, 'DRAFT-')
                ? $this->generateUniqueFinalCreditNoteNumber($lockedCreditNote->shop, $lockedCreditNote->credit_note_date)
                : $draftNumber;

            $auditNote = "Finalized from {$draftNumber}";
            if (filled($finalizationNote)) {
                $auditNote .= '. '.trim($finalizationNote);
            }

            $noteText = filled($lockedCreditNote->notes)
                ? trim((string) $lockedCreditNote->notes)."\n".$auditNote
                : $auditNote;

            $lockedCreditNote->forceFill([
                'credit_note_number' => $finalNumber,
                'status' => ShopCreditNoteStatus::Approved,
                'approved_by' => $actorUser->id,
                'approved_at' => now(),
                'subtotal' => $finalCreditTotal,
                'total_credit_amount' => $finalCreditTotal,
                'discount_amount' => $discountCreditTotal,
                'notes' => $noteText,
                'lock_version' => $lockedCreditNote->lock_version + 1,
            ])->save();

            // Transition return-to-stock items to PendingReceipt for warehouse verification
            foreach ($lockedCreditNote->items as $item) {
                if ($item->inventory_action === ShopCreditNoteInventoryAction::ReturnToStock
                    && ($item->receipt_status === null || $item->receipt_status === ShopCreditNoteReceiptStatus::NotApplicable || $item->receipt_status->value === 'not_applicable')) {
                    $item->update(['receipt_status' => ShopCreditNoteReceiptStatus::PendingReceipt->value]);
                }
            }

            // Note: NO inventory/stock movements are created at office finalization.
            // Stock increases only after warehouse staff physically confirm actual receipt.

            // Post single financial credit to cashbook
            $this->cashbookProjectionService()->syncCreditNote($lockedCreditNote, $actorUser->id);

            // Sync owned shop accounting closing balances if applicable
            if ($lockedCreditNote->shop instanceof Shop && $lockedCreditNote->shop->isOwnedAccountingEnabled()) {
                $businessDate = Carbon::parse($lockedCreditNote->credit_note_date?->toDateString() ?? today()->toDateString());
                $this->ownedAccountingService()->syncStoredClosingBalancesFromDate(
                    $lockedCreditNote->shop,
                    $businessDate,
                    $actorUser->id,
                );
            }

            return $lockedCreditNote->fresh(['items.product', 'items.invoiceItem', 'items.sourceInvoice', 'shop', 'ledgerTransaction']);
        });
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
        return app(ShopCreditApplicationService::class)->applyCredit(
            $creditNote,
            $invoice,
            $amount,
            $actor,
            $notes,
            $appliedAt,
            $idempotencyKey,
        );
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
        return app(ShopCreditApplicationService::class)->applyCreditToMany(
            $creditNote,
            $allocations,
            $actor,
            $appliedAt,
            $idempotencyKey,
        );
    }

    /**
     * Safely reverses an existing active credit application.
     *
     * @throws ValidationException
     */
    public function reverseCreditApplication(
        ShopCreditApplication|int $application,
        User|int $actor,
        string $reason,
        ?CarbonInterface $reversedAt = null,
    ): ShopCreditApplication {
        return app(ShopCreditApplicationService::class)->reverseCreditApplication(
            $application,
            $actor,
            $reason,
            $reversedAt,
        );
    }

    /**
     * Check if a user is authorized to cancel shop-level credit notes.
     */
    public function isAuthorizedToCancelCreditNote(?User $user): bool
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
        $actorUser = $actor instanceof User ? $actor : User::find($actor);

        if (! $this->isAuthorizedToCancelCreditNote($actorUser)) {
            throw ValidationException::withMessages([
                'cancelled_by' => 'User is not authorized to cancel credit notes.',
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'void_reason' => 'A valid cancellation reason is required.',
            ]);
        }

        $creditNoteInstance = $creditNote instanceof ShopCreditNote
            ? $creditNote
            : ShopCreditNote::findOrFail($creditNote);

        if (! $creditNoteInstance->isShopLevelMode()) {
            throw ValidationException::withMessages([
                'accounting_mode' => 'Only shop-level credit notes can be cancelled using this method.',
            ]);
        }

        if ($creditNoteInstance->isVoided()) {
            throw ValidationException::withMessages([
                'status' => 'Credit note is already voided.',
            ]);
        }

        if ($creditNoteInstance->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Draft credit notes cannot be cancelled using this method. Use draft discard/delete instead.',
            ]);
        }

        if (! $creditNoteInstance->isApproved()) {
            throw ValidationException::withMessages([
                'status' => "Cannot cancel credit note with status '{$creditNoteInstance->status->value}'.",
            ]);
        }

        $hasPhysicalStockMovements = $creditNoteInstance->stockMovements()->exists()
            || StockMovement::query()
                ->where('type', StockMovementType::SaleReversal->value)
                ->where('notes', 'like', "%SCN: {$creditNoteInstance->credit_note_number}%")
                ->exists();

        if ($hasPhysicalStockMovements) {
            throw ValidationException::withMessages([
                'stock_movements' => 'Cannot cancel credit note with associated inventory stock movements without warehouse reconciliation.',
            ]);
        }

        $cancelledTimestamp = $cancelledAt !== null ? Carbon::instance($cancelledAt) : now();
        $cancellationDate = $cancelledTimestamp->toDateString();
        $creditNoteDate = $creditNoteInstance->credit_note_date?->toDateString() ?? today()->toDateString();

        $cancellationPeriodClosed = ShopAccountingPeriodClosure::query()
            ->where('shop_id', $creditNoteInstance->shop_id)
            ->whereDate('period_start', '<=', $cancellationDate)
            ->whereDate('period_end', '>=', $cancellationDate)
            ->exists();

        if ($cancellationPeriodClosed) {
            throw ValidationException::withMessages([
                'cancelled_at' => 'Cannot cancel credit note in a closed accounting period.',
            ]);
        }

        $creditNotePeriodClosed = ShopAccountingPeriodClosure::query()
            ->where('shop_id', $creditNoteInstance->shop_id)
            ->whereDate('period_start', '<=', $creditNoteDate)
            ->whereDate('period_end', '>=', $creditNoteDate)
            ->exists();

        if ($creditNotePeriodClosed) {
            throw ValidationException::withMessages([
                'credit_note_date' => 'Cannot cancel credit note posted in a closed accounting period. Compensating adjustment required.',
            ]);
        }

        $activeApplications = ShopCreditApplication::query()
            ->where('shop_credit_note_id', $creditNoteInstance->id)
            ->whereNull('reversed_at')
            ->get();

        foreach ($activeApplications as $app) {
            $appliedDate = $app->applied_at?->toDateString()
                ?? $app->created_at?->toDateString()
                ?? today()->toDateString();

            $appPeriodClosed = ShopAccountingPeriodClosure::query()
                ->where('shop_id', $creditNoteInstance->shop_id)
                ->whereDate('period_start', '<=', $appliedDate)
                ->whereDate('period_end', '>=', $appliedDate)
                ->exists();

            if ($appPeriodClosed) {
                throw ValidationException::withMessages([
                    'reversed_at' => 'Cannot cancel credit note with applications originally applied in a closed accounting period. Compensating adjustment required.',
                ]);
            }
        }

        return DB::transaction(function () use (
            $creditNoteInstance,
            $actorUser,
            $reason,
            $cancelledTimestamp,
            $cancellationDate,
            $creditNoteDate
        ): ShopCreditNote {
            /** @var ShopCreditNote $lockedCreditNote */
            $lockedCreditNote = ShopCreditNote::query()
                ->whereKey($creditNoteInstance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCreditNote->isVoided()) {
                throw ValidationException::withMessages([
                    'status' => 'Credit note is already voided.',
                ]);
            }

            if (! $lockedCreditNote->isApproved()) {
                throw ValidationException::withMessages([
                    'status' => "Cannot cancel credit note with status '{$lockedCreditNote->status->value}'.",
                ]);
            }

            $hasPhysicalMovements = $lockedCreditNote->stockMovements()->exists()
                || StockMovement::query()
                    ->where('type', StockMovementType::SaleReversal->value)
                    ->where('notes', 'like', "%SCN: {$lockedCreditNote->credit_note_number}%")
                    ->exists();

            if ($hasPhysicalMovements) {
                throw ValidationException::withMessages([
                    'stock_movements' => 'Cannot cancel credit note with associated inventory stock movements without warehouse reconciliation.',
                ]);
            }

            $cancellationPeriodClosed = ShopAccountingPeriodClosure::query()
                ->where('shop_id', $lockedCreditNote->shop_id)
                ->whereDate('period_start', '<=', $cancellationDate)
                ->whereDate('period_end', '>=', $cancellationDate)
                ->exists();

            if ($cancellationPeriodClosed) {
                throw ValidationException::withMessages([
                    'cancelled_at' => 'Cannot cancel credit note in a closed accounting period.',
                ]);
            }

            $creditNotePeriodClosed = ShopAccountingPeriodClosure::query()
                ->where('shop_id', $lockedCreditNote->shop_id)
                ->whereDate('period_start', '<=', $creditNoteDate)
                ->whereDate('period_end', '>=', $creditNoteDate)
                ->exists();

            if ($creditNotePeriodClosed) {
                throw ValidationException::withMessages([
                    'credit_note_date' => 'Cannot cancel credit note posted in a closed accounting period. Compensating adjustment required.',
                ]);
            }

            $activeApps = ShopCreditApplication::query()
                ->where('shop_credit_note_id', $lockedCreditNote->id)
                ->whereNull('reversed_at')
                ->get();

            if ($activeApps->isNotEmpty()) {
                $targetInvoiceIds = $activeApps->pluck('shop_invoice_id')->unique()->sort()->values()->all();
                ShopInvoice::query()
                    ->whereIn('id', $targetInvoiceIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $lockedApps = ShopCreditApplication::query()
                    ->whereIn('id', $activeApps->pluck('id')->sort()->values()->all())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($lockedApps as $lockedApp) {
                    if ($lockedApp->isReversed()) {
                        continue;
                    }

                    $appliedDate = $lockedApp->applied_at?->toDateString()
                        ?? $lockedApp->created_at?->toDateString()
                        ?? today()->toDateString();

                    $appPeriodClosed = ShopAccountingPeriodClosure::query()
                        ->where('shop_id', $lockedCreditNote->shop_id)
                        ->whereDate('period_start', '<=', $appliedDate)
                        ->whereDate('period_end', '>=', $appliedDate)
                        ->exists();

                    if ($appPeriodClosed) {
                        throw ValidationException::withMessages([
                            'reversed_at' => 'Cannot cancel credit note with applications originally applied in a closed accounting period. Compensating adjustment required.',
                        ]);
                    }

                    $lockedApp->forceFill([
                        'reversed_at' => $cancelledTimestamp,
                        'reversed_by' => $actorUser->id,
                        'reversal_reason' => "Credit note {$lockedCreditNote->credit_note_number} cancelled: ".trim($reason),
                    ])->save();
                }
            }

            $auditEntry = 'Cancelled on '.$cancelledTimestamp->toDateTimeString()." by {$actorUser->name} (#{$actorUser->id}): ".trim($reason);
            $notesText = filled($lockedCreditNote->notes)
                ? trim((string) $lockedCreditNote->notes)."\n".$auditEntry
                : $auditEntry;

            $lockedCreditNote->forceFill([
                'status' => ShopCreditNoteStatus::Voided,
                'voided_by' => $actorUser->id,
                'voided_at' => $cancelledTimestamp,
                'void_reason' => trim($reason),
                'notes' => $notesText,
                'lock_version' => $lockedCreditNote->lock_version + 1,
            ])->save();

            // In-place void of Cashbook transaction & daily balance recalculation
            $this->cashbookProjectionService()->syncCreditNote($lockedCreditNote, $actorUser->id);

            // Sync owned shop accounting closing balances if applicable
            if ($lockedCreditNote->shop instanceof Shop && $lockedCreditNote->shop->isOwnedAccountingEnabled()) {
                $businessDate = Carbon::parse($lockedCreditNote->credit_note_date?->toDateString() ?? today()->toDateString());
                $this->ownedAccountingService()->syncStoredClosingBalancesFromDate(
                    $lockedCreditNote->shop,
                    $businessDate,
                    $actorUser->id,
                );
            }

            return $lockedCreditNote->fresh(['items.product', 'items.invoiceItem', 'items.sourceInvoice', 'shop', 'ledgerTransaction', 'applications']);
        });
    }

    /**
     * Check if a user is authorized to verify physical warehouse returns.
     */
    public function isAuthorizedToReceivePhysicalReturn(?User $user): bool
    {
        return app(ShopCreditNoteWarehouseReceiptService::class)->isAuthorizedToReceive($user);
    }

    /**
     * Verify physical warehouse receipt for a credit note item.
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
        return app(ShopCreditNoteWarehouseReceiptService::class)->receivePhysicalReturn(
            $item,
            $quantity,
            $grade,
            $warehouse,
            $actor,
            $receivedAt,
            $remarks,
            $idempotencyKey,
        );
    }

    /**
     * Record a physical return dispute without generating stock movements.
     *
     * @throws ValidationException
     */
    public function disputePhysicalReturn(
        ShopCreditNoteItem|int $item,
        User|int $actor,
        string $reason,
        ?CarbonInterface $disputedAt = null,
    ): ShopCreditNoteReceipt {
        return app(ShopCreditNoteWarehouseReceiptService::class)->disputePhysicalReturn(
            $item,
            $actor,
            $reason,
            $disputedAt,
        );
    }

    /**
     * Parse and categorize lines into product adjustment lines and commercial discount lines.
     *
     * @param  array<string, mixed>  $payload
     * @return array{product_lines: array<int, array<string, mixed>>, discount_lines: array<int, array<string, mixed>>}
     *
     * @throws ValidationException
     */
    private function parseDraftLinesPayload(array $payload): array
    {
        $items = $payload['items'] ?? [];
        $discounts = $payload['discounts'] ?? [];

        if (! is_array($items)) {
            throw ValidationException::withMessages(['items' => 'Items payload must be an array.']);
        }
        if (! is_array($discounts)) {
            throw ValidationException::withMessages(['discounts' => 'Discounts payload must be an array.']);
        }

        $productLines = [];
        $discountLines = [];

        foreach ($items as $index => $line) {
            if (! is_array($line)) {
                throw ValidationException::withMessages(["items.{$index}" => 'Line adjustment must be an array.']);
            }

            $lineType = match (true) {
                isset($line['line_type']) && $line['line_type'] instanceof ShopCreditNoteLineType => $line['line_type']->value,
                isset($line['line_type']) && is_string($line['line_type']) => $line['line_type'],
                default => null,
            };

            $isDiscount = ($lineType === ShopCreditNoteLineType::CommercialDiscount->value)
                || ($lineType === 'commercial_discount')
                || (empty($line['shop_invoice_item_id']) && (isset($line['discount_amount']) || isset($line['amount'])));

            if ($isDiscount) {
                $amt = (float) ($line['discount_amount'] ?? $line['amount'] ?? $line['line_credit_amount'] ?? $line['credit_amount'] ?? 0.0);
                if ($amt <= 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$index}.discount_amount" => 'Commercial discount amount must be greater than zero.',
                    ]);
                }
                $discountLines[] = [
                    'source_shop_invoice_id' => $line['source_shop_invoice_id'] ?? null,
                    'line_credit_amount' => round($amt, 2),
                    'notes' => $line['notes'] ?? ($line['description'] ?? 'Commercial Discount'),
                    'reason_type' => isset($line['reason_type'])
                        ? (is_string($line['reason_type']) ? ShopCreditNoteReason::tryFrom($line['reason_type']) ?? ShopCreditNoteReason::CommercialDiscount : $line['reason_type'])
                        : ShopCreditNoteReason::CommercialDiscount,
                ];
            } else {
                if (empty($line['shop_invoice_item_id'])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.shop_invoice_item_id" => 'Source invoice item ID is required for product adjustments.',
                    ]);
                }

                $qty = (float) ($line['credit_quantity'] ?? $line['adjusted_quantity'] ?? 0.0);
                $rate = (float) ($line['credit_rate'] ?? $line['adjusted_unit_price'] ?? 0.0);

                if ($qty < 0.0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.credit_quantity" => 'Credit quantity cannot be negative.',
                    ]);
                }
                if ($rate < 0.0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.credit_rate" => 'Credit rate cannot be negative.',
                    ]);
                }

                $amt = array_key_exists('credit_amount', $line) && $line['credit_amount'] !== null
                    ? (float) $line['credit_amount']
                    : (array_key_exists('line_credit_amount', $line) && $line['line_credit_amount'] !== null
                        ? (float) $line['line_credit_amount']
                        : round($qty * $rate, 2));

                if ($amt < 0.0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.credit_amount" => 'Credit amount cannot be negative.',
                    ]);
                }

                if ($qty <= 0.0001 && $amt <= 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$index}" => "Line adjustment for item #{$line['shop_invoice_item_id']} must have either credit quantity or credit amount greater than zero.",
                    ]);
                }

                $productLines[] = [
                    'source_shop_invoice_id' => $line['source_shop_invoice_id'] ?? null,
                    'shop_invoice_item_id' => (int) $line['shop_invoice_item_id'],
                    'product_id' => isset($line['product_id']) ? (int) $line['product_id'] : null,
                    'product_grade' => $line['product_grade'] ?? null,
                    'unit' => $line['unit'] ?? null,
                    'credit_quantity' => round($qty, 4),
                    'credit_rate' => round($rate, 2),
                    'credit_amount' => round($amt, 2),
                    'reason_type' => $line['reason_type'] ?? null,
                    'received_grade' => $line['received_grade'] ?? null,
                    'inventory_action' => $line['inventory_action'] ?? null,
                    'direction' => $line['direction'] ?? ShopCreditNoteDirection::Credit,
                    'notes' => $line['notes'] ?? null,
                ];
            }
        }

        foreach ($discounts as $dIndex => $disc) {
            if (! is_array($disc)) {
                throw ValidationException::withMessages(["discounts.{$dIndex}" => 'Discount line must be an array.']);
            }
            $amt = (float) ($disc['amount'] ?? $disc['discount_amount'] ?? 0.0);
            if ($amt <= 0.0001) {
                throw ValidationException::withMessages([
                    "discounts.{$dIndex}.amount" => 'Commercial discount amount must be greater than zero.',
                ]);
            }
            $discountLines[] = [
                'source_shop_invoice_id' => $disc['source_shop_invoice_id'] ?? null,
                'line_credit_amount' => round($amt, 2),
                'notes' => $disc['notes'] ?? 'Commercial Discount',
                'reason_type' => isset($disc['reason_type'])
                    ? (is_string($disc['reason_type']) ? ShopCreditNoteReason::tryFrom($disc['reason_type']) ?? ShopCreditNoteReason::CommercialDiscount : $disc['reason_type'])
                    : ShopCreditNoteReason::CommercialDiscount,
            ];
        }

        if (empty($productLines) && empty($discountLines)) {
            throw ValidationException::withMessages([
                'items' => 'At least one product adjustment or commercial discount line must be provided.',
            ]);
        }

        return [
            'product_lines' => $productLines,
            'discount_lines' => $discountLines,
        ];
    }
}
