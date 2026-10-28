<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Enums\Inventory\ProductGrade;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class DailyPriceMatrixUiPriceValidityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    private ShopPriceGroup $groupA;

    private ShopPriceGroup $groupB;

    private Shop $shopA;

    private Shop $shopB;

    private ApprovedDailyPriceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('purchase', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->category = Category::factory()->create(['name' => 'Vegetables']);

        $this->groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['code' => 'A', 'description' => 'Tier A', 'is_active' => true]
        );
        $this->groupB = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'B'],
            ['code' => 'B', 'description' => 'Tier B', 'is_active' => true]
        );

        $this->shopA = Shop::factory()->create([
            'name' => 'Hotel Alpha',
            'shop_price_group_id' => $this->groupA->id,
            'status' => 'active',
        ]);

        $this->shopB = Shop::factory()->create([
            'name' => 'Hotel Beta',
            'shop_price_group_id' => $this->groupB->id,
            'status' => 'active',
        ]);

        $this->resolver = app(ApprovedDailyPriceResolver::class);
    }

    /**
     * Test 1: Approved positive selling price shows normal green Approved styling and indicator.
     */
    public function test_approved_positive_price_shows_normal_approved_styling(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category->id,
            'name' => 'Onion',
            'sku' => 'VEG-ONI-01',
            'unit' => 'kg',
            'base_price' => 30.00,
            'vendor_price' => 20.00,
            'is_active' => true,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-28',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 18.00,
            'price_unit' => 'kg',
            'price_a' => 45.00,
            'price_b' => 42.00,
            'price_c' => 40.00,
        ]);

        $response = $this->actingAs($this->admin)->get('/purchasing/prices/matrix?date=2026-10-28&matrix_category=a&quality_grade=B');

        $response->assertOk();
        $response->assertSee('Product Grade B');
        $response->assertSee('Shop Price Group A');
        $response->assertSee('cell-billable');
        $response->assertSee('Approved & valid for billing', false);

        $products = $response->viewData('matrixProducts');
        $this->assertCount(1, $products);
        $this->assertEquals(45.00, $products[0]['latest_approved_price']);
        $this->assertEquals('2026-10-28', $products[0]['latest_approved_date']);
        $this->assertTrue($products[0]['prices']['2026-10-28']['is_billable']);
        $this->assertFalse($products[0]['prices']['2026-10-28']['is_unpriced']);

        // Verify resolver succeeds for billing
        $resolved = $this->resolver->resolve($product, $this->shopA, '2026-10-28', ProductGrade::GradeB);
        $this->assertEquals(45.00, $resolved['price']);
        $this->assertEquals('price_a', $resolved['price_column']);
    }

    /**
     * Test 2: Approved zero selling price shows missing-price warning, cannot be treated as billable,
     * and does not invalidate other valid tiers.
     */
    public function test_approved_zero_selling_price_shows_missing_price_warning_and_cannot_be_treated_as_billable(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category->id,
            'name' => 'Potato Agra',
            'sku' => 'VEG-POT-01',
            'unit' => 'kg',
            'base_price' => 25.00,
            'vendor_price' => 15.00,
            'is_active' => true,
        ]);

        // Approved record with price_a = 0.00, but price_b = 35.00
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-28',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 0.00,
            'price_unit' => 'kg',
            'price_a' => 0.00,
            'price_b' => 35.00,
            'price_c' => 0.00,
        ]);

        // 1. Group A view: price_a is 0.00 -> should show Price Missing warning
        $responseA = $this->actingAs($this->admin)->get('/purchasing/prices/matrix?date=2026-10-28&matrix_category=a&quality_grade=B');
        $responseA->assertOk();
        $responseA->assertSee('cell-unpriced');
        $responseA->assertSee('Zero or missing price on approved record');

        $productsA = $responseA->viewData('matrixProducts');
        // 0.00 must NOT be selected as latest approved usable price
        $this->assertNull($productsA[0]['latest_approved_price']);
        $this->assertTrue($productsA[0]['has_unpriced_active']);
        $this->assertFalse($productsA[0]['prices']['2026-10-28']['is_billable']);
        $this->assertTrue($productsA[0]['prices']['2026-10-28']['is_unpriced']);

        // 2. Group B view: price_b is 35.00 -> should be billable and approved, not invalidated by Group A zero
        $responseB = $this->actingAs($this->admin)->get('/purchasing/prices/matrix?date=2026-10-28&matrix_category=b&quality_grade=B');
        $responseB->assertOk();
        $productsB = $responseB->viewData('matrixProducts');
        $this->assertEquals(35.00, $productsB[0]['latest_approved_price']);
        $this->assertTrue($productsB[0]['prices']['2026-10-28']['is_billable']);
        $this->assertFalse($productsB[0]['prices']['2026-10-28']['is_unpriced']);

        // Resolver succeeds for Group B
        $resolvedB = $this->resolver->resolve($product, $this->shopB, '2026-10-28', ProductGrade::GradeB);
        $this->assertEquals(35.00, $resolvedB['price']);

        // Resolver must reject zero price for Group A invoice generation
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Approved daily price column 'price_a' (Shop Price Group A) for Potato Agra (Grade B) on 2026-10-28 is zero or not set.");
        $this->resolver->resolve($product, $this->shopA, '2026-10-28', ProductGrade::GradeB);
    }

    /**
     * Test 3: Correcting a price updates the matrix display and supports existing invoice pricing
     * resolution without affecting other grades or tiers.
     */
    public function test_correcting_a_price_updates_matrix_display_and_supports_invoice_pricing_resolution_without_affecting_other_grades_or_tiers(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category->id,
            'name' => 'Garlic Natti',
            'sku' => 'VEG-GAR-01',
            'unit' => 'kg',
            'base_price' => 150.00,
            'vendor_price' => 120.00,
            'is_active' => true,
        ]);

        // Grade A row exists with price_a = 220.00
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-28',
            'grade' => 'A',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 200.00,
            'price_unit' => 'kg',
            'price_a' => 220.00,
            'price_b' => 210.00,
            'price_c' => 200.00,
        ]);

        // Grade B row initially approved with 0.00
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-28',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 0.00,
            'price_unit' => 'kg',
            'price_a' => 0.00,
            'price_b' => 0.00,
            'price_c' => 0.00,
        ]);

        // Operator corrects the price via cell update endpoint
        $updateResponse = $this->actingAs($this->admin)->postJson(route('purchasing.prices.matrix.cell.update'), [
            'product_id' => $product->id,
            'date' => '2026-10-28',
            'price_category' => 'a',
            'product_grade' => 'B',
            'price' => 180.00,
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJson([
            'success' => true,
            'price_a' => 180.00,
            'is_billable' => true,
            'is_unpriced' => false,
        ]);

        // Now matrix displays billable Approved status
        $matrixResponse = $this->actingAs($this->admin)->get('/purchasing/prices/matrix?date=2026-10-28&matrix_category=a&quality_grade=B');
        $matrixResponse->assertOk();
        $matrixResponse->assertSee('cell-billable');
        $matrixResponse->assertSee('Approved & valid for billing', false);

        $products = $matrixResponse->viewData('matrixProducts');
        $this->assertEquals(180.00, $products[0]['latest_approved_price']);
        $this->assertTrue($products[0]['prices']['2026-10-28']['is_billable']);
        $this->assertFalse($products[0]['prices']['2026-10-28']['is_unpriced']);

        // Resolver now resolves successfully for Shop Price Group A
        $resolvedB = $this->resolver->resolve($product, $this->shopA, '2026-10-28', ProductGrade::GradeB);
        $this->assertEquals(180.00, $resolvedB['price']);

        // Grade A price remains untouched and intact at 220.00
        $resolvedA = $this->resolver->resolve($product, $this->shopA, '2026-10-28', ProductGrade::GradeA);
        $this->assertEquals(220.00, $resolvedA['price']);
    }
}
