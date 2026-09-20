<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Cashbook\LedgerEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'zoho_books_connection_id',
    'ledger_entry_type_id',
    'zoho_account_id',
    'zoho_account_name',
    'zoho_account_code',
    'zoho_account_type',
    'mapped_by',
    'mapped_at',
])]
class ZohoBooksAccountMapping extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mapped_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ZohoBooksConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZohoBooksConnection::class, 'zoho_books_connection_id');
    }

    /**
     * @return BelongsTo<LedgerEntryType, $this>
     */
    public function entryType(): BelongsTo
    {
        return $this->belongsTo(LedgerEntryType::class, 'ledger_entry_type_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mappedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by');
    }
}
