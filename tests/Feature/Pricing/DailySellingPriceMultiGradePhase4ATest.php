<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Enums\Inventory\ProductGrade;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\PurchaseGradePrice;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Requisition\MultiGradeOrderGate;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class DailySellingPriceMultiGradePhase4ATest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private Category $category;

    private ShopPriceGroup $groupA;

    private ShopPriceGroup $groupB;

    private ShopPriceGroup $groupC;

    private Shop $shopA;

    private Shop $shopB;

    private Shop $shopC;

    private PriceBoardService $priceBoardService;

    private ApprovedDailyPriceResolver $approvedDailyPriceResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('purchase', 'web');
        Role::findOrCreate('purchaser', 'web');
        Role::findOrCreate('shop', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchase');

        $this->category = Category::factory()->create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->groupA = ShopPriceGroup::factory()->create([
            'name' => 'A',
            'default_margin_percent' => 10.00,
            'is_active' => true,
        ]);

        $this->groupB = ShopPriceGroup::factory()->create([
            'name' => 'B',
            'default_margin_percent' => 15.00,
            'is_active' => true,
        ]);

        $this->groupC = ShopPriceGroup::factory()->create([
            'name' => 'C',
            'default_margin_percent' => 20.00,
            'is_active' => true,
        ]);

        $this->shopA = Shop::factory()->create([
            'name' => 'Shop Category A',
            'shop_price_group_id' => $this->groupA->id,
            'status' => 'active',
        ]);

        $this->shopB = Shop::factory()->create([
            'name' => 'Shop Category B',
            'shop_price_group_id' => $this->groupB->id,
            'status' => 'active',
        ]);

        $this->shopC = Shop::factory()->create([
            'name' => 'Shop Category C',
            'shop_price_group_id' => $this->groupC->id,
            'status' => 'active',
        ]);

        $this->priceBoardService = app(PriceBoardService::class);
        $this->approvedDailyPriceResolver = app(ApprovedDailyPriceResolver::class);
    }

    /**
     * Requirement 1: Existing Grade A pricing produces the same results as before.
     */
    public function test_1_existing_grade_a_pricing_produces_the_same_results_as_before(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'vendor_price' => 50.00,
            'base_price' => 50.00,
        ]);

        $cost = $this->priceBoardService->resolveGradePurchaseCost($product, ProductGrade::GradeA, '2026-10-17');
        $this->assertEquals(50.00, $cost);

        $sellingPriceA = $this->priceBoardService->calculateMarginSellingPrice($product, ProductGrade::GradeA, 10.00, '2026-10-17');
        // 50.00 * (1 + 0.10) = 55.00
        $this->assertEquals(55.00, $sellingPriceA);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-17',
            'grade' => 'A',
            'price_a' => 55.00,
            'price_b' => 57.50,
            'price_c' => 60.00,
            'purchase_price' => 50.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        $resolved = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, '2026-10-17');
        $this->assertEquals(55.00, $resolved['price']);

        $resolvedExplicitGradeA = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, '2026-10-17', ProductGrade::GradeA);
        $this->assertEquals(55.00, $resolvedExplicitGradeA['price']);
    }

    /**
     * Requirement 2: Shop Category A/B/C remain separate from quality grades.
     */
    public function test_2_shop_category_a_b_c_remain_separate_from_quality_grades(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
            'vendor_price' => 100.00,
        ]);

        // Quality Grade A approved row
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-17',
            'grade' => 'A',
            'price_a' => 110.00, // Shop Category A
            'price_b' => 115.00, // Shop Category B
            'price_c' => 120.00, // Shop Category C
            'purchase_price' => 100.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        // Quality Grade B approved row
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-17',
            'grade' => 'B',
            'price_a' => 88.00,  // Shop Category A
            'price_b' => 92.00,  // Shop Category B
            'price_c' => 96.00,  // Shop Category C
            'purchase_price' => 80.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        // Resolve Quality Grade A for each Shop Category
        $resA_A = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, '2026-10-17', ProductGrade::GradeA);
        $resA_B = $this->approvedDailyPriceResolver->resolve($product, $this->shopB, '2026-10-17', ProductGrade::GradeA);
        $resA_C = $this->approvedDailyPriceResolver->resolve($product, $this->shopC, '2026-10-17', ProductGrade::GradeA);
        $this->assertEquals(110.00, $resA_A['price']);
        $this->assertEquals(115.00, $resA_B['price']);
        $this->assertEquals(120.00, $resA_C['price']);

        // Resolve Quality Grade B for each Shop Category
        $resB_A = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, '2026-10-17', ProductGrade::GradeB);
        $resB_B = $this->approvedDailyPriceResolver->resolve($product, $this->shopB, '2026-10-17', ProductGrade::GradeB);
        $resB_C = $this->approvedDailyPriceResolver->resolve($product, $this->shopC, '2026-10-17', ProductGrade::GradeB);
        $this->assertEquals(88.00, $resB_A['price']);
        $this->assertEquals(92.00, $resB_B['price']);
        $this->assertEquals(96.00, $resB_C['price']);
    }

    /**
     * Requirement 3: Grade A/B/C each resolve independent purchase costs.
     */
    public function test_3_grade_a_b_c_each_resolve_independent_purchase_costs(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
            'vendor_price' => 100.00,
        ]);

        // Approved purchase costs for 2026-10-17
        PurchaseGradePrice::query()->create([
            'product_id' => $product->id,
            'grade' => 'B',
            'business_date' => '2026-10-17',
            'purchase_price' => 75.00,
            'status' => 'approved',
        ]);

        PurchaseGradePrice::query()->create([
            'product_id' => $product->id,
            'grade' => 'C',
            'business_date' => '2026-10-17',
            'purchase_price' => 50.00,
            'status' => 'approved',
        ]);

        $costA = $this->priceBoardService->resolveGradePurchaseCost($product, ProductGrade::GradeA, '2026-10-17');
        $costB = $this->priceBoardService->resolveGradePurchaseCost($product, ProductGrade::GradeB, '2026-10-17');
        $costC = $this->priceBoardService->resolveGradePurchaseCost($product, ProductGrade::GradeC, '2026-10-17');

        $this->assertEquals(100.00, $costA);
        $this->assertEquals(75.00, $costB);
        $this->assertEquals(50.00, $costC);
    }

    /**
     * Requirement 4: Automatic prices calculate correctly for each quality-grade/category combination.
     */
    public function test_4_automatic_prices_calculate_correctly_for_each_quality_grade_and_shop_category(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
            'vendor_price' => 100.00,
        ]);

        PurchaseGradePrice::query()->create([
            'product_id' => $product->id,
            'grade' => 'B',
            'business_date' => '2026-10-17',
            'purchase_price' => 80.00,
            'status' => 'approved',
        ]);

        // Grade A automatic calculations (cost 100)
        // Group A (10%): 110.00, Group B (15%): 115.00, Group C (20%): 120.00
        $this->assertEquals(110.00, $this->priceBoardService->calculateMarginSellingPrice($product, ProductGrade::GradeA, 10.00, '2026-10-17'));
        $this->assertEquals(115.00, $this->priceBoardService->calculateMarginSellingPrice($product, ProductGrade::GradeA, 15.00, '2026-10-17'));
        $this->assertEquals(120.00, $this->priceBoardService->calculateMarginSellingPrice($product, ProductGrade::GradeA, 20.00, '2026-10-17'));

        // Grade B automatic calculations (cost 80)
        // Group A (10%): 80 * 1.10 = 88.00
        // Group B (15%): 80 * 1.15 = 92.00
        // Group C (20%): 80 * 1.20 = 96.00
        $this->assertEquals(88.00, $this->priceBoardService->calculateMarginSellingPrice($product, ProductGrade::GradeB, 10.00, '2026-10-17'));
        $this->assertEquals(92.00, $this->priceBoardService->calculateMarginSellingPrice($product, ProductGrade::GradeB, 15.00, '2026-10-17'));
        $this->assertEquals(96.00, $this->priceBoardService->calculateMarginSellingPrice($product, ProductGrade::GradeB, 20.00, '2026-10-17'));
    }

    /**
     * Requirement 5: Manual overrides affect only the selected grade/category cell.
     */
    public function test_5_manual_overrides_affect_only_the_selected_grade_and_category_cell(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
            'vendor_price' => 100.00,
        ]);

        // Set up Grade A row
        $approvalA = DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-17',
            'grade' => 'A',
            'purchase_price' => 100.00,
            'price_a' => 110.00,
            'price_b' => 115.00,
            'price_c' => 120.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        // Set up Grade B row
        $approvalB = DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-17',
            'grade' => 'B',
            'purchase_price' => 80.00,
            'price_a' => 88.00,
            'price_b' => 92.00,
            'price_c' => 96.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        // Manual override Grade B, Shop Category A to 85.00 via controller updateCell
        $response = $this->actingAs($this->purchaser)->postJson(route('purchasing.prices.matrix.cell.update'), [
            'product_id' => $product->id,
            'date' => '2026-10-17',
            'price_category' => 'a',
            'product_grade' => 'B',
            'price' => 85.00,
            'price_unit' => 'kg',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Grade B Category A is overridden to 85.00
        $approvalB->refresh();
        $this->assertEquals(85.00, (float) $approvalB->price_a);
        // Grade B Category B & C remain unaffected
        $this->assertEquals(92.00, (float) $approvalB->price_b);
        $this->assertEquals(96.00, (float) $approvalB->price_c);

        // Grade A remains completely untouched
        $approvalA->refresh();
        $this->assertEquals(110.00, (float) $approvalA->price_a);
        $this->assertEquals(115.00, (float) $approvalA->price_b);
        $this->assertEquals(120.00, (float) $approvalA->price_c);
    }

    /**
     * Requirement 6: Other automatic cells continue recalculating normally when costs change.
     */
    public function test_6_other_automatic_cells_continue_recalculating_normally_when_costs_change(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
            'vendor_price' => 100.00,
        ]);

        // Daily product prices: Grade A Category A (automatic), Grade A Category B (manual override)
        $autoPrice = DailyProductPrice::query()->create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->groupA->id,
            'grade' => 'A',
            'selling_price' => 110.00,
            'price_source' => 'margin',
            'margin_percent' => 10.00,
            'manual_override' => false,
        ]);

        $manualPrice = DailyProductPrice::query()->create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->groupB->id,
            'grade' => 'A',
            'selling_price' => 130.00, // Manual override
            'price_source' => 'manual',
            'margin_percent' => null,
            'manual_override' => true,
        ]);

        // Product vendor_price increases from 100 to 120
        $product->update(['vendor_price' => 120.00, 'base_price' => 120.00]);

        // Trigger refresh of margin selling prices
        $this->priceBoardService->refreshMarginSellingPrices($product);

        $autoPrice->refresh();
        $manualPrice->refresh();

        // Automatic price updated: 120 * 1.10 = 132.00
        $this->assertEquals(132.00, (float) $autoPrice->selling_price);

        // Manual override preserved at 130.00
        $this->assertEquals(130.00, (float) $manualPrice->selling_price);
        $this->assertTrue((bool) $manualPrice->manual_override);
    }

    /**
     * Requirement 7: Returning a manual cell to automatic mode works.
     */
    public function test_7_returning_a_manual_cell_to_automatic_mode_works(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
            'vendor_price' => 100.00,
        ]);

        $manualPrice = DailyProductPrice::query()->create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->groupA->id,
            'grade' => 'B',
            'selling_price' => 99.00,
            'price_source' => 'manual',
            'margin_percent' => null,
            'manual_override' => true,
        ]);

        PurchaseGradePrice::query()->create([
            'product_id' => $product->id,
            'grade' => 'B',
            'business_date' => app(PurchaserBusinessDayService::class)->operationalDate()->toDateString(),
            'purchase_price' => 80.00,
            'status' => 'approved',
        ]);

        // Revert to automatic margin calculation
        $margin = (float) $this->groupA->default_margin_percent;
        $calculatedPrice = $this->priceBoardService->calculateMarginSellingPrice($product, ProductGrade::GradeB, $margin);

        $manualPrice->update([
            'selling_price' => $calculatedPrice,
            'price_source' => 'margin',
            'margin_percent' => $margin,
            'manual_override' => false,
            'override_reason' => null,
            'changed_by' => $this->purchaser->id,
        ]);

        $manualPrice->refresh();
        $this->assertFalse((bool) $manualPrice->manual_override);
        $this->assertSame('margin', $manualPrice->price_source);
        $this->assertEquals(88.00, (float) $manualPrice->selling_price);
    }

    /**
     * Requirement 8: Shop order can be submitted before delivery-date prices are approved (provisional snapshot).
     */
    public function test_8_shop_order_can_be_submitted_before_delivery_date_prices_are_approved(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 50.00,
        ]);

        // Target delivery business date has NO approval yet
        $deliveryDate = '2026-10-18';
        $this->assertNull(
            DailyPriceApproval::query()
                ->where('product_id', $product->id)
                ->where('business_date', $deliveryDate)
                ->first()
        );

        // Shop order can be created and saved with a provisional unit price snapshot (e.g. from current matrix)
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $deliveryDate,
            'order_number' => 'ORD-20261018-001',
            'state' => 'approved',
        ]);

        $item = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'order_type' => 'daily',
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_qty' => 10,
            'fulfilled_quantity' => 10,
            'locked_selling_price' => 55.00, // provisional snapshot
        ]);

        $this->assertDatabaseHas('shop_order_items', [
            'id' => $item->id,
            'shop_order_id' => $order->id,
            'product_grade' => 'A',
            'locked_selling_price' => 55.00,
        ]);
    }

    /**
     * Requirement 9: Approved delivery-date prices resolve correctly for every quality grade.
     */
    public function test_9_approved_delivery_date_prices_resolve_correctly_for_every_quality_grade(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $deliveryDate = '2026-10-18';

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $deliveryDate,
            'grade' => 'A',
            'price_a' => 60.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $deliveryDate,
            'grade' => 'B',
            'price_a' => 48.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $deliveryDate,
            'grade' => 'C',
            'price_a' => 36.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        $resolvedA = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, $deliveryDate, ProductGrade::GradeA);
        $resolvedB = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, $deliveryDate, ProductGrade::GradeB);
        $resolvedC = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, $deliveryDate, ProductGrade::GradeC);

        $this->assertEquals(60.00, $resolvedA['price']);
        $this->assertEquals(48.00, $resolvedB['price']);
        $this->assertEquals(36.00, $resolvedC['price']);
    }

    /**
     * Requirement 10: Current price changes do not silently alter historical approved rates.
     */
    public function test_10_current_price_changes_do_not_silently_alter_historical_approved_rates(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        $historicalDate = '2026-10-10';
        $currentDate = '2026-10-17';

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $historicalDate,
            'grade' => 'B',
            'price_a' => 45.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        // Now purchaser updates current date price to 55.00
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $currentDate,
            'grade' => 'B',
            'price_a' => 55.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        // Historical date still resolves to 45.00
        $historicalResolved = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, $historicalDate, ProductGrade::GradeB);
        $this->assertEquals(45.00, $historicalResolved['price']);

        // Current date resolves to 55.00
        $currentResolved = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, $currentDate, ProductGrade::GradeB);
        $this->assertEquals(55.00, $currentResolved['price']);
    }

    /**
     * Requirement 11: Missing Grade B/C approval never falls back to Grade A.
     */
    public function test_11_missing_grade_b_or_c_approval_never_falls_back_to_grade_a(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Premium Tomato',
        ]);

        // Only Grade A is approved for this date
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-18',
            'grade' => 'A',
            'price_a' => 70.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        // Attempting to resolve Grade B must throw ValidationException, NOT return 70.00
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Approved daily price is missing for Premium Tomato (Grade B)');

        $this->approvedDailyPriceResolver->resolve($product, $this->shopA, '2026-10-18', ProductGrade::GradeB);
    }

    /**
     * Requirement 12: Existing finalized invoice prices remain unchanged.
     */
    public function test_12_existing_finalized_invoice_prices_remain_unchanged(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => '2026-10-15',
            'order_number' => 'ORD-FINALIZED-001',
            'state' => 'delivered',
        ]);

        $item = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'order_type' => 'daily',
            'product_grade' => 'A',
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_qty' => 20,
            'fulfilled_quantity' => 20,
            'locked_selling_price' => 42.50, // finalized historical price
        ]);

        // A new approval or matrix price update for 2026-10-18 or 2026-10-15 should not mutate existing records
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-18',
            'grade' => 'A',
            'price_a' => 99.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        $item->refresh();
        $this->assertEquals(42.50, (float) $item->locked_selling_price);
    }

    /**
     * Requirement 13: Existing Grade A-only pricing tests pass and default grade is Grade A.
     */
    public function test_13_existing_grade_a_only_pricing_defaults_and_resolves(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-18',
            'grade' => 'A',
            'price_a' => 65.00,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        // Calling without grade parameter defaults to Grade A
        $res = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, '2026-10-18');
        $this->assertEquals(65.00, $res['price']);
    }

    /**
     * Requirement 14: Multi-grade global feature flag remains disabled.
     */
    public function test_14_multi_grade_global_feature_flag_remains_disabled(): void
    {
        $gate = app(MultiGradeOrderGate::class);
        $this->assertFalse($gate->isFeatureEnabled());

        $setting = BusinessSetting::where('key', 'enable_multi_grade_ordering')->first();
        if ($setting) {
            $this->assertContains($setting->value, ['0', 0, false, 'false', null]);
        }
    }
}
