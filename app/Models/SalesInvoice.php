<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Sales\SalesInvoiceStatus;
use Database\Factories\SalesInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SalesInvoice extends Model
{
    /** @use HasFactory<SalesInvoiceFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'sales_order_id',
        'customer_id',
        'invoice_number',
        'amount',
        'paid_amount',
        'due_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status' => SalesInvoiceStatus::class,
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
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
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Computed
    public function getOutstandingAmountAttribute(): float
    {
        return (float) $this->amount - (float) $this->paid_amount;
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->paid_amount >= (float) $this->amount;
    }
}
