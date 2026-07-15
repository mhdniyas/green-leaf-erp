<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopCreditFactory;
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
        'created_by',
        'business_date',
    ];

    protected function casts(): array
    {
        return [
            'is_petty_cash' => 'boolean',
            'amount' => 'decimal:2',
            'business_date' => 'date',
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
}
