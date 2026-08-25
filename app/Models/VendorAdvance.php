<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorAdvance extends Model
{
    protected $fillable = ['supplier_id', 'source_settlement_id', 'amount_original', 'amount_remaining', 'business_date', 'status', 'journal_entry_id', 'created_by'];

    protected function casts(): array
    {
        return ['amount_original' => 'decimal:2', 'amount_remaining' => 'decimal:2', 'business_date' => 'date'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function sourceSettlement(): BelongsTo
    {
        return $this->belongsTo(VendorSettlement::class, 'source_settlement_id');
    }
}
