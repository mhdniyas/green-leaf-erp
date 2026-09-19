<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Shop;
use App\Models\User;
use Database\Factories\Cashbook\ShopAccountingOpeningFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopAccountingOpening extends Model
{
    /** @use HasFactory<ShopAccountingOpeningFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'accounting_start_date',
        'opening_shop_company_balance',
        'opening_balance_direction',
        'opening_allocation_pending',
        'opening_petty_balance',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'accounting_start_date' => 'date',
            'opening_shop_company_balance' => 'decimal:2',
            'opening_allocation_pending' => 'decimal:2',
            'opening_petty_balance' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get signed shop-company opening balance:
     * Positive if shop owes company, negative if company owes shop, 0 if settled.
     */
    public function getSignedShopCompanyBalance(): float
    {
        $amount = abs((float) $this->opening_shop_company_balance);

        return match ($this->opening_balance_direction) {
            'company_owes_shop' => -$amount,
            'shop_owes_company' => $amount,
            default => 0.0,
        };
    }
}
