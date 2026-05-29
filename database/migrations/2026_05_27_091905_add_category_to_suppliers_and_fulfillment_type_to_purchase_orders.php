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
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('category', 50)->default('own_purchase')->index();
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->string('fulfillment_type', 30)->default('warehouse')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn('fulfillment_type');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
