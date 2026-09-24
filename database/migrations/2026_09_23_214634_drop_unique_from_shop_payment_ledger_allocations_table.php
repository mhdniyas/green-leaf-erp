<?php

declare(strict_types=1);

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
        Schema::table('shop_payment_ledger_allocations', function (Blueprint $table): void {
            $table->index(['payment_request_id', 'shop_ledger_transaction_id'], 'shop_payment_ledger_allocation_payment_tx_index');
        });

        Schema::table('shop_payment_ledger_allocations', function (Blueprint $table): void {
            $table->dropUnique('shop_payment_ledger_allocation_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_payment_ledger_allocations', function (Blueprint $table): void {
            $table->unique(['payment_request_id', 'shop_ledger_transaction_id'], 'shop_payment_ledger_allocation_unique');
        });

        Schema::table('shop_payment_ledger_allocations', function (Blueprint $table): void {
            $table->dropIndex('shop_payment_ledger_allocation_payment_tx_index');
        });
    }
};
