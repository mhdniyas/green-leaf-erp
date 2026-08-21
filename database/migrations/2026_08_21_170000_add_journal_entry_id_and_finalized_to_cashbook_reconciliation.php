<?php

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
        Schema::table('cashbook_company_account_statement_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('cashbook_company_account_statement_entries', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')
                    ->nullable()
                    ->after('company_account_id')
                    ->constrained('journal_entries', indexName: 'ccase_je_id_foreign')
                    ->onDelete('set null');
            }
            if (! Schema::hasColumn('cashbook_company_account_statement_entries', 'is_finalized')) {
                $table->boolean('is_finalized')->default(false)->after('status');
            }
            if (! Schema::hasColumn('cashbook_company_account_statement_entries', 'finalized_at')) {
                $table->timestamp('finalized_at')->nullable()->after('is_finalized');
            }
        });

        Schema::table('cashbook_company_payment_reconciliations', function (Blueprint $table): void {
            if (! Schema::hasColumn('cashbook_company_payment_reconciliations', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')
                    ->nullable()
                    ->after('statement_entry_id')
                    ->constrained('journal_entries', indexName: 'ccpr_je_id_foreign')
                    ->onDelete('set null');
            }
            if (! Schema::hasColumn('cashbook_company_payment_reconciliations', 'is_finalized')) {
                $table->boolean('is_finalized')->default(false)->after('status');
            }
            if (! Schema::hasColumn('cashbook_company_payment_reconciliations', 'finalized_at')) {
                $table->timestamp('finalized_at')->nullable()->after('is_finalized');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashbook_company_payment_reconciliations', function (Blueprint $table): void {
            if (Schema::hasColumn('cashbook_company_payment_reconciliations', 'journal_entry_id')) {
                $table->dropForeign('ccpr_je_id_foreign');
                $table->dropColumn('journal_entry_id');
            }
            if (Schema::hasColumn('cashbook_company_payment_reconciliations', 'is_finalized')) {
                $table->dropColumn('is_finalized');
            }
            if (Schema::hasColumn('cashbook_company_payment_reconciliations', 'finalized_at')) {
                $table->dropColumn('finalized_at');
            }
        });

        Schema::table('cashbook_company_account_statement_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('cashbook_company_account_statement_entries', 'journal_entry_id')) {
                $table->dropForeign('ccase_je_id_foreign');
                $table->dropColumn('journal_entry_id');
            }
            if (Schema::hasColumn('cashbook_company_account_statement_entries', 'is_finalized')) {
                $table->dropColumn('is_finalized');
            }
            if (Schema::hasColumn('cashbook_company_account_statement_entries', 'finalized_at')) {
                $table->dropColumn('finalized_at');
            }
        });
    }
};
