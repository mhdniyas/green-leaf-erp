<?php

namespace App\Models;

use App\Models\Cashbook\CompanyAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaserCredit extends Model
{
    protected $fillable = [
        'purchaser_id',
        'type',
        'amount',
        'description',
        'payment_source',
        'company_account_id',
        'reference',
        'purchase_invoice_id',
        'created_by',
        'business_date',
    ];

    protected $casts = [
        'amount' => 'float',
        'business_date' => 'date',
    ];

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_id');
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
