<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopCreditApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopCreditApplication extends Model
{
    /** @use HasFactory<ShopCreditApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_credit_note_id',
        'shop_invoice_id',
        'shop_id',
        'amount',
        'applied_at',
        'applied_by',
        'notes',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'applied_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(ShopCreditNote::class, 'shop_credit_note_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ShopInvoice::class, 'shop_invoice_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('reversed_at');
    }

    #[Scope]
    protected function reversed(Builder $query): void
    {
        $query->whereNotNull('reversed_at');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    public function isActive(): bool
    {
        return $this->reversed_at === null;
    }
}
