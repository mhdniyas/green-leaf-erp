<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresetCollectionGroup extends Model
{
    protected $table = 'cashbook_preset_collection_groups';

    protected $fillable = [
        'preset_id', 'name', 'code', 'enabled', 'display_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function preset(): BelongsTo
    {
        return $this->belongsTo(ShopConfigPreset::class, 'preset_id');
    }

    public function entryTypes(): HasMany
    {
        return $this->hasMany(PresetCollectionGroupEntryType::class, 'collection_group_id');
    }
}
