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
        Schema::create('purchaser_business_day_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('purchaser_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('business_date');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('submitted_received_summary', 12, 3)->default(0);
            $table->decimal('submitted_billed_summary', 12, 3)->default(0);
            $table->decimal('submitted_pending_summary', 12, 3)->default(0);
            $table->decimal('submitted_excess_summary', 12, 3)->default(0);
            $table->decimal('submission_coverage_percentage', 5, 2)->default(100);
            $table->unsignedInteger('pending_products_count')->default(0);
            $table->unsignedInteger('unit_mismatch_products_count')->default(0);
            $table->unsignedInteger('excess_products_count')->default(0);
            $table->boolean('has_mixed_units')->default(false);
            $table->string('status', 30)->default('submitted');
            $table->text('note')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['purchaser_user_id', 'business_date'], 'uniq_purchaser_business_day_sub');
        });

        Schema::create('purchaser_business_day_submission_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained('purchaser_business_day_submissions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('unit', 20)->default('kg');
            $table->string('ownership_source', 30)->default('allotment');
            $table->decimal('received_qty', 12, 3)->default(0);
            $table->decimal('billed_qty', 12, 3)->default(0);
            $table->decimal('pending_qty', 12, 3)->default(0);
            $table->decimal('excess_qty', 12, 3)->default(0);
            $table->decimal('coverage_percentage', 5, 2)->default(100);
            $table->boolean('unit_mismatch')->default(false);
            $table->boolean('is_fully_covered')->default(true);
            $table->string('status_at_submission', 30)->default('fully_covered');
            $table->json('item_details_snapshot')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'product_id'], 'idx_sub_item_prod');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchaser_business_day_submission_items');
        Schema::dropIfExists('purchaser_business_day_submissions');
    }
};
