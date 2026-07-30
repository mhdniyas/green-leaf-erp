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

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['last_price', 'last_purchased_at'])
            ->withTimestamps();
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
            $this->load('purchaseInvoices');
        }

        return round((float) $this->purchaseInvoices->sum(function ($invoice) {
            return max(0, ((float) $invoice->amount - (float) $invoice->discount_amount) - (float) $invoice->paid_amount);
        }), 2);
    }
}
