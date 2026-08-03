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
        Schema::table('daily_price_approvals', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('approved_at');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_price_approvals', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['locked_at', 'locked_by']);
        });
    }
};
