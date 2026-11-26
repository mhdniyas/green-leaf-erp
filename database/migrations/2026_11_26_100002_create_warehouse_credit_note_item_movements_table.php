<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_credit_note_item_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_credit_note_item_id')
                ->constrained('warehouse_credit_note_items', 'id', 'wcn_item_mov_item_fk')
                ->cascadeOnDelete();
            $table->foreignId('stock_batch_id')
                ->constrained('stock_batches', 'id', 'wcn_item_mov_batch_fk')
                ->restrictOnDelete();
            $table->foreignId('stock_movement_id')
                ->constrained('stock_movements', 'id', 'wcn_item_mov_mov_fk')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 3)->default(0.000);
            $table->decimal('cost_per_unit', 15, 4)->default(0.0000);
            $table->timestamps();

            $table->unique(['warehouse_credit_note_item_id', 'stock_movement_id'], 'wcn_item_movements_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_credit_note_item_movements');
    }
};
