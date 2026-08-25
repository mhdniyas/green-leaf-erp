<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use RuntimeException;

class CompanyAccountStatementEntry extends Model
{
    protected $table = 'cashbook_company_account_statement_entries';

    protected $fillable = [
        'company_account_id',
        'public_uuid',
        'journal_entry_id',
        'request_uuid',
        'transaction_date',
        'value_date',
        'direction',
        'amount',
        'reference',
        'narration',
        'source',
        'source_type',
        'source_id',
        'counterpart_type',
        'counterpart_id',
        'status',
        'is_finalized',
        'finalized_at',
        'matched_amount',
        'statement_batch',
        'import_fingerprint',
        'imported_month',
        'import_file_name',
        'duplicate_status',
        'duplicate_of_statement_entry_id',
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
            'is_finalized' => 'boolean',
            'finalized_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->public_uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $entry): void {
            if ($entry->isDirty('public_uuid')) {
                throw new RuntimeException('Cashbook movement routing identity cannot be changed.');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_uuid';
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(CompanyPaymentReconciliation::class, 'statement_entry_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_statement_entry_id');
    }

    public function sourceRecord(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function counterpart(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'counterpart_type', 'counterpart_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function secureRouteKey(): string
    {
        return rtrim(strtr(base64_encode(Crypt::encryptString('statement-entry:'.$this->getKey())), '+/', '-_'), '=');
    }

    public function getSourceLabelAttribute(): string
    {
        if ($this->source_type) {
            $baseClass = class_basename($this->source_type);

            if ($baseClass === 'CompanyAccountingEntry') {
                return $this->direction === 'in' ? 'Other Income' : 'Other Expense';
            }

            return match ($baseClass) {
                'ShopInvoicePaymentRequest', 'ShopInvoice', 'Payment' => 'Shop Payment',
                'PurchaserCredit' => 'Purchaser Funding',
                'ShopLedgerTransaction' => 'Shop Petty Funding',
                'CompanyPayableSettlement' => 'Company Payable',
                'VendorSettlement', 'PurchaseInvoice' => 'Vendor Settlement',
                'DirectCompanySale' => 'Direct Sale',
                'PayrollPayment' => $this->source === 'salary_advance' ? 'Salary Advance' : 'Salary Payment',
                default => $baseClass,
            };
        }

        return match ($this->source) {
            'purchaser_funding' => 'Purchaser Funding',
            'company_accounting_entry' => $this->direction === 'in' ? 'Other Income' : 'Other Expense',
            'shop_petty_funding' => 'Shop Petty Funding',
            'company_payable' => 'Company Payable',
            'direct_company_sale' => 'Direct Sale',
            'salary_payment' => 'Salary Payment',
            'salary_advance' => 'Salary Advance',
            default => 'Statement Entry',
        };
    }
}
