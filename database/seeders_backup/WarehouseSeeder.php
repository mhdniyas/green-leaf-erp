<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $vegWarehouse = Warehouse::updateOrCreate(
            ['code' => 'VEG-WH'],
            ['name' => 'Vegetable Warehouse', 'is_active' => true]
        );

        $fruitWarehouse = Warehouse::updateOrCreate(
            ['code' => 'FRT-WH'],
            ['name' => 'Fruit Warehouse', 'is_active' => true]
        );

        // Assign default warehouse to existing products based on category name
        $fruitCategories = Category::whereIn('name', ['Frut', 'Banana'])->pluck('id')->toArray();

        // Update products belonging to fruit categories
        Product::whereIn('category_id', $fruitCategories)->update([
            'default_warehouse_id' => $fruitWarehouse->id,
        ]);

        // Update all other products to default to veg warehouse
        Product::whereNotIn('category_id', $fruitCategories)->update([
            'default_warehouse_id' => $vegWarehouse->id,
        ]);

        $this->command->info('✅ Default warehouses seeded and products mapped successfully.');
    }
}
