<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_credit_note_receipts')) {
            Schema::create('shop_credit_note_receipts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_credit_note_id')->constrained('shop_credit_notes')->cascadeOnDelete();
                $table->foreignId('shop_credit_note_item_id')->constrained('shop_credit_note_items')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
                $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
                $table->string('received_grade', 5)->default('A');
                $table->decimal('received_quantity', 12, 4)->default(0.0000);
                $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('received_at');
                $table->string('status', 30)->default('verified');
                $table->text('remarks')->nullable();
                $table->string('idempotency_key', 100)->nullable();
                $table->timestamps();

                $table->index(['shop_credit_note_id', 'shop_credit_note_item_id'], 'scn_receipts_note_item_idx');
                $table->index(['product_id', 'received_grade'], 'scn_receipts_prod_grade_idx');
                $table->index(['warehouse_id', 'received_at'], 'scn_receipts_wh_date_idx');
                $table->index(['shop_credit_note_item_id', 'idempotency_key'], 'scn_receipts_item_idemp_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_credit_note_receipts');
    }
};
