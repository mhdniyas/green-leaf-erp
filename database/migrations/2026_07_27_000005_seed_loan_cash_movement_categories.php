<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('shop_cash_movement_categories')
            ->where('is_default', true)
            ->update(['is_default' => false, 'updated_at' => $now]);

        DB::table('shop_cash_movement_categories')->updateOrInsert(
            ['name' => 'Loan'],
            [
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('shop_cash_movement_categories')->updateOrInsert(
            ['name' => 'Advance Loan for Salary'],
            [
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $loanCategoryId = DB::table('shop_cash_movement_categories')->where('name', 'Loan')->value('id');
        $pettyCashCategoryId = DB::table('shop_cash_movement_categories')->where('name', 'Petty Cash')->value('id');

        if ($loanCategoryId !== null && $pettyCashCategoryId !== null) {
            DB::table('shop_credits')
                ->where('shop_cash_movement_category_id', $pettyCashCategoryId)
                ->update(['shop_cash_movement_category_id' => $loanCategoryId, 'updated_at' => $now]);

            DB::table('shop_cash_movement_categories')
                ->where('id', $pettyCashCategoryId)
                ->update(['is_default' => false, 'is_active' => false, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('shop_cash_movement_categories')
            ->whereIn('name', ['Loan', 'Advance Loan for Salary'])
            ->delete();

        DB::table('shop_cash_movement_categories')
            ->where('name', 'Petty Cash')
            ->update(['is_default' => true, 'is_active' => true, 'updated_at' => now()]);
    }
};
