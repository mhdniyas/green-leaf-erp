<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_sales', 'customer_type')) {
                $table->string('customer_type', 30)->default('cash_sales')->after('customer_id');
            }
            if (! Schema::hasColumn('warehouse_sales', 'shop_id')) {
                $table->foreignId('shop_id')->nullable()->after('customer_type')->constrained('shops')->nullOnDelete();
            }

            $table->index(['customer_type', 'business_date'], 'ws_customer_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_sales', function (Blueprint $table): void {
            $table->dropIndex('ws_customer_type_date_idx');
            if (Schema::hasColumn('warehouse_sales', 'shop_id')) {
                $table->dropConstrainedForeignId('shop_id');
            }
            if (Schema::hasColumn('warehouse_sales', 'customer_type')) {
                $table->dropColumn('customer_type');
            }
        });
    }
};
