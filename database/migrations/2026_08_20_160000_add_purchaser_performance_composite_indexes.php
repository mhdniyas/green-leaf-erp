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
            $table->index(['show_in_purchaser_order', 'is_active', 'category_id'], 'products_purchaser_active_cat_idx');
        });

        Schema::table('purchaser_cart_items', function (Blueprint $table): void {
            $table->index(['product_id', 'grade'], 'purchaser_cart_items_product_grade_idx');
        });

        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->index(['business_date', 'state'], 'shop_orders_date_state_idx');
        });

        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->index(['product_grade', 'product_id'], 'shop_order_items_grade_product_idx');
        });

        Schema::table('purchaser_carts', function (Blueprint $table): void {
            $table->index(['user_id', 'business_date', 'purchase_grade', 'status'], 'purchaser_carts_user_date_grade_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchaser_carts', function (Blueprint $table): void {
            $table->dropIndex('purchaser_carts_user_date_grade_status_idx');
        });

        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropIndex('shop_order_items_grade_product_idx');
        });

        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->dropIndex('shop_orders_date_state_idx');
        });

        Schema::table('purchaser_cart_items', function (Blueprint $table): void {
            $table->dropIndex('purchaser_cart_items_product_grade_idx');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_purchaser_active_cat_idx');
        });
    }
};
