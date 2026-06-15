<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaserCorrectionRequest extends Model
{
    protected $fillable = [
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
