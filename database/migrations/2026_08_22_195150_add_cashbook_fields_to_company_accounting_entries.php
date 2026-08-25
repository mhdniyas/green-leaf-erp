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
        Schema::table('company_accounting_entries', function (Blueprint $table): void {
            $table->uuid('public_uuid')->nullable()->unique()->after('id');
            $table->foreignId('company_account_id')->nullable()->after('reversal_journal_entry_id')
                ->constrained('cashbook_company_accounts')->nullOnDelete();
            $table->index(['company_account_id', 'business_date'], 'company_acct_entries_cashbook_account_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_accounting_entries', function (Blueprint $table): void {
            $table->dropIndex('company_acct_entries_cashbook_account_date_idx');
            $table->dropConstrainedForeignId('company_account_id');
            $table->dropUnique(['public_uuid']);
            $table->dropColumn('public_uuid');
        });
    }
};
