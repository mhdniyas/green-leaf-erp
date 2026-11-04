<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_selling_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('pricing_mode', 30)->default('inherited'); // inherited, custom_percentage, fixed_price
            $table->decimal('markup_percentage', 8, 2)->nullable();
            $table->decimal('fixed_price', 10, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pricing_mode'], 'product_selling_prices_mode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_selling_prices');
    }
};
