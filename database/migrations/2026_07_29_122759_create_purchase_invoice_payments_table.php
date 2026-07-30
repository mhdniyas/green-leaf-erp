<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('payment_date')->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('payment_method', 100)->nullable();
            $table->string('payment_paid_by', 30)->nullable()->index();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purchase_invoice_id', 'payment_date'], 'purchase_invoice_payments_invoice_date_idx');
            $table->index(['supplier_id', 'payment_date'], 'purchase_invoice_payments_supplier_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_payments');
    }
};
