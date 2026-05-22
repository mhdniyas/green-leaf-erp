<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('stock_batches')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('grade', 5); // ProductGrade enum: A, B, C, D
            $table->string('type', 20);  // StockMovementType enum
            $table->decimal('quantity', 10, 3); // kg
            $table->decimal('cost_per_unit', 10, 4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'grade']);
            $table->index(['batch_id', 'grade']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
