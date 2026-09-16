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
        Schema::table('purchaser_carts', function (Blueprint $table): void {
            $table->foreignId('business_day_id')
                ->nullable()
                ->after('destination_shop_id')
                ->constrained('purchase_business_days')
                ->nullOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('business_day_id')
                ->nullable()
                ->after('destination_shop_id')
                ->constrained('purchase_business_days')
                ->nullOnDelete();
        });

        Schema::table('goods_received', function (Blueprint $table): void {
            $table->foreignId('business_day_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('purchase_business_days')
                ->nullOnDelete();
        });

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->foreignId('business_day_id')
                ->nullable()
                ->after('purchaser_cart_id')
                ->constrained('purchase_business_days')
                ->nullOnDelete();
        });

        Schema::table('advance_receive_matches', function (Blueprint $table): void {
            $table->foreignId('business_day_id')
                ->nullable()
                ->after('id')
                ->constrained('purchase_business_days')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advance_receive_matches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_day_id');
        });

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_day_id');
        });

        Schema::table('goods_received', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_day_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_day_id');
        });

        Schema::table('purchaser_carts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_day_id');
        });
    }
};
