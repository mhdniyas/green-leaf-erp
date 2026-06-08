<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('purchase_unit', 20)->default('kg');
            $table->decimal('packet_qty', 10, 3)->nullable();
            $table->decimal('weight_per_packet', 10, 3)->nullable();
            $table->decimal('actual_weight', 10, 3)->nullable();
            $table->decimal('quantity', 10, 3); // expected quantity in kg
            $table->decimal('unit_price', 10, 4); // purchase price per kg
            $table->string('price_basis', 20)->default('per_kg');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchase_order_id', 'product_id'], 'po_items_po_prod_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
