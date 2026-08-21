<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopConfigPreset extends Model
{
    protected $table = 'cashbook_config_presets';

    protected $fillable = [
        'name', 'slug', 'description', 'is_default', 'enabled',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'enabled' => 'boolean',
    ];

    /** All entry-type rules belonging to this preset. */
    public function entrySettings(): HasMany
    {
        return $this->hasMany(PresetEntrySetting::class, 'preset_id');
    }

    public function collectionGroups(): HasMany
    {
        return $this->hasMany(PresetCollectionGroup::class, 'preset_id');
    }

    /** Shops that are currently assigned to this preset. */
    public function shops(): HasMany
    {
        return $this->hasMany(ShopLedgerProfile::class, 'preset_id');
    }
}
