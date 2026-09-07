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
            if (! Schema::hasColumn('shop_cashbook_relations', 'is_net_balance')) {
                $table->boolean('is_net_balance')->default(false)->after('is_company_payable');
                $table->index(['shop_id', 'is_net_balance'], 'shop_net_balance_idx');
            }
        });

        // Safe backfill for existing shops: mark default_balance / Balance settlement as is_net_balance
        $shopIds = DB::table('shop_cashbook_relations')->distinct()->pluck('shop_id');
        foreach ($shopIds as $shopId) {
            $hasNetBalance = DB::table('shop_cashbook_relations')
                ->where('shop_id', $shopId)
                ->where('is_net_balance', true)
                ->exists();

            if (! $hasNetBalance) {
                $targetId = DB::table('shop_cashbook_relations')
                    ->where('shop_id', $shopId)
                    ->where('relation_type', 'default_balance')
                    ->value('id')
                    ?? DB::table('shop_cashbook_relations')
                        ->where('shop_id', $shopId)
                        ->where('name', 'like', '%Balance%')
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
                        ->update(['is_net_balance' => true]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_cashbook_relations', function (Blueprint $table) {
            if (Schema::hasColumn('shop_cashbook_relations', 'is_net_balance')) {
                $table->dropIndex('shop_net_balance_idx');
                $table->dropColumn('is_net_balance');
            }
        });
    }
};
