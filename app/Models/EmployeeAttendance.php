<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmployeeAttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAttendance extends Model
{
    /** @use HasFactory<EmployeeAttendanceFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'status',
        'shop_id',
        'marked_by',
        'marked_at',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'marked_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function isHrManaged(): bool
    {
        return $this->source === 'admin';
    }
}
