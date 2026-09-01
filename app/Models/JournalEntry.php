<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class JournalEntry extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'entry_date',
        'reference',
        'description',
        'source_type',
        'source_id',
        'source_event',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'source_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // Relationships
    public function transactions(): HasMany
    {
        return $this->hasMany(JournalTransaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(CompanyPaymentReconciliation::class, 'journal_entry_id');
    }

    public function statementEntries(): HasMany
    {
        return $this->hasMany(CompanyAccountStatementEntry::class, 'journal_entry_id');
    }

    public function purchaserCredit(): BelongsTo
    {
        return $this->belongsTo(PurchaserCredit::class, 'source_id');
    }

    public function getIsReversedAttribute(): bool
    {
        return str_starts_with((string) $this->source_event, 'reversal:')
            || $this->source_event === 'reversal'
            || str_contains((string) $this->description, '[REVERSED]')
            || static::query()->where('source_event', "reversal:{$this->id}")->exists();
    }

    public function getIsReversalAttribute(): bool
    {
        return str_starts_with((string) $this->source_event, 'reversal:')
            || $this->source_event === 'reversal'
            || str_starts_with((string) $this->reference, 'REV-JE-');
    }

    public function getIsReplacementAttribute(): bool
    {
        return str_contains((string) $this->description, '[Replacement for JE #');
    }

    public function getReversalEntryAttribute(): ?self
    {
        return static::query()->where('source_event', "reversal:{$this->id}")->first();
    }

    public function getReplacementEntryAttribute(): ?self
    {
        return static::query()->where('description', 'like', "%[Replacement for JE #{$this->id}]%")->first();
    }

    public function getOriginalReversedEntryAttribute(): ?self
    {
        if ($this->is_reversal) {
            $idStr = str_replace('reversal:', '', (string) $this->source_event);
            if (is_numeric($idStr)) {
                return static::query()->find((int) $idStr);
            }
        }

        if ($this->is_replacement) {
            if (preg_match('/\[Replacement for JE #(\d+)\]/', (string) $this->description, $matches)) {
                return static::query()->find((int) $matches[1]);
            }
        }

        return null;
    }

    // Accessors & Helpers
    public function getTotalDebitAttribute(): float
    {
        return round((float) $this->transactions->where('type', 'debit')->sum('amount'), 2);
    }

    public function getTotalCreditAttribute(): float
    {
        return round((float) $this->transactions->where('type', 'credit')->sum('amount'), 2);
    }

    public function getIsBalancedAttribute(): bool
    {
        return abs($this->total_debit - $this->total_credit) <= 0.01;
    }

    public function getDebitAccountsSummaryAttribute(): string
    {
        $accounts = $this->transactions
            ->where('type', 'debit')
            ->map(fn ($t) => ($t->account?->code ? $t->account->code.' - ' : '').($t->account?->name ?? 'Unknown'))
            ->unique();

        return $accounts->isEmpty() ? '—' : $accounts->join(', ');
    }

    public function getCreditAccountsSummaryAttribute(): string
    {
        $accounts = $this->transactions
            ->where('type', 'credit')
            ->map(fn ($t) => ($t->account?->code ? $t->account->code.' - ' : '').($t->account?->name ?? 'Unknown'))
            ->unique();

        return $accounts->isEmpty() ? '—' : $accounts->join(', ');
    }

    public function getPrimaryAmountAttribute(): float
    {
        $cashBankTxn = $this->transactions->first(fn ($t) => in_array($t->account?->code, ['1010', '1020'], true));

        if ($cashBankTxn) {
            return round((float) $cashBankTxn->amount, 2);
        }

        return $this->total_debit > 0 ? $this->total_debit : $this->total_credit;
    }

    public function getFormattedReferenceAttribute(): string
    {
        return 'JE-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getSourceLabelAttribute(): string
    {
        if (empty($this->source_type)) {
            return 'Manual Journal';
        }

        $baseClass = class_basename($this->source_type);

        return match ($baseClass) {
            'PurchaseInvoice' => 'Purchase Invoice'.($this->source_id ? ' #'.$this->source_id : ''),
            'PurchaseInvoicePayment' => 'Vendor Payment'.($this->source_id ? ' #'.$this->source_id : ''),
            'PurchaserCredit' => 'Purchaser Funding'.($this->source_id ? ' #'.$this->source_id : ''),
            'GoodsReceived' => 'Goods Receipt'.($this->source_id ? ' #'.$this->source_id : ''),
            'CompanyAccountingEntry' => $this->companyAccountingSourceLabel(),
            'DirectCompanySale' => 'Direct Company Sale'.($this->source_id ? ' #'.$this->source_id : ''),
            'ShopInvoicePaymentRequest', 'ShopInvoice', 'Payment' => 'Shop Payment'.($this->source_id ? ' #'.$this->source_id : ''),
            'PayrollRun' => 'Payroll Run'.($this->source_id ? ' #'.$this->source_id : ''),
            'PayrollPayment' => ($this->source_event === 'salary_advance' ? 'Salary Advance' : 'Salary Payment').($this->source_id ? ' #'.$this->source_id : ''),
            'ContractWorkerPayment' => 'Contract Labour'.($this->source_id ? ' #'.$this->source_id : ''),
            'WastageEntry' => 'Inventory Wastage'.($this->source_id ? ' #'.$this->source_id : ''),
            'CompanyPayableSettlement' => 'Company Payable'.($this->source_id ? ' #'.$this->source_id : ''),
            'VendorSettlement' => 'Vendor Settlement'.($this->source_id ? ' #'.$this->source_id : ''),
            'ShopLedgerTransaction' => 'Shop Petty Funding'.($this->source_id ? ' #'.$this->source_id : ''),
            default => $baseClass.($this->source_id ? ' #'.$this->source_id : ''),
        };
    }

    private function companyAccountingSourceLabel(): string
    {
        $cashBankTransaction = $this->transactions->first(fn ($transaction) => in_array($transaction->account?->code, ['1010', '1020'], true));

        $label = $cashBankTransaction?->type === 'credit' ? 'Other Expense' : 'Other Income';

        return $label.($this->source_id ? ' #'.$this->source_id : '');
    }

    public function getIsFinalizedAttribute(): bool
    {
        $target = $this->primary_amount;
        if ($target <= 0.0) {
            return false;
        }

        $hasFinalizedRecord = $this->reconciliations->contains('is_finalized', true) || $this->statementEntries->contains('is_finalized', true);
        if (! $hasFinalizedRecord) {
            return false;
        }

        $clearedReconciliation = (float) $this->reconciliations->where('status', 'approved')->sum('cleared_amount');
        $matchedStatement = (float) $this->statementEntries->where('status', 'reconciled')->sum('matched_amount');
        $totalMatched = max($clearedReconciliation, $matchedStatement);

        return $totalMatched >= ($target - 0.01);
    }

    public function getReconciliationStatusAttribute(): string
    {
        if ($this->is_finalized) {
            return 'finalized';
        }

        $matchedStatement = (float) $this->statementEntries->where('status', 'reconciled')->sum('matched_amount');
        $clearedReconciliation = (float) $this->reconciliations->where('status', 'approved')->sum('cleared_amount');
        $totalMatched = max($matchedStatement, $clearedReconciliation);

        if ($totalMatched <= 0.0) {
            return 'unreconciled';
        }

        $target = $this->primary_amount;
        if ($target > 0.0 && $totalMatched >= ($target - 0.01)) {
            return 'reconciled';
        }

        return 'partially_reconciled';
    }

    public function getReconciliationStatusLabelAttribute(): string
    {
        return match ($this->reconciliation_status) {
            'finalized' => 'FINALIZED',
            'reconciled' => 'RECONCILED',
            'partially_reconciled' => 'PARTIALLY RECONCILED',
            default => 'UNRECONCILED',
        };
    }
}
