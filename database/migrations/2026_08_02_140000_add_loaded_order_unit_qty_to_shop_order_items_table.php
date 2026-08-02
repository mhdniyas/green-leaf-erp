<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('shop_order_items', 'loaded_order_unit_qty')) {
                $table->decimal('loaded_order_unit_qty', 10, 2)->nullable()->after('loaded_qty');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('shop_order_items', 'loaded_order_unit_qty')) {
                $table->dropColumn('loaded_order_unit_qty');
            }
        });
    }
};
