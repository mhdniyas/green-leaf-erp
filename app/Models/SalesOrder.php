<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Sales\SOStatus;
use Database\Factories\SalesOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'so_number',
        'status',
        'order_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status' => SOStatus::class,
        'order_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'so_number';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(SalesInvoice::class);
    }

    // Computed
    public function getTotalAmountAttribute(): float
    {
        return $this->items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);
    }
}
