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
        Schema::table('shop_accounting_categories', function (Blueprint $table): void {
            $table->boolean('cash_effect')->default(true)->after('type');
        });

        Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
            $table->boolean('cash_effect')->default(true)->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
            $table->dropColumn('cash_effect');
        });

        Schema::table('shop_accounting_categories', function (Blueprint $table): void {
            $table->dropColumn('cash_effect');
        });
    }
};
