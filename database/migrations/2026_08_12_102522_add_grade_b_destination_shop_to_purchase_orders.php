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
        Schema::table('purchaser_carts', function (Blueprint $table): void {
            $table->foreignId('destination_shop_id')->nullable()->after('supplier_id')->constrained('shops')->nullOnDelete();
        });
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('destination_shop_id')->nullable()->after('supplier_id')->constrained('shops')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('destination_shop_id'));
        Schema::table('purchaser_carts', fn (Blueprint $table) => $table->dropConstrainedForeignId('destination_shop_id'));
    }
};
