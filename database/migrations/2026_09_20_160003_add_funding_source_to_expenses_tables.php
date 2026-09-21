<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_expenses', function (Blueprint $table) {
            $table->string('funding_source', 30)->default('company_cash')->after('category');
        });

        Schema::table('other_expenses', function (Blueprint $table) {
            $table->string('funding_source', 30)->default('company_cash')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_expenses', function (Blueprint $table) {
            $table->dropColumn('funding_source');
        });

        Schema::table('other_expenses', function (Blueprint $table) {
            $table->dropColumn('funding_source');
        });
    }
};
