<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Cashbook\CategoryVendorMapping;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopSupplier extends Model
{
    use HasFactory;

    protected $table = 'shop_suppliers';

    protected $fillable = [
        'shop_id',
        'supplier_id',
        'is_active',
        'credit_approved',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'supplier_id' => 'integer',
        'is_active' => 'boolean',
        'credit_approved' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function categoryMappings(): HasMany
    {
        return $this->hasMany(CategoryVendorMapping::class, 'shop_supplier_id');
    }

    public function entrySettings(): BelongsToMany
    {
        return $this->belongsToMany(
            ShopLedgerEntrySetting::class,
            'category_vendor_mappings',
            'shop_supplier_id',
            'shop_ledger_entry_setting_id'
        )->withTimestamps();
    }
}
