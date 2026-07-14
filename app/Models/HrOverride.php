<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HrOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrOverride extends Model
{
    /** @use HasFactory<HrOverrideFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'override_type',
        'related_type',
        'related_id',
        'old_values',
        'new_values',
        'reason',
        'overridden_by',
        'overridden_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'overridden_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
