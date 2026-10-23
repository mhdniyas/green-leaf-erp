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
        Schema::table('shop_order_change_request_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_order_change_request_items', 'shop_order_item_id')) {
                $table->foreignId('shop_order_item_id')
                    ->nullable()
                    ->after('shop_order_change_request_id')
                    ->constrained('shop_order_items')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('shop_order_change_request_items', 'product_grade')) {
                $table->string('product_grade', 5)->default('A')->after('product_id');
            }

            // Dedicated index on foreign key so MySQL allows replacing the composite unique key
            $table->index('shop_order_change_request_id', 'socr_items_request_id_idx');
        });

        Schema::table('shop_order_change_request_items', function (Blueprint $table): void {
            $table->dropUnique('shop_order_change_request_items_unique');
            $table->unique(
                ['shop_order_change_request_id', 'product_id', 'product_grade'],
                'shop_order_change_request_items_unique'
            );
            $table->dropIndex('socr_items_request_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('shop_order_change_request_items') && Schema::hasColumn('shop_order_change_request_items', 'product_grade')) {
            $nonACount = DB::table('shop_order_change_request_items')
                ->where(function ($query): void {
                    $query->whereNull('product_grade')
                        ->orWhere('product_grade', '!=', 'A');
                })
                ->count();

            if ($nonACount > 0) {
                throw new RuntimeException(
                    "Rollback aborted: Found {$nonACount} shop_order_change_request_items record(s) with non-Grade-A or NULL product_grade values. "
                    .'Manual cleanup is required to prevent data loss.'
                );
            }
        }

        Schema::table('shop_order_change_request_items', function (Blueprint $table): void {
            $table->index('shop_order_change_request_id', 'socr_items_request_id_idx');
        });

        Schema::table('shop_order_change_request_items', function (Blueprint $table): void {
            $table->dropUnique('shop_order_change_request_items_unique');
            $table->unique(
                ['shop_order_change_request_id', 'product_id'],
                'shop_order_change_request_items_unique'
            );
            $table->dropIndex('socr_items_request_id_idx');

            if (Schema::hasColumn('shop_order_change_request_items', 'product_grade')) {
                $table->dropColumn('product_grade');
            }

            if (Schema::hasColumn('shop_order_change_request_items', 'shop_order_item_id')) {
                $table->dropConstrainedForeignId('shop_order_item_id');
            }
        });
    }
};
