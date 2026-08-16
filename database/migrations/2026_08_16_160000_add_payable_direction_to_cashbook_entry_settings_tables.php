<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_ledger_entry_settings') && ! Schema::hasColumn('shop_ledger_entry_settings', 'payable_direction')) {
            Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
                $table->string('payable_direction')->nullable()->after('include_in_payable');
            });
        }

        if (Schema::hasTable('cashbook_preset_entry_settings')) {
            Schema::table('cashbook_preset_entry_settings', function (Blueprint $table): void {
                if (! Schema::hasColumn('cashbook_preset_entry_settings', 'include_in_payable')) {
                    $table->boolean('include_in_payable')->default(false)->after('include_in_pl');
                }
                if (! Schema::hasColumn('cashbook_preset_entry_settings', 'payable_direction')) {
                    $table->string('payable_direction')->nullable()->after('include_in_payable');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_ledger_entry_settings') && Schema::hasColumn('shop_ledger_entry_settings', 'payable_direction')) {
            Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
                $table->dropColumn('payable_direction');
            });
        }

        if (Schema::hasTable('cashbook_preset_entry_settings')) {
            Schema::table('cashbook_preset_entry_settings', function (Blueprint $table): void {
                if (Schema::hasColumn('cashbook_preset_entry_settings', 'payable_direction')) {
                    $table->dropColumn('payable_direction');
                }
                if (Schema::hasColumn('cashbook_preset_entry_settings', 'include_in_payable')) {
                    $table->dropColumn('include_in_payable');
                }
            });
        }
    }
};
