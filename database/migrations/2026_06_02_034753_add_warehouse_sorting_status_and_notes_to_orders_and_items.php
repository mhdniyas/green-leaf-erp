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
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->string('sorting_status', 30)->default('pending')->after('is_sorted');
        });

        Schema::table('shop_orders', function (Blueprint $table) {
            $table->boolean('is_allocation_completed')->default(false)->after('state');
            $table->text('sorting_notes')->nullable()->after('is_allocation_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->dropColumn('sorting_status');
        });

        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn(['is_allocation_completed', 'sorting_notes']);
        });
    }
};
