<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shop_invoice_payment_requests', 'applied_amount')) {
            Schema::table('shop_invoice_payment_requests', function (Blueprint $table): void {
                $table->decimal('applied_amount', 12, 2)->default(0)->after('approved_amount');
            });
        }

        if (! Schema::hasColumn('shop_invoice_payment_requests', 'credit_amount')) {
            Schema::table('shop_invoice_payment_requests', function (Blueprint $table): void {
                $table->decimal('credit_amount', 12, 2)->default(0)->after('applied_amount');
            });
        }

        Schema::create('shop_invoice_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_request_id')
                ->constrained('shop_invoice_payment_requests', indexName: 'sip_alloc_request_fk')
                ->cascadeOnDelete();
            $table->foreignId('shop_invoice_id')
                ->constrained('shop_invoices')
                ->cascadeOnDelete();
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shop_id', 'shop_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_invoice_payment_allocations');

        foreach (['applied_amount', 'credit_amount'] as $column) {
            if (Schema::hasColumn('shop_invoice_payment_requests', $column)) {
                Schema::table('shop_invoice_payment_requests', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
