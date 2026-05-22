<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('reference', 100)->unique(); // e.g. BATCH-20260522-001
            $table->date('received_at');
            $table->decimal('total_kg', 10, 3); // received quantity
            $table->decimal('cost_per_kg', 10, 4); // landed cost per kg
            $table->decimal('transport_cost', 10, 2)->default(0);
            $table->decimal('labour_cost', 10, 2)->default(0);
            $table->string('status', 20)->default('pending'); // BatchStatus enum
            $table->text('notes')->nullable();
            $table->timestamp('sorted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
