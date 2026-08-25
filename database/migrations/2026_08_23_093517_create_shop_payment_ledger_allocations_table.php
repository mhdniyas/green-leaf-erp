<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_payment_ledger_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_request_id')
                ->constrained('shop_invoice_payment_requests', indexName: 'spl_alloc_payment_fk')
                ->cascadeOnDelete();
            $table->foreignId('shop_id')
                ->constrained('shops', indexName: 'spl_alloc_shop_fk')
                ->cascadeOnDelete();
            $table->foreignId('shop_ledger_transaction_id')
                ->constrained('shop_ledger_transactions', indexName: 'spl_alloc_transaction_fk')
                ->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->foreignId('reconciled_by')
                ->nullable()
                ->constrained('users', indexName: 'spl_alloc_reconciled_by_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['payment_request_id', 'shop_ledger_transaction_id'], 'shop_payment_ledger_allocation_unique');
            $table->index(['shop_id', 'shop_ledger_transaction_id'], 'shop_payment_ledger_allocation_shop_transaction_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_payment_ledger_allocations');
    }
};
