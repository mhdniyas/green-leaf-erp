<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashbook_company_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_type'); // 'bank', 'cash', 'upi', 'wallet'
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->decimal('current_balance', 15, 2)->default(0.00);
            $table->boolean('is_default')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::table('shop_ledger_transactions', function (Blueprint $table) {
            $table->foreignId('company_account_id')
                ->nullable()
                ->constrained('cashbook_company_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_ledger_transactions', function (Blueprint $table) {
            $table->dropForeign(['company_account_id']);
            $table->dropColumn('company_account_id');
        });

        Schema::dropIfExists('cashbook_company_accounts');
    }
};
