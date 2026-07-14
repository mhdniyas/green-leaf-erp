<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['code' => '2150'],
            [
                'name' => 'Goods Received Not Invoiced',
                'type' => 'liability',
                'is_active' => true,
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '2150')->delete();
    }
};
