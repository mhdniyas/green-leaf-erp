<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopAccountingInvoiceSplit extends Model
{
    protected $fillable = [
        'shop_accounting_invoice_id',
        'shop_ownership_id',
        'owner_name_snapshot',
        'ownership_percent_snapshot',
        'share_amount',
    ];

    protected $casts = [
        'ownership_percent_snapshot' => 'decimal:2',
        'share_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ShopAccountingInvoice::class, 'shop_accounting_invoice_id');
    }

    public function ownership(): BelongsTo
    {
        return $this->belongsTo(ShopOwnership::class, 'shop_ownership_id');
    }
}
