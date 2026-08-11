<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->index(['warehouse_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
