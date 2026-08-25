<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashbook_company_account_statement_entries', function (Blueprint $table): void {
            $table->uuid('public_uuid')->nullable()->unique()->after('id');
            $table->uuid('request_uuid')->nullable()->unique()->after('journal_entry_id');
            $table->string('source_type', 160)->nullable()->after('source');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('counterpart_type', 160)->nullable()->after('source_id');
            $table->unsignedBigInteger('counterpart_id')->nullable()->after('counterpart_type');
            $table->unique(['source_type', 'source_id'], 'cb_stmt_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cashbook_company_account_statement_entries', function (Blueprint $table): void {
            $table->dropUnique('cb_stmt_source_unique');
            $table->dropColumn(['public_uuid', 'request_uuid', 'source_type', 'source_id', 'counterpart_type', 'counterpart_id']);
        });
    }
};
