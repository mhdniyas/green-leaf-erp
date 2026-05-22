<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wastage_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('grade', 5);  // ProductGrade enum
            $table->decimal('quantity', 10, 3); // kg wasted
            $table->decimal('cost_per_kg', 10, 4); // cost at time of wastage
            $table->string('reason', 50); // WastageReason enum
            $table->date('wastage_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'wastage_date']);
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wastage_entries');
    }
};
