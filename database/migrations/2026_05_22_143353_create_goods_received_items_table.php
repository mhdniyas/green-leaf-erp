<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_received_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_received_id')->constrained('goods_received')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('received_unit', 20)->default('kg');
            $table->decimal('received_packet_qty', 10, 3)->nullable();
            $table->decimal('received_weight_per_packet', 10, 3)->nullable();
            $table->decimal('received_qty', 10, 3); // actual received quantity in kg
            $table->decimal('variance', 10, 3); // received_qty - ordered_qty
            $table->timestamps();
            $table->softDeletes();

            $table->index(['goods_received_id', 'product_id'], 'grn_items_grn_prod_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_received_items');
    }
};
