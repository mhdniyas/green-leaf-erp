<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update stock_batches
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->foreignId('goods_received_id')->nullable()->constrained('goods_received')->nullOnDelete()->after('warehouse_id');
        });

        // 2. Update goods_received_items
        Schema::table('goods_received_items', function (Blueprint $table): void {
            $table->decimal('purchased_qty', 10, 3)->nullable()->after('received_qty');
            $table->string('discrepancy_type', 50)->default('none')->after('purchased_qty');
            $table->text('discrepancy_note')->nullable()->after('discrepancy_type');
        });

        // 3. Update shop_order_items
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->decimal('loaded_qty', 10, 2)->nullable()->after('approved_qty');
            $table->string('loadout_discrepancy_type', 50)->default('none')->after('loaded_qty');
            $table->text('loadout_discrepancy_note')->nullable()->after('loadout_discrepancy_type');
            $table->string('delivery_discrepancy_type', 50)->default('none')->after('shortage_value');
            $table->text('delivery_discrepancy_note')->nullable()->after('delivery_discrepancy_type');
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'loaded_qty',
                'loadout_discrepancy_type',
                'loadout_discrepancy_note',
                'delivery_discrepancy_type',
                'delivery_discrepancy_note',
            ]);
        });

        Schema::table('goods_received_items', function (Blueprint $table): void {
            $table->dropColumn([
                'purchased_qty',
                'discrepancy_type',
                'discrepancy_note',
            ]);
        });

        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('goods_received_id');
        });
    }
};
