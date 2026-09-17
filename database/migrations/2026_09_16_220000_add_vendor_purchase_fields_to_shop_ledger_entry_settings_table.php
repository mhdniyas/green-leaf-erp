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
                if (! Schema::hasColumn('shop_ledger_entry_settings', 'is_vendor_purchase')) {
                    $table->boolean('is_vendor_purchase')->default(false)->after('enabled');
                }
                if (! Schema::hasColumn('shop_ledger_entry_settings', 'vendor_access_mode')) {
                    $table->string('vendor_access_mode', 50)->nullable()->after('is_vendor_purchase');
                }
                if (! Schema::hasColumn('shop_ledger_entry_settings', 'vendor_settlement_relation_id')) {
                    $table->foreignId('vendor_settlement_relation_id')
                        ->nullable()
                        ->after('vendor_access_mode')
                        ->constrained('shop_cashbook_relations')
                        ->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('category_vendor_mappings')) {
            Schema::create('category_vendor_mappings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_ledger_entry_setting_id')
                    ->constrained('shop_ledger_entry_settings')
                    ->cascadeOnDelete();
                $table->foreignId('shop_supplier_id')
                    ->constrained('shop_suppliers')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->unique(
                    ['shop_ledger_entry_setting_id', 'shop_supplier_id'],
                    'cat_vendor_setting_supplier_unique'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_vendor_mappings');

        if (Schema::hasTable('shop_ledger_entry_settings')) {
            Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
                if (Schema::hasColumn('shop_ledger_entry_settings', 'vendor_settlement_relation_id')) {
                    $table->dropConstrainedForeignId('vendor_settlement_relation_id');
                }
                if (Schema::hasColumn('shop_ledger_entry_settings', 'vendor_access_mode')) {
                    $table->dropColumn('vendor_access_mode');
                }
                if (Schema::hasColumn('shop_ledger_entry_settings', 'is_vendor_purchase')) {
                    $table->dropColumn('is_vendor_purchase');
                }
            });
        }
    }
};
