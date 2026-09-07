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
        Schema::table('shop_cashbook_relations', function (Blueprint $table) {
            if (! Schema::hasColumn('shop_cashbook_relations', 'is_company_payable')) {
                $table->boolean('is_company_payable')->default(false)->after('enabled');
                $table->index(['shop_id', 'is_company_payable'], 'shop_company_payable_idx');
            }
        });

        // Safe backfill for existing shops to ensure exactly one Company Payable settlement per shop
        $shopIds = DB::table('shop_cashbook_relations')->distinct()->pluck('shop_id');
        foreach ($shopIds as $shopId) {
            $hasPayable = DB::table('shop_cashbook_relations')
                ->where('shop_id', $shopId)
                ->where('is_company_payable', true)
                ->exists();

            if (! $hasPayable) {
                // Find default_company_payable or match by name
                $targetId = DB::table('shop_cashbook_relations')
                    ->where('shop_id', $shopId)
                    ->where('relation_type', 'default_company_payable')
                    ->value('id')
                    ?? DB::table('shop_cashbook_relations')
                        ->where('shop_id', $shopId)
                        ->where('name', 'like', '%Company Payable%')
                        ->orderBy('id')
                        ->value('id')
                    ?? DB::table('shop_cashbook_relations')
                        ->where('shop_id', $shopId)
                        ->where('enabled', true)
                        ->orderBy('display_order')
                        ->value('id');

                if ($targetId) {
                    DB::table('shop_cashbook_relations')
                        ->where('id', $targetId)
                        ->update(['is_company_payable' => true]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('shop_cashbook_relations', function (Blueprint $table) {
            if (Schema::hasColumn('shop_cashbook_relations', 'is_company_payable')) {
                $table->dropIndex('shop_company_payable_idx');
                $table->dropColumn('is_company_payable');
            }
        });
    }
};
