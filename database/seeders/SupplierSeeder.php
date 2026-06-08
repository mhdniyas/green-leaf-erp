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
                'is_default_purchase' => true,
                'contact' => 'John Doe (+91 98765 43210)',
                'payment_terms' => 'COD',
                'quality_score' => 100.00,
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
