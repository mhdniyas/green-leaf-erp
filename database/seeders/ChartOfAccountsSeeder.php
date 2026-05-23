<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            // Assets (1000-1999)
            ['code' => '1010', 'name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true, 'parent_id' => null],
            ['code' => '1020', 'name' => 'Bank Account', 'type' => 'asset', 'is_active' => true, 'parent_id' => null],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true, 'parent_id' => null],
            ['code' => '1200', 'name' => 'Graded Inventory', 'type' => 'asset', 'is_active' => true, 'parent_id' => null],

            // Liabilities (2000-2999)
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true, 'parent_id' => null],

            // Equity (3000-3999)
            ['code' => '3100', 'name' => 'Owner\'s Equity', 'type' => 'equity', 'is_active' => true, 'parent_id' => null],
            ['code' => '3200', 'name' => 'Retained Earnings', 'type' => 'equity', 'is_active' => true, 'parent_id' => null],

            // Revenue (4000-4999)
            ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true, 'parent_id' => null],

            // Expenses (5000-5999)
            ['code' => '5100', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5200', 'name' => 'Wastage Expense', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5300', 'name' => 'Transport Expenses', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5400', 'name' => 'Labour Expenses', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5500', 'name' => 'Utilities Expense', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5600', 'name' => 'Rent Expense', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5700', 'name' => 'Salaries Expense', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5900', 'name' => 'Miscellaneous Expense', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
        ];

        foreach ($accounts as $account) {
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
