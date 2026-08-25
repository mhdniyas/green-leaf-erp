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
        Schema::table('payroll_payments', function (Blueprint $table): void {
            $table->foreignId('company_account_id')
                ->nullable()
                ->after('journal_entry_id')
                ->constrained('cashbook_company_accounts')
                ->nullOnDelete();
            $table->uuid('request_uuid')->nullable()->unique()->after('company_account_id');
            $table->string('reference', 160)->nullable()->after('request_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table): void {
            $table->dropForeign(['company_account_id']);
            $table->dropUnique(['request_uuid']);
            $table->dropColumn(['company_account_id', 'request_uuid', 'reference']);
        });
    }
};
