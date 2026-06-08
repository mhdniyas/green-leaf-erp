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
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->string('product_grade', 5)->default('A')->after('product_id');
            $table->foreignId('locked_price_group_id')
                ->nullable()
                ->after('unit')
                ->constrained('shop_price_groups')
                ->nullOnDelete();
            $table->decimal('locked_selling_price', 10, 2)->default(0)->after('locked_price_group_id');
            $table->string('locked_price_source', 20)->default('margin')->after('locked_selling_price');
            $table->decimal('line_total', 12, 2)->default(0)->after('locked_price_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('locked_price_group_id');
            $table->dropColumn([
                'product_grade',
                'locked_selling_price',
                'locked_price_source',
                'line_total',
            ]);
        });
    }
};
