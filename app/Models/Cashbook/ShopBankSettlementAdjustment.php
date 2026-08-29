<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopBankSettlementAdjustment extends Model
{
    protected $table = 'shop_bank_settlement_adjustments';

    protected $fillable = [
        'shop_id',
        'business_date',
        'entry_type_id',
        'rule_id',
        'label',
        'direction',
        'amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'business_date' => 'date:Y-m-d',
        'entry_type_id' => 'integer',
        'rule_id' => 'integer',
        'amount' => 'decimal:2',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function entryType(): BelongsTo
    {
        return $this->belongsTo(LedgerEntryType::class, 'entry_type_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ShopBankSettlementAdjustmentRule::class, 'rule_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeForBusinessDate(Builder $query, string $businessDate): Builder
    {
        return $query->whereDate('business_date', $businessDate);
    }

    public function scopeForEntryType(Builder $query, int $entryTypeId): Builder
    {
        return $query->where('entry_type_id', $entryTypeId);
    }
}
