<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('credit_approved')->default(false)->after('preferred_payment_method');
            $table->string('credit_terms', 100)->nullable()->after('credit_approved');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['credit_approved', 'credit_terms']);
        });
    }
};
