<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', fn (Blueprint $table) => $table->index(['created_at', 'warehouse_id', 'product_id'], 'stock_movements_listing_index'));
        Schema::table('stock_batches', fn (Blueprint $table) => $table->index(['status', 'warehouse_id', 'received_at'], 'stock_batches_listing_index'));
    }

    public function down(): void
    {
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropIndex('stock_movements_listing_index'));
        Schema::table('stock_batches', fn (Blueprint $table) => $table->dropIndex('stock_batches_listing_index'));
    }
};
