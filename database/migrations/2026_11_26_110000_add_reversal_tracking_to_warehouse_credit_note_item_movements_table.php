<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_credit_note_item_movements', function (Blueprint $table): void {
            $table->foreignId('reversal_stock_movement_id')
                ->nullable()
                ->after('cost_per_unit')
                ->constrained('stock_movements', 'id', 'wcn_item_mov_rev_mov_fk')
                ->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('reversal_stock_movement_id');

            $table->unique('stock_movement_id', 'wcn_item_movements_stock_movement_unique');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_credit_note_item_movements', function (Blueprint $table): void {
            $table->dropUnique('wcn_item_movements_stock_movement_unique');
            $table->dropForeign('wcn_item_mov_rev_mov_fk');
            $table->dropColumn(['reversal_stock_movement_id', 'reversed_at']);
        });
    }
};
