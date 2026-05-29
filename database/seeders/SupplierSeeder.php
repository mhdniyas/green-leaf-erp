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
                'category' => 'own_purchase',
                'contact' => 'John Doe (+61 412 345 678)',
                'payment_terms' => 'COD',
                'quality_score' => 100.00,
            ],
            [
                'name' => 'Sunset Organic Cultivators',
                'type' => 'Farmer',
                'category' => 'own_purchase',
                'contact' => 'Sarah Smith (contact@sunsetorganic.com)',
                'payment_terms' => 'Net 7',
                'quality_score' => 100.00,
            ],
            [
                'name' => 'Apex Fresh Produce',
                'type' => 'Market Agent',
                'category' => 'own_purchase',
                'contact' => 'David Lee (+61 498 765 432)',
                'payment_terms' => 'Net 15',
                'quality_score' => 100.00,
            ],
            [
                'name' => 'Global Produce Direct',
                'type' => 'Importer',
                'category' => 'b2b',
                'contact' => 'operations@globalproduce.com',
                'payment_terms' => 'Net 30',
                'quality_score' => 100.00,
            ],
            [
                'name' => 'Unity Growers Co-op',
                'type' => 'Co-operative',
                'category' => 'own_purchase',
                'contact' => 'Co-op Office (03 9876 5432)',
                'payment_terms' => 'Net 15',
                'quality_score' => 100.00,
            ],
            [
                'name' => 'B2B Partners Ltd',
                'type' => 'B2B Supplier',
                'category' => 'b2b',
                'contact' => 'b2b@partners.com',
                'payment_terms' => 'Net 30',
                'quality_score' => 100.00,
            ],
        ];

        foreach ($suppliers as $supplier) {
            $record = Supplier::firstOrCreate(['name' => $supplier['name']], $supplier);
            $record->update(['category' => $supplier['category']]);
        }
    }
}
