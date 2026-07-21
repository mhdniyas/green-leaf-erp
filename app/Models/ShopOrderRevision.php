<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopOrderRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopOrderRevision extends Model
{
    /** @use HasFactory<ShopOrderRevisionFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_order_id',
        'revision_no',
        'status',
        'reason',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'manager_note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function resolvedLabel(): string
    {
        return match ($this->status) {
            'pending' => sprintf('Update #%d Pending', $this->revision_no),
            'rejected' => sprintf('Update #%d Rejected', $this->revision_no),
            'blocked' => sprintf('Update #%d Blocked', $this->revision_no),
            'applied' => sprintf(
                'Update #%d %s',
                $this->revision_no,
                $this->isFullyAccepted() ? 'Accepted' : 'Partially Accepted'
            ),
            default => sprintf('Update #%d %s', $this->revision_no, str($this->status)->replace('_', ' ')->title()),
        };
    }

    public function isFullyAccepted(): bool
    {
        if (! $this->relationLoaded('items')) {
            return false;
        }

        return $this->items->isNotEmpty()
            && $this->items->every(
                fn (ShopOrderRevisionItem $item): bool => $item->final_approved_qty !== null
                    && abs((float) $item->final_approved_qty - (float) $item->new_requested_qty) < 0.0001
            );
    }

    public function acceptedItemsCount(): int
    {
        if (! $this->relationLoaded('items')) {
            return 0;
        }

        return $this->items
            ->filter(
                fn (ShopOrderRevisionItem $item): bool => $item->final_approved_qty !== null
                    && abs((float) $item->final_approved_qty - (float) $item->old_requested_qty) > 0.0001
            )
            ->count();
    }

    /**
     * @return BelongsTo<ShopOrder, $this>
     */
    public function shopOrder(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class);
    }

    /**
     * @return HasMany<ShopOrderRevisionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderRevisionItem::class);
    }

    /**
     * @return HasMany<ShopOrderChangeRequest, $this>
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(ShopOrderChangeRequest::class);
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
