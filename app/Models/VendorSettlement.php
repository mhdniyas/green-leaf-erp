<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Cashbook\CompanyAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;

class VendorSettlement extends Model
{
    protected $fillable = ['supplier_id', 'actual_payment_amount', 'settlement_discount_amount', 'vendor_advance_used_amount', 'new_vendor_advance_amount', 'company_account_id', 'payment_method', 'payment_date', 'reference', 'note', 'status', 'journal_entry_id', 'reconciliation_status', 'is_finalized', 'finalized_at', 'created_by'];

    protected function casts(): array
    {
        return ['actual_payment_amount' => 'decimal:2', 'settlement_discount_amount' => 'decimal:2', 'vendor_advance_used_amount' => 'decimal:2', 'new_vendor_advance_amount' => 'decimal:2', 'payment_date' => 'date', 'is_finalized' => 'boolean', 'finalized_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $settlement): void {
            $settlement->public_uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $settlement): void {
            if ($settlement->isDirty('public_uuid')) {
                throw new RuntimeException('Vendor settlement routing identity cannot be changed.');
            }

            if ($settlement->getOriginal('is_finalized') && $settlement->isDirty(['actual_payment_amount', 'settlement_discount_amount', 'vendor_advance_used_amount', 'new_vendor_advance_amount', 'supplier_id'])) {
                throw new RuntimeException('Finalized vendor settlements cannot be changed.');
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VendorSettlementAllocation::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(VendorAdvance::class, 'source_settlement_id');
    }
}
