<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchaser_carts', function (Blueprint $table) {
            $table->timestamp('whatsapp_sent_at')->nullable()->after('submitted_at');
            $table->timestamp('goods_received_at')->nullable()->after('whatsapp_sent_at');
            $table->timestamp('bill_received_at')->nullable()->after('goods_received_at');
            $table->timestamp('payment_made_at')->nullable()->after('bill_received_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchaser_carts', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_sent_at',
                'goods_received_at',
                'bill_received_at',
                'payment_made_at',
            ]);
        });
    }
};
