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
        Schema::table('goods_received', function (Blueprint $table): void {
            $table->string('bill_status', 30)->default('bill_available')->after('status');
            $table->string('bill_number', 100)->nullable()->after('bill_status');
            $table->index('bill_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_received', function (Blueprint $table): void {
            $table->dropIndex(['bill_status']);
            $table->dropColumn(['bill_status', 'bill_number']);
        });
    }
};
