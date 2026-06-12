<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_invoice_id')->constrained('shop_invoices')->cascadeOnDelete();
            $table->foreignId('shop_order_item_id')->nullable()->constrained('shop_order_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('product_name', 255);
            $table->string('unit', 20);
            $table->decimal('approved_qty', 10, 2)->default(0.00);
            $table->decimal('delivered_qty', 10, 2)->default(0.00);
            $table->decimal('shortage_qty', 10, 2)->default(0.00);
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->decimal('line_subtotal', 12, 2)->default(0.00);
            $table->decimal('shortage_amount', 12, 2)->default(0.00);
            $table->decimal('final_line_total', 12, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['shop_invoice_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_invoice_items');
    }
};
