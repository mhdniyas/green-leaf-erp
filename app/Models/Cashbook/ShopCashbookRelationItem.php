<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopCashbookRelationItem extends Model
{
    protected $fillable = [
        'relation_id',
        'shop_ledger_entry_setting_id',
        'header_group_id',
        'header_mode',
        'source_settlement_id',
        'role',
        'display_order',
    ];

    protected $casts = [
        'relation_id' => 'integer',
        'shop_ledger_entry_setting_id' => 'integer',
        'header_group_id' => 'integer',
        'source_settlement_id' => 'integer',
        'display_order' => 'integer',
    ];

    public function relation(): BelongsTo
    {
        return $this->belongsTo(ShopCashbookRelation::class, 'relation_id');
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ShopLedgerEntrySetting::class, 'shop_ledger_entry_setting_id');
    }

    public function headerGroup(): BelongsTo
    {
        return $this->belongsTo(ShopLedgerHeaderGroup::class, 'header_group_id');
    }

    public function sourceSettlement(): BelongsTo
    {
        return $this->belongsTo(ShopCashbookRelation::class, 'source_settlement_id');
    }
}
