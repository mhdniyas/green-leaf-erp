<?php

declare(strict_types=1);

namespace Database\Seeders\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use Illuminate\Database\Seeder;

/**
 * Seeds the global ledger_entry_types catalogue required by the cashbook engine.
 * Run with: php artisan db:seed --class="Database\Seeders\Cashbook\LedgerEntryTypeSeeder"
 *
 * This seeder is safe to re-run (uses updateOrCreate), so it can be executed
 * after new entry types are added to the list below without duplicating rows.
 */
class LedgerEntryTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // Income
            ['code' => 'cash_sales',   'name' => 'Cash Sales',   'category' => 'income'],
            ['code' => 'card',         'name' => 'Card',          'category' => 'income'],
            ['code' => 'paytm',        'name' => 'Paytm',         'category' => 'income'],
            ['code' => 'upi',          'name' => 'UPI',           'category' => 'income'],
            ['code' => 'income_rent',  'name' => 'Rent',          'category' => 'income'],
            ['code' => 'income_cp',    'name' => 'CP',            'category' => 'income'],
            ['code' => 'other_income', 'name' => 'Other Income',  'category' => 'income'],

            // Expense
            ['code' => 'purchase_bill',   'name' => 'Daily Expense', 'category' => 'expense'],
            ['code' => 'cash_purchase',   'name' => 'Cash Purchase',   'category' => 'expense'],
            ['code' => 'rent_expense',    'name' => 'Rent',             'category' => 'expense'],
            ['code' => 'vehicle',         'name' => 'Vehicle',          'category' => 'expense'],
            ['code' => 'labour',          'name' => 'Labour',           'category' => 'expense'],
            ['code' => 'food',            'name' => 'Food',             'category' => 'expense'],
            ['code' => 'maintenance',     'name' => 'Maintenance',      'category' => 'expense'],
            ['code' => 'electricity',     'name' => 'Electricity',      'category' => 'expense'],
            ['code' => 'shop_deduct',     'name' => 'Shop Deduct',      'category' => 'expense'],
            ['code' => 'salary',          'name' => 'Salary',           'category' => 'expense'],
            ['code' => 'other_expense',   'name' => 'Other Expense',    'category' => 'expense'],
            ['code' => 'expense_cp',      'name' => 'CP (Expense)',      'category' => 'expense'],
            ['code' => 'expense_rent',    'name' => 'Rent (Expense)',    'category' => 'expense'],

            // Transfers (overrides in shop_ledger_entry_settings drive their accounting behaviour)
            ['code' => 'sales_to_petty',    'name' => 'Sales → Petty',     'category' => 'transfer'],
            ['code' => 'company_to_petty',  'name' => 'Company → Petty',   'category' => 'transfer'],
            ['code' => 'petty_to_company',  'name' => 'Petty → Company',   'category' => 'transfer'],
            ['code' => 'sales_to_company',  'name' => 'Sales → Company',   'category' => 'transfer'],
            ['code' => 'company_to_shop',   'name' => 'Company → Shop',    'category' => 'transfer'],
            ['code' => 'bank_to_petty',     'name' => 'Bank → Petty',      'category' => 'transfer'],

            // Settlements
            ['code' => 'shop_paid_company',   'name' => 'Shop Paid Company',   'category' => 'settlement'],
            ['code' => 'company_paid_shop',   'name' => 'Company Paid Shop',   'category' => 'settlement'],
            ['code' => 'company_paid_vendor', 'name' => 'Company Paid Vendor', 'category' => 'settlement'],
            ['code' => 'petty_reimbursement', 'name' => 'Petty Reimbursement', 'category' => 'settlement'],
        ];

        foreach ($types as $i => $type) {
            LedgerEntryType::updateOrCreate(
                ['code' => $type['code']],
                $type + ['active' => true, 'display_order' => $i]
            );
        }

        $this->command->info('Seeded ' . count($types) . ' cashbook ledger entry types.');
    }
}
