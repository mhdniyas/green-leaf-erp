<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shop_daily_product_prices', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_daily_product_prices', 'product_grade')) {
                $table->string('product_grade', 5)->default('A')->after('product_id');
            }
            $table->dropUnique('shop_daily_product_prices_unique');
            $table->unique(
                ['business_date', 'shop_id', 'product_id', 'product_grade'],
                'shop_daily_prices_date_shop_prod_grade_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('shop_daily_product_prices') && Schema::hasColumn('shop_daily_product_prices', 'product_grade')) {
            $nonACount = DB::table('shop_daily_product_prices')
                ->where(function ($query): void {
                    $query->whereNull('product_grade')
                        ->orWhere('product_grade', '!=', 'A');
                })
                ->count();

            if ($nonACount > 0) {
                throw new RuntimeException(
                    "Rollback aborted: Found {$nonACount} shop_daily_product_prices record(s) with non-Grade-A or NULL product_grade values. ".
                    'Reversing this migration would destroy financial grade audit trails.'
                );
            }

            $duplicateIdentities = DB::table('shop_daily_product_prices')
                ->select('business_date', 'shop_id', 'product_id')
                ->groupBy('business_date', 'shop_id', 'product_id')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($duplicateIdentities > 0) {
                throw new RuntimeException(
                    "Rollback aborted: Found {$duplicateIdentities} duplicate (business_date, shop_id, product_id) group(s) in shop_daily_product_prices. ".
                    'Re-creating the legacy unique index would violate database integrity.'
                );
            }
        }

        Schema::table('shop_daily_product_prices', function (Blueprint $table): void {
            $table->unique(
                ['business_date', 'shop_id', 'product_id'],
                'shop_daily_product_prices_unique'
            );
            $table->dropUnique('shop_daily_prices_date_shop_prod_grade_unique');
            $table->dropColumn('product_grade');
        });
    }
};
