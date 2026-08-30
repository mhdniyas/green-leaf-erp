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
        Schema::create('company_expense_ledger_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_statement_entry_id')
                ->nullable()
                ->constrained('cashbook_company_account_statement_entries', indexName: 'cela_stmt_entry_fk')
                ->nullOnDelete();
            $table->foreignId('payment_request_id')
                ->nullable()
                ->constrained('shop_invoice_payment_requests', indexName: 'cela_payment_req_fk')
                ->nullOnDelete();
            $table->foreignId('shop_id')
                ->constrained('shops', indexName: 'cela_shop_fk')
                ->cascadeOnDelete();
            $table->foreignId('shop_ledger_transaction_id')
                ->constrained('shop_ledger_transactions', indexName: 'cela_transaction_fk')
                ->cascadeOnDelete();
            $table->decimal('allocated_amount', 15, 2);
            $table->date('allocation_date');
            $table->string('status', 30)->default('active')->index('cela_status_idx');
            $table->text('notes')->nullable();
            $table->foreignId('allocated_by')
                ->nullable()
                ->constrained('users', indexName: 'cela_allocated_by_fk')
                ->nullOnDelete();
            $table->foreignId('reversed_by')
                ->nullable()
                ->constrained('users', indexName: 'cela_reversed_by_fk')
                ->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'shop_ledger_transaction_id'], 'cela_shop_tx_idx');
            $table->index(['company_statement_entry_id', 'status'], 'cela_stmt_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_expense_ledger_allocations');
    }
};
