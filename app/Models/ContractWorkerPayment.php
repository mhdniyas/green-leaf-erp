<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContractWorkerPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractWorkerPayment extends Model
{
    /** @use HasFactory<ContractWorkerPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'journal_entry_id',
        'paid_by',
        'worker_name',
        'work_type',
        'worked_on',
        'paid_on',
        'amount',
        'payment_method',
        'notes',
    ];

    protected $attributes = [
        'payment_method' => 'cash',
    ];

    protected function casts(): array
    {
        return [
            'worked_on' => 'date',
            'paid_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
