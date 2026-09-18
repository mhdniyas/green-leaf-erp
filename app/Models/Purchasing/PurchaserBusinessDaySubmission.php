<?php

declare(strict_types=1);

namespace App\Models\Purchasing;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PurchaserBusinessDaySubmission extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'purchaser_business_day_submissions';

    protected $fillable = [
        'uuid',
        'purchaser_user_id',
        'business_date',
        'warehouse_id',
        'submitted_received_summary',
        'submitted_billed_summary',
        'submitted_pending_summary',
        'submitted_excess_summary',
        'submission_coverage_percentage',
        'pending_products_count',
        'unit_mismatch_products_count',
        'excess_products_count',
        'has_mixed_units',
        'status',
        'note',
        'submitted_by',
        'submitted_at',
    ];

    protected $casts = [
        'purchaser_user_id' => 'integer',
        'warehouse_id' => 'integer',
        'submitted_by' => 'integer',
        'business_date' => 'date:Y-m-d',
        'submitted_received_summary' => 'decimal:3',
        'submitted_billed_summary' => 'decimal:3',
        'submitted_pending_summary' => 'decimal:3',
        'submitted_excess_summary' => 'decimal:3',
        'submission_coverage_percentage' => 'decimal:2',
        'pending_products_count' => 'integer',
        'unit_mismatch_products_count' => 'integer',
        'excess_products_count' => 'integer',
        'has_mixed_units' => 'boolean',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaserBusinessDaySubmission $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('purchaser_business_day_submission');
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaserBusinessDaySubmissionItem::class, 'submission_id');
    }
}
