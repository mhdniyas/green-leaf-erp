<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_order_items', 'actual_weight')) {
                $table->decimal('actual_weight', 10, 2)->nullable()->after('loaded_order_unit_qty');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_order_items', 'actual_weight')) {
                $table->dropColumn('actual_weight');
            }
        });
    }
};
