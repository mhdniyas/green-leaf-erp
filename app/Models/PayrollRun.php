<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    protected $fillable = [
        'period_start',
        'period_end',
        'status',
        'generated_by',
        'finalized_by',
        'journal_entry_id',
        'finalized_at',
        'gross_amount',
        'net_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'finalized_at' => 'datetime',
            'gross_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
