<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_ledger_entry_settings', function (Blueprint $table) {
            $table->string('monthly_report_bucket', 40)->nullable()->after('sales_report_bucket');
            $table->index(['shop_id', 'monthly_report_bucket']);
        });
    }

    public function down(): void
    {
        Schema::table('shop_ledger_entry_settings', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'monthly_report_bucket']);
            $table->dropColumn('monthly_report_bucket');
        });
    }
};
