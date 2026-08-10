<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_invoices', function (Blueprint $table): void {
            $table->index(['business_date', 'status', 'shop_id'], 'shop_invoices_report_period_status_shop');
        });

        Schema::table('shop_invoice_items', function (Blueprint $table): void {
            $table->index(['product_id', 'price_unit'], 'shop_invoice_items_report_product_unit');
        });
    }

    public function down(): void
    {
        Schema::table('shop_invoice_items', function (Blueprint $table): void {
            $table->dropIndex('shop_invoice_items_report_product_unit');
        });

        Schema::table('shop_invoices', function (Blueprint $table): void {
            $table->dropIndex('shop_invoices_report_period_status_shop');
        });
    }
};
