<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->updateOrInsert(['code' => '1400'], ['name' => 'Vendor Advances', 'type' => 'asset', 'is_active' => true, 'parent_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('accounts')->updateOrInsert(['code' => '4200'], ['name' => 'Vendor Settlement Discounts', 'type' => 'revenue', 'is_active' => true, 'parent_id' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('accounts')->whereIn('code', ['1400', '4200'])->delete();
    }
};
