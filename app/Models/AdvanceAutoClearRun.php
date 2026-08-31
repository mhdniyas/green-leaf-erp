<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AdvanceAutoClearRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_uuid',
        'client_submission_id',
        'warehouse_id',
        'requested_by',
        'requested_plan_hash',
        'status',
        'plan_snapshot',
        'initialized_at',
        'result_summary',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'plan_snapshot' => 'array',
        'result_summary' => 'array',
        'initialized_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AdvanceAutoClearRun $run): void {
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
        return $this->hasMany(AdvanceAutoClearRunItem::class, 'run_id')->orderBy('position');
    }
}
