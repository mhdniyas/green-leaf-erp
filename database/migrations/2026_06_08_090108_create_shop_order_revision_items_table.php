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
        Schema::create('shop_order_revision_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_requested_qty', 10, 2);
            $table->decimal('new_requested_qty', 10, 2);
            $table->decimal('delta_qty', 10, 2);
            $table->timestamps();

            $table->unique(['shop_order_revision_id', 'product_id'], 'shop_order_rev_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_order_revision_items');
    }
};
