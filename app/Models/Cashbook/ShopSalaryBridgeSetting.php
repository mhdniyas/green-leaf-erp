<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Enums\Cashbook\SalaryHrTransactionType;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopSalaryBridgeSetting extends Model
{
    protected $table = 'shop_salary_bridge_settings';

    protected $fillable = [
        'shop_id',
        'transaction_type',
        'shop_ledger_entry_setting_id',
        'default_payment_mode',
        'allowed_payment_modes',
        'company_payable_settlement_id',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shop_id' => 'integer',
            'shop_ledger_entry_setting_id' => 'integer',
            'company_payable_settlement_id' => 'integer',
            'allowed_payment_modes' => 'array',
            'is_enabled' => 'boolean',
            'transaction_type' => SalaryHrTransactionType::class,
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function entrySetting(): BelongsTo
    {
        return $this->belongsTo(ShopLedgerEntrySetting::class, 'shop_ledger_entry_setting_id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ShopCashbookRelation::class, 'company_payable_settlement_id');
    }

    public function typeEnum(): SalaryHrTransactionType
    {
        if ($this->transaction_type instanceof SalaryHrTransactionType) {
            return $this->transaction_type;
        }

        return SalaryHrTransactionType::from((string) $this->transaction_type);
    }

    public function typeLabel(): string
    {
        return $this->typeEnum()->label();
    }

    public function isModeAllowed(string $mode): bool
    {
        $modes = is_array($this->allowed_payment_modes) ? $this->allowed_payment_modes : [];

        return in_array($mode, $modes, true);
    }
}
