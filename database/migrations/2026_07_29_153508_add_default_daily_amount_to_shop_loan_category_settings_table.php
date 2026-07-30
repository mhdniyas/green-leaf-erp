<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shop_loan_category_settings', 'default_daily_amount')) {
            return;
        }

        Schema::table('shop_loan_category_settings', function (Blueprint $table): void {
            $table->decimal('default_daily_amount', 12, 2)->default(0)->after('effect');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('shop_loan_category_settings', 'default_daily_amount')) {
            return;
        }

        Schema::table('shop_loan_category_settings', function (Blueprint $table): void {
            $table->dropColumn('default_daily_amount');
        });
    }
};
