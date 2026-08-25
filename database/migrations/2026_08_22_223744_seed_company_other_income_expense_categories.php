<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $accounts = [
            ['code' => '4300', 'name' => 'Refunds and Miscellaneous Income', 'type' => 'revenue'],
            ['code' => '4310', 'name' => 'Rent Received', 'type' => 'revenue'],
            ['code' => '4320', 'name' => 'Scrap Sale Income', 'type' => 'revenue'],
            ['code' => '4330', 'name' => 'Rebate Income', 'type' => 'revenue'],
            ['code' => '5310', 'name' => 'Vehicle Expense', 'type' => 'expense'],
            ['code' => '5320', 'name' => 'Fuel Expense', 'type' => 'expense'],
            ['code' => '5330', 'name' => 'Maintenance Expense', 'type' => 'expense'],
            ['code' => '5340', 'name' => 'Office Expense', 'type' => 'expense'],
            ['code' => '5500', 'name' => 'Utilities Expense', 'type' => 'expense'],
            ['code' => '5600', 'name' => 'Rent Expense', 'type' => 'expense'],
            ['code' => '5350', 'name' => 'Travel Expense', 'type' => 'expense'],
            ['code' => '5360', 'name' => 'Food Expense', 'type' => 'expense'],
            ['code' => '5900', 'name' => 'Miscellaneous Expense', 'type' => 'expense'],
            ['code' => '5910', 'name' => 'Bank Charges', 'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            DB::table('accounts')->insertOrIgnore([
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'is_active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('accounts')
                ->where('code', $account['code'])
                ->update([
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'is_active' => true,
                    'parent_id' => null,
                    'updated_at' => $now,
                ]);
        }

        $categories = [
            ['type' => 'income', 'name' => 'Refund', 'account_code' => '4300'],
            ['type' => 'income', 'name' => 'Rent Received', 'account_code' => '4310'],
            ['type' => 'income', 'name' => 'Scrap Sale', 'account_code' => '4320'],
            ['type' => 'income', 'name' => 'Rebate', 'account_code' => '4330'],
            ['type' => 'income', 'name' => 'Miscellaneous', 'account_code' => '4300'],
            ['type' => 'income', 'name' => 'Other', 'account_code' => '4300'],
            ['type' => 'expense', 'name' => 'Vehicle', 'account_code' => '5310'],
            ['type' => 'expense', 'name' => 'Fuel', 'account_code' => '5320'],
            ['type' => 'expense', 'name' => 'Maintenance', 'account_code' => '5330'],
            ['type' => 'expense', 'name' => 'Office', 'account_code' => '5340'],
            ['type' => 'expense', 'name' => 'Utilities', 'account_code' => '5500'],
            ['type' => 'expense', 'name' => 'Travel', 'account_code' => '5350'],
            ['type' => 'expense', 'name' => 'Food', 'account_code' => '5360'],
            ['type' => 'expense', 'name' => 'Rent', 'account_code' => '5600'],
            ['type' => 'expense', 'name' => 'Bank Charges', 'account_code' => '5910'],
            ['type' => 'expense', 'name' => 'Miscellaneous', 'account_code' => '5900'],
            ['type' => 'expense', 'name' => 'Other', 'account_code' => '5900'],
        ];

        foreach ($categories as $category) {
            $accountId = DB::table('accounts')->where('code', $category['account_code'])->value('id');

            if (! $accountId) {
                continue;
            }

            DB::table('company_accounting_categories')->insertOrIgnore([
                'type' => $category['type'],
                'name' => $category['name'],
                'account_id' => $accountId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('company_accounting_categories')
                ->where('type', $category['type'])
                ->where('name', $category['name'])
                ->update([
                    'account_id' => $accountId,
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Seed migration is intentionally forward-only to avoid deleting preexisting same-name categories.
    }
};
