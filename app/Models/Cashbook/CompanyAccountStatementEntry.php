<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyAccountStatementEntry extends Model
{
    protected $table = 'cashbook_company_account_statement_entries';

    protected $fillable = [
        'company_account_id',
        'transaction_date',
        'value_date',
        'direction',
        'amount',
        'reference',
        'narration',
        'source',
        'status',
        'matched_amount',
        'statement_batch',
        'notes',
        'imported_by',
        'reconciled_by',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'value_date' => 'date',
            'amount' => 'decimal:2',
            'matched_amount' => 'decimal:2',
            'reconciled_at' => 'datetime',
        ];
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(CompanyPaymentReconciliation::class, 'statement_entry_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
