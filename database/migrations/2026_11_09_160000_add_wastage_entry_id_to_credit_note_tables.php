<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_credit_note_items')) {
            Schema::table('shop_credit_note_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('shop_credit_note_items', 'wastage_entry_id')) {
                    $table->foreignId('wastage_entry_id')
                        ->nullable()
                        ->after('direction')
                        ->constrained('wastage_entries')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('shop_credit_note_receipts')) {
            Schema::table('shop_credit_note_receipts', function (Blueprint $table): void {
                if (! Schema::hasColumn('shop_credit_note_receipts', 'wastage_entry_id')) {
                    $table->foreignId('wastage_entry_id')
                        ->nullable()
                        ->after('reversal_stock_movement_id')
                        ->constrained('wastage_entries')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_credit_note_items')) {
            Schema::table('shop_credit_note_items', function (Blueprint $table): void {
                if (Schema::hasColumn('shop_credit_note_items', 'wastage_entry_id')) {
                    $table->dropForeign(['wastage_entry_id']);
                    $table->dropColumn('wastage_entry_id');
                }
            });
        }

        if (Schema::hasTable('shop_credit_note_receipts')) {
            Schema::table('shop_credit_note_receipts', function (Blueprint $table): void {
                if (Schema::hasColumn('shop_credit_note_receipts', 'wastage_entry_id')) {
                    $table->dropForeign(['wastage_entry_id']);
                    $table->dropColumn('wastage_entry_id');
                }
            });
        }
    }
};
