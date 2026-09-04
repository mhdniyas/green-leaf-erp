<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shop_ledger_header_groups', function (Blueprint $table) {
            $table->boolean('show_both_sides')->default(false)->after('product_tagging_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_ledger_header_groups', function (Blueprint $table) {
            $table->dropColumn('show_both_sides');
        });
    }
};
