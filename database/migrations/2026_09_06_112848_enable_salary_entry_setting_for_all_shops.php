<?php

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
        $salaryType = LedgerEntryType::where('code', 'salary')->first();
        if (! $salaryType) {
            return;
        }

        ShopLedgerEntrySetting::query()
            ->where('entry_type_id', $salaryType->id)
            ->update([
                'enabled' => true,
                'include_in_expense' => true,
                'include_in_pl' => true,
            ]);

        $shops = Shop::all();
        foreach ($shops as $shop) {
            ShopLedgerEntrySetting::query()->firstOrCreate(
                [
                    'shop_id' => $shop->id,
                    'entry_type_id' => $salaryType->id,
                ],
                [
                    'version' => 1,
                    'effective_from' => '2026-01-01',
                    'effective_to' => null,
                    'enabled' => true,
                    'default_funding_source' => 'sales',
                    'allowed_funding_sources' => ['sales', 'petty', 'company', 'company_later'],
                    'include_in_sales' => false,
                    'include_in_income' => false,
                    'include_in_expense' => true,
                    'include_in_pl' => true,
                    'settlement_behavior' => 'none',
                    'petty_behavior' => 'none',
                    'company_pending_behavior' => 'none',
                    'display_order' => 17,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
