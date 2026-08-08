<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_daily_product_prices', function (Blueprint $table): void {
            $table->id();
            $table->date('business_date')->index();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('selling_price', 10, 2);
            $table->string('price_unit', 20)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['business_date', 'shop_id', 'product_id'], 'shop_daily_product_prices_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_daily_product_prices');
    }
};