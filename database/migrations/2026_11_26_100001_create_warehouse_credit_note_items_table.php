<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_credit_note_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_credit_note_id')
                ->constrained('warehouse_credit_notes')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('grade', 10)->default('A');
            $table->string('inventory_action', 20)->index();
            $table->string('unit', 30)->default('kg');
            $table->decimal('conversion_to_base', 12, 4)->default(1.0000);
            $table->decimal('quantity', 15, 3)->default(0.000);
            $table->decimal('base_qty', 15, 3)->default(0.000);
            $table->decimal('valuation_rate', 15, 4)->default(0.0000);
            $table->decimal('line_total', 15, 2)->default(0.00);
            $table->decimal('fifo_cost_total', 15, 2)->default(0.00);
            $table->foreignId('wastage_entry_id')->nullable()->constrained('wastage_entries')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['warehouse_credit_note_id', 'product_id'], 'wcn_items_note_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_credit_note_items');
    }
};
