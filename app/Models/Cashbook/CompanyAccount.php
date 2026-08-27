<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
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

    public static function getDefaultAccount(?string $accountType = null): ?self
    {
        return static::query()
            ->where('enabled', true)
            ->where('is_default', true)
            ->when($accountType !== null, fn ($query) => $query->where('account_type', $accountType))
            ->first();
    }

    /**
     * Resolve the preselected company account ID for a form.
     *
     * @param  mixed  $currentValue  Existing explicit / old value
     * @param  iterable<CompanyAccount>|null  $eligibleAccounts  Optional list of eligible accounts in this form
     * @param  string|null  $accountType  Optional account type constraint ('bank', 'cash', 'wallet')
     */
    public static function resolveSelectedId(mixed $currentValue = null, ?iterable $eligibleAccounts = null, ?string $accountType = null): ?int
    {
        if ($currentValue !== null && $currentValue !== '') {
            return (int) $currentValue;
        }

        if ($eligibleAccounts !== null) {
            $collection = $eligibleAccounts instanceof Collection ? $eligibleAccounts : collect($eligibleAccounts);
            $default = $collection
                ->where('enabled', true)
                ->where('is_default', true)
                ->when($accountType !== null, fn ($col) => $col->where('account_type', $accountType))
                ->first();

            return $default ? (int) $default->id : null;
        }

        $default = static::getDefaultAccount($accountType);

        return $default ? (int) $default->id : null;
    }

    /**
     * Resolve the preselected company account UUID for a form.
     *
     * @param  mixed  $currentValue  Existing explicit / old value
     * @param  iterable<CompanyAccount>|null  $eligibleAccounts  Optional list of eligible accounts in this form
     * @param  string|null  $accountType  Optional account type constraint ('bank', 'cash', 'wallet')
     */
    public static function resolveSelectedUuid(mixed $currentValue = null, ?iterable $eligibleAccounts = null, ?string $accountType = null): ?string
    {
        if ($currentValue !== null && $currentValue !== '') {
            return (string) $currentValue;
        }

        if ($eligibleAccounts !== null) {
            $collection = $eligibleAccounts instanceof Collection ? $eligibleAccounts : collect($eligibleAccounts);
            $default = $collection
                ->where('enabled', true)
                ->where('is_default', true)
                ->when($accountType !== null, fn ($col) => $col->where('account_type', $accountType))
                ->first();

            return $default ? (string) $default->public_uuid : null;
        }

        $default = static::getDefaultAccount($accountType);

        return $default ? (string) $default->public_uuid : null;
    }

    /**
     * Determine if an account option should be selected in a Blade dropdown.
     *
     * @param  CompanyAccount|int|string  $account  The account instance, ID, or UUID being rendered
     * @param  mixed  $currentValue  Existing explicit / old value
     * @param  iterable<CompanyAccount>|null  $eligibleAccounts  List of accounts available in the select
     * @param  string|null  $accountType  Optional account type constraint
     * @param  string  $valueField  'id' or 'public_uuid'
     */
    public static function isSelected(
        CompanyAccount|int|string $account,
        mixed $currentValue = null,
        ?iterable $eligibleAccounts = null,
        ?string $accountType = null,
        string $valueField = 'id'
    ): bool {
        if ($valueField === 'public_uuid') {
            $selectedUuid = static::resolveSelectedUuid($currentValue, $eligibleAccounts, $accountType);
            $accountUuid = $account instanceof self ? $account->public_uuid : (string) $account;

            return $selectedUuid !== null && $selectedUuid === $accountUuid;
        }

        $selectedId = static::resolveSelectedId($currentValue, $eligibleAccounts, $accountType);
        $accountId = $account instanceof self ? $account->id : (int) $account;

        return $selectedId !== null && $selectedId === $accountId;
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
