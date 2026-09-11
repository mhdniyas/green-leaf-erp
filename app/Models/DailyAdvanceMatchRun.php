<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DailyAdvanceMatchRun extends Model
{
    use HasFactory;

    protected $table = 'daily_advance_match_runs';

    protected $fillable = [
        'public_uuid',
        'client_submission_id',
        'warehouse_id',
        'bill_date',
        'cursor',
        'batch_size',
        'requested_by',
        'requested_plan_hash',
        'status',
        'plan_snapshot',
        'result_summary',
        'initialized_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'bill_date' => 'date:Y-m-d',
        'cursor' => 'integer',
        'batch_size' => 'integer',
        'plan_snapshot' => 'array',
        'result_summary' => 'array',
        'initialized_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DailyAdvanceMatchRun $run): void {
            if (empty($run->public_uuid)) {
                $run->public_uuid = (string) Str::uuid();
            }
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DailyAdvanceMatchRunItem::class, 'run_id')->orderBy('position');
    }
}
