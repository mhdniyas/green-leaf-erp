<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ledger_entry_types')
            ->where('code', 'purchase_bill')
            ->update([
                'name' => 'GL Bill',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('ledger_entry_types')
            ->where('code', 'purchase_bill')
            ->update([
                'name' => 'Daily Expense',
                'updated_at' => now(),
            ]);
    }
};
