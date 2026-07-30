<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAccountingEntry extends Model
{
    public const StatusFinal = 'final';

    public const StatusReversed = 'reversed';

    protected $fillable = [
        'company_accounting_category_id',
        'journal_entry_id',
        'reversal_journal_entry_id',
        'type',
        'business_date',
        'payment_mode',
        'payment_reference',
        'amount',
        'reference',
        'description',
        'status',
        'created_by',
        'reversed_by',
        'reversed_at',
        'reversal_note',
    ];

    protected $casts = [
        'business_date' => 'date',
        'amount' => 'decimal:2',
        'reversed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CompanyAccountingCategory::class, 'company_accounting_category_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function typeLabel(): string
    {
        return str($this->type)->replace('_', ' ')->title()->toString();
    }

    public function paymentModeLabel(): string
    {
        return match ($this->payment_mode) {
            'cash' => 'Cash',
            'bank' => 'Bank',
            'upi' => 'UPI',
            'cheque' => 'Cheque',
            default => str((string) $this->payment_mode)->replace('_', ' ')->title()->toString(),
        };
    }
}
