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
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->boolean('is_delivered')->default(false)->after('is_allocation_completed');
            $table->timestamp('delivered_at')->nullable()->after('is_delivered');
            $table->unsignedBigInteger('delivered_by')->nullable()->after('delivered_at');
            $table->text('delivery_notes')->nullable()->after('delivered_by');
            $table->decimal('cash_collected', 12, 2)->default(0.00)->after('delivery_notes');
            $table->decimal('cash_discrepancy', 12, 2)->default(0.00)->after('cash_collected');
            $table->decimal('total_shortage_value', 12, 2)->default(0.00)->after('cash_discrepancy');

            $table->foreign('delivered_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->decimal('delivered_qty', 10, 2)->nullable()->after('approved_qty');
            $table->decimal('shortage_qty', 10, 2)->default(0.00)->after('delivered_qty');
            $table->decimal('unit_cost', 10, 4)->default(0.00)->after('shortage_qty');
            $table->decimal('shortage_value', 10, 2)->default(0.00)->after('unit_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->dropColumn(['delivered_qty', 'shortage_qty', 'unit_cost', 'shortage_value']);
        });

        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropForeign(['delivered_by']);
            $table->dropColumn([
                'is_delivered',
                'delivered_at',
                'delivered_by',
                'delivery_notes',
                'cash_collected',
                'cash_discrepancy',
                'total_shortage_value',
            ]);
        });
    }
};
