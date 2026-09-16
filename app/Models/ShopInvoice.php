<?php

declare(strict_types=1);

namespace App\Models;

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
     * Get credit note summary of quantity reductions recorded in invoice edit/change history.
     *
     * @return array{
     *     rows: Collection<int, array{
     *         product_name: string,
     *         unit: string,
     *         credit_qty: float,
     *         rate: float,
     *         credit_amount: float
     *     }>,
     *     total_credit_note: float
     * }
     */
    public function creditNoteSummary(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->with('product')->get();

        $activities = Activity::query()
            ->where('subject_type', self::class)
            ->where('subject_id', $this->id)
            ->latest('id')
            ->get()
            ->filter(fn ($a): bool => in_array(data_get($a->properties, 'source'), ['admin_item_adjustment', 'admin_delivery_review_finalized'], true));

        $itemAdjustments = $activities
            ->filter(fn ($a): bool => data_get($a->properties, 'source') === 'admin_item_adjustment')
            ->keyBy(fn ($a): int => (int) (data_get($a->properties, 'after.product_id') ?: data_get($a->properties, 'before.product_id')));

        $finalizedActivity = $activities->firstWhere(fn ($a): bool => data_get($a->properties, 'source') === 'admin_delivery_review_finalized');
        $resolutionsByProduct = collect();

        if ($finalizedActivity && is_array(data_get($finalizedActivity->properties, 'inventory_resolutions'))) {
            foreach (data_get($finalizedActivity->properties, 'inventory_resolutions') as $res) {
                $productId = (int) data_get($res, 'product_id');
                $diffQty = (float) data_get($res, 'difference_qty');
                $resQty = (float) data_get($res, 'resolution_qty');

                if ($diffQty < 0 || $resQty > 0) {
                    $resolutionsByProduct->put($productId, [
                        'credit_qty' => $diffQty < 0 ? abs($diffQty) : $resQty,
                    ]);
                }
            }
        }

        $rows = collect();
        $totalCreditNote = 0.0;

        foreach ($items as $item) {
            $productId = (int) $item->product_id;
            $unitPrice = (float) $item->unit_price;

            $creditQty = 0.0;
            $rate = $unitPrice;
            $beforeQty = (float) ($item->price_quantity ?: $item->approved_qty ?: 0);
            $afterQty = (float) ($item->delivered_price_quantity ?: $item->delivered_qty ?: 0);

            if ($itemAdjustments->has($productId)) {
                $adj = $itemAdjustments->get($productId);
                $beforeQty = (float) data_get($adj->properties, 'before.qty');
                $afterQty = (float) data_get($adj->properties, 'after.qty');
                $adjPrice = (float) (data_get($adj->properties, 'after.price') ?: data_get($adj->properties, 'before.price') ?: $unitPrice);

                if ($beforeQty > $afterQty) {
                    $creditQty = round($beforeQty - $afterQty, 4);
                    $rate = $adjPrice;
                }
            } elseif ($resolutionsByProduct->has($productId)) {
                $res = $resolutionsByProduct->get($productId);
                $creditQty = round((float) $res['credit_qty'], 4);
                $afterQty = (float) ($item->delivered_price_quantity ?: $item->delivered_qty ?: 0);
                $beforeQty = round($afterQty + $creditQty, 4);
            } elseif ((float) ($item->shortage_qty ?? 0) > 0) {
                $creditQty = round((float) $item->shortage_qty, 4);
                $afterQty = (float) ($item->delivered_price_quantity ?: $item->delivered_qty ?: 0);
                $beforeQty = round($afterQty + $creditQty, 4);
            }

            if ($creditQty > 0.0001) {
                $creditAmount = round($creditQty * $rate, 2);
                $totalCreditNote += $creditAmount;

                $rows->push([
                    'product_name' => (string) ($item->product?->name ?? $item->product_name ?? 'Unknown Product'),
                    'unit' => ProductUnit::normalizeUnit($item->price_unit ?: $item->unit),
                    'previous_qty' => $beforeQty,
                    'final_qty' => $afterQty,
                    'credit_qty' => $creditQty,
                    'rate' => $rate,
                    'credit_amount' => $creditAmount,
                ]);
            }
        }

        $revisedInvoiceTotal = round((float) ($this->final_total ?? $this->subtotal ?? 0), 2);
        $totalCreditNote = round($totalCreditNote, 2);
        $originalInvoiceTotal = round($revisedInvoiceTotal + $totalCreditNote, 2);

        return [
            'rows' => $rows,
            'original_invoice_total' => $originalInvoiceTotal,
            'revised_invoice_total' => $revisedInvoiceTotal,
            'total_credit_note' => $totalCreditNote,
        ];
    }
}
