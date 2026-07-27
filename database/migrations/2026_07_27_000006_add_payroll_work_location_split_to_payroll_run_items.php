<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->decimal('green_leaf_payable_units', 8, 2)->default(0)->after('payable_units');
            $table->decimal('client_shop_payable_units', 8, 2)->default(0)->after('green_leaf_payable_units');
            $table->decimal('green_leaf_computed_amount', 12, 2)->default(0)->after('computed_amount');
            $table->decimal('client_shop_computed_amount', 12, 2)->default(0)->after('green_leaf_computed_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->dropColumn([
                'green_leaf_payable_units',
                'client_shop_payable_units',
                'green_leaf_computed_amount',
                'client_shop_computed_amount',
            ]);
        });
    }
};
