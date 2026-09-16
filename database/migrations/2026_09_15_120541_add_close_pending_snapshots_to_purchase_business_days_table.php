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
        Schema::table('purchase_business_days', function (Blueprint $table) {
            $table->json('close_pending_snapshot')->nullable()->after('close_note');
            $table->json('first_close_pending_snapshot')->nullable()->after('close_pending_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_business_days', function (Blueprint $table) {
            $table->dropColumn(['close_pending_snapshot', 'first_close_pending_snapshot']);
        });
    }
};
