<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_credit_note_receipts', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_credit_note_receipts', 'reversal_stock_movement_id')) {
                $table->foreignId('reversal_stock_movement_id')->nullable()->after('stock_movement_id')->constrained('stock_movements')->nullOnDelete();
            }
            if (! Schema::hasColumn('shop_credit_note_receipts', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('remarks');
            }
            if (! Schema::hasColumn('shop_credit_note_receipts', 'reversed_by')) {
                $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('shop_credit_note_receipts', 'reversal_reason')) {
                $table->text('reversal_reason')->nullable()->after('reversed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_credit_note_receipts', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_credit_note_receipts', 'reversal_stock_movement_id')) {
                $table->dropForeign(['reversal_stock_movement_id']);
            }
            if (Schema::hasColumn('shop_credit_note_receipts', 'reversed_by')) {
                $table->dropForeign(['reversed_by']);
            }
            $table->dropColumn(array_filter([
                Schema::hasColumn('shop_credit_note_receipts', 'reversal_stock_movement_id') ? 'reversal_stock_movement_id' : null,
                Schema::hasColumn('shop_credit_note_receipts', 'reversed_at') ? 'reversed_at' : null,
                Schema::hasColumn('shop_credit_note_receipts', 'reversed_by') ? 'reversed_by' : null,
                Schema::hasColumn('shop_credit_note_receipts', 'reversal_reason') ? 'reversal_reason' : null,
            ]));
        });
    }
};
