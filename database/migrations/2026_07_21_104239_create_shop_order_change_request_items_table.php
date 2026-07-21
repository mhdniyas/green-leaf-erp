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
        Schema::create('shop_order_change_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_change_request_id')
                ->constrained(table: 'shop_order_change_requests', indexName: 'socr_items_request_fk')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('old_qty', 10, 2)->default(0);
            $table->decimal('new_qty', 10, 2)->default(0);
            $table->decimal('approved_qty', 10, 2)->nullable();
            $table->decimal('delta_qty', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['shop_order_change_request_id', 'product_id'], 'shop_order_change_request_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_order_change_request_items');
    }
};
