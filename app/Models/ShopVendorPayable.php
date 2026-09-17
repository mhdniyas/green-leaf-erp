<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Cashbook\ShopLedgerEntrySetting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShopVendorPayable extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_uuid',
        'purchase_invoice_id',
        'shop_id',
        'supplier_id',
        'shop_ledger_entry_setting_id',
        'business_date',
        'original_amount',
        'paid_amount',
        'outstanding_amount',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'shop_ledger_entry_setting_id' => 'integer',
            'business_date' => 'date',
            'original_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $payable): void {
            $payable->public_uuid ??= (string) Str::uuid();
            if ($payable->outstanding_amount === null || $payable->outstanding_amount == 0.0) {
                $payable->outstanding_amount = round(max(0, (float) $payable->original_amount - (float) $payable->paid_amount), 2);
            }
        });
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function shopLedgerEntrySetting(): BelongsTo
    {
        return $this->belongsTo(ShopLedgerEntrySetting::class, 'shop_ledger_entry_setting_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
