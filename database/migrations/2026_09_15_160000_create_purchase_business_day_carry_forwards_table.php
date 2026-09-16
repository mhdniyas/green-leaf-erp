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
        Schema::create('purchase_business_day_carry_forwards', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('origin_business_day_id')->constrained('purchase_business_days', indexName: 'pbd_cf_origin_foreign')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses', indexName: 'pbd_cf_wh_foreign')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products', indexName: 'pbd_cf_prod_foreign')->cascadeOnDelete();
            $table->string('unit', 50);
            $table->decimal('pending_qty_at_close', 12, 3);
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'pbd_cf_cb_foreign')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users', indexName: 'pbd_cf_rb_foreign')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('origin_business_day_id', 'pbd_cf_origin_idx');
            $table->index('warehouse_id', 'pbd_cf_wh_idx');
            $table->index('product_id', 'pbd_cf_prod_idx');
            $table->index('status', 'pbd_cf_status_idx');
            $table->unique(['origin_business_day_id', 'product_id', 'unit'], 'pbd_carry_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_business_day_carry_forwards');
    }
};
