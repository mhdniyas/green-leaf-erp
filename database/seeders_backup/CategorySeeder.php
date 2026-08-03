<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Supply', 'description' => 'Primary supply items like tomatoes.'],
            ['name' => 'VEG', 'description' => 'Regular vegetables.'],
            ['name' => 'HAL', 'description' => 'Special selection items.'],
            ['name' => 'Leaf', 'description' => 'Leafy greens and herbs.'],
            ['name' => 'Full Bunch', 'description' => 'Full bunch produce items.'],
            ['name' => 'English', 'description' => 'English and premium vegetables.'],
            ['name' => 'Kolkata', 'description' => 'Special Kolkata produce items.'],
            ['name' => 'Banana', 'description' => 'Banana varieties.'],
            ['name' => 'Onion', 'description' => 'Onions, garlic, potatoes.'],
            ['name' => 'C', 'description' => 'Coconut and derivatives.'],
            ['name' => 'Frut', 'description' => 'Fruits catalog.'],
            ['name' => 'Stationory', 'description' => 'Packaging materials and stationary.'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                array_merge($category, ['is_active' => true])
            );
        }

        $this->command?->info('✅ Categories seeded successfully.');
    }
}
