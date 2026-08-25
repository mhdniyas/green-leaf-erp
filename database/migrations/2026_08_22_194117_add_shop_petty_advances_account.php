<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('accounts')->updateOrInsert(['code' => '1500'], ['name' => 'Shop Petty Advances', 'type' => 'asset', 'is_active' => true, 'parent_id' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('accounts')->where('code', '1500')->delete();
    }
};
