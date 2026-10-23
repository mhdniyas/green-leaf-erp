<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_order_revision_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_order_revision_items', 'product_grade')) {
                $table->string('product_grade', 5)->default('A')->after('product_id');
            }
            // Add dedicated foreign key index so MySQL allows replacing the composite unique key
            $table->index('shop_order_revision_id', 'shop_order_rev_items_rev_id_idx');
        });

        Schema::table('shop_order_revision_items', function (Blueprint $table): void {
            $table->dropUnique('shop_order_rev_items_unique');
            $table->unique(['shop_order_revision_id', 'product_id', 'product_grade'], 'shop_order_rev_items_unique');
            $table->dropIndex('shop_order_rev_items_rev_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_revision_items', function (Blueprint $table): void {
            $table->index('shop_order_revision_id', 'shop_order_rev_items_rev_id_idx');
        });

        Schema::table('shop_order_revision_items', function (Blueprint $table): void {
            $table->dropUnique('shop_order_rev_items_unique');
            $table->unique(['shop_order_revision_id', 'product_id'], 'shop_order_rev_items_unique');
            $table->dropIndex('shop_order_rev_items_rev_id_idx');
            $table->dropColumn('product_grade');
        });
    }
};
