<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Account extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'type',
        'is_active',
        'parent_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(JournalTransaction::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAsset($query)
    {
        return $query->where('type', 'asset');
    }

    public function scopeLiability($query)
    {
        return $query->where('type', 'liability');
    }

    public function scopeEquity($query)
    {
        return $query->where('type', 'equity');
    }

    public function scopeRevenue($query)
    {
        return $query->where('type', 'revenue');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'expense');
    }

    // Balance calculation
    public function getBalanceAttribute(): float
    {
        return $this->getBalanceForPeriod();
    }

    public function getBalanceForPeriod(?string $startDate = null, ?string $endDate = null): float
    {
        $query = $this->transactions();

        if ($startDate || $endDate) {
            $query->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                if ($startDate) {
                    $q->where('entry_date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('entry_date', '<=', $endDate);
                }
            });
        }

        $debits = (float) (clone $query)->where('type', 'debit')->sum('amount');
        $credits = (float) (clone $query)->where('type', 'credit')->sum('amount');

        if (in_array(strtolower($this->type), ['asset', 'expense'])) {
            return $debits - $credits;
        }

        return $credits - $debits;
    }
}
