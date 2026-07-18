<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopCreditFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShopCredit extends Model
{
    /** @use HasFactory<ShopCreditFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'type',
        'is_petty_cash',
        'amount',
        'description',
        'admin_note',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'business_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_petty_cash' => 'boolean',
            'amount' => 'decimal:2',
            'business_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', 'approved');
    }

    public function isAccountingOut(): bool
    {
        if (Str::startsWith((string) $this->description, 'Reserve amount increased')) {
            return true;
        }

        if (Str::startsWith((string) $this->description, 'Reserve amount reduced')) {
            return false;
        }

        return $this->type === 'in';
    }

    public function accountingDirection(): string
    {
        return $this->isAccountingOut() ? 'OUT' : 'IN';
    }

    public function accountingLabel(): string
    {
        return $this->isAccountingOut() ? 'Given to Shop' : 'Received from Shop';
    }

    public function accountingCategory(): string
    {
        return $this->isAccountingOut() ? 'Cash Given to Shop' : 'Cash Received from Shop';
    }

    public function signedAccountingAmount(): float
    {
        $amount = (float) $this->amount;

        return $this->isAccountingOut() ? $amount * -1 : $amount;
    }

    public function shopCashLabel(): string
    {
        return $this->type === 'in' ? 'Shop Cash Credit' : 'Cash Returned To Company';
    }

    public function shopSignedAmount(): float
    {
        $amount = (float) $this->amount;

        return $this->type === 'in' ? $amount : $amount * -1;
    }

    public function isShopCashIn(): bool
    {
        return $this->shopSignedAmount() >= 0;
    }

    public function statusLabel(): string
    {
        return match ((string) $this->status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => 'Pending Approval',
        };
    }

    public function statusTone(): string
    {
        return match ((string) $this->status) {
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'warning',
        };
    }
}
