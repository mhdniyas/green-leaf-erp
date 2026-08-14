<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shop_ledger_entry_settings', 'include_in_payable')) {
            Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
                $table->boolean('include_in_payable')->default(false)->after('include_in_pl');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
            $table->dropColumn('include_in_payable');
        });
    }
};
