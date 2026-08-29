<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopBankSettlementAdjustmentRule extends Model
{
    protected $table = 'shop_bank_settlement_adjustment_rules';

    protected $fillable = [
        'shop_id',
        'entry_type_id',
        'label',
        'direction',
        'enabled',
        'created_by',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'entry_type_id' => 'integer',
        'enabled' => 'boolean',
        'created_by' => 'integer',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function entryType(): BelongsTo
    {
        return $this->belongsTo(LedgerEntryType::class, 'entry_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dailyAdjustments(): HasMany
    {
        return $this->hasMany(ShopBankSettlementAdjustment::class, 'rule_id');
    }

    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeForEntryType(Builder $query, int $entryTypeId): Builder
    {
        return $query->where('entry_type_id', $entryTypeId);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
