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
        Schema::table('shop_accounting_entry_lines', function (Blueprint $table) {
            $table->string('review_status', 20)->nullable()->after('description');
            $table->string('review_note', 255)->nullable()->after('review_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_accounting_entry_lines', function (Blueprint $table) {
            $table->dropColumn(['review_status', 'review_note']);
        });
    }
};
