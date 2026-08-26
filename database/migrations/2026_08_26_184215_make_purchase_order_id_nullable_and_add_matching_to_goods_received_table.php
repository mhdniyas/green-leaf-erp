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
        Schema::table('goods_received', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable()->change();
            $table->foreignId('destination_shop_id')->nullable()->after('purchase_order_id')->constrained('shops')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('destination_shop_id')->constrained('warehouses')->nullOnDelete();
            $table->foreignId('matched_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable()->after('matched_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_received', function (Blueprint $table): void {
            $table->dropForeign(['matched_by']);
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['destination_shop_id']);
            $table->dropColumn(['destination_shop_id', 'warehouse_id', 'matched_by', 'matched_at']);
        });
    }
};
