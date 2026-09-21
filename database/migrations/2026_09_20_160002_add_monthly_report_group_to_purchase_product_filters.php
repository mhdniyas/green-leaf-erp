<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_product_filters', function (Blueprint $table) {
            $table->string('monthly_report_group', 30)->nullable()->after('is_active');
            $table->index('monthly_report_group');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_product_filters', function (Blueprint $table) {
            $table->dropIndex(['monthly_report_group']);
            $table->dropColumn('monthly_report_group');
        });
    }
};
