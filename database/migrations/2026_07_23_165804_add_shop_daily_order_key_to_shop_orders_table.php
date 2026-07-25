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
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->string('shop_daily_order_key', 120)
                ->nullable()
                ->after('order_source')
                ->unique('shop_orders_daily_order_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropUnique('shop_orders_daily_order_key_unique');
            $table->dropColumn('shop_daily_order_key');
        });
    }
};
