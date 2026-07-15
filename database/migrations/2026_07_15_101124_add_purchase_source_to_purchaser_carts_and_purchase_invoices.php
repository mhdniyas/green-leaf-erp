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
        Schema::table('purchaser_carts', function (Blueprint $table) {
            $table->string('purchase_source', 50)->default('shop_order')->after('status')->index();
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->string('purchase_source', 50)->default('shop_order')->after('purchaser_cart_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropIndex(['purchase_source']);
            $table->dropColumn('purchase_source');
        });

        Schema::table('purchaser_carts', function (Blueprint $table) {
            $table->dropIndex(['purchase_source']);
            $table->dropColumn('purchase_source');
        });
    }
};
