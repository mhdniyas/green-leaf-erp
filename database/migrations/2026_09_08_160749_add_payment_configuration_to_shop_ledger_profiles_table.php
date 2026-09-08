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
        Schema::table('shop_ledger_profiles', function (Blueprint $table): void {
            $table->json('payment_configuration')->nullable()->after('preset_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_ledger_profiles', function (Blueprint $table): void {
            $table->dropColumn('payment_configuration');
        });
    }
};
