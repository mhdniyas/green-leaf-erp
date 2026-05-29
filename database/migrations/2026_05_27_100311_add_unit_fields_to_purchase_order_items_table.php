<?php

declare(strict_types=1);

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
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('purchase_unit', 20)->default('kg')->after('product_id');
            $table->decimal('packet_qty', 10, 3)->nullable()->after('purchase_unit');
            $table->decimal('weight_per_packet', 10, 3)->nullable()->after('packet_qty');
            $table->decimal('actual_weight', 10, 3)->nullable()->after('weight_per_packet');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['purchase_unit', 'packet_qty', 'weight_per_packet', 'actual_weight']);
        });
    }
};
