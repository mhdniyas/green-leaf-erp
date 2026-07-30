<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_invoice_payment_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_invoice_payment_requests', 'admin_verified_amount')) {
                $table->decimal('admin_verified_amount', 12, 2)->nullable()->after('requested_amount');
            }

            if (! Schema::hasColumn('shop_invoice_payment_requests', 'cheque_status')) {
                $table->string('cheque_status', 30)->nullable()->after('payment_date')->index();
            }

            if (! Schema::hasColumn('shop_invoice_payment_requests', 'cheque_bank_name')) {
                $table->string('cheque_bank_name', 120)->nullable()->after('cheque_status');
            }

            if (! Schema::hasColumn('shop_invoice_payment_requests', 'cheque_date')) {
                $table->date('cheque_date')->nullable()->after('cheque_bank_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_invoice_payment_requests', function (Blueprint $table): void {
            foreach (['cheque_date', 'cheque_bank_name', 'cheque_status', 'admin_verified_amount'] as $column) {
                if (Schema::hasColumn('shop_invoice_payment_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
