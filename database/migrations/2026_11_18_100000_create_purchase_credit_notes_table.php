<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('public_uuid', 36)->unique();
            $table->string('credit_note_number', 80)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->nullOnDelete();
            $table->date('credit_date')->index();
            $table->string('reason', 50)->index();
            $table->string('money_effect', 50)->index();
            $table->string('inventory_action', 50)->index();
            $table->string('status', 30)->default('active')->index();
            $table->foreignId('company_account_id')->nullable()->constrained('cashbook_company_accounts')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('wastage_reason', 50)->nullable();
            $table->boolean('deduct_stock_for_damage')->default(true);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('applied_to_invoice_amount', 15, 2)->default(0.00);
            $table->foreignId('vendor_advance_id')->nullable()->constrained('vendor_advances')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'credit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_credit_notes');
    }
};
