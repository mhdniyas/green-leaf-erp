<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Enums\Inventory\ProductGrade;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDailyProductPrice;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ShopDailyProductPriceGradeTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Product $product;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create(['name' => 'Downtown Branch']);
        $this->product = Product::factory()->create(['name' => 'Fresh Tomato', 'unit' => 'KG']);
        $this->user = User::factory()->create();
    }

    public function test_creating_special_price_without_grade_defaults_to_grade_a(): void
    {
        $price = ShopDailyProductPrice::query()->create([
            'business_date' => '2026-11-04',
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'selling_price' => 45.00,
            'price_unit' => 'KG',
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $this->assertSame('A', $price->product_grade);
        $this->assertSame(ProductGrade::GradeA, $price->productGrade());

        $this->assertDatabaseHas('shop_daily_product_prices', [
            'id' => $price->id,
            'product_grade' => 'A',
            'selling_price' => 45.00,
        ]);
    }

    public function test_multiple_grades_can_coexist_for_same_product_shop_and_date(): void
    {
        $priceA = ShopDailyProductPrice::query()->create([
            'business_date' => '2026-11-04',
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'selling_price' => 50.00,
            'price_unit' => 'KG',
            'status' => 'approved',
        ]);

        $priceB = ShopDailyProductPrice::query()->create([
            'business_date' => '2026-11-04',
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'product_grade' => ProductGrade::GradeB,
            'selling_price' => 40.00,
            'price_unit' => 'KG',
            'status' => 'approved',
        ]);

        $priceC = ShopDailyProductPrice::query()->create([
            'business_date' => '2026-11-04',
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'product_grade' => 'C',
            'selling_price' => 30.00,
            'price_unit' => 'KG',
            'status' => 'approved',
        ]);

        $this->assertSame('A', $priceA->product_grade);
        $this->assertSame('B', $priceB->product_grade);
        $this->assertSame('C', $priceC->product_grade);

        $this->assertDatabaseCount('shop_daily_product_prices', 3);

        $prices = ShopDailyProductPrice::query()
            ->whereDate('business_date', '2026-11-04')
            ->where('shop_id', $this->shop->id)
            ->where('product_id', $this->product->id)
            ->orderBy('product_grade')
            ->get();

        $this->assertCount(3, $prices);
        $this->assertEquals(['A', 'B', 'C'], $prices->pluck('product_grade')->all());
        $this->assertEquals([50.00, 40.00, 30.00], $prices->map(fn ($p) => (float) $p->selling_price)->all());
    }

    public function test_duplicate_records_for_same_grade_are_rejected(): void
    {
        ShopDailyProductPrice::query()->create([
            'business_date' => '2026-11-04',
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'selling_price' => 40.00,
            'status' => 'approved',
        ]);

        $this->expectException(QueryException::class);

        ShopDailyProductPrice::query()->create([
            'business_date' => '2026-11-04',
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'selling_price' => 38.00,
            'status' => 'draft',
        ]);
    }

    public function test_invalid_grade_is_rejected_by_model(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid product grade [DMG]. Allowed commercial grades are: A, B, C.');

        ShopDailyProductPrice::query()->create([
            'business_date' => '2026-11-04',
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'product_grade' => 'DMG',
            'selling_price' => 15.00,
        ]);
    }

    public function test_scope_for_grade_filters_records(): void
    {
        ShopDailyProductPrice::query()->create([
            'business_date' => '2026-11-04',
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'selling_price' => 50.00,
        ]);

        ShopDailyProductPrice::query()->create([
            'business_date' => '2026-11-04',
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'selling_price' => 35.00,
        ]);

        $gradeARecords = ShopDailyProductPrice::query()->forGrade(ProductGrade::GradeA)->get();
        $this->assertCount(1, $gradeARecords);
        $this->assertSame('A', $gradeARecords->first()->product_grade);

        $gradeBRecords = ShopDailyProductPrice::query()->forGrade('B')->get();
        $this->assertCount(1, $gradeBRecords);
        $this->assertSame('B', $gradeBRecords->first()->product_grade);

        $gradeCRecords = ShopDailyProductPrice::query()->forGrade('C')->get();
        $this->assertCount(0, $gradeCRecords);
    }

    public function test_migration_rollback_aborts_when_non_grade_a_records_exist(): void
    {
        ShopDailyProductPrice::query()->create([
            'business_date' => '2026-11-04',
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'product_grade' => 'B',
            'selling_price' => 35.00,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rollback aborted: Found 1 shop_daily_product_prices record(s) with non-Grade-A or NULL product_grade values.');

        Artisan::call('migrate:rollback', ['--step' => 1]);
    }

    public function test_migration_up_and_down_lifecycle_with_only_grade_a_records(): void
    {
        ShopDailyProductPrice::query()->create([
            'business_date' => '2026-11-04',
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'selling_price' => 50.00,
        ]);

        $this->assertTrue(Schema::hasColumn('shop_daily_product_prices', 'product_grade'));

        Artisan::call('migrate:rollback', ['--step' => 1]);
        $this->assertFalse(Schema::hasColumn('shop_daily_product_prices', 'product_grade'));

        Artisan::call('migrate');
        $this->assertTrue(Schema::hasColumn('shop_daily_product_prices', 'product_grade'));
    }
}
