<?php

namespace App\Models;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use Database\Factories\DirectCompanySaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

class DirectCompanySale extends Model
{
    /** @use HasFactory<DirectCompanySaleFactory> */
    use HasFactory;

    protected $fillable = ['public_uuid', 'request_uuid', 'business_date', 'customer_name', 'shop_id', 'sale_status', 'amount', 'payment_method', 'company_account_id', 'reference', 'note', 'journal_entry_id', 'reconciliation_status', 'is_finalized', 'finalized_at', 'created_by'];

    protected function casts(): array
    {
        return ['business_date' => 'date', 'amount' => 'decimal:2', 'is_finalized' => 'boolean', 'finalized_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $sale): void {
            $sale->public_uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_uuid';
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DirectCompanySaleItem::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function cashbookMovement(): MorphOne
    {
        return $this->morphOne(CompanyAccountStatementEntry::class, 'sourceRecord', 'source_type', 'source_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
