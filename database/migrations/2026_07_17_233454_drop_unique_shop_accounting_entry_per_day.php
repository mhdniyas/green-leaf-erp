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
        Schema::table('shop_accounting_entries', function (Blueprint $table): void {
            $table->dropUnique('shop_accounting_entries_shop_id_business_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_accounting_entries', function (Blueprint $table): void {
            $table->unique(['shop_id', 'business_date']);
        });
    }
};
