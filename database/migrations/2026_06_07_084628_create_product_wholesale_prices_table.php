<?php

declare(strict_types=1);

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
        Schema::create('product_wholesale_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 5)->default('A')->index();
            $table->decimal('weighted_average_cost', 12, 4)->default(0);
            $table->decimal('wholesale_price', 12, 4)->default(0);
            $table->decimal('sellable_quantity', 12, 3)->default(0);
            $table->decimal('total_cost', 14, 4)->default(0);
            $table->string('source_type', 30)->default('seed');
            $table->string('source_reference', 100)->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'grade']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_wholesale_prices');
    }
};
