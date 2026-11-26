<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_settlement_credit_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_settlement_id')->constrained('vendor_settlements', 'id', 'fk_vsca_settlement')->restrictOnDelete();
            $table->foreignId('purchase_credit_note_id')->constrained('purchase_credit_notes', 'id', 'fk_vsca_pcn')->restrictOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices', 'id', 'fk_vsca_invoice')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->boolean('is_reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users', 'id', 'fk_vsca_rev_by')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['vendor_settlement_id', 'is_reversed'], 'idx_vsca_settlement_rev');
            $table->index(['purchase_credit_note_id', 'is_reversed'], 'idx_vsca_pcn_rev');
            $table->index(['purchase_invoice_id', 'is_reversed'], 'idx_vsca_invoice_rev');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_settlement_credit_allocations');
    }
};
