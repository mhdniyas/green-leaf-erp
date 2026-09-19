<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopCashbookMonthConfigSnapshot extends Model
{
    protected $table = 'shop_cashbook_month_config_snapshots';

    protected $fillable = [
        'shop_id',
        'month',
        'status',
        'source',
        'config_data',
        'captured_by',
        'notes',
        'recalculated_at',
        'recalculation_summary',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'captured_by' => 'integer',
        'config_data' => 'array',
        'recalculated_at' => 'datetime',
        'recalculation_summary' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    /**
     * Get headers stored in the snapshot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHeaders(): array
    {
        return $this->config_data['headers'] ?? [];
    }

    /**
     * Get entry settings stored in the snapshot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSettings(): array
    {
        return $this->config_data['settings'] ?? [];
    }

    /**
     * Get relations stored in the snapshot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRelations(): array
    {
        return $this->config_data['relations'] ?? [];
    }

    /**
     * Get bank adjustment rules stored in the snapshot.
     *
     * @return array<string|int, mixed>
     */
    public function getBankAdjustmentRules(): array
    {
        return $this->config_data['bank_adjustment_rules'] ?? [];
    }

    /**
     * Get collection group stored in the snapshot.
     *
     * @return array<string, mixed>|null
     */
    public function getCollectionGroup(): ?array
    {
        return $this->config_data['collection_group'] ?? null;
    }

    /**
     * Check if this snapshot is considered legacy/reconstructed.
     */
    public function isLegacy(): bool
    {
        return $this->status === 'legacy_reconstructed' || $this->source === 'legacy_reconstruction';
    }
}
