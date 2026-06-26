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
            [
                'name' => 'Market C',
                'type' => 'Market',
                'category' => 'own_purchase',
                'is_default_purchase' => false,
                'contact' => 'Market C Stall (+91 98765 43212)',
                'location' => 'MGR Nagar Market',
                'mobile_number' => '9876543212',
                'payment_terms' => 'Daily settlement',
                'preferred_payment_method' => 'Cash',
                'credit_approved' => false,
                'credit_terms' => null,
                'quality_score' => 96.00,
            ],
            [
                'name' => 'Market D',
                'type' => 'Market',
                'category' => 'own_purchase',
                'is_default_purchase' => false,
                'contact' => 'Market D Counter (+91 98765 43213)',
                'location' => 'Saidapet Market',
                'mobile_number' => '9876543213',
                'payment_terms' => 'Net 2 days',
                'preferred_payment_method' => 'Online',
                'credit_approved' => true,
                'credit_terms' => 'Net 2 days',
                'quality_score' => 97.20,
            ],
            [
                'name' => 'Market E',
                'type' => 'Market',
                'category' => 'own_purchase',
                'is_default_purchase' => false,
                'contact' => 'Market E Point (+91 98765 43214)',
                'location' => 'Washermanpet Market',
                'mobile_number' => '9876543214',
                'payment_terms' => 'COD',
                'preferred_payment_method' => 'GPay',
                'credit_approved' => false,
                'credit_terms' => null,
                'quality_score' => 95.40,
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
