<?php

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
        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->string('grade', 20)->nullable()->after('warehouse_id');
            $table->index(['product_id', 'warehouse_id', 'grade']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->dropIndex(['product_id', 'warehouse_id', 'grade']);
            $table->dropColumn('grade');
        });
    }
};
