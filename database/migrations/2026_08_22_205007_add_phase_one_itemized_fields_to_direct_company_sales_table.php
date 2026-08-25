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
        Schema::table('direct_company_sales', function (Blueprint $table): void {
            $table->foreignId('shop_id')->nullable()->after('customer_name')->constrained('shops')->restrictOnDelete();
            $table->string('sale_status', 30)->default('confirmed')->after('shop_id')->index();
            $table->string('payment_method')->nullable()->change();
            $table->foreignId('company_account_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('direct_company_sales', function (Blueprint $table): void {
            $table->dropForeign(['shop_id']);
            $table->dropColumn(['shop_id', 'sale_status']);
            $table->string('payment_method')->nullable(false)->change();
            $table->foreignId('company_account_id')->nullable(false)->change();
        });
    }
};
