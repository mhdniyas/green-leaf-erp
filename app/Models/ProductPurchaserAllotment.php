<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ProductPurchaserAllotment extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'product_purchaser_allotments';

    protected $fillable = [
        'product_id',
        'purchaser_user_id',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'purchaser_user_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'effective_from' => 'date:Y-m-d',
        'effective_to' => 'date:Y-m-d',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('product_purchaser_allotment');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActiveOn(string|\DateTimeInterface $date): bool
    {
        $targetDate = is_string($date) ? $date : $date->format('Y-m-d');
        $from = $this->effective_from?->format('Y-m-d');
        $to = $this->effective_to?->format('Y-m-d');

        if ($from === null || $from > $targetDate) {
            return false;
        }

        return $to === null || $to >= $targetDate;
    }
}
