<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_invoice_items', function (Blueprint $table): void {
            $table->string('product_grade', 5)->default('A')->after('product_name');
            $table->unique(
                ['shop_invoice_id', 'product_id', 'product_grade'],
                'shop_invoice_items_invoice_product_grade_unique'
            );
            $table->dropUnique('shop_invoice_items_shop_invoice_id_product_id_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_invoice_items') && Schema::hasColumn('shop_invoice_items', 'product_grade')) {
            $nonACount = DB::table('shop_invoice_items')
                ->where(function ($query): void {
                    $query->whereNull('product_grade')
                        ->orWhere('product_grade', '!=', 'A');
                })
                ->count();

            if ($nonACount > 0) {
                throw new RuntimeException(
                    "Rollback aborted: Found {$nonACount} shop_invoice_items record(s) with non-Grade-A or NULL product_grade values. ".
                    'Reversing this migration would destroy financial grade audit trails.'
                );
            }

            $duplicateIdentities = DB::table('shop_invoice_items')
                ->select('shop_invoice_id', 'product_id')
                ->groupBy('shop_invoice_id', 'product_id')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($duplicateIdentities > 0) {
                throw new RuntimeException(
                    "Rollback aborted: Found {$duplicateIdentities} duplicate (shop_invoice_id, product_id) group(s) in shop_invoice_items. ".
                    'Re-creating the legacy unique index would violate database integrity.'
                );
            }
        }

        Schema::table('shop_invoice_items', function (Blueprint $table): void {
            $table->unique(
                ['shop_invoice_id', 'product_id'],
                'shop_invoice_items_shop_invoice_id_product_id_unique'
            );
            $table->dropUnique('shop_invoice_items_invoice_product_grade_unique');
            $table->dropColumn('product_grade');
        });
    }
};
