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
                'name' => 'Green Valley Farm',
                'type' => 'Farmer',
                'contact' => 'John Doe (+61 412 345 678)',
                'payment_terms' => 'COD',
                'quality_score' => 100.00,
            ],
            [
                'name' => 'Sunset Organic Cultivators',
                'type' => 'Farmer',
                'contact' => 'Sarah Smith (contact@sunsetorganic.com)',
                'payment_terms' => 'Net 7',
                'quality_score' => 100.00,
            ],
            [
                'name' => 'Apex Fresh Produce',
                'type' => 'Market Agent',
                'contact' => 'David Lee (+61 498 765 432)',
                'payment_terms' => 'Net 15',
                'quality_score' => 100.00,
            ],
            [
                'name' => 'Global Produce Direct',
                'type' => 'Importer',
                'contact' => 'operations@globalproduce.com',
                'payment_terms' => 'Net 30',
                'quality_score' => 100.00,
            ],
            [
                'name' => 'Unity Growers Co-op',
                'type' => 'Co-operative',
                'contact' => 'Co-op Office (03 9876 5432)',
                'payment_terms' => 'Net 15',
                'quality_score' => 100.00,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(['name' => $supplier['name']], $supplier);
        }
    }
}
