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
        if (Schema::hasColumn('shop_accounting_entry_lines', 'loan_cashbook_offset_enabled')) {
            Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
                $table->dropColumn('loan_cashbook_offset_enabled');
            });
        }

        if (Schema::hasColumn('shop_loan_category_settings', 'cashbook_offset_enabled')) {
            Schema::table('shop_loan_category_settings', function (Blueprint $table): void {
                $table->dropColumn('cashbook_offset_enabled');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('shop_accounting_entry_lines', 'loan_cashbook_offset_enabled')) {
            Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
                $table->boolean('loan_cashbook_offset_enabled')->default(false)->after('cash_effect');
            });
        }

        if (! Schema::hasColumn('shop_loan_category_settings', 'cashbook_offset_enabled')) {
            Schema::table('shop_loan_category_settings', function (Blueprint $table): void {
                $table->boolean('cashbook_offset_enabled')->default(false)->after('default_daily_amount');
            });
        }
    }
};
