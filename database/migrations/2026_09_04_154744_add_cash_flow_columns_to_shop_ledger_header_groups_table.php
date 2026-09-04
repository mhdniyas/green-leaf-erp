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
        Schema::table('shop_ledger_header_groups', function (Blueprint $table) {
            $table->string('cash_flow_mode')->nullable()->after('type'); // shop_cash, petty, company, company_account, entry_decides, none
            $table->foreignId('company_account_id')->nullable()->after('cash_flow_mode')->constrained('cashbook_company_accounts')->nullOnDelete();
            $table->string('from_balance')->nullable()->after('company_account_id'); // for transfer/others: shop_cash, petty, company, company_account, vendor, none
            $table->string('to_balance')->nullable()->after('from_balance');
            $table->boolean('note_enabled')->default(false)->after('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_ledger_header_groups', function (Blueprint $table) {
            $table->dropForeign(['company_account_id']);
            $table->dropColumn(['cash_flow_mode', 'company_account_id', 'from_balance', 'to_balance', 'note_enabled']);
        });
    }
};
