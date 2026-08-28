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
        Schema::create('advance_receive_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('advance_goods_received_id')->constrained('goods_received')->cascadeOnDelete();
            $table->foreignId('advance_goods_received_item_id')->nullable()->constrained('goods_received_items')->nullOnDelete();
            $table->foreignId('advance_stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
            $table->foreignId('bill_goods_received_id')->constrained('goods_received')->cascadeOnDelete();
            $table->foreignId('bill_goods_received_item_id')->nullable()->constrained('goods_received_items')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('matched_qty', 12, 3);
            $table->string('matched_unit', 20)->default('kg');
            $table->decimal('base_qty', 12, 3);
            $table->decimal('conversion_to_base', 10, 4)->default(1.0);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at');
            $table->string('client_submission_id', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('advance_goods_received_id');
            $table->index('bill_goods_received_id');
            $table->index('product_id');
            $table->index('client_submission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advance_receive_matches');
    }
};
