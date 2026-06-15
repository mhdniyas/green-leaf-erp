<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Market A',
                'type' => 'Market',
                'category' => 'own_purchase',
                'is_default_purchase' => true,
                'contact' => 'Market A Desk (+91 98765 43210)',
                'location' => 'Koyambedu Wholesale Market',
                'mobile_number' => '9876543210',
                'payment_terms' => 'COD',
                'preferred_payment_method' => 'Cash',
                'credit_approved' => false,
                'credit_terms' => null,
                'quality_score' => 100.00,
            ],
            [
                'name' => 'Market B',
                'type' => 'Market',
                'category' => 'own_purchase',
                'is_default_purchase' => false,
                'contact' => 'Market B Desk (+91 98765 43211)',
                'location' => 'Shivaji Market',
                'mobile_number' => '9876543211',
                'payment_terms' => '1 Day Credit',
                'preferred_payment_method' => 'Credit',
                'credit_approved' => true,
                'credit_terms' => 'Net 1 day',
                'quality_score' => 98.50,
            ],
        ];

        Supplier::query()
            ->whereNotIn('name', collect($suppliers)->pluck('name'))
            ->delete();

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(['name' => $supplier['name']], $supplier);
        }
    }
}
