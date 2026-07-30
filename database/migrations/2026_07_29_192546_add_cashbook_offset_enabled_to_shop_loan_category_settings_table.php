<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shop_loan_category_settings', 'cashbook_offset_enabled')) {
            return;
        }

        Schema::table('shop_loan_category_settings', function (Blueprint $table): void {
            $table->boolean('cashbook_offset_enabled')->default(false)->after('default_daily_amount');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('shop_loan_category_settings', 'cashbook_offset_enabled')) {
            return;
        }

        Schema::table('shop_loan_category_settings', function (Blueprint $table): void {
            $table->dropColumn('cashbook_offset_enabled');
        });
    }
};
