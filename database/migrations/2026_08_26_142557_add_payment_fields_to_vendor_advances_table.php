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
        Schema::table('vendor_advances', function (Blueprint $table): void {
            $table->string('payment_method', 50)->nullable()->after('business_date');
            $table->string('reference', 160)->nullable()->after('payment_method');
            $table->text('notes')->nullable()->after('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_advances', function (Blueprint $table): void {
            $table->dropColumn(['payment_method', 'reference', 'notes']);
        });
    }
};
