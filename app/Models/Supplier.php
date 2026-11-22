<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    private static ?bool $hasPublicUuidColumn = null;

    protected $fillable = [
        'public_uuid',
        'name',
        'type',
        'category',
        'is_default_purchase',
        'contact',
        'location',
        'mobile_number',
        'payment_terms',
        'preferred_payment_method',
        'bank_details',
        'credit_approved',
        'credit_approval_requested_at',
        'credit_approval_requested_by',
        'credit_approval_note',
        'credit_approved_at',
        'credit_approved_by',
        'credit_terms',
        'quality_score',
    ];

    public function getRouteKeyName(): string
    {
        return static::hasPublicUuidColumn() ? 'public_uuid' : $this->getKeyName();
    }

    public function getRouteKey(): mixed
    {
        if (static::hasPublicUuidColumn() && $this->public_uuid) {
            return $this->public_uuid;
        }

        return $this->getKey();
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        $query = $this->newQuery()->where($field, $value);

        if (is_numeric($value)) {
            $query->orWhere($this->getKeyName(), (int) $value);
        }

        if ($field !== 'name') {
            $query->orWhere('name', $value);
        }

        return $query->first();
    }

    protected static function booted(): void
    {
        static::creating(function (self $supplier): void {
            if (static::hasPublicUuidColumn() && ! $supplier->public_uuid) {
                $supplier->public_uuid = (string) Str::uuid();
            }
        });
    }

    public static function hasPublicUuidColumn(): bool
    {
        return self::$hasPublicUuidColumn ??= Schema::hasColumn('suppliers', 'public_uuid');
    }

    protected $casts = [
        'is_default_purchase' => 'boolean',
        'credit_approved' => 'boolean',
        'credit_approval_requested_at' => 'datetime',
        'credit_approved_at' => 'datetime',
        'quality_score' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // Relationships
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function purchaserCarts(): HasMany
    {
        return $this->hasMany(PurchaserCart::class);
    }

    public function vendorSettlements(): HasMany
    {
        return $this->hasMany(VendorSettlement::class);
    }

    public function vendorAdvances(): HasMany
    {
        return $this->hasMany(VendorAdvance::class);
    }

    public function purchaseCreditNotes(): HasMany
    {
        return $this->hasMany(PurchaseCreditNote::class);
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_suppliers')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function shopVendorPayables(): HasMany
    {
        return $this->hasMany(ShopVendorPayable::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->purchaseCreditNotes();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['last_price', 'last_purchased_at'])
            ->withTimestamps();
    }

    /**
     * Get IDs of products linked or previously purchased from this supplier.
     *
     * @return array<int>
     */
    public function relatedProductIds(?int $purchaseInvoiceId = null): array
    {
        $supplierId = (int) $this->id;

        $pivotIds = DB::table('product_supplier')
            ->where('supplier_id', $supplierId)
            ->pluck('product_id');

        $cartIds = DB::table('purchaser_cart_items')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->where('purchaser_carts.supplier_id', $supplierId)
            ->pluck('purchaser_cart_items.product_id');

        $invoiceCartIds = DB::table('purchaser_cart_items')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->where('purchase_invoices.supplier_id', $supplierId)
            ->pluck('purchaser_cart_items.product_id');

        $poGrnIds = DB::table('goods_received_items')
            ->join('goods_received', 'goods_received.id', '=', 'goods_received_items.goods_received_id')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'goods_received.purchase_order_id')
            ->where('purchase_orders.supplier_id', $supplierId)
            ->pluck('goods_received_items.product_id');

        $invGrnIds = DB::table('goods_received_items')
            ->join('purchase_invoices', 'purchase_invoices.goods_received_id', '=', 'goods_received_items.goods_received_id')
            ->where('purchase_invoices.supplier_id', $supplierId)
            ->pluck('goods_received_items.product_id');

        $merged = $pivotIds
            ->merge($cartIds)
            ->merge($invoiceCartIds)
            ->merge($poGrnIds)
            ->merge($invGrnIds);

        if ($purchaseInvoiceId !== null) {
            $singleInvCart = DB::table('purchaser_cart_items')
                ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_cart_items.purchaser_cart_id')
                ->where('purchase_invoices.id', $purchaseInvoiceId)
                ->pluck('purchaser_cart_items.product_id');

            $singleInvGrn = DB::table('goods_received_items')
                ->join('purchase_invoices', 'purchase_invoices.goods_received_id', '=', 'goods_received_items.goods_received_id')
                ->where('purchase_invoices.id', $purchaseInvoiceId)
                ->pluck('goods_received_items.product_id');

            $merged = $merged->merge($singleInvCart)->merge($singleInvGrn);
        }

        return $merged
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get collection of products related to this supplier.
     *
     * @return Collection<int, Product>
     */
    public function relatedProducts(?int $purchaseInvoiceId = null): Collection
    {
        $ids = $this->relatedProductIds($purchaseInvoiceId);
        if (empty($ids)) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'unit']);
    }

    public function creditApprovalRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credit_approval_requested_by');
    }

    public function creditApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credit_approved_by');
    }

    public function scopeDefaultPurchase(Builder $query): Builder
    {
        return $query
            ->where('category', 'own_purchase')
            ->where('is_default_purchase', true);
    }

    public function getPendingAmountAttribute(): float
    {
        if (! $this->relationLoaded('purchaseInvoices')) {
            $this->load(['purchaseInvoices' => fn ($q) => $q->notCancelled()]);
        }

        return round((float) $this->purchaseInvoices->sum(function ($invoice) {
            return $invoice->remainingBalance();
        }), 2);
    }
}
