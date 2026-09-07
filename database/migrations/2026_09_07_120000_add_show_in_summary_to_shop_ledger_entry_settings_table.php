<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_ledger_entry_settings') && ! Schema::hasColumn('shop_ledger_entry_settings', 'show_in_summary')) {
            Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
                $table->boolean('show_in_summary')->default(true)->after('enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_ledger_entry_settings') && Schema::hasColumn('shop_ledger_entry_settings', 'show_in_summary')) {
            Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
                $table->dropColumn('show_in_summary');
            });
        }
    }
};
