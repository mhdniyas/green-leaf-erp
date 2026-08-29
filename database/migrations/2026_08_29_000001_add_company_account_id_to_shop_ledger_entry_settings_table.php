<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_ledger_entry_settings', function (Blueprint $table) {
            $table->foreignId('company_account_id')
                ->nullable()
                ->after('entry_type_id')
                ->constrained('cashbook_company_accounts')
                ->nullOnDelete();
        });

        if (Schema::hasTable('cashbook_preset_entry_settings')) {
            Schema::table('cashbook_preset_entry_settings', function (Blueprint $table) {
                $table->foreignId('company_account_id')
                    ->nullable()
                    ->after('entry_type_id')
                    ->constrained('cashbook_company_accounts')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('shop_ledger_entry_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_account_id');
        });

        if (Schema::hasTable('cashbook_preset_entry_settings')) {
            Schema::table('cashbook_preset_entry_settings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_account_id');
            });
        }
    }
};
