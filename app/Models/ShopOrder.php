<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'order_number',
        'state',
        'delivery_status',
        'payment_status',
        'business_date',
        'submitted_at',
        'deadline_at',
        'update_reason',
        'created_by',
        'is_allocation_completed',
        'sorting_notes',
        'is_delivered',
        'delivered_at',
        'delivered_by',
        'delivery_notes',
        'cash_collected',
        'cash_discrepancy',
        'balance_amount',
        'finance_note',
        'total_shortage_value',
    ];

    protected $casts = [
        'business_date' => 'date',
        'submitted_at' => 'datetime',
        'deadline_at' => 'datetime',
        'is_allocation_completed' => 'boolean',
        'is_delivered' => 'boolean',
        'delivered_at' => 'datetime',
        'cash_collected' => 'decimal:2',
        'cash_discrepancy' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'total_shortage_value' => 'decimal:2',
    ];

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (empty($order->order_number)) {
                $date = Carbon::parse($order->business_date)->format('Ymd');
                do {
                    $suffix = strtoupper(bin2hex(random_bytes(2)));
                    $orderNumber = "RQ-{$date}-{$suffix}";
                } while (self::where('order_number', $orderNumber)->exists());
                $order->order_number = $orderNumber;
            }
        });
    }

    /**
     * Check if this order is editable directly (before 9:30 PM of the day before delivery).
     */
    public function canEditDirectly(): bool
    {
        if (in_array($this->state, ['approved', 'rejected'], true) || $this->is_delivered) {
            return false;
        }

        // Cutoff is 9:30 PM (21:30) of the day before the target business date.
        $cutoff = Carbon::parse($this->business_date)->subDay()->setTime(21, 30, 0);

        return now()->lessThanOrEqualTo($cutoff);
    }

    /**
     * Get the shop that owns the order.
     *
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the user who created the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the items in this order.
     *
     * @return HasMany<ShopOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class);
    }
}
