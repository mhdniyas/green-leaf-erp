<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Services\ShopInvoices\ShopFinalBill;
use App\Services\ShopInvoices\ShopFinalBillService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ShopInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'shop_order_id',
        'invoice_number',
        'business_date',
        'status',
        'delivery_status',
        'payment_status',
        'subtotal',
        'shortage_total',
        'excess_total',
        'discount_total',
        'final_total',
        'paid_amount',
        'balance_amount',
        'delivery_note',
        'payment_note',
        'discount_note',
        'admin_price_note',
        'generated_by',
        'delivery_confirmed_by',
        'delivery_confirmed_at',
        'discount_approved_by',
        'discount_approved_at',
        'payment_approved_by',
        'payment_approved_at',
        'price_updated_by',
        'price_updated_at',
        'finalized_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'subtotal' => 'decimal:2',
            'shortage_total' => 'decimal:2',
            'excess_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'final_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'delivery_confirmed_at' => 'datetime',
            'discount_approved_at' => 'datetime',
            'payment_approved_at' => 'datetime',
            'price_updated_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ShopInvoice $invoice): void {
            if ($invoice->isFinalized() || $invoice->creditNotes()->exists()) {
                throw new \RuntimeException(
                    "Cannot delete invoice {$invoice->invoice_number}: Finalized invoices and invoices with associated credit notes cannot be deleted."
                );
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'invoice_number';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'shop_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopInvoiceItem::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(ShopInvoicePaymentRequest::class)->latest('id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(ShopCreditNote::class, 'shop_invoice_id');
    }

    public function approvedCreditNotes(): HasMany
    {
        return $this->hasMany(ShopCreditNote::class, 'shop_invoice_id')
            ->where('status', ShopCreditNoteStatus::Approved->value);
    }

    /**
     * Total approved credit adjustment amount (reducing shop payable).
     */
    public function approvedCreditTotal(): float
    {
        if ($this->relationLoaded('creditNotes')) {
            return round((float) $this->creditNotes
                ->filter(fn ($cn): bool => ($cn->status === ShopCreditNoteStatus::Approved || $cn->status === 'approved')
                    && ($cn->direction === ShopCreditNoteDirection::Credit || $cn->direction === 'credit'))
                ->sum('total_credit_amount'), 2);
        }

        return round((float) $this->creditNotes()
            ->where('status', ShopCreditNoteStatus::Approved->value)
            ->where('direction', ShopCreditNoteDirection::Credit->value)
            ->sum('total_credit_amount'), 2);
    }

    /**
     * Total approved debit adjustment amount (increasing shop payable).
     */
    public function approvedDebitTotal(): float
    {
        if ($this->relationLoaded('creditNotes')) {
            return round((float) $this->creditNotes
                ->filter(fn ($cn): bool => ($cn->status === ShopCreditNoteStatus::Approved || $cn->status === 'approved')
                    && ($cn->direction === ShopCreditNoteDirection::Debit || $cn->direction === 'debit'))
                ->sum('total_credit_amount'), 2);
        }

        return round((float) $this->creditNotes()
            ->where('status', ShopCreditNoteStatus::Approved->value)
            ->where('direction', ShopCreditNoteDirection::Debit->value)
            ->sum('total_credit_amount'), 2);
    }

    /**
     * Signed net adjustment from all approved credit/debit notes:
     * Positive means net debit (increase in bill), negative means net credit (reduction in bill).
     */
    public function netApprovedAdjustment(): float
    {
        return round($this->approvedDebitTotal() - $this->approvedCreditTotal(), 2);
    }

    /**
     * Effective final total after applying all approved credit notes and debit notes.
     * Original finalized final_total remains unchanged in the database.
     */
    public function effectiveFinalTotal(): float
    {
        $baseTotal = round((float) $this->final_total, 2);

        return round(max(0.00, $baseTotal - $this->approvedCreditTotal() + $this->approvedDebitTotal()), 2);
    }

    /**
     * Effective balance amount due after applying approved adjustments and recorded payments.
     */
    public function effectiveBalanceAmount(): float
    {
        $effectiveTotal = $this->effectiveFinalTotal();
        $paid = round((float) $this->paid_amount, 2);

        return round(max(0.00, $effectiveTotal - $paid), 2);
    }

    /**
     * Explicit liability / overpayment credit when payments exceed the effective final total.
     */
    public function overpaidAmount(): float
    {
        $effectiveTotal = $this->effectiveFinalTotal();
        $paid = round((float) $this->paid_amount, 2);

        return round(max(0.00, $paid - $effectiveTotal), 2);
    }

    public function isOverpaid(): bool
    {
        return $this->overpaidAmount() > 0.0001;
    }

    public function originalInvoiceTotal(): float
    {
        return round((float) $this->final_total, 2);
    }

    public function amountPaid(): float
    {
        return round((float) $this->paid_amount, 2);
    }

    public function netPayable(): float
    {
        return $this->effectiveFinalTotal();
    }

    public function remainingBalance(): float
    {
        return $this->effectiveBalanceAmount();
    }

    public function overpaymentCredit(): float
    {
        return $this->overpaidAmount();
    }

    /**
     * Canonical, read-only final bill representation for this invoice.
     */
    public function finalBill(): ShopFinalBill
    {
        return app(ShopFinalBillService::class)->calculate($this);
    }

    /**
     * Effective payment status based on credit-adjusted net payable and payments.
     */
    public function effectivePaymentStatus(): string
    {
        return $this->finalBill()->effectivePaymentStatus;
    }

    /**
     * Complete financial reconciliation breakdown preserving separate original, adjusted, paid, and balance figures.
     *
     * @return array{
     *     original_invoice_total: float,
     *     approved_credit_total: float,
     *     approved_debit_total: float,
     *     net_payable: float,
     *     amount_paid: float,
     *     remaining_balance: float,
     *     overpayment_credit: float,
     *     has_adjustments: bool
     * }
     */
    public function financialReconciliationSummary(): array
    {
        $orig = $this->originalInvoiceTotal();
        $credit = $this->approvedCreditTotal();
        $debit = $this->approvedDebitTotal();
        $netPayable = $this->netPayable();
        $paid = $this->amountPaid();
        $balance = $this->remainingBalance();
        $overpayment = $this->overpaymentCredit();

        return [
            'original_invoice_total' => $orig,
            'approved_credit_total' => $credit,
            'approved_debit_total' => $debit,
            'net_payable' => $netPayable,
            'amount_paid' => $paid,
            'remaining_balance' => $balance,
            'overpayment_credit' => $overpayment,
            'has_adjustments' => ($credit > 0.0001 || $debit > 0.0001),
        ];
    }

    public function isFinalLocked(): bool
    {
        $hasZeroOrNegativeBalance = (float) $this->balance_amount <= 0.0001;
        $hasBillableTotal = (float) $this->final_total > 0.0001;

        return $this->finalized_at !== null
            || in_array($this->delivery_status, ['received_full', 'approved_after_discrepancy'], true)
            || in_array($this->status, ['finalized', 'payment_pending'], true)
            || ($this->status === 'paid' && $hasZeroOrNegativeBalance && $hasBillableTotal);
    }

    public function isBillingProtected(): bool
    {
        if (! $this->exists) {
            return false;
        }

        if ($this->finalized_at !== null) {
            return true;
        }

        if (in_array((string) $this->status, ['delivery_review', 'payment_pending', 'finalized', 'paid', 'cancelled'], true)) {
            return true;
        }

        if (in_array((string) $this->delivery_status, ['in_transit', 'awaiting_review', 'received_full', 'received_with_discrepancy', 'approved_after_discrepancy'], true)) {
            return true;
        }

        if ((float) $this->paid_amount > 0.0001 || $this->payment_approved_at !== null) {
            return true;
        }

        $order = $this->relationLoaded('order') ? $this->order : $this->order()->first();
        if ($order && in_array((string) $order->delivery_status, ['in_transit', 'delivered', 'pending_approval', 'partially_delivered'], true)) {
            return true;
        }

        if ($this->status === 'generated') {
            if (! $order) {
                return true;
            }

            if (! in_array((string) $order->delivery_status, ['not_started', 'loading', 'sorting', 'ready_for_dispatch', 'pending_delivery'], true)) {
                return true;
            }
        }

        return false;
    }

    public function isFinalized(): bool
    {
        return $this->finalized_at !== null || in_array($this->status, ['finalized', 'payment_pending', 'paid'], true);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function paymentApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_approved_by');
    }

    public function discountApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_approved_by');
    }

    public function deliveryConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_confirmed_by');
    }

    public function priceUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'price_updated_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /**
     * Get summary of all actual invoice adjustments for the Credit Note / Invoice Changes section.
     *
     * @return array{
     *     rows: Collection<int, array{
     *         product_id: int,
     *         product_name: string,
     *         unit: string,
     *         previous_qty: float,
     *         revised_qty: float,
     *         rate: float,
     *         previous_amount: float,
     *         revised_amount: float,
     *         difference: float
     *     }>,
     *     has_changes: bool,
     *     previous_invoice_total: float,
     *     revised_invoice_total: float,
     *     net_difference: float,
     *     is_verified: bool,
     *     verified_by_name: ?string,
     *     verified_at: ?CarbonInterface
     * }
     */
    public function creditNoteSummary(): array
    {
        $activities = Activity::query()
            ->where('subject_type', self::class)
            ->where('subject_id', $this->id)
            ->oldest('id')
            ->get()
            ->filter(fn ($a): bool => data_get($a->properties, 'source') === 'admin_item_adjustment');

        $itemAdjustmentGroups = $activities->groupBy(function ($a): string {
            $itemId = data_get($a->properties, 'after.item_id') ?: data_get($a->properties, 'before.item_id');
            if ($itemId) {
                return 'item:'.$itemId;
            }

            $productId = data_get($a->properties, 'after.product_id') ?: data_get($a->properties, 'before.product_id');
            $grade = data_get($a->properties, 'after.product_grade') ?: data_get($a->properties, 'before.product_grade') ?: 'A';

            return 'pg:'.$productId.'|'.$grade;
        });

        $items = $this->relationLoaded('items') ? $this->items : $this->items()->with('product')->get();
        $itemsById = $items->keyBy('id');
        $itemsByProductGrade = $items->keyBy(fn ($i): string => $i->product_id.'|'.($i->product_grade ?? 'A'));

        $rows = collect();
        $netDifference = 0.0;

        foreach ($itemAdjustmentGroups as $groupKey => $group) {
            $firstAdj = $group->first();
            $lastAdj = $group->last();

            $itemId = data_get($lastAdj->properties, 'after.item_id') ?: data_get($firstAdj->properties, 'before.item_id');
            $productId = (int) (data_get($lastAdj->properties, 'after.product_id') ?: data_get($firstAdj->properties, 'before.product_id'));
            $grade = (string) (data_get($lastAdj->properties, 'after.product_grade') ?: data_get($firstAdj->properties, 'before.product_grade') ?: 'A');

            $item = $itemId ? $itemsById->get((int) $itemId) : $itemsByProductGrade->get($productId.'|'.$grade);
            if (! $item && ! $itemId) {
                $item = $items->firstWhere('product_id', $productId);
            }
            if ($item && $item->product_grade) {
                $grade = (string) $item->product_grade;
            }

            $beforeQty = (float) data_get($firstAdj->properties, 'before.qty');
            $beforePrice = (float) (data_get($firstAdj->properties, 'before.price') ?: 0);
            $beforeAmount = (float) (data_get($firstAdj->properties, 'before.amount') ?: round($beforeQty * $beforePrice, 2));

            if ($item) {
                $afterQty = (float) ($item->delivered_qty ?? $item->approved_qty ?? 0);
                $afterPrice = (float) $item->unit_price;
                $afterAmount = round((float) ($item->final_line_total ?? ($item->delivered_price_quantity * $item->unit_price) ?? ($afterQty * $afterPrice)), 2);
            } else {
                $afterQty = (float) (data_get($lastAdj->properties, 'after.qty') ?? 0);
                $afterPrice = (float) (data_get($lastAdj->properties, 'after.price') ?: $beforePrice);
                $afterAmount = (float) (data_get($lastAdj->properties, 'after.amount') ?: round($afterQty * $afterPrice, 2));
            }

            if ($beforePrice <= 0.0) {
                $beforePrice = $afterPrice;
                $beforeAmount = round($beforeQty * $beforePrice, 2);
            }
            if ($afterPrice <= 0.0) {
                $afterPrice = $beforePrice;
                $afterAmount = round($afterQty * $afterPrice, 2);
            }

            $rate = $afterPrice > 0 ? $afterPrice : $beforePrice;
            $difference = round($afterAmount - $beforeAmount, 2);

            $qtyChanged = abs($afterQty - $beforeQty) > 0.0001;
            $priceChanged = abs($afterPrice - $beforePrice) > 0.001;
            $amountChanged = abs($difference) >= 0.01;

            if (! $qtyChanged && ! $priceChanged && ! $amountChanged) {
                continue;
            }

            $netDifference += $difference;

            $baseProductName = (string) ($item?->product?->name ?? $item?->product_name ?? data_get($lastAdj->properties, 'after.product_name') ?? data_get($firstAdj->properties, 'before.product_name') ?? 'Unknown Product');
            $productName = $grade !== 'A' ? "{$baseProductName} — Grade {$grade}" : $baseProductName;
            $unit = ProductUnit::normalizeUnit($item?->price_unit ?: $item?->unit ?: 'kg');

            $rows->push([
                'item_id' => $item?->id ?: ($itemId ? (int) $itemId : null),
                'product_id' => (int) $productId,
                'product_grade' => $grade,
                'product_name' => $productName,
                'unit' => $unit,
                'previous_qty' => $beforeQty,
                'revised_qty' => $afterQty,
                'rate' => $rate,
                'previous_amount' => $beforeAmount,
                'revised_amount' => $afterAmount,
                'difference' => $difference,
            ]);
        }

        $revisedInvoiceTotal = round((float) ($this->final_total ?? $this->subtotal ?? 0), 2);
        $netDifference = round($netDifference, 2);
        $previousInvoiceTotal = round($revisedInvoiceTotal - $netDifference, 2);

        $hasChanges = $rows->isNotEmpty();

        $latestVerification = Activity::query()
            ->where('subject_type', self::class)
            ->where('subject_id', $this->id)
            ->where(function ($q): void {
                $q->where('description', 'shop_changes_verified')
                    ->orWhere('properties->source', 'shop_changes_verified');
            })
            ->latest('id')
            ->first();

        $latestAdjustment = $activities->last();

        $isVerified = false;
        $verifiedByName = null;
        $verifiedAt = null;

        if (! $hasChanges) {
            $isVerified = true;
        } elseif ($latestVerification !== null) {
            if ($latestAdjustment === null || $latestVerification->id >= $latestAdjustment->id) {
                $isVerified = true;
                $verifiedByName = $latestVerification->causer?->name ?? 'Shop Manager';
                $verifiedAt = $latestVerification->created_at;
            }
        }

        return [
            'rows' => $rows,
            'has_changes' => $hasChanges,
            'previous_invoice_total' => $previousInvoiceTotal,
            'revised_invoice_total' => $revisedInvoiceTotal,
            'net_difference' => $netDifference,
            'is_verified' => $isVerified,
            'verified_by_name' => $verifiedByName,
            'verified_at' => $verifiedAt,
        ];
    }
}
