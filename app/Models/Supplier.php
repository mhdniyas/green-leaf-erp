<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'category',
        'is_default_purchase',
        'contact',
        'location',
        'mobile_number',
        'payment_terms',
        'preferred_payment_method',
        'credit_approved',
        'credit_approval_requested_at',
        'credit_approval_requested_by',
        'credit_approval_note',
        'credit_approved_at',
        'credit_approved_by',
        'credit_terms',
        'quality_score',
    ];

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
}
