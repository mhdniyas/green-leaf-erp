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
        Schema::create('daily_product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_price_group_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 5)->default('A')->index();
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->string('price_source', 20)->default('margin');
            $table->decimal('margin_percent', 6, 2)->nullable();
            $table->boolean('manual_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'shop_price_group_id', 'grade'], 'daily_product_prices_unique_current');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_product_prices');
    }
};
