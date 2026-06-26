<?php

namespace App\Models;

use App\Enums\Purchasing\POStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PurchaserCart extends Model
{
    protected $fillable = [
        'user_id',
        'supplier_id',
        'business_date',
        'status',
        'cart_number',
        'bill_number',
        'discount_amount',
        'payment_method',
        'payment_status',
        'paid_amount',
        'payment_note',
        'payment_details',
        'notes',
        'purchase_order_id',
        'goods_received_id',
        'purchase_invoice_id',
        'submitted_at',
        'whatsapp_sent_at',
        'goods_received_at',
        'bill_received_at',
        'payment_made_at',
    ];

    protected $casts = [
        'business_date' => 'date',
        'discount_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
        'goods_received_at' => 'datetime',
        'bill_received_at' => 'datetime',
        'payment_made_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'cart_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceived(): BelongsTo
    {
        return $this->belongsTo(GoodsReceived::class);
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaserCartItem::class);
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => (string) str($this->status)->replace('_', ' ')->title(),
        );
    }

    protected function workflowStatus(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->status === 'submitted') {
                    $grnStatus = $this->goodsReceived?->status;

                    return match ($grnStatus) {
                        'approved' => 'approved',
                        'recheck_required' => 'rejected',
                        default => 'submitted',
                    };
                }

                if ($this->whatsapp_sent_at !== null) {
                    return 'whatsapp_sent';
                }

                return 'draft';
            },
        );
    }

    protected function workflowLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => (string) str($this->workflow_status)->replace('_', ' ')->title(),
        );
    }

    public static function generateCartNumber(Carbon|string $date): string
    {
        $formattedDate = Carbon::parse($date)->format('Ymd');

        do {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
            $cartNumber = "VC-{$formattedDate}-{$suffix}";
        } while (self::query()->where('cart_number', $cartNumber)->exists());

        return $cartNumber;
    }

    public static function cancelOverdueCartsAndOrders(Carbon $operationalDate): void
    {
        self::query()
            ->whereDate('business_date', '<', $operationalDate)
            ->where('status', 'draft')
            ->update(['status' => 'cancelled']);

        PurchaseOrder::query()
            ->whereDate('order_date', '<', $operationalDate)
            ->whereIn('status', [
                POStatus::Draft,
                POStatus::Approved,
                POStatus::SentToSupplier,
                POStatus::PartiallyReceived,
            ])
            ->update(['status' => POStatus::Cancelled]);
    }
}
