<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopEmployeeAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ShopEmployeeAssignment extends Model
{
    /** @use HasFactory<ShopEmployeeAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'employee_id',
        'assigned_by',
        'effective_from',
        'effective_to',
        'status',
        'notes',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isActiveOn(\DateTimeInterface|string $date): bool
    {
        $day = Carbon::parse($date)->toDateString();

        return $this->status === 'active'
            && ($this->effective_from === null || $this->effective_from->toDateString() <= $day)
            && ($this->effective_to === null || $this->effective_to->toDateString() >= $day);
    }
}
