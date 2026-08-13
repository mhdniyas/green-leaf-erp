<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('ledger_entry_types')
            ->where('code', 'income_s_m_delivery')
            ->exists();

        if ($exists) {
            DB::table('ledger_entry_types')
                ->where('code', 'income_s_m_delivery')
                ->update([
                    'name' => 'S/M Delivery',
                    'category' => 'income',
                    'active' => true,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('ledger_entry_types')->insert([
            'code' => 'income_s_m_delivery',
            'name' => 'S/M Delivery',
            'category' => 'income',
            'system_type' => 'custom',
            'active' => true,
            'display_order' => ((int) DB::table('ledger_entry_types')->max('display_order')) + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ledger_entry_types')
            ->where('code', 'income_s_m_delivery')
            ->where('system_type', 'custom')
            ->delete();
    }
};
