<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Inventory\ProductGrade;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\ProductWholesalePrice;
use App\Models\ShopPriceGroup;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PriceBoardSellingPriceIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_grn_wholesale_update_does_not_populate_selling_prices(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-11 12:00:00'));

        $product = $this->createProduct(basePrice: 100.00);
        ProductWholesalePrice::create([
            'product_id' => $product->id,
            'grade' => ProductGrade::GradeA->value,
            'weighted_average_cost' => 56.9565,
            'wholesale_price' => 56.9565,
            'sellable_quantity' => 10,
            'total_cost' => 569.565,
            'source_type' => 'grn',
        ]);
        DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-08-10',
            'purchase_price' => 50.00,
            'price_unit' => 'kg',
            'price_a' => 68.00,
            'price_b' => 65.00,
            'price_c' => 60.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        app(PriceBoardService::class)->createOrUpdatePendingApproval($product);

        $this->assertDatabaseHas('daily_price_approvals', [
            'product_id' => $product->id,
            'business_date' => '2026-08-11 00:00:00',
            'purchase_price' => 56.9565,
            'price_a' => 0.00,
            'price_b' => 0.00,
            'price_c' => 0.00,
            'status' => 'pending',
        ]);
    }

    public function test_grn_wholesale_update_preserves_existing_matrix_selling_prices(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-11 12:00:00'));

        $product = $this->createProduct(basePrice: 100.00);
        ProductWholesalePrice::create([
            'product_id' => $product->id,
            'grade' => ProductGrade::GradeA->value,
            'weighted_average_cost' => 56.9565,
            'wholesale_price' => 56.9565,
            'sellable_quantity' => 10,
            'total_cost' => 569.565,
            'source_type' => 'grn',
        ]);
        DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-08-11',
            'purchase_price' => 50.00,
            'price_unit' => 'kg',
            'price_a' => 90.00,
            'price_b' => 80.00,
            'price_c' => 70.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        app(PriceBoardService::class)->createOrUpdatePendingApproval($product);

        $this->assertDatabaseHas('daily_price_approvals', [
            'product_id' => $product->id,
            'business_date' => '2026-08-11 00:00:00',
            'purchase_price' => 56.9565,
            'price_a' => 90.00,
            'price_b' => 80.00,
            'price_c' => 70.00,
            'status' => 'approved',
        ]);
    }

    public function test_new_active_selling_price_uses_product_base_not_wholesale_cost(): void
    {
        $product = $this->createProduct(basePrice: 100.00);
        $group = ShopPriceGroup::create([
            'name' => 'A',
            'default_margin_percent' => 0,
            'is_active' => true,
        ]);
        ProductWholesalePrice::create([
            'product_id' => $product->id,
            'grade' => ProductGrade::GradeA->value,
            'weighted_average_cost' => 50.00,
            'wholesale_price' => 50.00,
            'sellable_quantity' => 10,
            'total_cost' => 500.00,
            'source_type' => 'grn',
        ]);

        $result = app(PriceBoardService::class)->sellingPriceFor($product, null);

        $this->assertSame($group->id, $result['group']->id);
        $this->assertSame(100.00, $result['price']);
    }

    private function createProduct(float $basePrice): Product
    {
        $category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        return Product::create([
            'name' => 'Test Vegetable',
            'sku' => 'TEST-VEG',
            'category_id' => $category->id,
            'unit' => 'KG',
            'base_price' => $basePrice,
            'vendor_price' => 50.00,
            'is_active' => true,
        ]);
    }
}
