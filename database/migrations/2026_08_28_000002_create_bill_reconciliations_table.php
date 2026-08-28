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
        Schema::create('bill_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('goods_received_id')->nullable()->constrained('goods_received')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('source_type', 20)->default('normal'); // 'normal', 'advance', 'mixed'
            $table->string('status', 20)->default('confirmed');
            $table->decimal('total_bill_base_qty', 12, 3)->default(0);
            $table->decimal('total_matched_base_qty', 12, 3)->default(0);
            $table->decimal('total_new_receive_base_qty', 12, 3)->default(0);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at');
            $table->string('client_submission_id', 100)->nullable();
            $table->string('submission_payload_hash', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index('goods_received_id');
            $table->index('warehouse_id');
            $table->index('client_submission_id');
            $table->index('source_type');
        });

        Schema::create('bill_reconciliation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bill_reconciliation_id')->constrained('bill_reconciliations')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('bill_qty', 12, 3)->default(0);
            $table->string('bill_unit', 20)->default('kg');
            $table->decimal('bill_base_qty', 12, 3)->default(0);
            $table->decimal('advance_matched_qty', 12, 3)->default(0);
            $table->string('advance_matched_unit', 20)->default('kg');
            $table->decimal('advance_matched_base_qty', 12, 3)->default(0);
            $table->decimal('new_receive_qty', 12, 3)->default(0);
            $table->string('new_receive_unit', 20)->default('kg');
            $table->decimal('new_receive_base_qty', 12, 3)->default(0);
            $table->decimal('relevant_loadout_qty', 12, 3)->default(0);
            $table->decimal('unbilled_loadout_qty', 12, 3)->default(0);
            $table->decimal('reconciled_qty', 12, 3)->default(0);
            $table->decimal('reconciled_base_qty', 12, 3)->default(0);
            $table->string('difference_status', 30)->default('matched'); // 'matched', 'partial', 'bill_shortage', 'unmatched'
            $table->timestamps();

            $table->index('bill_reconciliation_id');
            $table->index('purchase_order_item_id');
            $table->index('product_id');
        });

        Schema::table('advance_receive_matches', function (Blueprint $table): void {
            $table->foreignId('bill_reconciliation_id')->nullable()->after('bill_goods_received_item_id')->constrained('bill_reconciliations')->cascadeOnDelete();
            $table->foreignId('bill_reconciliation_line_id')->nullable()->after('bill_reconciliation_id')->constrained('bill_reconciliation_lines')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advance_receive_matches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bill_reconciliation_line_id');
            $table->dropConstrainedForeignId('bill_reconciliation_id');
        });

        Schema::dropIfExists('bill_reconciliation_lines');
        Schema::dropIfExists('bill_reconciliations');
    }
};
