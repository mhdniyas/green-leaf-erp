<?php

declare(strict_types=1);

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
        if (Schema::hasTable('shop_ledger_entry_settings')) {
            Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
                if (! Schema::hasColumn('shop_ledger_entry_settings', 'mirror_to_cashbook')) {
                    $table->boolean('mirror_to_cashbook')->default(true)->after('is_vendor_purchase');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('shop_ledger_entry_settings')) {
            Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
                if (Schema::hasColumn('shop_ledger_entry_settings', 'mirror_to_cashbook')) {
                    $table->dropColumn('mirror_to_cashbook');
                }
            });
        }
    }
};
