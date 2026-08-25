<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->decimal('actual_payment_amount', 14, 2)->default(0);
            $table->decimal('settlement_discount_amount', 14, 2)->default(0);
            $table->decimal('vendor_advance_used_amount', 14, 2)->default(0);
            $table->decimal('new_vendor_advance_amount', 14, 2)->default(0);
            $table->foreignId('company_account_id')->nullable()->constrained('cashbook_company_accounts')->nullOnDelete();
            $table->string('payment_method', 30)->nullable();
            $table->date('payment_date');
            $table->string('reference', 160)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 30)->default('approved');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reconciliation_status', 30)->default('not_required');
            $table->boolean('is_finalized')->default(false);
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('vendor_settlement_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('cash_allocated', 14, 2)->default(0);
            $table->decimal('advance_allocated', 14, 2)->default(0);
            $table->decimal('discount_allocated', 14, 2)->default(0);
            $table->decimal('total_settled', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['vendor_settlement_id', 'purchase_invoice_id'], 'vendor_settlement_invoice_unique');
        });

        Schema::create('vendor_advances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_settlement_id')->nullable()->constrained('vendor_settlements')->nullOnDelete();
            $table->decimal('amount_original', 14, 2);
            $table->decimal('amount_remaining', 14, 2);
            $table->date('business_date');
            $table->string('status', 30)->default('open');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_advances');
        Schema::dropIfExists('vendor_settlement_allocations');
        Schema::dropIfExists('vendor_settlements');
    }
};
