<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StaffSyncFlag extends Model
{
    public const CODE_MISSING_CASHBOOK = 'MISSING_CASHBOOK';

    public const CODE_CASHBOOK_AMOUNT_MISMATCH = 'CASHBOOK_AMOUNT_MISMATCH';

    public const CODE_CASHBOOK_DATE_MISMATCH = 'CASHBOOK_DATE_MISMATCH';

    public const CODE_CASHBOOK_SHOP_MISMATCH = 'CASHBOOK_SHOP_MISMATCH';

    public const CODE_CASHBOOK_CATEGORY_MISMATCH = 'CASHBOOK_CATEGORY_MISMATCH';

    public const CODE_CASHBOOK_FUND_SOURCE_MISMATCH = 'CASHBOOK_FUND_SOURCE_MISMATCH';

    public const CODE_ORPHAN_CASHBOOK = 'ORPHAN_CASHBOOK';

    public const CODE_HR_HISTORY_MISSING = 'HR_HISTORY_MISSING';

    public const CODE_SHOP_HISTORY_MISSING = 'SHOP_HISTORY_MISSING';

    public const CODE_SALARY_NOT_UPDATED = 'SALARY_NOT_UPDATED';

    public const CODE_ADVANCE_BALANCE_MISMATCH = 'ADVANCE_BALANCE_MISMATCH';

    public const CODE_PAYROLL_MISMATCH = 'PAYROLL_MISMATCH';

    public const CODE_DUPLICATE_CASHBOOK = 'DUPLICATE_CASHBOOK';

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'flag_code',
        'severity',
        'source_type',
        'source_id',
        'shop_id',
        'employee_id',
        'payment_date',
        'status',
        'title',
        'description',
        'details',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $attributes = [
        'severity' => 'danger',
        'status' => self::STATUS_OPEN,
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'details' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function getIssueTitleAttribute(): string
    {
        return (string) ($this->title ?: str_replace('_', ' ', ucwords(strtolower((string) $this->flag_code), '_')));
    }

    public function getIssueDescriptionAttribute(): string
    {
        return (string) ($this->description ?: '');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForSource(Builder $query, string $sourceType, int $sourceId): Builder
    {
        return $query->where('source_type', $sourceType)->where('source_id', $sourceId);
    }
}
