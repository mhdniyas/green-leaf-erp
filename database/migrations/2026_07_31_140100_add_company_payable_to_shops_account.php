<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        $exists = DB::table('accounts')->where('code', '2200')->exists();
        if ($exists) {
            return;
        }

        DB::table('accounts')->insert([
            'code' => '2200',
            'name' => 'Company Payable to Shops',
            'type' => 'liability',
            'is_active' => true,
            'parent_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        DB::table('accounts')->where('code', '2200')->delete();
    }
};
