<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inventory\StockMovementType;
use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteReceiptStatus;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Cashbook\ShopLedgerTransaction;
use Database\Factories\ShopCreditNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShopCreditNote extends Model
{
    /** @use HasFactory<ShopCreditNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'credit_note_number',
        'shop_id',
        'shop_invoice_id',
        'credit_note_date',
        'direction',
        'accounting_mode',
        'reason_type',
        'status',
        'inventory_action',
        'discount_amount',
        'discount_reason',
        'subtotal',
        'total_credit_amount',
        'lock_version',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'credit_note_date' => 'date',
            'direction' => ShopCreditNoteDirection::class,
            'accounting_mode' => ShopCreditNoteAccountingMode::class,
            'reason_type' => ShopCreditNoteReason::class,
            'status' => ShopCreditNoteStatus::class,
            'inventory_action' => ShopCreditNoteInventoryAction::class,
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total_credit_amount' => 'decimal:2',
            'lock_version' => 'integer',
            'approved_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ShopCreditNote $creditNote): void {
            if ($creditNote->isApproved()) {
                throw new \RuntimeException(
                    "Cannot delete approved credit note {$creditNote->credit_note_number}. Approved credit notes must be voided, not deleted."
                );
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'credit_note_number';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ShopInvoice::class, 'shop_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopCreditNoteItem::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ShopCreditApplication::class, 'shop_credit_note_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ShopCreditNoteReceipt::class, 'shop_credit_note_id');
    }

    public function activeReceipts(): HasMany
    {
        return $this->hasMany(ShopCreditNoteReceipt::class, 'shop_credit_note_id')
            ->whereIn('status', ['verified', 'damaged'])
            ->whereNull('reversed_at');
    }

    /**
     * Determine whether this credit note has active, unreconciled physical returns.
     */
    public function hasUnreconciledPhysicalReturns(): bool
    {
        if ($this->receipts()->exists()) {
            return $this->receipts()
                ->whereIn('status', ['verified', 'damaged'])
                ->whereNull('reversed_at')
                ->exists();
        }

        if ($this->stockMovements()->exists()) {
            return true;
        }

        return StockMovement::query()
            ->where('type', StockMovementType::SaleReversal->value)
            ->where('notes', 'like', "%SCN: {$this->credit_note_number}%")
            ->exists();
    }

    public function activeApplications(): HasMany
    {
        return $this->hasMany(ShopCreditApplication::class, 'shop_credit_note_id')
            ->whereNull('reversed_at');
    }

    public function appliedCreditTotal(): float
    {
        if ($this->relationLoaded('activeApplications')) {
            return round((float) $this->activeApplications->sum('amount'), 2);
        }

        return round((float) $this->activeApplications()->sum('amount'), 2);
    }

    public function unappliedCreditTotal(): float
    {
        if ($this->isVoided()) {
            return 0.00;
        }

        return round(max(0.00, (float) $this->total_credit_amount - $this->appliedCreditTotal()), 2);
    }

    /**
     * Summarize physical returns for this Credit Note.
     *
     * @return array{
     *     has_physical_return: bool,
     *     status: string,
     *     status_label: string,
     *     expected_quantity: float,
     *     received_quantity: float,
     *     remaining_quantity: float,
     *     items_count: int
     * }
     */
    public function physicalReturnSummary(): array
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->get();

        $returnItems = $items->filter(
            fn (ShopCreditNoteItem $it) => $it->inventory_action === ShopCreditNoteInventoryAction::ReturnToStock
        );

        if ($returnItems->isEmpty()) {
            return [
                'has_physical_return' => false,
                'status' => 'not_applicable',
                'status_label' => 'Financial Only',
                'expected_quantity' => 0.0,
                'received_quantity' => 0.0,
                'remaining_quantity' => 0.0,
                'items_count' => 0,
            ];
        }

        $expectedQty = (float) $returnItems->sum('adjusted_quantity');
        $receivedQty = (float) $returnItems->sum('received_quantity');
        $remainingQty = max(0.0, $expectedQty - $receivedQty);

        $hasDispute = $returnItems->contains(fn ($it) => $it->receipt_status === ShopCreditNoteReceiptStatus::Disputed);
        $allVerified = $returnItems->every(fn ($it) => $it->receipt_status === ShopCreditNoteReceiptStatus::ReceivedAndVerified);
        $anyPartial = $returnItems->contains(fn ($it) => $it->receipt_status === ShopCreditNoteReceiptStatus::PartiallyReceived || (float) $it->received_quantity > 0.0001);

        // Check if all previously recorded receipts were reversed
        $allReversed = false;
        if (! $hasDispute && ! $allVerified && ! $anyPartial) {
            $receipts = $this->relationLoaded('receipts') ? $this->receipts : $this->receipts()->get();
            $allReversed = $receipts->isNotEmpty() && $receipts->every(fn ($r) => $r->isReversed());
        }

        $status = match (true) {
            $hasDispute => 'disputed',
            $allVerified => 'received_and_verified',
            $anyPartial => 'partially_received',
            $allReversed => 'reconciled',
            default => 'pending_receipt',
        };

        $statusLabel = match ($status) {
            'disputed' => 'Disputed',
            'received_and_verified' => 'Fully Verified',
            'partially_received' => 'Partially Received',
            'reconciled' => 'Reconciled / Reversed',
            default => 'Pending Receipt',
        };

        return [
            'has_physical_return' => true,
            'status' => $status,
            'status_label' => $statusLabel,
            'expected_quantity' => round($expectedQty, 3),
            'received_quantity' => round($receivedQty, 3),
            'remaining_quantity' => round($remainingQty, 3),
            'items_count' => $returnItems->count(),
        ];
    }

    /**
     * Get distinct source invoice numbers referenced by this Credit Note.
     *
     * @return array<int, string>
     */
    public function sourceInvoiceNumbers(): array
    {
        if ($this->isLegacyMode() && $this->invoice) {
            return [$this->invoice->invoice_number];
        }

        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->with(['sourceInvoice', 'invoiceItem.invoice'])->get();

        $numbers = [];
        foreach ($items as $item) {
            $inv = $item->sourceInvoice ?? $item->invoiceItem?->invoice;
            if ($inv && filled($inv->invoice_number)) {
                $numbers[] = $inv->invoice_number;
            }
        }

        if (empty($numbers) && $this->invoice) {
            $numbers[] = $this->invoice->invoice_number;
        }

        return array_values(array_unique($numbers));
    }

    public function isLegacyMode(): bool
    {
        return ($this->accounting_mode ?? ShopCreditNoteAccountingMode::LegacyInvoiceLinked) === ShopCreditNoteAccountingMode::LegacyInvoiceLinked;
    }

    public function isShopLevelMode(): bool
    {
        return $this->accounting_mode === ShopCreditNoteAccountingMode::ShopLevel;
    }

    public function ledgerTransaction(): HasOne
    {
        return $this->hasOne(ShopLedgerTransaction::class, 'reference_id')
            ->where(function ($q): void {
                $q->where('reference_type', self::class)
                    ->orWhere('reference_type', 'App\\Models\\ShopCreditNote');
            });
    }

    /**
     * Stock movements generated by this credit note's physical returns.
     *
     * @return Builder<StockMovement>
     */
    public function stockMovements(): Builder
    {
        return StockMovement::query()
            ->where('type', StockMovementType::SaleReversal->value)
            ->where('notes', 'like', "Credit Note Return — SCN: {$this->credit_note_number};%");
    }

    public function totalRestoredStockQuantity(): float
    {
        return round((float) $this->stockMovements()->sum('quantity'), 3);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approver(): BelongsTo
    {
        return $this->approvedBy();
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', ShopCreditNoteStatus::Approved->value);
    }

    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('status', ShopCreditNoteStatus::Draft->value);
    }

    public function isApproved(): bool
    {
        return $this->status === ShopCreditNoteStatus::Approved;
    }

    public function isDraft(): bool
    {
        return $this->status === ShopCreditNoteStatus::Draft;
    }

    public function isVoided(): bool
    {
        return $this->status === ShopCreditNoteStatus::Voided;
    }

    public function isCredit(): bool
    {
        return $this->direction === ShopCreditNoteDirection::Credit;
    }

    public function isDebit(): bool
    {
        return $this->direction === ShopCreditNoteDirection::Debit;
    }

    public function creditTotal(): float
    {
        $this->loadMissing('items');

        return round((float) $this->items->where('direction', ShopCreditNoteDirection::Credit)->sum('line_credit_amount'), 2);
    }

    public function debitTotal(): float
    {
        $this->loadMissing('items');

        return round((float) $this->items->where('direction', ShopCreditNoteDirection::Debit)->sum('line_credit_amount'), 2);
    }

    /**
     * Signed net impact on shop payable balance:
     * Negative reduces payable (Credit), Positive increases payable (Debit).
     */
    public function netPayableAdjustment(): float
    {
        return $this->isDebit()
            ? round((float) $this->total_credit_amount, 2)
            : -round((float) $this->total_credit_amount, 2);
    }

    public function isNetZero(): bool
    {
        return (float) $this->total_credit_amount <= 0.0001;
    }

    /**
     * Recalculate and update subtotal, direction, and total credit amount from related line items.
     */
    public function recalculateTotals(): self
    {
        $this->loadMissing('items');

        $creditTotal = $this->creditTotal();
        $debitTotal = $this->debitTotal();
        $grossSubtotal = round($creditTotal + $debitTotal, 2);
        $netAmount = round(abs($creditTotal - $debitTotal), 2);
        $direction = $debitTotal > $creditTotal ? ShopCreditNoteDirection::Debit : ShopCreditNoteDirection::Credit;
        $totalAmount = $netAmount > 0.0001 ? $netAmount : $grossSubtotal;

        $this->update([
            'direction' => $direction,
            'subtotal' => $grossSubtotal,
            'total_credit_amount' => $totalAmount,
        ]);

        return $this;
    }

    /**
     * Generate standard unique credit note number for a given shop invoice.
     * E.g. SCN-20261105-SHOP001-01
     */
    public static function generateCreditNoteNumber(ShopInvoice $invoice): string
    {
        $invoice->loadMissing('shop');

        $dateStr = ($invoice->business_date ?? today())->format('Ymd');
        $shopCode = strtoupper((string) ($invoice->shop?->code ?? sprintf('SHOP%04d', $invoice->shop_id)));
        $base = sprintf('SCN-%s-%s', $dateStr, $shopCode);

        $existingCount = static::query()
            ->where('shop_invoice_id', $invoice->id)
            ->count();

        $sequence = $existingCount + 1;
        $number = sprintf('%s-%02d', $base, $sequence);

        while (static::query()->where('credit_note_number', $number)->exists()) {
            $sequence++;
            $number = sprintf('%s-%02d', $base, $sequence);
        }

        return $number;
    }
}
