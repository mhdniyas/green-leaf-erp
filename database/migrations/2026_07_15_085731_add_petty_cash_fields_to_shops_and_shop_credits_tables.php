<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->decimal('default_petty_cash_amount', 12, 2)->default(0)->after('reserve_amount');
        });

        Schema::table('shop_credits', function (Blueprint $table): void {
            $table->boolean('is_petty_cash')->default(false)->after('type');
            $table->index(['shop_id', 'is_petty_cash', 'business_date'], 'shop_credits_petty_cash_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('shop_credits', function (Blueprint $table): void {
            $table->dropIndex('shop_credits_petty_cash_date_index');
            $table->dropColumn('is_petty_cash');
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->dropColumn('default_petty_cash_amount');
        });
    }
};
