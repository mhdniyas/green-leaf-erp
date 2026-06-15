<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchaser_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('business_date');
            $table->string('status', 30)->default('draft');
            $table->string('cart_number', 100)->unique();
            $table->string('bill_number', 100)->nullable();
            $table->string('payment_method', 100)->nullable();
            $table->text('payment_note')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('goods_received_id')->nullable()->constrained('goods_received')->nullOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['business_date', 'status']);
            $table->index(['user_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchaser_carts');
    }
};
