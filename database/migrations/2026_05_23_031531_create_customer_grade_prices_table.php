<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_grade_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('grade'); // A, B, C
            $table->decimal('price_per_kg', 10, 4);
            $table->timestamps();

            $table->unique(['customer_id', 'product_id', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_grade_prices');
    }
};
