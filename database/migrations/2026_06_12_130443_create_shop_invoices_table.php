<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('shop_order_id')->unique()->constrained('shop_orders')->cascadeOnDelete();
            $table->string('invoice_number', 80)->unique();
            $table->date('business_date')->index();
            $table->string('status', 30)->default('generated')->index();
            $table->string('delivery_status', 30)->default('pending')->index();
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('shortage_total', 12, 2)->default(0.00);
            $table->decimal('discount_total', 12, 2)->default(0.00);
            $table->decimal('final_total', 12, 2)->default(0.00);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->decimal('balance_amount', 12, 2)->default(0.00);
            $table->text('delivery_note')->nullable();
            $table->text('payment_note')->nullable();
            $table->text('admin_price_note')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivery_confirmed_at')->nullable();
            $table->foreignId('payment_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_approved_at')->nullable();
            $table->foreignId('price_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('price_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_invoices');
    }
};
