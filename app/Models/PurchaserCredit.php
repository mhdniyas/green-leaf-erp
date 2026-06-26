<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaserCredit extends Model
{
    protected $fillable = [
        'purchaser_id',
        'type',
        'amount',
        'description',
        'purchase_invoice_id',
        'created_by',
        'business_date',
    ];

    protected $casts = [
        'amount' => 'float',
        'business_date' => 'date',
    ];

    public function purchaser()
    {
        return $this->belongsTo(User::class, 'purchaser_id');
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
