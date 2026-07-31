<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopLoanEntry extends Model
{
    public const TypeCashGiven = 'cash_given';

    public const TypeRepayment = 'repayment';

    protected $fillable = [
        'shop_id',
        'type',
        'business_date',
        'amount',
        'title',
        'description',
        'status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'amount' => 'decimal:2',
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

    public function loanSignedAmount(): float
    {
        $amount = (float) $this->amount;

        return $this->type === self::TypeCashGiven ? $amount : -1 * $amount;
    }

    public function cashJournalDirection(): string
    {
        return $this->type === self::TypeCashGiven ? 'OUT' : 'IN';
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TypeRepayment => 'Petty returned',
            default => 'Petty given',
        };
    }
}
