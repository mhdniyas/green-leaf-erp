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
        Schema::create('purchase_finance_accounting_openings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchaser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('account_scope', 32)->default('purchaser'); // purchaser, vendor, global
            $table->date('accounting_start_date');
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->string('opening_balance_direction', 32)->default('settled'); // settled, purchaser_holds_company_cash, company_owes_purchaser, company_owes_vendor, vendor_owes_company
            $table->decimal('opening_credit_balance', 15, 2)->default(0.00); // Continuous credit carry-forward
            $table->decimal('opening_credit_outstanding', 15, 2)->default(0.00);
            $table->decimal('opening_advance_credit', 15, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['account_scope', 'purchaser_id', 'supplier_id', 'accounting_start_date'],
                'uq_purch_fin_open_scope_date'
            );
            $table->index(['account_scope', 'accounting_start_date'], 'idx_purch_open_scope_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_finance_accounting_openings');
    }
};
