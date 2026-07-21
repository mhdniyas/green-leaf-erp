<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopOrderChangeRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopOrderChangeRequest extends Model
{
    /** @use HasFactory<ShopOrderChangeRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_order_id',
        'shop_order_revision_id',
        'type',
        'status',
        'requested_by',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
        'reason',
        'manager_note',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ShopOrder, $this>
     */
    public function shopOrder(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class);
    }

    /**
     * @return BelongsTo<ShopOrderRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ShopOrderRevision::class, 'shop_order_revision_id');
    }

    /**
     * @return HasMany<ShopOrderChangeRequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderChangeRequestItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
