<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerClient extends Model
{
    protected $table = 'cashbook_ledger_clients';

    protected $fillable = [
        'name', 'slug', 'contact_name', 'contact_phone', 'gstin', 'address', 'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /** All shops owned by this client. */
    public function shops(): HasMany
    {
        return $this->hasMany(ShopLedgerProfile::class, 'client_id');
    }
}
