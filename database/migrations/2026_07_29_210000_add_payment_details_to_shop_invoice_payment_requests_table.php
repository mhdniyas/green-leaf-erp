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
            if (! Schema::hasColumn('shop_invoice_payment_requests', 'payment_method')) {
                $table->string('payment_method', 30)->nullable()->after('request_type');
            }

            if (! Schema::hasColumn('shop_invoice_payment_requests', 'payment_reference')) {
                $table->string('payment_reference', 120)->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('shop_invoice_payment_requests', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('payment_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_invoice_payment_requests', function (Blueprint $table): void {
            foreach (['payment_date', 'payment_reference', 'payment_method'] as $column) {
                if (Schema::hasColumn('shop_invoice_payment_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
