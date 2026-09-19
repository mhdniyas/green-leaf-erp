<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_ledger_entry_settings') && ! Schema::hasColumn('shop_ledger_entry_settings', 'sales_report_bucket')) {
            Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
                $table->string('sales_report_bucket')->nullable()->after('payable_direction');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shop_ledger_entry_settings', function (Blueprint $table) {
            //
        });
    }
};
