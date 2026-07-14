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
            $table->index(['shop_id', 'business_date', 'status'], 'shop_accounting_entries_report_index');
        });

        Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
            $table->index(['shop_accounting_entry_id', 'cash_effect', 'type'], 'shop_accounting_lines_cash_index');
        });

        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->index(['entry_date', 'source_type', 'source_event'], 'journal_entries_reporting_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropIndex('journal_entries_reporting_index');
        });

        Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
            $table->dropIndex('shop_accounting_lines_cash_index');
        });

        Schema::table('shop_accounting_entries', function (Blueprint $table): void {
            $table->dropIndex('shop_accounting_entries_report_index');
        });
    }
};
