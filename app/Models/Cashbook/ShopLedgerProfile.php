<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopLedgerProfile extends Model
{
    protected $table = 'shop_ledger_profiles';

    protected $fillable = [
        'shop_id', 'uuid', 'slug', 'code', 'name', 'profile_template',
        'enabled', 'closing_mode', 'preset_id', 'client_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /** The client (e.g. Aiswarya Veg) that owns this shop. */
    public function client(): BelongsTo
    {
        return $this->belongsTo(LedgerClient::class, 'client_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /** The named preset configuration this shop follows. */
    public function preset(): BelongsTo
    {
        return $this->belongsTo(ShopConfigPreset::class, 'preset_id');
    }

    /** All entry settings for this shop. */
    public function entrySettings(): HasMany
    {
        return $this->hasMany(ShopLedgerEntrySetting::class, 'shop_id', 'shop_id');
    }
}
