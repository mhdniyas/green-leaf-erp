<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSettlementAllocation extends Model
{
    protected $fillable = ['vendor_settlement_id', 'purchase_invoice_id', 'cash_allocated', 'advance_allocated', 'discount_allocated', 'total_settled'];

    protected function casts(): array
    {
        return ['cash_allocated' => 'decimal:2', 'advance_allocated' => 'decimal:2', 'discount_allocated' => 'decimal:2', 'total_settled' => 'decimal:2'];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(VendorSettlement::class, 'vendor_settlement_id');
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }
}
