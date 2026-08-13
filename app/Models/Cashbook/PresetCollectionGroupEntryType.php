<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresetCollectionGroupEntryType extends Model
{
    protected $table = 'cashbook_preset_collection_group_entry_types';

    protected $fillable = [
        'collection_group_id', 'entry_type_id', 'role', 'required', 'display_order',
    ];

    protected $casts = [
        'required' => 'boolean',
    ];

    public function collectionGroup(): BelongsTo
    {
        return $this->belongsTo(PresetCollectionGroup::class, 'collection_group_id');
    }

    public function entryType(): BelongsTo
    {
        return $this->belongsTo(LedgerEntryType::class, 'entry_type_id');
    }
}
