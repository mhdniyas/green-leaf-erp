<?php

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
        Schema::table('purchaser_carts', function (Blueprint $table): void {
            $table->string('purchase_grade', 5)->default('A')->after('purchase_source')->index();
        });
        Schema::table('purchaser_cart_items', function (Blueprint $table): void {
            $table->index('purchaser_cart_id', 'purchaser_cart_items_cart_fk_index');
            $table->dropUnique(['purchaser_cart_id', 'product_id']);
            $table->string('grade', 5)->default('A')->after('product_id')->index();
            $table->unique(['purchaser_cart_id', 'product_id', 'grade'], 'purchaser_cart_items_cart_product_grade_unique');
        });
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->string('purchase_grade', 5)->default('A')->after('fulfillment_type')->index();
        });
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->string('grade', 5)->default('A')->after('product_id')->index();
        });
        Schema::table('goods_received', function (Blueprint $table): void {
            $table->string('purchase_grade', 5)->default('A')->after('is_extra')->index();
        });
        Schema::table('goods_received_items', function (Blueprint $table): void {
            $table->string('grade', 5)->default('A')->after('product_id')->index();
        });
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->foreignId('goods_received_item_id')->nullable()->after('goods_received_id')->constrained('goods_received_items')->nullOnDelete();
            $table->string('purchase_grade', 5)->default('A')->after('goods_received_item_id')->index();
            $table->string('grading_mode', 30)->default('sort_required')->after('purchase_grade')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('goods_received_item_id');
            $table->dropIndex(['purchase_grade']);
            $table->dropIndex(['grading_mode']);
            $table->dropColumn(['purchase_grade', 'grading_mode']);
        });
        Schema::table('goods_received_items', function (Blueprint $table): void {
            $table->dropIndex(['grade']);
            $table->dropColumn('grade');
        });
        Schema::table('goods_received', function (Blueprint $table): void {
            $table->dropIndex(['purchase_grade']);
            $table->dropColumn('purchase_grade');
        });
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropIndex(['grade']);
            $table->dropColumn('grade');
        });
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex(['purchase_grade']);
            $table->dropColumn('purchase_grade');
        });
        Schema::table('purchaser_cart_items', function (Blueprint $table): void {
            $table->dropUnique('purchaser_cart_items_cart_product_grade_unique');
            $table->dropIndex(['grade']);
            $table->dropColumn('grade');
            $table->unique(['purchaser_cart_id', 'product_id']);
            $table->dropIndex('purchaser_cart_items_cart_fk_index');
        });
        Schema::table('purchaser_carts', function (Blueprint $table): void {
            $table->dropIndex(['purchase_grade']);
            $table->dropColumn('purchase_grade');
        });
    }
};
