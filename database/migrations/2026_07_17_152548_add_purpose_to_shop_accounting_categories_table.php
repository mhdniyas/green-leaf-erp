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
        Schema::table('shop_accounting_categories', function (Blueprint $table): void {
            $table->string('purpose', 40)->default('custom')->after('cash_effect');
            $table->index(['purpose', 'is_active'], 'shop_accounting_categories_purpose_active_index');
        });

        DB::table('shop_accounting_categories')
            ->where('name', 'Sales Income - Cash')
            ->update(['purpose' => 'sales_cash']);

        DB::table('shop_accounting_categories')
            ->whereIn('name', ['Sales Income - GPay', 'Sales Income - Paytm', 'Sales Income - PhonePe', 'Sales Income - Online'])
            ->update(['purpose' => 'sales_non_cash']);

        DB::table('shop_accounting_categories')
            ->where('name', 'Shop Cash Credit')
            ->update(['purpose' => 'shop_cash_credit']);

        DB::table('shop_accounting_categories')
            ->where('name', 'Cash Sent To Company')
            ->update(['purpose' => 'cash_sent_company']);

        DB::table('shop_accounting_categories')
            ->where('name', 'Staff Salary')
            ->update(['purpose' => 'staff_salary']);

        DB::table('shop_accounting_categories')
            ->where('name', 'Staff Salary Advance')
            ->update(['purpose' => 'staff_advance']);
    }

    public function down(): void
    {
        Schema::table('shop_accounting_categories', function (Blueprint $table): void {
            $table->dropIndex('shop_accounting_categories_purpose_active_index');
            $table->dropColumn('purpose');
        });
    }
};
