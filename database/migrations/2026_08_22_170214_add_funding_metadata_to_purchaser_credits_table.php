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
        Schema::table('purchaser_credits', function (Blueprint $table) {
            $table->string('payment_source', 30)->nullable()->after('description');
            $table->foreignId('company_account_id')->nullable()->after('payment_source')->constrained('cashbook_company_accounts')->nullOnDelete();
            $table->string('reference', 160)->nullable()->after('company_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchaser_credits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_account_id');
            $table->dropColumn(['payment_source', 'reference']);
        });
    }
};
