<?php

declare(strict_types=1);

namespace App\Support;

class ChartOfAccounts
{
    /**
     * @return list<array{code:string,name:string,type:string,is_active:bool,parent_id:null}>
     */
    public static function defaults(): array
    {
        return [
            ['code' => '1010', 'name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true, 'parent_id' => null],
            ['code' => '1020', 'name' => 'Bank Account', 'type' => 'asset', 'is_active' => true, 'parent_id' => null],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true, 'parent_id' => null],
            ['code' => '1200', 'name' => 'Graded Inventory', 'type' => 'asset', 'is_active' => true, 'parent_id' => null],
            ['code' => '1300', 'name' => 'Purchaser Advances', 'type' => 'asset', 'is_active' => true, 'parent_id' => null],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true, 'parent_id' => null],
            ['code' => '2150', 'name' => 'Goods Received Not Invoiced', 'type' => 'liability', 'is_active' => true, 'parent_id' => null],
            ['code' => '3100', 'name' => 'Owner\'s Equity', 'type' => 'equity', 'is_active' => true, 'parent_id' => null],
            ['code' => '3200', 'name' => 'Retained Earnings', 'type' => 'equity', 'is_active' => true, 'parent_id' => null],
            ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true, 'parent_id' => null],
            ['code' => '5100', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5200', 'name' => 'Wastage Expense', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5300', 'name' => 'Transport Expenses', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5400', 'name' => 'Labour Expenses', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5500', 'name' => 'Utilities Expense', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5600', 'name' => 'Rent Expense', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5700', 'name' => 'Salaries Expense', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
            ['code' => '5900', 'name' => 'Miscellaneous Expense', 'type' => 'expense', 'is_active' => true, 'parent_id' => null],
        ];
    }

    /**
     * @return array{code:string,name:string,type:string,is_active:bool,parent_id:null}|null
     */
    public static function find(string $code): ?array
    {
        foreach (self::defaults() as $account) {
            if ($account['code'] === $code) {
                return $account;
            }
        }

        return null;
    }
}
