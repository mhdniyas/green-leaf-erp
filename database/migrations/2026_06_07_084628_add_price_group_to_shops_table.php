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
        Schema::table('shops', function (Blueprint $table): void {
            $table->foreignId('shop_price_group_id')
                ->nullable()
                ->after('warehouse_tag')
                ->constrained('shop_price_groups')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shop_price_group_id');
        });
    }
};
