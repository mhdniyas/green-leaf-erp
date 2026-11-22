<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_credit_note_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_credit_note_id')->constrained('purchase_credit_notes')->cascadeOnDelete();
            $table->foreignId('purchaser_cart_item_id')->nullable()->constrained('purchaser_cart_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('grade', 10)->nullable()->default('A');
            $table->decimal('quantity', 15, 3)->default(0.000);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->decimal('line_total', 15, 2)->default(0.00);
            $table->string('inventory_action', 50)->nullable();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('wastage_entry_id')->nullable()->constrained('wastage_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['purchase_credit_note_id', 'product_id'], 'pcn_items_note_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_credit_note_items');
    }
};
