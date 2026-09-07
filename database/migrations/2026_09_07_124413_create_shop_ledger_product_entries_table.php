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
        if (! Schema::hasTable('shop_ledger_product_entries')) {
            Schema::create('shop_ledger_product_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->date('business_date');
                $table->foreignId('header_group_id')->constrained('shop_ledger_header_groups')->restrictOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->string('product_name');
                $table->string('product_sku')->nullable();
                $table->decimal('quantity', 15, 4)->default(0);
                $table->string('unit', 40)->default('unit');
                $table->decimal('amount', 15, 2)->default(0);
                $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['shop_id', 'business_date', 'header_group_id'], 'shop_ledger_product_entry_lookup');
                $table->unique(['shop_id', 'business_date', 'header_group_id', 'product_id'], 'shop_ledger_product_entry_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_ledger_product_entries');
    }
};
