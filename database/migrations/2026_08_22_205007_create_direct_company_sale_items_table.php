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
        Schema::create('direct_company_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('direct_company_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('unit', 20);
            $table->decimal('quantity', 12, 3);
            $table->decimal('conversion_to_base', 12, 4);
            $table->decimal('base_quantity', 12, 3);
            $table->decimal('unit_rate', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->string('price_source', 30);
            $table->timestamps();

            $table->index(['direct_company_sale_id', 'product_id'], 'direct_sale_items_sale_product_index');
            $table->index(['warehouse_id', 'product_id'], 'direct_sale_items_warehouse_product_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direct_company_sale_items');
    }
};
