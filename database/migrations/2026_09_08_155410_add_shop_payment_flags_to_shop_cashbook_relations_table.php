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
        Schema::table('shop_cashbook_relations', function (Blueprint $table) {
            $table->boolean('is_payment_payable')->default(false)->after('is_net_balance')->index();
            $table->boolean('is_payment_paid')->default(false)->after('is_payment_payable')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_cashbook_relations', function (Blueprint $table) {
            $table->dropColumn(['is_payment_payable', 'is_payment_paid']);
        });
    }
};
