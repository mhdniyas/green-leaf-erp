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
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->foreignId('parent_batch_id')
                ->nullable()
                ->after('goods_received_item_id')
                ->constrained('stock_batches')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->dropForeign(['parent_batch_id']);
            $table->dropColumn('parent_batch_id');
        });
    }
};
