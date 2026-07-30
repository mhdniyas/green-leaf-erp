<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'assigned_category_ids')) {
                $table->json('assigned_category_ids')->nullable()->after('own_purchase_purchaser_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'assigned_category_ids')) {
                $table->dropColumn('assigned_category_ids');
            }
        });
    }
};
