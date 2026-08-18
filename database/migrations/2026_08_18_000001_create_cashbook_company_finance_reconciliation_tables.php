<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cashbook_company_payment_reconciliations')) {
            Schema::dropIfExists('cashbook_company_payment_reconciliations');
            Schema::dropIfExists('cashbook_company_account_statement_entries');
        }

        if (! Schema::hasTable('cashbook_company_account_statement_entries')) {
            Schema::create('cashbook_company_account_statement_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_account_id')
                    ->constrained('cashbook_company_accounts', indexName: 'cb_stmt_company_account_fk')
                    ->cascadeOnDelete();
                $table->date('transaction_date');
                $table->date('value_date')->nullable();
                $table->string('direction', 10)->default('in');
                $table->decimal('amount', 15, 2);
                $table->string('reference', 160)->nullable();
                $table->text('narration')->nullable();
                $table->string('source', 30)->default('manual');
                $table->string('status', 30)->default('unmatched')->index('cb_stmt_status_idx');
                $table->decimal('matched_amount', 15, 2)->default(0);
                $table->string('statement_batch', 120)->nullable()->index('cb_stmt_batch_idx');
                $table->text('notes')->nullable();
                $table->foreignId('imported_by')
                    ->nullable()
                    ->constrained('users', indexName: 'cb_stmt_imported_by_fk')
                    ->nullOnDelete();
                $table->foreignId('reconciled_by')
                    ->nullable()
                    ->constrained('users', indexName: 'cb_stmt_reconciled_by_fk')
                    ->nullOnDelete();
                $table->timestamp('reconciled_at')->nullable();
                $table->timestamps();

                $table->index(['company_account_id', 'transaction_date'], 'cb_stmt_acc_tx_date_idx');
                $table->index(['company_account_id', 'status'], 'cb_stmt_acc_status_idx');
            });
        }

        if (! Schema::hasTable('cashbook_company_payment_reconciliations')) {
            Schema::create('cashbook_company_payment_reconciliations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payment_request_id')
                    ->constrained('shop_invoice_payment_requests', indexName: 'cb_recon_payment_req_fk')
                    ->cascadeOnDelete();
                $table->foreignId('shop_id')
                    ->constrained('shops', indexName: 'cb_recon_shop_fk')
                    ->cascadeOnDelete();
                $table->foreignId('company_account_id')
                    ->constrained('cashbook_company_accounts', indexName: 'cb_recon_comp_acc_fk')
                    ->cascadeOnDelete();
                $table->foreignId('statement_entry_id')
                    ->nullable()
                    ->constrained('cashbook_company_account_statement_entries', indexName: 'cb_recon_stmt_entry_fk')
                    ->nullOnDelete();
                $table->decimal('statement_amount', 15, 2)->default(0);
                $table->decimal('cleared_amount', 15, 2);
                $table->decimal('difference_amount', 15, 2)->default(0);
                $table->string('difference_action', 40)->default('none');
                $table->foreignId('difference_entry_type_id')
                    ->nullable()
                    ->constrained('ledger_entry_types', indexName: 'cb_recon_diff_type_fk')
                    ->nullOnDelete();
                $table->foreignId('difference_transaction_id')
                    ->nullable()
                    ->constrained('shop_ledger_transactions', indexName: 'cb_recon_diff_tx_fk')
                    ->nullOnDelete();
                $table->string('status', 30)->default('approved')->index('cb_recon_status_idx');
                $table->text('admin_note')->nullable();
                $table->foreignId('reconciled_by')
                    ->nullable()
                    ->constrained('users', indexName: 'cb_recon_reconciled_by_fk')
                    ->nullOnDelete();
                $table->timestamp('reconciled_at')->nullable();
                $table->timestamps();

                $table->index(['shop_id', 'status'], 'cb_recon_shop_status_idx');
                $table->index(['payment_request_id', 'status'], 'cb_recon_pay_req_status_idx');
            });
        }

        Schema::table('shop_invoice_payment_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_invoice_payment_requests', 'reconciliation_status')) {
                $table->string('reconciliation_status', 30)->default('pending')->after('status')->index('sipr_reconciliation_status_idx');
            }

            if (! Schema::hasColumn('shop_invoice_payment_requests', 'reconciled_amount')) {
                $table->decimal('reconciled_amount', 15, 2)->default(0)->after('credit_amount');
            }

            if (! Schema::hasColumn('shop_invoice_payment_requests', 'floating_amount')) {
                $table->decimal('floating_amount', 15, 2)->default(0)->after('reconciled_amount');
            }

            if (! Schema::hasColumn('shop_invoice_payment_requests', 'shop_advance_amount')) {
                $table->decimal('shop_advance_amount', 15, 2)->default(0)->after('floating_amount');
            }

            if (! Schema::hasColumn('shop_invoice_payment_requests', 'last_reconciled_at')) {
                $table->timestamp('last_reconciled_at')->nullable()->after('reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_invoice_payment_requests', function (Blueprint $table): void {
            foreach (['last_reconciled_at', 'shop_advance_amount', 'floating_amount', 'reconciled_amount', 'reconciliation_status'] as $column) {
                if (Schema::hasColumn('shop_invoice_payment_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('cashbook_company_payment_reconciliations');
        Schema::dropIfExists('cashbook_company_account_statement_entries');
    }
};
