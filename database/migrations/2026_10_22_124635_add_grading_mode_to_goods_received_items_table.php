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
        Schema::table('goods_received_items', function (Blueprint $table): void {
            $table->string('grading_mode', 30)->nullable()->default(null)->after('grade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_received_items', function (Blueprint $table): void {
            $table->dropColumn('grading_mode');
        });
    }
};
