<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->string('delivery_status', 30)->default('pending_delivery')->after('state');
            $table->string('payment_status', 30)->default('unpaid')->after('delivery_status');
            $table->decimal('balance_amount', 12, 2)->default(0.00)->after('cash_discrepancy');
            $table->text('finance_note')->nullable()->after('balance_amount');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_status',
                'payment_status',
                'balance_amount',
                'finance_note',
            ]);
        });
    }
};
