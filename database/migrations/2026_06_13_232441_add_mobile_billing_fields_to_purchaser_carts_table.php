<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchaser_carts', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0)->after('bill_number');
            $table->string('payment_status', 30)->default('unpaid')->after('payment_method');
            $table->decimal('paid_amount', 12, 2)->default(0)->after('payment_status');
            $table->text('payment_details')->nullable()->after('payment_note');
        });
    }

    public function down(): void
    {
        Schema::table('purchaser_carts', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'payment_status', 'paid_amount', 'payment_details']);
        });
    }
};
