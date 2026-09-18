<?php

declare(strict_types=1);

namespace App\Models;

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

    public function isFinalLocked(): bool
    {
        $hasZeroOrNegativeBalance = (float) $this->balance_amount <= 0.0001;
        $hasBillableTotal = (float) $this->final_total > 0.0001;

        return $this->finalized_at !== null
            || in_array($this->delivery_status, ['received_full', 'approved_after_discrepancy'], true)
            || in_array($this->status, ['finalized', 'payment_pending'], true)
            || ($this->status === 'paid' && $hasZeroOrNegativeBalance && $hasBillableTotal);
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

        $productAdjustmentGroups = $activities->groupBy(
            fn ($a): int => (int) (data_get($a->properties, 'after.product_id') ?: data_get($a->properties, 'before.product_id'))
        );

        $itemsByProductId = ($this->relationLoaded('items') ? $this->items : $this->items()->with('product')->get())->keyBy('product_id');

        $rows = collect();
        $netDifference = 0.0;

        foreach ($productAdjustmentGroups as $productId => $group) {
            $firstAdj = $group->first();
            $lastAdj = $group->last();

            $item = $itemsByProductId->get($productId);

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

            $productName = (string) ($item?->product?->name ?? $item?->product_name ?? data_get($lastAdj->properties, 'after.product_name') ?? data_get($firstAdj->properties, 'before.product_name') ?? 'Unknown Product');
            $unit = ProductUnit::normalizeUnit($item?->price_unit ?: $item?->unit ?: 'kg');

            $rows->push([
                'product_id' => (int) $productId,
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
