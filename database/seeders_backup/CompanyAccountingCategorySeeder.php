<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\CompanyAccountingCategory;
use Illuminate\Database\Seeder;

class CompanyAccountingCategorySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['type' => 'income', 'name' => 'Sales Income', 'account_code' => '4100'],
            ['type' => 'income', 'name' => 'Other Income', 'account_code' => '4100'],
            ['type' => 'expense', 'name' => 'Transport Expense', 'account_code' => '5300'],
            ['type' => 'expense', 'name' => 'Labour Expense', 'account_code' => '5400'],
            ['type' => 'expense', 'name' => 'Utilities Expense', 'account_code' => '5500'],
            ['type' => 'expense', 'name' => 'Rent Expense', 'account_code' => '5600'],
            ['type' => 'expense', 'name' => 'Salaries Expense', 'account_code' => '5700'],
            ['type' => 'expense', 'name' => 'Miscellaneous Expense', 'account_code' => '5900'],
        ];

        foreach ($defaults as $default) {
            $account = Account::query()->where('code', $default['account_code'])->first();

            if (! $account instanceof Account) {
                continue;
            }

            CompanyAccountingCategory::query()->updateOrCreate(
                ['type' => $default['type'], 'name' => $default['name']],
                ['account_id' => $account->id, 'is_active' => true],
            );
        }
    }
}
