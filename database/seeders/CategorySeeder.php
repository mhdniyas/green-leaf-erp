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
            ['name' => 'Leafy Vegetables', 'description' => 'Spinach, lettuce, coriander, mint, etc.'],
            ['name' => 'Root Vegetables', 'description' => 'Carrot, radish, beetroot, turnip, etc.'],
            ['name' => 'Fruit Vegetables', 'description' => 'Tomato, brinjal, capsicum, chilli, etc.'],
            ['name' => 'Cruciferous', 'description' => 'Cauliflower, broccoli, cabbage, etc.'],
            ['name' => 'Alliums', 'description' => 'Onion, garlic, leek, spring onion, etc.'],
            ['name' => 'Gourds', 'description' => 'Bitter gourd, bottle gourd, ridge gourd, etc.'],
            ['name' => 'Tubers', 'description' => 'Potato, sweet potato, taro, etc.'],
            ['name' => 'Beans & Legumes', 'description' => 'Beans, peas, drumstick, etc.'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                array_merge($category, ['is_active' => true])
            );
        }

        $this->command->info('✅ Categories seeded successfully.');
    }
}
