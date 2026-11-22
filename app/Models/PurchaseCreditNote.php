<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Enums\Purchasing\PurchaseCreditStatus;
use App\Models\Cashbook\CompanyAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PurchaseCreditNote extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'public_uuid',
        'credit_note_number',
        'supplier_id',
        'purchase_invoice_id',
        'credit_date',
        'reason',
        'money_effect',
        'inventory_action',
        'status',
        'company_account_id',
        'warehouse_id',
        'wastage_reason',
        'deduct_stock_for_damage',
        'allowed_negative_stock',
        'total_amount',
        'applied_to_invoice_amount',
        'vendor_advance_id',
        'journal_entry_id',
        'notes',
        'void_reason',
        'created_by',
        'voided_by',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'credit_date' => 'date:Y-m-d',
            'reason' => PurchaseCreditReason::class,
            'money_effect' => PurchaseCreditMoneyEffect::class,
            'inventory_action' => PurchaseCreditInventoryAction::class,
            'status' => PurchaseCreditStatus::class,
            'deduct_stock_for_damage' => 'boolean',
            'allowed_negative_stock' => 'boolean',
            'total_amount' => 'decimal:2',
            'applied_to_invoice_amount' => 'decimal:2',
            'voided_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_uuid';
    }

    public function getRouteKey(): mixed
    {
        return $this->public_uuid ?: $this->getKey();
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        return $query->where(function (Builder $q) use ($field, $value): void {
            $q->where($field, $value);

            if ($field !== 'credit_note_number') {
                $q->orWhere('credit_note_number', $value);
            }

            if (is_numeric($value)) {
                $q->orWhere($this->getKeyName(), (int) $value);
            }
        });
    }

    protected static function booted(): void
    {
        static::creating(function (self $note): void {
            if (! $note->public_uuid) {
                $note->public_uuid = (string) Str::uuid();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Generate unique Purchase Credit Note reference number: PCNYY-MMDD-SUFFIX.
     */
    public static function generateCreditNoteNumber(Carbon|\DateTimeInterface|string $date): string
    {
        $parsedDate = $date instanceof Carbon ? $date : Carbon::parse($date);
        $yy = $parsedDate->format('y');
        $mmdd = $parsedDate->format('md');

        do {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
            $candidate = "PCN{$yy}-{$mmdd}-{$suffix}";
        } while (static::withTrashed()->where('credit_note_number', $candidate)->exists());

        return $candidate;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PurchaseCreditStatus::Active->value);
    }

    public function scopeForPeriod(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('credit_date', [$startDate, $endDate]);
    }

    public function isActive(): bool
    {
        return $this->status === PurchaseCreditStatus::Active;
    }

    public function isVoided(): bool
    {
        return $this->status === PurchaseCreditStatus::Voided;
    }

    public function getVendorAdvanceAmountAttribute(): float
    {
        if ($this->money_effect === PurchaseCreditMoneyEffect::VendorCredit) {
            return round(max(0.0, (float) $this->total_amount - (float) $this->applied_to_invoice_amount), 2);
        }

        return 0.0;
    }

    public function getCashRefundAmountAttribute(): float
    {
        if (in_array($this->money_effect, [PurchaseCreditMoneyEffect::MoneyIn, PurchaseCreditMoneyEffect::MoneyOut], true)) {
            return (float) $this->total_amount;
        }

        return 0.0;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function vendorAdvance(): BelongsTo
    {
        return $this->belongsTo(VendorAdvance::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseCreditNoteItem::class, 'purchase_credit_note_id');
    }
}
