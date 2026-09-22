<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\PurchaseGradePrice;
use App\Models\PurchaserCartItem;
use App\Models\User;
use App\Services\Purchasing\PurchaseGradePriceResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseGradePriceResolverTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseGradePriceResolver $resolver;

    private User $purchaser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->resolver = app(PurchaseGradePriceResolver::class);

        Role::findOrCreate('purchaser');
        Role::findOrCreate('admin');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->category = Category::factory()->create(['name' => 'Vegetables']);
    }

    public function test_1_grade_a_daily_price_approval_exists_returns_it(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'purchase_price' => 45.50,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        $resolvedPrice = $this->resolver->resolve($product->id, '2026-09-22', 'A');

        $this->assertSame(45.50, $resolvedPrice);
    }

    public function test_2_explicit_approved_grade_a_purchase_grade_price_has_precedence_over_daily_price_approval(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'purchase_price' => 40.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        PurchaseGradePrice::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'grade' => 'A',
            'purchase_price' => 50.00,
            'status' => 'approved',
        ]);

        $resolvedPrice = $this->resolver->resolve($product->id, '2026-09-22', 'A');

        $this->assertSame(50.00, $resolvedPrice);
    }

    public function test_3_grade_a_missing_everywhere_throws_validation_error(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No approved Grade A purchase price is available for this product and date.');

        $this->resolver->resolve($product->id, '2026-09-22', 'A');
    }

    public function test_4_grade_b_exists_resolves_correctly(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        PurchaseGradePrice::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'grade' => 'B',
            'purchase_price' => 28.00,
            'status' => 'approved',
        ]);

        $resolvedPrice = $this->resolver->resolve($product->id, '2026-09-22', 'B');

        $this->assertSame(28.00, $resolvedPrice);
    }

    public function test_5_grade_b_missing_while_grade_a_exists_fails_without_falling_back_to_grade_a(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'purchase_price' => 45.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No approved Grade B purchase price is available for this product and date.');

        $this->resolver->resolve($product->id, '2026-09-22', 'B');
    }

    public function test_6_grade_a_missing_while_grade_b_exists_must_not_use_grade_b(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        PurchaseGradePrice::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'grade' => 'B',
            'purchase_price' => 25.00,
            'status' => 'approved',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No approved Grade A purchase price is available for this product and date.');

        $this->resolver->resolve($product->id, '2026-09-22', 'A');
    }

    public function test_7_future_grade_a_approval_only_fails(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        // Future approval on 2026-09-25
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-25',
            'purchase_price' => 45.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No approved Grade A purchase price is available for this product and date.');

        // Requested on 2026-09-22
        $this->resolver->resolve($product->id, '2026-09-22', 'A');
    }

    public function test_8_draft_unapproved_grade_a_approval_fails(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'purchase_price' => 45.00,
            'status' => 'draft',
            'price_unit' => 'kg',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No approved Grade A purchase price is available for this product and date.');

        $this->resolver->resolve($product->id, '2026-09-22', 'A');
    }

    public function test_9_zero_or_null_purchase_price_fails(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'purchase_price' => 0.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No approved Grade A purchase price is available for this product and date.');

        $this->resolver->resolve($product->id, '2026-09-22', 'A');
    }

    public function test_10_latest_valid_historical_approved_price_is_selected(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        // Older approved price: 30.00 on 2026-09-18
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-18',
            'purchase_price' => 30.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // Newer approved price: 35.00 on 2026-09-21
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-21',
            'purchase_price' => 35.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        $resolvedPrice = $this->resolver->resolve($product->id, '2026-09-22', 'A');

        $this->assertSame(35.00, $resolvedPrice);
    }

    public function test_11_addon_and_standard_bulk_buy_resolve_identical_grade_a_price(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Tomato Hybrid',
            'sku' => 'TOM-HYB',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'purchase_price' => 32.50,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // 1. Test Add-on -> Add to Cart
        $addonResponse = $this->actingAs($this->purchaser)
            ->postJson(route('purchaser.bulk-buy.add-ons-to-cart.store'), [
                'date' => '2026-09-22',
                'purchase_grade' => 'A',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 10.0],
                ],
            ]);

        $addonResponse->assertOk();
        $addonResponse->assertJsonFragment(['success' => true]);

        $addonCartItem = PurchaserCartItem::query()
            ->where('product_id', $product->id)
            ->where('grade', 'A')
            ->firstOrFail();

        $this->assertSame(32.50, (float) $addonCartItem->unit_price);
        $this->assertSame(325.00, (float) $addonCartItem->line_total);

        // 2. Standard Bulk Buy cart store uses the exact same price
        $standardResolvedPrice = $this->resolver->resolve($product->id, '2026-09-22', 'A');
        $this->assertSame((float) $addonCartItem->unit_price, $standardResolvedPrice);
    }

    public function test_12_addon_and_standard_bulk_buy_resolve_identical_grade_b_price(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Potato Standard',
            'sku' => 'POT-STD',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        PurchaseGradePrice::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'grade' => 'B',
            'purchase_price' => 18.75,
            'status' => 'approved',
        ]);

        // 1. Add-on -> Add to Cart for Grade B
        $addonResponse = $this->actingAs($this->purchaser)
            ->postJson(route('purchaser.bulk-buy.add-ons-to-cart.store'), [
                'date' => '2026-09-22',
                'purchase_grade' => 'B',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 15.0],
                ],
            ]);

        $addonResponse->assertOk();
        $addonResponse->assertJsonFragment(['success' => true]);

        $gradeBCartItem = PurchaserCartItem::query()
            ->where('product_id', $product->id)
            ->where('grade', 'B')
            ->firstOrFail();

        $this->assertSame(18.75, (float) $gradeBCartItem->unit_price);
        $this->assertSame(281.25, (float) $gradeBCartItem->line_total);

        // 2. Standard Bulk Buy resolver matches
        $standardGradeBPrice = $this->resolver->resolve($product->id, '2026-09-22', 'B');
        $this->assertSame((float) $gradeBCartItem->unit_price, $standardGradeBPrice);
    }
}
