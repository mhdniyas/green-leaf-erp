<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Warehouse\WarehouseCreditNoteStatus;
use App\Enums\Warehouse\WarehouseCreditReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class WarehouseCreditNote extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'public_uuid',
        'credit_note_number',
        'warehouse_id',
        'business_date',
        'reason',
        'status',
        'total_amount',
        'journal_entry_id',
        'remarks',
        'void_reason',
        'created_by',
        'finalized_by',
        'finalized_at',
        'voided_by',
        'voided_at',
    ];

    protected $attributes = [
        'status' => WarehouseCreditNoteStatus::Draft->value,
        'total_amount' => 0.00,
    ];

    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'business_date' => 'date:Y-m-d',
            'reason' => WarehouseCreditReason::class,
            'status' => WarehouseCreditNoteStatus::class,
            'total_amount' => 'decimal:2',
            'journal_entry_id' => 'integer',
            'created_by' => 'integer',
            'finalized_by' => 'integer',
            'finalized_at' => 'datetime',
            'voided_by' => 'integer',
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

        static::updating(function (self $note): void {
            $originalStatus = $note->getOriginal('status');
            $originalStatusValue = $originalStatus instanceof WarehouseCreditNoteStatus
                ? $originalStatus->value
                : (string) $originalStatus;

            if ($originalStatusValue === WarehouseCreditNoteStatus::Draft->value) {
                $newStatus = $note->status instanceof WarehouseCreditNoteStatus
                    ? $note->status->value
                    : (string) $note->status;

                if ($newStatus === WarehouseCreditNoteStatus::Active->value && empty($note->journal_entry_id)) {
                    throw new \RuntimeException(
                        'Draft warehouse credit note cannot become active without a linked journal entry.'
                    );
                }

                if ($newStatus === WarehouseCreditNoteStatus::Voided->value) {
                    throw new \RuntimeException(
                        'Draft warehouse credit note cannot transition directly to voided.'
                    );
                }
            }

            if ($originalStatusValue === WarehouseCreditNoteStatus::Voided->value) {
                throw new \RuntimeException(
                    "Cannot modify voided warehouse credit note {$note->credit_note_number}."
                );
            }

            if ($originalStatusValue === WarehouseCreditNoteStatus::Active->value) {
                $dirtyKeys = array_keys($note->getDirty());
                $allowedVoidKeys = ['status', 'voided_by', 'voided_at', 'void_reason', 'updated_at', 'journal_entry_id'];
                $disallowed = array_diff($dirtyKeys, $allowedVoidKeys);

                if ($disallowed !== []) {
                    throw new \RuntimeException(
                        "Cannot directly modify active warehouse credit note {$note->credit_note_number}."
                    );
                }

                $newStatus = $note->status instanceof WarehouseCreditNoteStatus
                    ? $note->status->value
                    : (string) $note->status;

                if (in_array('status', $dirtyKeys, true) && $newStatus !== WarehouseCreditNoteStatus::Voided->value) {
                    throw new \RuntimeException(
                        "Active warehouse credit note {$note->credit_note_number} can only transition to voided."
                    );
                }
            }
        });

        static::deleting(function (self $note): void {
            if (! $note->isDraft()) {
                throw new \RuntimeException(
                    "Cannot delete non-draft warehouse credit note {$note->credit_note_number}. Active credit notes must be voided, not deleted."
                );
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
     * Generate unique Warehouse Credit Note reference number: WCNYY-MMDD-SUFFIX.
     */
    public static function generateCreditNoteNumber(Carbon|\DateTimeInterface|string $date): string
    {
        $parsedDate = $date instanceof Carbon ? $date : Carbon::parse($date);
        $yy = $parsedDate->format('y');
        $mmdd = $parsedDate->format('md');

        do {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
            $candidate = "WCN{$yy}-{$mmdd}-{$suffix}";
        } while (static::withTrashed()->where('credit_note_number', $candidate)->exists());

        return $candidate;
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', WarehouseCreditNoteStatus::Draft->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', WarehouseCreditNoteStatus::Active->value);
    }

    public function scopeVoided(Builder $query): Builder
    {
        return $query->where('status', WarehouseCreditNoteStatus::Voided->value);
    }

    public function scopeForWarehouse(Builder $query, Warehouse|int $warehouse): Builder
    {
        $warehouseId = $warehouse instanceof Warehouse ? $warehouse->getKey() : $warehouse;

        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeForPeriod(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('business_date', [$startDate, $endDate]);
    }

    public function isDraft(): bool
    {
        return $this->status === WarehouseCreditNoteStatus::Draft;
    }

    public function isActive(): bool
    {
        return $this->status === WarehouseCreditNoteStatus::Active;
    }

    public function isVoided(): bool
    {
        return $this->status === WarehouseCreditNoteStatus::Voided;
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseCreditNoteItem::class, 'warehouse_credit_note_id');
    }

    public function itemMovements(): HasManyThrough
    {
        return $this->hasManyThrough(
            WarehouseCreditNoteItemMovement::class,
            WarehouseCreditNoteItem::class,
            'warehouse_credit_note_id',
            'warehouse_credit_note_item_id',
        );
    }
}
