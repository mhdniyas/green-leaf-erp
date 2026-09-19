<?php

declare(strict_types=1);

use App\Models\Cashbook\LedgerEntryType;
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
        // 1. Add vendor_purchase_payment_type to shop_ledger_entry_settings
        if (Schema::hasTable('shop_ledger_entry_settings') && ! Schema::hasColumn('shop_ledger_entry_settings', 'vendor_purchase_payment_type')) {
            Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
                $table->string('vendor_purchase_payment_type', 30)->nullable()->after('is_vendor_purchase');
            });
        }

        // 2. Add original_header_group_id to purchase_invoices
        if (Schema::hasTable('purchase_invoices') && ! Schema::hasColumn('purchase_invoices', 'original_header_group_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table): void {
                $table->foreignId('original_header_group_id')
                    ->nullable()
                    ->after('shop_ledger_entry_setting_id')
                    ->constrained('shop_ledger_header_groups')
                    ->nullOnDelete();
            });
        }

        // 3. Add original_header_group_id to shop_vendor_payables
        if (Schema::hasTable('shop_vendor_payables') && ! Schema::hasColumn('shop_vendor_payables', 'original_header_group_id')) {
            Schema::table('shop_vendor_payables', function (Blueprint $table): void {
                $table->foreignId('original_header_group_id')
                    ->nullable()
                    ->after('shop_ledger_entry_setting_id')
                    ->constrained('shop_ledger_header_groups')
                    ->nullOnDelete();
            });
        }

        // 4. Ensure the global ledger entry types exist
        if (Schema::hasTable('ledger_entry_types')) {
            LedgerEntryType::firstOrCreate(
                ['code' => 'vendor_purchase_cash'],
                [
                    'name' => 'Vendor Purchase - Cash',
                    'category' => 'expense',
                    'active' => true,
                    'is_system' => true,
                    'display_order' => 19,
                ]
            );

            LedgerEntryType::firstOrCreate(
                ['code' => 'vendor_purchase_credit'],
                [
                    'name' => 'Vendor Purchase - Credit',
                    'category' => 'expense',
                    'active' => true,
                    'is_system' => true,
                    'display_order' => 20,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('shop_vendor_payables') && Schema::hasColumn('shop_vendor_payables', 'original_header_group_id')) {
            Schema::table('shop_vendor_payables', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('original_header_group_id');
            });
        }

        if (Schema::hasTable('purchase_invoices') && Schema::hasColumn('purchase_invoices', 'original_header_group_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('original_header_group_id');
            });
        }

        if (Schema::hasTable('shop_ledger_entry_settings') && Schema::hasColumn('shop_ledger_entry_settings', 'vendor_purchase_payment_type')) {
            Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
                $table->dropColumn('vendor_purchase_payment_type');
            });
        }
    }
};
