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
        if (! Schema::hasColumn('shop_suppliers', 'credit_approved')) {
            Schema::table('shop_suppliers', function (Blueprint $table): void {
                $table->boolean('credit_approved')->nullable()->after('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('shop_suppliers', 'credit_approved')) {
            Schema::table('shop_suppliers', function (Blueprint $table): void {
                $table->dropColumn('credit_approved');
            });
        }
    }
};
