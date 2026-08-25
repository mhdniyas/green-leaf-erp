<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('cashbook_company_account_statement_entries', 'public_uuid')) {
            return;
        }

        DB::table('cashbook_company_account_statement_entries')
            ->where(function ($query): void {
                $query->whereNull('public_uuid')
                    ->orWhere('public_uuid', '');
            })
            ->eachById(function (object $statementEntry): void {
                DB::table('cashbook_company_account_statement_entries')
                    ->where('id', $statementEntry->id)
                    ->update(['public_uuid' => (string) Str::uuid()]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Forward-only data repair: public UUIDs are stable route identities.
    }
};
