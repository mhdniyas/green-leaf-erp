<?php

declare(strict_types=1);

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Shop;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $vendorPurchase = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'vendor_purchase'],
            [
                'name' => 'Vendor Purchase',
                'category' => 'expense',
                'active' => true,
                'display_order' => 19,
            ]
        );

        // Ensure every shop with active ledger profiles has a setting for vendor_purchase
        $shops = Shop::all();
        foreach ($shops as $shop) {
            ShopLedgerEntrySetting::query()->firstOrCreate(
                [
                    'shop_id' => $shop->id,
                    'entry_type_id' => $vendorPurchase->id,
                ],
                [
                    'version' => 1,
                    'effective_from' => '2026-01-01',
                    'effective_to' => null,
                    'enabled' => (bool) ($shop->shop_purchasing_enabled ?? true),
                    'default_funding_source' => 'sales',
                    'allowed_funding_sources' => ['sales', 'petty', 'company', 'company_later'],
                    'include_in_sales' => false,
                    'include_in_income' => false,
                    'include_in_expense' => true,
                    'include_in_pl' => true,
                    'settlement_behavior' => 'none',
                    'petty_behavior' => 'none',
                    'company_pending_behavior' => 'none',
                    'display_order' => 19,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Preserving entry types to prevent data loss or orphan transactions
    }
};
