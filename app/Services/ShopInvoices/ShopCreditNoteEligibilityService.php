<?php

declare(strict_types=1);

namespace App\Services\ShopInvoices;

use App\DTOs\ShopInvoice\SourceLineEligibilityResult;
use App\DTOs\ShopInvoice\ValidatedSourceLineItem;
use App\Enums\Inventory\ProductGrade;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use Illuminate\Validation\ValidationException;

class ShopCreditNoteEligibilityService
{
    /**
     * Validate and acquire pessimistic row locks on source invoice lines in deterministic order.
     *
     * @param  array<int, array<string, mixed>>  $lines
     *
     * @throws ValidationException
     */
    public function validateAndLockSourceLines(
        Shop|int $shop,
        array $lines,
        ?int $excludeCreditNoteId = null
    ): SourceLineEligibilityResult {
        $shopInstance = $shop instanceof Shop ? $shop : Shop::findOrFail($shop);

        if (empty($lines)) {
            throw ValidationException::withMessages([
                'lines' => 'At least one source invoice line must be provided.',
            ]);
        }

        $invoiceIds = [];
        $itemIds = [];

        foreach ($lines as $i => $line) {
            $invId = (int) ($line['source_shop_invoice_id'] ?? $line['shop_invoice_id'] ?? 0);
            $itemId = (int) ($line['shop_invoice_item_id'] ?? 0);

            if ($invId <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$i}.source_shop_invoice_id" => 'Source invoice reference is required.',
                ]);
            }

            if ($itemId <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$i}.shop_invoice_item_id" => 'Source invoice item reference is required.',
                ]);
            }

            $invoiceIds[$invId] = true;
            $itemIds[$itemId] = true;
        }

        $uniqueInvoiceIds = array_keys($invoiceIds);
        $uniqueItemIds = array_keys($itemIds);

        // Deterministic ascending order locking to eliminate database deadlocks
        sort($uniqueInvoiceIds);
        sort($uniqueItemIds);

        $lockedInvoices = ShopInvoice::query()
            ->whereIn('id', $uniqueInvoiceIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $lockedInvoiceItems = ShopInvoiceItem::query()
            ->whereIn('id', $uniqueItemIds)
            ->with('product')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // Verify existence of all requested invoices and items
        foreach ($lines as $i => $line) {
            $invId = (int) ($line['source_shop_invoice_id'] ?? $line['shop_invoice_id'] ?? 0);
            $itemId = (int) ($line['shop_invoice_item_id'] ?? 0);

            if (! $lockedInvoices->has($invId)) {
                throw ValidationException::withMessages([
                    "lines.{$i}.source_shop_invoice_id" => "Source invoice #{$invId} does not exist.",
                ]);
            }

            if (! $lockedInvoiceItems->has($itemId)) {
                throw ValidationException::withMessages([
                    "lines.{$i}.shop_invoice_item_id" => "Source invoice item #{$itemId} does not exist.",
                ]);
            }
        }

        // Verify shop ownership, item-invoice binding, and sale attribute compatibility
        foreach ($lines as $i => $line) {
            $invId = (int) ($line['source_shop_invoice_id'] ?? $line['shop_invoice_id'] ?? 0);
            $itemId = (int) ($line['shop_invoice_item_id'] ?? 0);

            $invoice = $lockedInvoices->get($invId);
            $item = $lockedInvoiceItems->get($itemId);

            if ((int) $invoice->shop_id !== (int) $shopInstance->id) {
                throw ValidationException::withMessages([
                    "lines.{$i}.source_shop_invoice_id" => "Source invoice {$invoice->invoice_number} does not belong to shop {$shopInstance->name}.",
                ]);
            }

            if ((int) $item->shop_invoice_id !== (int) $invoice->id) {
                throw ValidationException::withMessages([
                    "lines.{$i}.shop_invoice_item_id" => "Invoice item #{$itemId} does not belong to source invoice {$invoice->invoice_number}.",
                ]);
            }

            if ($invoice->status === 'cancelled') {
                throw ValidationException::withMessages([
                    "lines.{$i}.source_shop_invoice_id" => "Source invoice {$invoice->invoice_number} is cancelled and cannot receive adjustments.",
                ]);
            }

            if (isset($line['product_id']) && (int) $line['product_id'] !== (int) $item->product_id) {
                throw ValidationException::withMessages([
                    "lines.{$i}.product_id" => "Product ID {$line['product_id']} does not match original sale product ID {$item->product_id}.",
                ]);
            }

            if (isset($line['product_grade'])) {
                $expectedGrade = ProductGrade::tryResolve($item->product_grade) ?? ProductGrade::GradeA;
                $givenGrade = ProductGrade::tryResolve($line['product_grade']);

                if ($givenGrade !== $expectedGrade) {
                    throw ValidationException::withMessages([
                        "lines.{$i}.product_grade" => "Product grade '{$line['product_grade']}' does not match original sale grade '{$item->product_grade}'.",
                    ]);
                }
            }

            if (isset($line['unit'])) {
                $normalizedGiven = ProductUnit::normalizeUnit((string) $line['unit']);
                $normalizedExpected = ProductUnit::normalizeUnit((string) ($item->price_unit ?: $item->unit));

                if ($normalizedGiven !== $normalizedExpected) {
                    throw ValidationException::withMessages([
                        "lines.{$i}.unit" => "Unit '{$line['unit']}' is incompatible with original sale unit '{$item->unit}'.",
                    ]);
                }
            }
        }

        // Track intra-request allocations per item to prevent duplicate line bypass
        $intraAllocations = [];
        $validatedLines = [];
        $totalCredits = 0.0;
        $totalDebits = 0.0;

        foreach ($lines as $i => $line) {
            $invId = (int) ($line['source_shop_invoice_id'] ?? $line['shop_invoice_id'] ?? 0);
            $itemId = (int) ($line['shop_invoice_item_id'] ?? 0);

            $invoice = $lockedInvoices->get($invId);
            $item = $lockedInvoiceItems->get($itemId);

            $direction = match (true) {
                isset($line['direction']) && $line['direction'] instanceof ShopCreditNoteDirection => $line['direction'],
                isset($line['direction']) && is_string($line['direction']) => ShopCreditNoteDirection::tryFrom($line['direction']) ?? throw ValidationException::withMessages(["lines.{$i}.direction" => 'Invalid credit note direction.']),
                default => ShopCreditNoteDirection::Credit,
            };

            $inventoryAction = match (true) {
                isset($line['inventory_action']) && $line['inventory_action'] instanceof ShopCreditNoteInventoryAction => $line['inventory_action'],
                isset($line['inventory_action']) && is_string($line['inventory_action']) => ShopCreditNoteInventoryAction::tryFrom($line['inventory_action']) ?? ShopCreditNoteInventoryAction::None,
                default => ShopCreditNoteInventoryAction::None,
            };

            $origQty = (float) ($item->delivered_qty ?? $item->approved_qty ?? 0.0);
            $origPrice = (float) $item->unit_price;
            $origTotal = round((float) ($item->final_line_total ?? ($origQty * $origPrice)), 2);

            $creditQty = isset($line['credit_quantity']) || isset($line['adjusted_quantity'])
                ? round((float) ($line['credit_quantity'] ?? $line['adjusted_quantity'] ?? 0.0), 4)
                : 0.0;

            if ($creditQty < 0.0) {
                throw ValidationException::withMessages([
                    "lines.{$i}.credit_quantity" => 'Credit quantity cannot be negative.',
                ]);
            }

            $creditRate = isset($line['credit_rate']) || isset($line['adjusted_unit_price'])
                ? round((float) ($line['credit_rate'] ?? $line['adjusted_unit_price'] ?? $origPrice), 2)
                : $origPrice;

            if ($creditRate < 0.0) {
                throw ValidationException::withMessages([
                    "lines.{$i}.credit_rate" => 'Credit rate cannot be negative.',
                ]);
            }

            $creditAmount = isset($line['credit_amount']) || isset($line['line_credit_amount'])
                ? round((float) ($line['credit_amount'] ?? $line['line_credit_amount']), 2)
                : round($creditQty * $creditRate, 2);

            if ($creditAmount <= 0.0 && $creditQty <= 0.0) {
                throw ValidationException::withMessages([
                    "lines.{$i}" => "Line adjustment for item #{$itemId} must have either credit quantity or credit amount greater than zero.",
                ]);
            }

            // Recalculate remaining eligible limits within transaction lock
            $cumulative = $this->calculateItemAdjustments($item, $excludeCreditNoteId);
            $previousCreditedQty = $cumulative['active']['net_quantity_credited'];
            $previousCreditedAmt = $cumulative['active']['net_amount_credited'];

            $currentIntraQty = (float) ($intraAllocations[$itemId]['qty'] ?? 0.0);
            $currentIntraAmt = (float) ($intraAllocations[$itemId]['amt'] ?? 0.0);

            $remainingCreditableQty = round(max(0.0, $origQty - $previousCreditedQty - $currentIntraQty), 4);
            $remainingCreditableAmt = round(max(0.0, $origTotal - $previousCreditedAmt - $currentIntraAmt), 2);

            if ($direction === ShopCreditNoteDirection::Credit) {
                // Physical quantity check
                if ($creditQty > ($remainingCreditableQty + 0.0001)) {
                    throw ValidationException::withMessages([
                        "lines.{$i}.credit_quantity" => "Requested credit quantity ({$creditQty}) for item #{$item->id} ({$item->product_name}) exceeds remaining eligible quantity ({$remainingCreditableQty}). Original billed: {$origQty}, Already credited: {$previousCreditedQty}.",
                    ]);
                }

                // Financial amount check
                if ($creditAmount > ($remainingCreditableAmt + 0.01)) {
                    throw ValidationException::withMessages([
                        "lines.{$i}.credit_amount" => 'Requested credit amount (₹'.number_format($creditAmount, 2).") for item #{$item->id} ({$item->product_name}) exceeds remaining eligible amount (₹".number_format($remainingCreditableAmt, 2).'). Original billed: ₹'.number_format($origTotal, 2).', Already credited: ₹'.number_format($previousCreditedAmt, 2).'.',
                    ]);
                }

                $intraAllocations[$itemId]['qty'] = $currentIntraQty + $creditQty;
                $intraAllocations[$itemId]['amt'] = $currentIntraAmt + $creditAmount;
                $totalCredits += $creditAmount;
            } else {
                $intraAllocations[$itemId]['qty'] = $currentIntraQty - $creditQty;
                $intraAllocations[$itemId]['amt'] = $currentIntraAmt - $creditAmount;
                $totalDebits += $creditAmount;
            }

            $validatedLines[] = new ValidatedSourceLineItem(
                sourceShopInvoiceId: $invoice->id,
                shopInvoiceItemId: $item->id,
                productId: (int) $item->product_id,
                productName: (string) ($item->product?->name ?? $item->product_name),
                productGrade: (string) ($item->product_grade ?? 'A'),
                unit: (string) ($item->price_unit ?: $item->unit),
                priceUnit: $item->price_unit ? (string) $item->price_unit : null,
                originalQuantity: $origQty,
                originalUnitPrice: $origPrice,
                originalLineTotal: $origTotal,
                remainingEligibleQuantity: $remainingCreditableQty,
                remainingEligibleAmount: $remainingCreditableAmt,
                creditQuantity: $creditQty,
                creditRate: $creditRate,
                creditAmount: $creditAmount,
                direction: $direction,
                inventoryAction: $inventoryAction,
                notes: $line['notes'] ?? null,
                receivedGrade: $line['received_grade'] ?? null,
                invoice: $invoice,
                invoiceItem: $item,
            );
        }

        $totalCredits = round($totalCredits, 2);
        $totalDebits = round($totalDebits, 2);
        $netAdjustment = round(abs($totalCredits - $totalDebits), 2);
        $overallDirection = $totalDebits > $totalCredits ? ShopCreditNoteDirection::Debit : ShopCreditNoteDirection::Credit;

        return new SourceLineEligibilityResult(
            shop: $shopInstance,
            lockedInvoices: $lockedInvoices,
            lockedInvoiceItems: $lockedInvoiceItems,
            validatedLines: $validatedLines,
            totalCreditAmount: $totalCredits,
            totalDebitAmount: $totalDebits,
            netAdjustmentAmount: $netAdjustment,
            overallDirection: $overallDirection,
        );
    }

    /**
     * Calculate cumulative adjustments on an invoice item.
     *
     * @return array{
     *     approved: array{quantity_credited: float, quantity_debited: float, net_quantity: float, amount_credited: float, amount_debited: float, net_amount: float},
     *     draft: array{quantity_credited: float, quantity_debited: float, net_quantity: float, amount_credited: float, amount_debited: float, net_amount: float},
     *     voided: array{quantity_credited: float, quantity_debited: float, net_quantity: float, amount_credited: float, amount_debited: float, net_amount: float},
     *     active: array{net_quantity_credited: float, net_amount_credited: float, effective_quantity: float, effective_line_total: float, remaining_creditable_quantity: float, remaining_creditable_amount: float}
     * }
     */
    public function calculateItemAdjustments(
        ShopInvoiceItem $invoiceItem,
        ?int $excludeCreditNoteId = null
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

        $originalQty = (float) ($invoiceItem->delivered_qty ?? $invoiceItem->approved_qty ?? 0.0);
        $originalPrice = (float) $invoiceItem->unit_price;
        $originalTotal = round((float) ($invoiceItem->final_line_total ?? ($originalQty * $originalPrice)), 2);

        $remainingCreditableQty = round(max(0.0, $originalQty - $activeNetQtyCredited), 4);
        $remainingCreditableAmt = round(max(0.0, $originalTotal - $activeNetAmtCredited), 2);

        return [
            'approved' => $categories['approved'],
            'draft' => $categories['draft'],
            'voided' => $categories['voided'],
            'active' => [
                'net_quantity_credited' => $activeNetQtyCredited,
                'net_amount_credited' => $activeNetAmtCredited,
                'effective_quantity' => $remainingCreditableQty,
                'effective_line_total' => $remainingCreditableAmt,
                'remaining_creditable_quantity' => $remainingCreditableQty,
                'remaining_creditable_amount' => $remainingCreditableAmt,
            ],
        ];
    }
}
