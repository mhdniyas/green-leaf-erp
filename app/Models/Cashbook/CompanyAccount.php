<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyAccount extends Model
{
    protected $table = 'cashbook_company_accounts';

    protected $fillable = [
        'name',
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
        'is_default'      => 'boolean',
        'enabled'         => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(ShopLedgerTransaction::class, 'company_account_id');
    }
}
