<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Enums\Inventory\ProductGrade;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use App\Services\Pricing\GradeSellingPriceService;
use App\Services\ShopInvoices\ShopInvoiceService;
use App\Services\ShopOrders\DeliveryPriceReadinessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class DailyPriceTierInheritanceAndPropagationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    private ShopPriceGroup $groupA;

    private ShopPriceGroup $groupB;

    private ShopPriceGroup $groupC;

    private Shop $shopA;

    private Shop $shopB;

    private Shop $shopC;

    private ApprovedDailyPriceResolver $resolver;

    private ShopInvoiceService $shopInvoiceService;

    private DeliveryPriceReadinessService $readinessService;

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
        $this->groupC = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'C'],
            ['code' => 'C', 'description' => 'Tier C', 'is_active' => true]
        );

        $this->shopA = Shop::factory()->create([
            'name' => 'Shop Tier A',
            'shop_price_group_id' => $this->groupA->id,
            'status' => 'active',
        ]);

        $this->shopB = Shop::factory()->create([
            'name' => 'Shop Tier B',
            'shop_price_group_id' => $this->groupB->id,
            'status' => 'active',
        ]);

        $this->shopC = Shop::factory()->create([
            'name' => 'Shop Tier C',
            'shop_price_group_id' => $this->groupC->id,
            'status' => 'active',
        ]);

        $this->resolver = app(ApprovedDailyPriceResolver::class);
        $this->shopInvoiceService = app(ShopInvoiceService::class);
        $this->readinessService = app(DeliveryPriceReadinessService::class);
    }

    private function createProduct(string $name = 'Tomato N', float $basePrice = 30.00): Product
    {
        return Product::query()->create([
            'category_id' => $this->category->id,
            'name' => $name,
            'sku' => 'SKU-'.strtoupper(substr(md5($name), 0, 6)),
            'unit' => 'kg',
            'base_price' => $basePrice,
            'vendor_price' => round($basePrice * 0.7, 2),
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: When previous day B/C price is 0 or null, updating Category A falls back to new Price A.
     */
    public function test_previous_bc_price_zero_or_null_falls_back_to_new_price_a(): void
    {
        $product = $this->createProduct('Baby Potato', 35.00);

        // Previous day has approved row where price_b = 0.00 and price_c = 0.00
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-28',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 20.00,
            'price_unit' => 'kg',
            'price_a' => 34.00,
            'price_b' => 0.00,
            'price_c' => 0.00,
        ]);

        // Operator updates Category A on 2026-10-29 via cell update
        $response = $this->actingAs($this->admin)->postJson(route('purchasing.prices.matrix.cell.update'), [
            'product_id' => $product->id,
            'date' => '2026-10-29',
            'price_category' => 'a',
            'product_grade' => 'B',
            'price' => 36.00,
        ]);

        $response->assertOk();

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'B')
            ->whereDate('business_date', '2026-10-29')
            ->first();

        $this->assertNotNull($approval);
        $this->assertEquals(36.00, (float) $approval->price_a);
        // Zero must NOT be inherited; must fall back to new Category A price
        $this->assertEquals(36.00, (float) $approval->price_b);
        $this->assertEquals(36.00, (float) $approval->price_c);
    }

    /**
     * Test 2: When previous day B/C price is strictly positive, it is preserved during Category A update.
     */
    public function test_previous_bc_price_positive_is_inherited_when_category_a_is_updated(): void
    {
        $product = $this->createProduct('Tomato H', 25.00);

        // Previous day had explicit positive price_b = 20.00 and price_c = 19.00
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-28',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 15.00,
            'price_unit' => 'kg',
            'price_a' => 22.00,
            'price_b' => 20.00,
            'price_c' => 19.00,
        ]);

        // Operator updates Category A on 2026-10-29
        $response = $this->actingAs($this->admin)->postJson(route('purchasing.prices.matrix.cell.update'), [
            'product_id' => $product->id,
            'date' => '2026-10-29',
            'price_category' => 'a',
            'product_grade' => 'B',
            'price' => 24.00,
        ]);

        $response->assertOk();

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'B')
            ->whereDate('business_date', '2026-10-29')
            ->first();

        $this->assertNotNull($approval);
        $this->assertEquals(24.00, (float) $approval->price_a);
        $this->assertEquals(20.00, (float) $approval->price_b);
        $this->assertEquals(19.00, (float) $approval->price_c);
    }

    /**
     * Test 3: Explicit positive Category B/C price on current day is preserved when Category A is updated.
     */
    public function test_explicit_positive_bc_price_on_current_day_is_preserved_when_category_a_is_updated(): void
    {
        $product = $this->createProduct('Onion White', 80.00);

        // Current day already has explicit B and C prices
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-29',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 45.00,
            'price_unit' => 'kg',
            'price_a' => 60.00,
            'price_b' => 52.00,
            'price_c' => 48.00,
        ]);

        // Operator updates Category A to 65.00
        $response = $this->actingAs($this->admin)->postJson(route('purchasing.prices.matrix.cell.update'), [
            'product_id' => $product->id,
            'date' => '2026-10-29',
            'price_category' => 'a',
            'product_grade' => 'B',
            'price' => 65.00,
        ]);

        $response->assertOk();

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'B')
            ->whereDate('business_date', '2026-10-29')
            ->first();

        $this->assertNotNull($approval);
        $this->assertEquals(65.00, (float) $approval->price_a);
        $this->assertEquals(52.00, (float) $approval->price_b);
        $this->assertEquals(48.00, (float) $approval->price_c);
    }

    /**
     * Test 4: Updating Category B does not overwrite Category A or C.
     */
    public function test_updating_category_b_does_not_overwrite_category_a_or_c(): void
    {
        $product = $this->createProduct('Garlic Natti', 200.00);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-29',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 120.00,
            'price_unit' => 'kg',
            'price_a' => 150.00,
            'price_b' => 140.00,
            'price_c' => 135.00,
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('purchasing.prices.matrix.cell.update'), [
            'product_id' => $product->id,
            'date' => '2026-10-29',
            'price_category' => 'b',
            'product_grade' => 'B',
            'price' => 138.00,
        ]);

        $response->assertOk();

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'B')
            ->whereDate('business_date', '2026-10-29')
            ->first();

        $this->assertNotNull($approval);
        $this->assertEquals(150.00, (float) $approval->price_a);
        $this->assertEquals(138.00, (float) $approval->price_b);
        $this->assertEquals(135.00, (float) $approval->price_c);
    }

    /**
     * Test 5: Updating Category C does not overwrite Category A or B.
     */
    public function test_updating_category_c_does_not_overwrite_category_a_or_b(): void
    {
        $product = $this->createProduct('Carrot', 50.00);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-29',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 25.00,
            'price_unit' => 'kg',
            'price_a' => 40.00,
            'price_b' => 38.00,
            'price_c' => 36.00,
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('purchasing.prices.matrix.cell.update'), [
            'product_id' => $product->id,
            'date' => '2026-10-29',
            'price_category' => 'c',
            'product_grade' => 'B',
            'price' => 34.00,
        ]);

        $response->assertOk();

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'B')
            ->whereDate('business_date', '2026-10-29')
            ->first();

        $this->assertNotNull($approval);
        $this->assertEquals(40.00, (float) $approval->price_a);
        $this->assertEquals(38.00, (float) $approval->price_b);
        $this->assertEquals(34.00, (float) $approval->price_c);
    }

    /**
     * Test 6: Bulk matrix update correctly applies fallback rule and prevents zero propagation.
     */
    public function test_bulk_matrix_update_applies_fallback_and_prevents_zero_propagation(): void
    {
        $product1 = $this->createProduct('Tomato N', 30.00);
        $product2 = $this->createProduct('Baby Potato', 35.00);

        // Previous day has zero price_b for both
        DailyPriceApproval::query()->create([
            'product_id' => $product1->id,
            'business_date' => '2026-10-28',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 20.00,
            'price_unit' => 'kg',
            'price_a' => 33.00,
            'price_b' => 0.00,
            'price_c' => 0.00,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product2->id,
            'business_date' => '2026-10-28',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 22.00,
            'price_unit' => 'kg',
            'price_a' => 34.00,
            'price_b' => 0.00,
            'price_c' => 0.00,
        ]);

        // Bulk update for Category A, Quality Grade B
        $response = $this->actingAs($this->admin)->post(route('purchasing.prices.matrix.update'), [
            'date' => '2026-10-29',
            'matrix_category' => 'a',
            'quality_grade' => 'B',
            'action' => 'update',
            'matrix_prices' => [
                $product1->id => ['2026-10-29' => '33.00'],
                $product2->id => ['2026-10-29' => '34.00'],
            ],
            'matrix_price_units' => [
                $product1->id => ['2026-10-29' => 'kg'],
                $product2->id => ['2026-10-29' => 'kg'],
            ],
        ]);

        $response->assertRedirect();

        $app1 = DailyPriceApproval::query()
            ->where('product_id', $product1->id)
            ->where('grade', 'B')
            ->whereDate('business_date', '2026-10-29')
            ->first();

        $app2 = DailyPriceApproval::query()
            ->where('product_id', $product2->id)
            ->where('grade', 'B')
            ->whereDate('business_date', '2026-10-29')
            ->first();

        $this->assertNotNull($app1);
        $this->assertEquals(33.00, (float) $app1->price_a);
        $this->assertEquals(33.00, (float) $app1->price_b);
        $this->assertEquals(33.00, (float) $app1->price_c);

        $this->assertNotNull($app2);
        $this->assertEquals(34.00, (float) $app2->price_a);
        $this->assertEquals(34.00, (float) $app2->price_b);
        $this->assertEquals(34.00, (float) $app2->price_c);
    }

    /**
     * Test 7: Required missing prices block invoice readiness and resolution.
     */
    public function test_unpriced_tier_b_blocks_invoice_readiness_and_resolution(): void
    {
        $product = $this->createProduct('Tomato N', 30.00);

        // Grade B row approved with price_b = 0.00
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-29',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 20.00,
            'price_unit' => 'kg',
            'price_a' => 33.00,
            'price_b' => 0.00,
            'price_c' => 0.00,
        ]);

        // Shop B ordering Grade B product must be rejected by resolver
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Approved daily price column 'price_b' (Shop Price Group B) for Tomato N (Grade B) on 2026-10-29 is zero or not set.");

        $this->resolver->resolve($product, $this->shopB, '2026-10-29', ProductGrade::GradeB);
    }

    /**
     * Test 8: Valid prices resolve correctly during invoice synchronization.
     */
    public function test_valid_prices_resolve_correctly_during_invoice_synchronization(): void
    {
        $product = $this->createProduct('Tomato N', 30.00);

        // Grade B row has positive prices across tiers
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-29',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 20.00,
            'price_unit' => 'kg',
            'price_a' => 33.00,
            'price_b' => 31.00,
            'price_c' => 29.00,
        ]);

        // Create Shop B Order for Grade B
        $order = ShopOrder::query()->create([
            'shop_id' => $this->shopB->id,
            'order_number' => 'RQ-20261029-TEST',
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'delivery_status' => 'ready_for_dispatch',
            'payment_status' => 'unpaid',
            'business_date' => '2026-10-29',
            'created_by' => $this->admin->id,
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'B',
            'requested_qty' => 10.00,
            'approved_qty' => 10.00,
            'loaded_qty' => 10.00,
            'actual_weight' => 10.00,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'sorting_status' => 'loaded',
            'is_sorted' => true,
        ]);

        // Invoice synchronization succeeds
        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);

        $this->assertNotNull($invoice);
        $this->assertCount(1, $invoice->items);
        $item = $invoice->items->first();
        $this->assertEquals(31.00, (float) $item->unit_price);
        $this->assertEquals('B', $item->product_grade);
        $this->assertEquals(310.00, (float) $item->final_line_total);
    }

    /**
     * Test 9: PurchaserDashboardController daily price update does not inherit zero into Tier B/C.
     */
    public function test_purchaser_dashboard_daily_price_update_does_not_inherit_zero(): void
    {
        $product = $this->createProduct('Purchaser Test Tomato', 40.00);

        // Previous day had zero B and C
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-28',
            'grade' => 'A',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 25.00,
            'price_unit' => 'kg',
            'price_a' => 45.00,
            'price_b' => 0.00,
            'price_c' => 0.00,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/purchaser/daily-prices', [
            'business_date' => '2026-10-29',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'price' => 48.00,
                    'purchase_price' => 30.00,
                ],
            ],
        ]);

        $response->assertOk();

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'A')
            ->whereDate('business_date', '2026-10-29')
            ->first();

        $this->assertNotNull($approval);
        $this->assertEquals(48.00, (float) $approval->price_a);
        $this->assertEquals(48.00, (float) $approval->price_b);
        $this->assertEquals(48.00, (float) $approval->price_c);
    }

    /**
     * Test 10: DailyPriceBoardController update does not inherit zero into Tier B/C.
     */
    public function test_daily_price_board_update_does_not_inherit_zero(): void
    {
        $product = $this->createProduct('Board Test Onion', 35.00);

        // Previous day has zero B and C
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-28',
            'grade' => 'A',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 20.00,
            'price_unit' => 'kg',
            'price_a' => 38.00,
            'price_b' => 0.00,
            'price_c' => 0.00,
        ]);

        $response = $this->actingAs($this->admin)->post(route('purchasing.prices.update'), [
            'date' => '2026-10-29',
            'products' => [
                [
                    'product_id' => $product->id,
                    'price_a' => 40.00,
                    'price_b' => null,
                    'price_c' => null,
                    'purchase_price' => 22.00,
                ],
            ],
        ]);

        $response->assertRedirect();

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'A')
            ->whereDate('business_date', '2026-10-29')
            ->first();

        $this->assertNotNull($approval);
        $this->assertEquals(40.00, (float) $approval->price_a);
        $this->assertEquals(40.00, (float) $approval->price_b);
        $this->assertEquals(40.00, (float) $approval->price_c);
    }

    /**
     * Test 11: GradeSellingPriceService manual update falls back to valid positive candidate.
     */
    public function test_grade_selling_price_service_manual_update_does_not_inherit_zero(): void
    {
        $product = $this->createProduct('Service Test Carrot', 50.00);

        // Previous day has zero B
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-28',
            'grade' => 'B',
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'purchase_price' => 25.00,
            'price_unit' => 'kg',
            'price_a' => 45.00,
            'price_b' => 0.00,
            'price_c' => 0.00,
        ]);

        $service = app(GradeSellingPriceService::class);
        $service->saveManualGradePrice($product, 'B', 46.00, '2026-10-29', $this->admin->id);

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'B')
            ->whereDate('business_date', '2026-10-29')
            ->first();

        $this->assertNotNull($approval);
        $this->assertEquals(46.00, (float) $approval->price_a);
        $this->assertEquals(46.00, (float) $approval->price_b);
        $this->assertEquals(46.00, (float) $approval->price_c);
    }

    /**
     * Test 12: All combinations of Quality Grades (A, B, C) and Shop Price Categories (A, B, C) resolve properly.
     */
    public function test_all_grade_and_shop_category_matrix_combinations(): void
    {
        $product = $this->createProduct('Universal Matrix Veg', 60.00);

        foreach (['A', 'B', 'C'] as $grade) {
            DailyPriceApproval::query()->create([
                'product_id' => $product->id,
                'business_date' => '2026-10-29',
                'grade' => $grade,
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $this->admin->id,
                'purchase_price' => 30.00,
                'price_unit' => 'kg',
                'price_a' => $grade === 'A' ? 60.00 : ($grade === 'B' ? 50.00 : 40.00),
                'price_b' => $grade === 'A' ? 55.00 : ($grade === 'B' ? 45.00 : 35.00),
                'price_c' => $grade === 'A' ? 52.00 : ($grade === 'B' ? 42.00 : 32.00),
            ]);
        }

        // Test all 9 combinations
        $expected = [
            'A' => ['A' => 60.00, 'B' => 55.00, 'C' => 52.00],
            'B' => ['A' => 50.00, 'B' => 45.00, 'C' => 42.00],
            'C' => ['A' => 40.00, 'B' => 35.00, 'C' => 32.00],
        ];

        $shops = [
            'A' => $this->shopA,
            'B' => $this->shopB,
            'C' => $this->shopC,
        ];

        foreach (['A', 'B', 'C'] as $grade) {
            $gradeEnum = ProductGrade::tryResolve($grade);
            foreach (['A', 'B', 'C'] as $category) {
                $shop = $shops[$category];
                $resolved = $this->resolver->resolve($product, $shop, '2026-10-29', $gradeEnum);
                $this->assertEquals($expected[$grade][$category], $resolved['price'], "Failed for Grade {$grade} and Shop Category {$category}");
            }
        }
    }
}
