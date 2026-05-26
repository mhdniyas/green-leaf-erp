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
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->string('fulfillment_type', 30)->default('warehouse')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->dropColumn('fulfillment_type');
        });
    }
};
