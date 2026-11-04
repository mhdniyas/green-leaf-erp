<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('selling_price_category_id')
                ->nullable()
                ->after('category_id')
                ->constrained('selling_price_categories')
                ->nullOnDelete();

            $table->index('selling_price_category_id', 'products_selling_price_category_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['selling_price_category_id']);
            $table->dropIndex('products_selling_price_category_id_idx');
            $table->dropColumn('selling_price_category_id');
        });
    }
};
