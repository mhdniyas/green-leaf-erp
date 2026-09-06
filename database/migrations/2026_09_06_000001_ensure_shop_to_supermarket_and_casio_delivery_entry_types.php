<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $typesToEnsure = [
            [
                'code' => 'shop_to_supermarket',
                'name' => 'Shop to Supermarket',
                'category' => 'transfer',
            ],
            [
                'code' => 'casio_delivery',
                'name' => 'Casio Delivery',
                'category' => 'transfer',
            ],
        ];

        foreach ($typesToEnsure as $type) {
            $existing = DB::table('ledger_entry_types')
                ->where('code', $type['code'])
                ->first();

            if ($existing) {
                DB::table('ledger_entry_types')
                    ->where('code', $type['code'])
                    ->update([
                        'name' => $type['name'],
                        'category' => $type['category'],
                        'active' => true,
                        'updated_at' => now(),
                    ]);
                $entryTypeId = (int) $existing->id;
            } else {
                $maxOrder = (int) (DB::table('ledger_entry_types')->max('display_order') ?? 0);
                $entryTypeId = DB::table('ledger_entry_types')->insertGetId([
                    'code' => $type['code'],
                    'name' => $type['name'],
                    'category' => $type['category'],
                    'system_type' => 'custom',
                    'active' => true,
                    'display_order' => $maxOrder + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Ensure setting rows exist for shops without altering custom configurations
            $shops = DB::table('shops')->get(['id']);
            foreach ($shops as $shop) {
                $settingExists = DB::table('shop_ledger_entry_settings')
                    ->where('shop_id', (int) $shop->id)
                    ->where('entry_type_id', $entryTypeId)
                    ->exists();

                if (! $settingExists) {
                    $maxShopOrder = (int) (DB::table('shop_ledger_entry_settings')
                        ->where('shop_id', (int) $shop->id)
                        ->max('display_order') ?? 0);

                    DB::table('shop_ledger_entry_settings')->insert([
                        'shop_id' => (int) $shop->id,
                        'entry_type_id' => $entryTypeId,
                        'version' => 1,
                        'effective_from' => '2026-01-01',
                        'enabled' => true,
                        'display_order' => $maxShopOrder + 1,
                        'default_funding_source' => 'sales',
                        'allowed_funding_sources' => json_encode(['sales', 'none']),
                        'settlement_behavior' => 'decrease',
                        'include_in_sales' => true,
                        'include_in_income' => false,
                        'include_in_expense' => false,
                        'include_in_pl' => false,
                        'include_in_payable' => true,
                        'payable_direction' => 'minus',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Safe down: do not delete existing production transaction data
    }
};
