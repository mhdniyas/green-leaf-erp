<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\ShopSupplier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryVendorMapping extends Model
{
    use HasFactory;

    protected $table = 'category_vendor_mappings';

    protected $fillable = [
        'shop_ledger_entry_setting_id',
        'shop_supplier_id',
    ];

    protected $casts = [
        'shop_ledger_entry_setting_id' => 'integer',
        'shop_supplier_id' => 'integer',
    ];

    public function entrySetting(): BelongsTo
    {
        return $this->belongsTo(ShopLedgerEntrySetting::class, 'shop_ledger_entry_setting_id');
    }

    public function shopSupplier(): BelongsTo
    {
        return $this->belongsTo(ShopSupplier::class, 'shop_supplier_id');
    }
}
