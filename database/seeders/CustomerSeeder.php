<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'name' => 'Lulu Hypermarket',
                'type' => 'Supermarket',
                'contact' => 'Procurement Dept (+971 4 263 5555)',
                'email' => 'procurement@lulu.ae',
                'address' => 'Al Barsha, Dubai, UAE',
                'payment_terms' => 'Net 30',
                'credit_limit' => 100000.00,
                'is_active' => true,
            ],
            [
                'name' => 'Al Futtaim Restaurants',
                'type' => 'Restaurant',
                'contact' => 'Ahmed Al Futtaim (+971 50 111 2222)',
                'email' => 'supplies@alfuttaim-restaurants.ae',
                'address' => 'DIFC, Dubai, UAE',
                'payment_terms' => 'Net 15',
                'credit_limit' => 25000.00,
                'is_active' => true,
            ],
            [
                'name' => 'Green Valley Wholesale',
                'type' => 'Wholesaler',
                'contact' => 'Ravi Kumar (+971 55 987 6543)',
                'email' => 'orders@greenvalley.ae',
                'address' => 'Deira Vegetable Market, Dubai',
                'payment_terms' => 'Net 7',
                'credit_limit' => 50000.00,
                'is_active' => true,
            ],
            [
                'name' => 'Corner Grocery LLC',
                'type' => 'Retailer',
                'contact' => 'Mohammed Hassan (+971 52 333 4444)',
                'email' => null,
                'address' => 'Satwa, Dubai, UAE',
                'payment_terms' => 'COD',
                'credit_limit' => 0.00,
                'is_active' => true,
            ],
            [
                'name' => 'Spice Garden Restaurant',
                'type' => 'Restaurant',
                'contact' => 'Priya Nair (+971 55 789 0123)',
                'email' => 'kitchen@spicegarden.ae',
                'address' => 'Karama, Dubai, UAE',
                'payment_terms' => 'COD',
                'credit_limit' => 5000.00,
                'is_active' => true,
            ],
        ];

        foreach ($customers as $data) {
            Customer::firstOrCreate(['name' => $data['name']], $data);
        }

        $this->command->info('✅ Customers seeded successfully.');
    }
}
