<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashbook_company_account_statement_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('cashbook_company_account_statement_entries', 'import_fingerprint')) {
                $table->string('import_fingerprint', 80)->nullable()->after('statement_batch')->index('cb_stmt_import_fingerprint_idx');
            }

            if (! Schema::hasColumn('cashbook_company_account_statement_entries', 'imported_month')) {
                $table->string('imported_month', 7)->nullable()->after('import_fingerprint')->index('cb_stmt_imported_month_idx');
            }

            if (! Schema::hasColumn('cashbook_company_account_statement_entries', 'import_file_name')) {
                $table->string('import_file_name')->nullable()->after('imported_month');
            }

            if (! Schema::hasColumn('cashbook_company_account_statement_entries', 'duplicate_status')) {
                $table->string('duplicate_status', 30)->default('clear')->after('import_file_name')->index('cb_stmt_duplicate_status_idx');
            }

            if (! Schema::hasColumn('cashbook_company_account_statement_entries', 'duplicate_of_statement_entry_id')) {
                $table->foreignId('duplicate_of_statement_entry_id')
                    ->nullable()
                    ->after('duplicate_status')
                    ->constrained('cashbook_company_account_statement_entries', indexName: 'cashbook_statement_duplicate_of_fk')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cashbook_company_account_statement_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('cashbook_company_account_statement_entries', 'duplicate_of_statement_entry_id')) {
                $table->dropForeign('cashbook_statement_duplicate_of_fk');
                $table->dropColumn('duplicate_of_statement_entry_id');
            }

            $columns = [
                'duplicate_status',
                'import_file_name',
                'imported_month',
                'import_fingerprint',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('cashbook_company_account_statement_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
