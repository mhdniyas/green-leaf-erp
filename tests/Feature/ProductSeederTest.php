<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_seeder_uses_numeric_codes_from_master_list(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);

        $this->assertSame(255, Product::query()->count());
        $this->assertDatabaseHas('products', ['sku' => '1', 'name' => 'Tomato H']);
        $this->assertDatabaseHas('products', ['sku' => '304', 'name' => 'Container 500 G']);
        $this->assertDatabaseMissing('products', ['sku' => 'TOMATOH-001']);
        $this->assertSame(['1', '2', '3', '4', '5'], Product::query()->ordered()->limit(5)->pluck('sku')->all());
    }
}
