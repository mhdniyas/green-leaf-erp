<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('location')->nullable()->after('contact');
            $table->string('mobile_number', 50)->nullable()->after('location');
            $table->string('preferred_payment_method', 100)->nullable()->after('payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['location', 'mobile_number', 'preferred_payment_method']);
        });
    }
};
