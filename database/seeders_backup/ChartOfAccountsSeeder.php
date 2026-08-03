<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\ChartOfAccounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (ChartOfAccounts::defaults() as $account) {
            DB::table('accounts')->updateOrInsert(
                ['code' => $account['code']],
                array_merge($account, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
