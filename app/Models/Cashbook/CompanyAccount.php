<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;

class CompanyAccount extends Model
{
    protected $table = 'cashbook_company_accounts';

    protected $fillable = [
        'name',
        'public_uuid',
        'account_type',
        'bank_name',
        'account_number',
        'opening_balance',
        'current_balance',
        'is_default',
        'enabled',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_default' => 'boolean',
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $account): void {
            $account->public_uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $account): void {
            if ($account->isDirty('public_uuid')) {
                throw new RuntimeException('Company account routing identity cannot be changed.');
            }
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ShopLedgerTransaction::class, 'company_account_id');
    }

    public function statementEntries(): HasMany
    {
        return $this->hasMany(CompanyAccountStatementEntry::class, 'company_account_id');
    }
}
