<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('warehouse_tag', 12)->nullable()->unique();
            $table->foreignId('shop_price_group_id')->nullable()->constrained('shop_price_groups')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->text('address')->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
        });

        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->string('order_number', 50)->unique();
            $table->string('state', 30)->default('draft'); // draft, submitted, approved, rejected, update_requested
            $table->string('delivery_status', 30)->default('pending_delivery');
            $table->string('payment_status', 30)->default('unpaid');
            $table->boolean('is_allocation_completed')->default(false);
            $table->boolean('is_delivered')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('delivery_notes')->nullable();
            $table->decimal('cash_collected', 12, 2)->default(0.00);
            $table->decimal('cash_discrepancy', 12, 2)->default(0.00);
            $table->decimal('balance_amount', 12, 2)->default(0.00);
            $table->text('finance_note')->nullable();
            $table->decimal('total_shortage_value', 12, 2)->default(0.00);
            $table->text('sorting_notes')->nullable();
            $table->date('business_date')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->text('update_reason')->nullable();
            $table->unsignedInteger('latest_revision_no')->default(1);
            $table->boolean('has_pending_revision')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('product_grade', 5)->default('A');
            $table->decimal('requested_qty', 10, 2);
            $table->decimal('approved_qty', 10, 2)->nullable();
            $table->decimal('delivered_qty', 10, 2)->nullable();
            $table->decimal('shortage_qty', 10, 2)->default(0.00);
            $table->decimal('unit_cost', 10, 4)->default(0.00);
            $table->decimal('shortage_value', 10, 2)->default(0.00);
            $table->string('unit', 20);
            $table->foreignId('locked_price_group_id')->nullable()->constrained('shop_price_groups')->nullOnDelete();
            $table->decimal('locked_selling_price', 10, 2)->default(0);
            $table->string('locked_price_source', 20)->default('margin');
            $table->decimal('line_total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('fulfillment_type', 30)->default('warehouse')->index();
            $table->boolean('is_sorted')->default(false)->index();
            $table->string('sorting_status', 30)->default('pending');
            $table->timestamp('sorted_at')->nullable();
            $table->foreignId('sorted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shop_order_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->string('status', 30)->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_order_id', 'revision_no']);
            $table->index(['shop_order_id', 'status']);
        });

        Schema::create('shop_order_revision_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_revision_id')->constrained('shop_order_revisions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('old_requested_qty', 10, 2);
            $table->decimal('new_requested_qty', 10, 2);
            $table->decimal('delta_qty', 10, 2);
            $table->timestamps();

            $table->unique(['shop_order_revision_id', 'product_id'], 'shop_order_rev_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_revision_items');
        Schema::dropIfExists('shop_order_revisions');
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_id');
        });

        Schema::dropIfExists('shops');
    }
};
