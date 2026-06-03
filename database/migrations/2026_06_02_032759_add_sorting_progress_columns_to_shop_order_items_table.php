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
            $table->boolean('is_sorted')->default(false)->after('fulfillment_type');
            $table->timestamp('sorted_at')->nullable()->after('is_sorted');
            $table->unsignedBigInteger('sorted_by')->nullable()->after('sorted_at');

            $table->foreign('sorted_by')->references('id')->on('users')->nullOnDelete();
            $table->index('is_sorted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->dropForeign(['sorted_by']);
            $table->dropColumn(['is_sorted', 'sorted_at', 'sorted_by']);
        });
    }
};
