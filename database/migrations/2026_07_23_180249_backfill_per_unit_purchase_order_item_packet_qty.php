<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('purchase_order_items')
            ->where('price_basis', 'per_unit')
            ->whereNull('packet_qty')
            ->where('quantity', '>', 0)
            ->update([
                'packet_qty' => DB::raw('quantity'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data backfill is intentionally not reversed.
    }
};
