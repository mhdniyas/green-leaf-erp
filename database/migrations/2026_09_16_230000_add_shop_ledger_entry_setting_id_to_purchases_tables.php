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
        if (Schema::hasTable('purchaser_carts') && ! Schema::hasColumn('purchaser_carts', 'shop_ledger_entry_setting_id')) {
            Schema::table('purchaser_carts', function (Blueprint $table): void {
                $table->foreignId('shop_ledger_entry_setting_id')
                    ->nullable()
                    ->after('destination_shop_id')
                    ->constrained('shop_ledger_entry_settings')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('purchase_invoices') && ! Schema::hasColumn('purchase_invoices', 'shop_ledger_entry_setting_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table): void {
                $table->foreignId('shop_ledger_entry_setting_id')
                    ->nullable()
                    ->after('shop_id')
                    ->constrained('shop_ledger_entry_settings')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('shop_vendor_payables') && ! Schema::hasColumn('shop_vendor_payables', 'shop_ledger_entry_setting_id')) {
            Schema::table('shop_vendor_payables', function (Blueprint $table): void {
                $table->foreignId('shop_ledger_entry_setting_id')
                    ->nullable()
                    ->after('supplier_id')
                    ->constrained('shop_ledger_entry_settings')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('shop_vendor_payables') && Schema::hasColumn('shop_vendor_payables', 'shop_ledger_entry_setting_id')) {
            Schema::table('shop_vendor_payables', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('shop_ledger_entry_setting_id');
            });
        }

        if (Schema::hasTable('purchase_invoices') && Schema::hasColumn('purchase_invoices', 'shop_ledger_entry_setting_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('shop_ledger_entry_setting_id');
            });
        }

        if (Schema::hasTable('purchaser_carts') && Schema::hasColumn('purchaser_carts', 'shop_ledger_entry_setting_id')) {
            Schema::table('purchaser_carts', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('shop_ledger_entry_setting_id');
            });
        }
    }
};
