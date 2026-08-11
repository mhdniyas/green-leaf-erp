<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->index(['shop_order_id', 'updated_at'], 'shop_order_items_order_updated_idx');
        });

        Schema::table('shop_invoice_items', function (Blueprint $table): void {
            $table->index(['shop_invoice_id', 'updated_at'], 'shop_invoice_items_invoice_updated_idx');
        });

        Schema::table('shop_order_loadout_states', function (Blueprint $table): void {
            $table->index(['shop_order_id', 'updated_at'], 'loadout_states_order_updated_idx');
        });

        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->index(['product_id', 'id'], 'stock_batches_product_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->dropIndex('stock_batches_product_id_idx');
        });

        Schema::table('shop_order_loadout_states', function (Blueprint $table): void {
            $table->dropIndex('loadout_states_order_updated_idx');
        });

        Schema::table('shop_invoice_items', function (Blueprint $table): void {
            $table->dropIndex('shop_invoice_items_invoice_updated_idx');
        });

        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropIndex('shop_order_items_order_updated_idx');
        });
    }
};
