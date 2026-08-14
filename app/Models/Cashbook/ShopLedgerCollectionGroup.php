<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopLedgerCollectionGroup extends Model
{
    protected $table = 'shop_ledger_collection_groups';

    protected $fillable = [
        'shop_id', 'name', 'code', 'enabled', 'display_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function entryTypes(): HasMany
    {
        return $this->hasMany(ShopLedgerCollectionGroupEntryType::class, 'collection_group_id');
    }
}
