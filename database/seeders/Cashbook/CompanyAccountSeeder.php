<?php

declare(strict_types=1);

namespace Database\Seeders\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use Illuminate\Database\Seeder;

class CompanyAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'HDFC Main Current Account',
                'account_type' => 'bank',
                'bank_name' => 'HDFC Bank',
                'account_number' => '50200084920194',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'is_default' => true,
                'enabled' => true,
            ],
            [
                'name' => 'ICICI Cheque & POS Account',
                'account_type' => 'bank',
                'bank_name' => 'ICICI Bank',
                'account_number' => '000905012384',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'is_default' => false,
                'enabled' => true,
            ],
            [
                'name' => 'Company Main Cash Box',
                'account_type' => 'cash',
                'bank_name' => 'Cash Vault',
                'account_number' => 'CASH-VAULT-01',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'is_default' => false,
                'enabled' => true,
            ],
            [
                'name' => 'Paytm & PhonePe Merchant Account',
                'account_type' => 'wallet',
                'bank_name' => 'Paytm Merchant',
                'account_number' => 'PAYTM-MER-098',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'is_default' => false,
                'enabled' => true,
            ],
        ];

        foreach ($accounts as $acc) {
            CompanyAccount::updateOrCreate(
                ['name' => $acc['name']],
                $acc
            );
        }
    }
}
