<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->decimal('total_excess_value', 12, 2)
                ->default(0.00)
                ->after('total_shortage_value');
        });

        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->decimal('shop_reported_excess_qty', 10, 2)
                ->default(0.00)
                ->after('shop_reported_missing_qty');
            $table->decimal('excess_qty', 10, 2)
                ->default(0.00)
                ->after('shortage_qty');
            $table->decimal('excess_value', 10, 2)
                ->default(0.00)
                ->after('shortage_value');
        });

        Schema::table('shop_invoices', function (Blueprint $table): void {
            $table->decimal('excess_total', 12, 2)
                ->default(0.00)
                ->after('shortage_total');
        });

        Schema::table('shop_invoice_items', function (Blueprint $table): void {
            $table->decimal('excess_qty', 10, 2)
                ->default(0.00)
                ->after('shortage_qty');
            $table->decimal('excess_amount', 12, 2)
                ->default(0.00)
                ->after('shortage_amount');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('shop_order_item_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('shop_order_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shop_order_item_id');
        });

        Schema::table('shop_invoice_items', function (Blueprint $table): void {
            $table->dropColumn(['excess_qty', 'excess_amount']);
        });

        Schema::table('shop_invoices', function (Blueprint $table): void {
            $table->dropColumn('excess_total');
        });

        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropColumn(['shop_reported_excess_qty', 'excess_qty', 'excess_value']);
        });

        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->dropColumn('total_excess_value');
        });
    }
};
