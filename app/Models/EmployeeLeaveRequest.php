<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmployeeLeaveRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveRequest extends Model
{
    /** @use HasFactory<EmployeeLeaveRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'submitted_by',
        'submitted_for_shop_id',
        'start_date',
        'end_date',
        'status',
        'submission_type',
        'reason',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function submittedForShop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'submitted_for_shop_id');
    }
}
