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
        Schema::table('shop_ledger_entry_settings', function (Blueprint $table) {
            $table->boolean('note_enabled')->default(false)->after('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_ledger_entry_settings', function (Blueprint $table) {
            $table->dropColumn('note_enabled');
        });
    }
};
