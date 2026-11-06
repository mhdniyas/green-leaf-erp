<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_credit_note_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_credit_note_id')->constrained('shop_credit_notes')->cascadeOnDelete();
            $table->foreignId('shop_invoice_item_id')->nullable()->constrained('shop_invoice_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('product_grade', 5)->default('A');
            $table->string('product_name', 255);
            $table->string('unit', 20);
            $table->string('price_unit', 20)->nullable();
            $table->decimal('original_quantity', 12, 4)->default(0.0000);
            $table->decimal('original_unit_price', 10, 2)->default(0.00);
            $table->decimal('original_line_total', 12, 2)->default(0.00);
            $table->decimal('adjusted_quantity', 12, 4)->default(0.0000);
            $table->decimal('adjusted_unit_price', 10, 2)->default(0.00);
            $table->decimal('line_credit_amount', 12, 2)->default(0.00);
            $table->string('direction', 20)->default('credit')->index();
            $table->string('inventory_action', 50)->default('none');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shop_credit_note_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_credit_note_items');
    }
};
