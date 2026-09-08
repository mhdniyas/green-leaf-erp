<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopLedgerProfile extends Model
{
    protected $table = 'shop_ledger_profiles';

    protected $fillable = [
        'shop_id', 'uuid', 'slug', 'code', 'name', 'profile_template',
        'enabled', 'closing_mode', 'preset_id', 'client_id', 'payment_configuration',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'payment_configuration' => 'array',
    ];

    /**
     * Normalized payment configuration for Payable and Sales Collections (Direct to Company + Cash).
     *
     * @return array{
     *     payment_settlement_id: ?int,
     *     payable: array{source: string, category_ids: array<int, int>, settlement_id: ?int},
     *     sales_collections: array{source: string, direct_category_ids: array<int, int>, cash_category_ids: array<int, int>, category_ids: array<int, int>, settlement_id: ?int},
     *     direct_to_company: array{source: string, category_ids: array<int, int>, settlement_id: ?int},
     *     paid: array{source: string, category_ids: array<int, int>, settlement_id: ?int}
     * }
     */
    public function getPaymentConfiguration(): array
    {
        $config = is_array($this->payment_configuration) ? $this->payment_configuration : [];
        $payable = $config['payable'] ?? [];
        $direct = $config['direct_to_company'] ?? $config['paid'] ?? [];
        $salesCollections = $config['sales_collections'] ?? [];

        $paymentSettlementId = isset($config['payment_settlement_id']) && $config['payment_settlement_id'] !== ''
            ? (int) $config['payment_settlement_id']
            : (isset($payable['settlement_id']) && $payable['settlement_id'] !== '' ? (int) $payable['settlement_id'] : null);

        $salesDirectIds = array_values(array_map('intval', (array) ($salesCollections['direct_category_ids'] ?? ($direct['category_ids'] ?? []))));
        $salesCashIds = array_values(array_map('intval', (array) ($salesCollections['cash_category_ids'] ?? [])));
        $salesSource = in_array($salesCollections['source'] ?? ($direct['source'] ?? ''), ['categories', 'settlement'], true) ? ($salesCollections['source'] ?? $direct['source']) : 'settlement';
        $salesSettlementId = isset($salesCollections['settlement_id']) && $salesCollections['settlement_id'] !== ''
            ? (int) $salesCollections['settlement_id']
            : (isset($direct['settlement_id']) && $direct['settlement_id'] !== '' ? (int) $direct['settlement_id'] : null);

        $salesConfig = [
            'source' => $salesSource,
            'direct_category_ids' => $salesDirectIds,
            'cash_category_ids' => $salesCashIds,
            'category_ids' => $salesDirectIds,
            'settlement_id' => $salesSettlementId,
        ];

        $directConfig = [
            'source' => $salesSource,
            'category_ids' => $salesDirectIds,
            'settlement_id' => $salesSettlementId,
        ];

        return [
            'payment_settlement_id' => $paymentSettlementId,
            'payable' => [
                'source' => in_array($payable['source'] ?? '', ['categories', 'settlement'], true) ? $payable['source'] : 'settlement',
                'category_ids' => array_values(array_map('intval', (array) ($payable['category_ids'] ?? []))),
                'settlement_id' => isset($payable['settlement_id']) && $payable['settlement_id'] !== '' ? (int) $payable['settlement_id'] : null,
            ],
            'sales_collections' => $salesConfig,
            'direct_to_company' => $directConfig,
            'paid' => $directConfig,
        ];
    }

    /** The client (e.g. Aiswarya Veg) that owns this shop. */
    public function client(): BelongsTo
    {
        return $this->belongsTo(LedgerClient::class, 'client_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /** The named preset configuration this shop follows. */
    public function preset(): BelongsTo
    {
        return $this->belongsTo(ShopConfigPreset::class, 'preset_id');
    }

    /** All entry settings for this shop. */
    public function entrySettings(): HasMany
    {
        return $this->hasMany(ShopLedgerEntrySetting::class, 'shop_id', 'shop_id');
    }
}
