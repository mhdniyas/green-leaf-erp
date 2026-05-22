<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // Leafy Vegetables
            ['category' => 'Leafy Vegetables', 'name' => 'Spinach', 'sku' => 'SPINACH-001', 'unit' => 'kg'],
            ['category' => 'Leafy Vegetables', 'name' => 'Coriander', 'sku' => 'CORIANDER-001', 'unit' => 'kg'],
            ['category' => 'Leafy Vegetables', 'name' => 'Mint', 'sku' => 'MINT-001', 'unit' => 'kg'],
            ['category' => 'Leafy Vegetables', 'name' => 'Fenugreek Leaves', 'sku' => 'FENUGREEK-001', 'unit' => 'kg'],

            // Root Vegetables
            ['category' => 'Root Vegetables', 'name' => 'Carrot', 'sku' => 'CARROT-001', 'unit' => 'kg'],
            ['category' => 'Root Vegetables', 'name' => 'Radish', 'sku' => 'RADISH-001', 'unit' => 'kg'],
            ['category' => 'Root Vegetables', 'name' => 'Beetroot', 'sku' => 'BEETROOT-001', 'unit' => 'kg'],

            // Fruit Vegetables
            ['category' => 'Fruit Vegetables', 'name' => 'Tomato', 'sku' => 'TOMATO-001', 'unit' => 'kg'],
            ['category' => 'Fruit Vegetables', 'name' => 'Brinjal', 'sku' => 'BRINJAL-001', 'unit' => 'kg'],
            ['category' => 'Fruit Vegetables', 'name' => 'Capsicum', 'sku' => 'CAPSICUM-001', 'unit' => 'kg'],
            ['category' => 'Fruit Vegetables', 'name' => 'Green Chilli', 'sku' => 'CHILLI-GRN-001', 'unit' => 'kg'],
            ['category' => 'Fruit Vegetables', 'name' => 'Lady Finger', 'sku' => 'LADYFINGER-001', 'unit' => 'kg'],

            // Cruciferous
            ['category' => 'Cruciferous', 'name' => 'Cauliflower', 'sku' => 'CAULIFLOWER-001', 'unit' => 'kg'],
            ['category' => 'Cruciferous', 'name' => 'Broccoli', 'sku' => 'BROCCOLI-001', 'unit' => 'kg'],
            ['category' => 'Cruciferous', 'name' => 'Cabbage', 'sku' => 'CABBAGE-001', 'unit' => 'kg'],

            // Alliums
            ['category' => 'Alliums', 'name' => 'Onion', 'sku' => 'ONION-001', 'unit' => 'kg'],
            ['category' => 'Alliums', 'name' => 'Garlic', 'sku' => 'GARLIC-001', 'unit' => 'kg'],
            ['category' => 'Alliums', 'name' => 'Ginger', 'sku' => 'GINGER-001', 'unit' => 'kg'],
            ['category' => 'Alliums', 'name' => 'Spring Onion', 'sku' => 'SPRINGONION-001', 'unit' => 'kg'],

            // Gourds
            ['category' => 'Gourds', 'name' => 'Bitter Gourd', 'sku' => 'BITTERGOURD-001', 'unit' => 'kg'],
            ['category' => 'Gourds', 'name' => 'Bottle Gourd', 'sku' => 'BOTTLEGOURD-001', 'unit' => 'kg'],
            ['category' => 'Gourds', 'name' => 'Ridge Gourd', 'sku' => 'RIDGEGOURD-001', 'unit' => 'kg'],

            // Tubers
            ['category' => 'Tubers', 'name' => 'Potato', 'sku' => 'POTATO-001', 'unit' => 'kg'],
            ['category' => 'Tubers', 'name' => 'Sweet Potato', 'sku' => 'SWEETPOTATO-001', 'unit' => 'kg'],

            // Beans & Legumes
            ['category' => 'Beans & Legumes', 'name' => 'Beans', 'sku' => 'BEANS-001', 'unit' => 'kg'],
            ['category' => 'Beans & Legumes', 'name' => 'Green Peas', 'sku' => 'GREENPEAS-001', 'unit' => 'kg'],
            ['category' => 'Beans & Legumes', 'name' => 'Drumstick', 'sku' => 'DRUMSTICK-001', 'unit' => 'kg'],
        ];

        foreach ($products as $data) {
            $category = Category::where('name', $data['category'])->first();
            if (! $category) {
                continue;
            }

            Product::updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'category_id' => $category->id,
                    'name' => $data['name'],
                    'sku' => $data['sku'],
                    'unit' => $data['unit'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✅ '.Product::count().' products seeded successfully.');
    }
}
