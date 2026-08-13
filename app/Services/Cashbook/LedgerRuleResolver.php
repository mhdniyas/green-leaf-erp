<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopLedgerEntrySetting;
use Carbon\Carbon;
use RuntimeException;

/**
 * Resolves which ShopLedgerEntrySetting governs a given shop + entry type
 * on a given business date (versioning rules apply).
 *
 * Precedence:
 *   1. Shop-specific setting version effective on the business date
 *   2. (none found) → hard error, forcing explicit configuration
 *
 * Transaction-specific overrides (e.g. an operator picking a non-default
 * funding source) are handled one layer up, in TransactionGenerator.
 */
class LedgerRuleResolver
{
    public function resolve(int $shopId, int $entryTypeId, string $businessDate): ShopLedgerEntrySetting
    {
        $date = Carbon::parse($businessDate)->toDateString();

        $setting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $shopId)
            ->where('entry_type_id', $entryTypeId)
            ->where('enabled', true)
            ->effectiveOn($date)
            ->orderByDesc('version')
            ->first();

        if (! $setting) {
            throw new RuntimeException(
                "No ledger configuration found for shop {$shopId}, entry type {$entryTypeId} on {$date}. " .
                'Apply a profile template or add a shop_ledger_entry_settings row before posting this entry.'
            );
        }

        return $setting;
    }
}
