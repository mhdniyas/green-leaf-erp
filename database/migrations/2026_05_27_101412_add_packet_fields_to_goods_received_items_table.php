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
        Schema::table('goods_received_items', function (Blueprint $table) {
            $table->string('received_unit', 20)->default('kg')->after('product_id');
            $table->decimal('received_packet_qty', 10, 3)->nullable()->after('received_unit');
            $table->decimal('received_weight_per_packet', 10, 3)->nullable()->after('received_packet_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_received_items', function (Blueprint $table) {
            $table->dropColumn(['received_unit', 'received_packet_qty', 'received_weight_per_packet']);
        });
    }
};
