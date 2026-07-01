<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PurchaserCorrectionRequest extends Model
{
    private static ?bool $hasPublicUuidColumn = null;

    protected $fillable = [
        'public_uuid',
        'business_date',
        'shop_order_item_id',
        'current_approved_qty',
        'proposed_corrected_qty',
        'purchaser_note',
        'requester_user_id',
        'status',
        'reviewer_user_id',
        'review_note',
        'reviewed_at',
    ];

    public function getRouteKeyName(): string
    {
        return static::hasPublicUuidColumn() ? 'public_uuid' : $this->getKeyName();
    }

    public function getRouteKey(): mixed
    {
        if (static::hasPublicUuidColumn() && $this->public_uuid) {
            return $this->public_uuid;
        }

        return $this->getKey();
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if (static::hasPublicUuidColumn() && ! $request->public_uuid) {
                $request->public_uuid = (string) Str::uuid();
            }
        });
    }

    public static function hasPublicUuidColumn(): bool
    {
        return self::$hasPublicUuidColumn ??= Schema::hasColumn('purchaser_correction_requests', 'public_uuid');
    }

    protected $casts = [
        'business_date' => 'date',
        'current_approved_qty' => 'decimal:2',
        'proposed_corrected_qty' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function shopOrderItem(): BelongsTo
    {
        return $this->belongsTo(ShopOrderItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
