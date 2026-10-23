<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Enums\Inventory\ProductGrade;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class DailySellingPriceGradeASafetyPhase1Test extends TestCase
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
            'name' => 'Shop A',
            'shop_price_group_id' => $this->groupA->id,
            'status' => 'active',
        ]);

        $this->shopB = Shop::factory()->create([
            'name' => 'Shop B',
            'shop_price_group_id' => $this->groupB->id,
            'status' => 'active',
        ]);

        $this->shopC = Shop::factory()->create([
            'name' => 'Shop C',
            'shop_price_group_id' => $this->groupC->id,
            'status' => 'active',
        ]);

        $this->approvedDailyPriceResolver = app(ApprovedDailyPriceResolver::class);
    }

    /**
     * 1. Main Pricing saves only Grade A.
     */
    public function test_01_main_pricing_saves_only_grade_a(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 50.00,
            'vendor_price' => 50.00,
        ]);

        $response = $this->actingAs($this->admin)->post(route('purchasing.prices.update'), [
            'date' => '2026-10-21',
            'products' => [
                [
                    'product_id' => $product->id,
                    'price_a' => 110.00,
                    'price_b' => 115.00,
                    'price_c' => 120.00,
                    'purchase_price' => 50.00,
                ],
            ],
        ]);

        $response->assertRedirect();

        $approvals = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '2026-10-21')
            ->get();

        $this->assertCount(1, $approvals);
        $this->assertEquals('A', $approvals->first()->grade->value);
        $this->assertEquals(110.00, (float) $approvals->first()->price_a);
        $this->assertEquals(115.00, (float) $approvals->first()->price_b);
        $this->assertEquals(120.00, (float) $approvals->first()->price_c);

        // Grade B and C records must not exist
        $this->assertEquals(0, DailyPriceApproval::where('product_id', $product->id)->where('grade', 'B')->count());
        $this->assertEquals(0, DailyPriceApproval::where('product_id', $product->id)->where('grade', 'C')->count());
    }

    /**
     * 2. Purchaser Daily Prices saves only Grade A.
     */
    public function test_02_purchaser_daily_prices_saves_only_grade_a(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 50.00,
            'vendor_price' => 50.00,
        ]);

        $response = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'purchase_price' => 110.00,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $approvals = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '2026-10-21')
            ->get();

        $this->assertCount(1, $approvals);
        $this->assertEquals('A', $approvals->first()->grade->value);
        $this->assertEquals(110.00, (float) $approvals->first()->price_a);

        // No Grade B or C approvals created
        $this->assertEquals(0, DailyPriceApproval::where('product_id', $product->id)->where('grade', 'B')->count());
        $this->assertEquals(0, DailyPriceApproval::where('product_id', $product->id)->where('grade', 'C')->count());
    }

    /**
     * 3. Purchaser price input does not overwrite purchase cost.
     */
    public function test_03_purchaser_price_input_does_not_overwrite_purchase_cost(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 45.00,
            'vendor_price' => 45.00,
        ]);

        // Pre-create an approval with purchase_price = 45.00
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'purchase_price' => 45.00,
            'price_a' => 55.00,
            'price_b' => 55.00,
            'price_c' => 55.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // Purchaser enters 70.00 in the UI input (sent as purchase_price)
        $response = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'purchase_price' => 70.00,
                ],
            ],
        ]);

        $response->assertOk();

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'A')
            ->whereDate('business_date', '2026-10-21')
            ->firstOrFail();

        // price_a is updated to the selling price 70.00
        $this->assertEquals(70.00, (float) $approval->price_a);
        // purchase_price remains 45.00 and is NOT overwritten to 70.00
        $this->assertEquals(45.00, (float) $approval->purchase_price);
        // Vendor price on product remains 45.00
        $this->assertEquals(45.00, (float) $product->fresh()->vendor_price);
    }

    /**
     * 4. Grade B approval is never selected accidentally as Grade A.
     */
    public function test_04_grade_b_approval_is_never_selected_accidentally_as_grade_a(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 60.00,
            'vendor_price' => 50.00,
        ]);

        // Only Grade B approval exists for 2026-10-21
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'B',
            'purchase_price' => 40.00,
            'price_a' => 44.00,
            'price_b' => 44.00,
            'price_c' => 44.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // Load Purchaser Daily Prices page
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));
        $response->assertOk();

        // The product today's Grade A price should be null / not_set, NEVER 44.00
        $viewProducts = $response->viewData('products');
        $item = collect($viewProducts)->firstWhere('id', $product->id);
        $this->assertNotNull($item);
        $this->assertNull($item['price_today']);
        $this->assertEquals('not_set', $item['price_state']);
    }

    /**
     * 5. Grade C approval is never selected accidentally as Grade A.
     */
    public function test_05_grade_c_approval_is_never_selected_accidentally_as_grade_a(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 60.00,
            'vendor_price' => 50.00,
        ]);

        // Only Grade C approval exists for 2026-10-21
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'C',
            'purchase_price' => 30.00,
            'price_a' => 33.00,
            'price_b' => 33.00,
            'price_c' => 33.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // Load Main Daily Prices page
        $response = $this->actingAs($this->admin)->get(route('purchasing.prices.index', ['date' => '2026-10-21']));
        $response->assertOk();

        $productsPaginator = $response->viewData('products');
        $row = collect($productsPaginator->items())->firstWhere('product_id', $product->id);
        $this->assertNotNull($row);
        // Current Grade A price should not be marked as updated today
        $this->assertFalse($row['is_updated_today']);
        $this->assertEquals('missing', $row['approval_status']);
    }

    /**
     * 6. Grade A updates preserve existing Grade B and C active prices.
     */
    public function test_06_grade_a_updates_preserve_existing_grade_b_and_c_active_prices(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
            'vendor_price' => 90.00,
        ]);

        // Seed existing active prices for Group A: Grade A = 100, Grade B = 85, Grade C = 70
        DailyProductPrice::query()->create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->groupA->id,
            'grade' => 'A',
            'selling_price' => 100.00,
            'min_margin_percent' => 10.00,
            'is_active' => true,
        ]);

        DailyProductPrice::query()->create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->groupA->id,
            'grade' => 'B',
            'selling_price' => 85.00,
            'min_margin_percent' => 10.00,
            'is_active' => true,
        ]);

        DailyProductPrice::query()->create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->groupA->id,
            'grade' => 'C',
            'selling_price' => 70.00,
            'min_margin_percent' => 10.00,
            'is_active' => true,
        ]);

        // Admin updates Grade A to 110.00 via Main Pricing
        $this->actingAs($this->admin)->post(route('purchasing.prices.update'), [
            'date' => '2026-10-21',
            'products' => [
                [
                    'product_id' => $product->id,
                    'price_a' => 110.00,
                    'price_b' => 110.00,
                    'price_c' => 110.00,
                    'purchase_price' => 90.00,
                ],
            ],
        ]);

        // Grade A active price updated to 110.00
        $activeA = DailyProductPrice::query()
            ->where('product_id', $product->id)
            ->where('shop_price_group_id', $this->groupA->id)
            ->where('grade', 'A')
            ->firstOrFail();
        $this->assertEquals(110.00, (float) $activeA->selling_price);

        // Grade B active price MUST remain 85.00 (not overwritten to 90% or 110.00)
        $activeB = DailyProductPrice::query()
            ->where('product_id', $product->id)
            ->where('shop_price_group_id', $this->groupA->id)
            ->where('grade', 'B')
            ->firstOrFail();
        $this->assertEquals(85.00, (float) $activeB->selling_price);

        // Grade C active price MUST remain 70.00 (not overwritten to 80% or 110.00)
        $activeC = DailyProductPrice::query()
            ->where('product_id', $product->id)
            ->where('shop_price_group_id', $this->groupA->id)
            ->where('grade', 'C')
            ->firstOrFail();
        $this->assertEquals(70.00, (float) $activeC->selling_price);

        // Purchaser updates Grade A to 120.00 via Purchaser Daily Prices
        $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'purchase_price' => 120.00,
                ],
            ],
        ]);

        // Grade A becomes 120.00
        $this->assertEquals(120.00, (float) $activeA->fresh()->selling_price);
        // Grade B and C still preserved
        $this->assertEquals(85.00, (float) $activeB->fresh()->selling_price);
        $this->assertEquals(70.00, (float) $activeC->fresh()->selling_price);
    }

    /**
     * 7. Grade A updates preserve existing Grade B and C approvals.
     */
    public function test_07_grade_a_updates_preserve_existing_grade_b_and_c_approvals(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
            'vendor_price' => 90.00,
        ]);

        $appA = DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'price_a' => 100.00,
            'price_b' => 105.00,
            'price_c' => 110.00,
            'purchase_price' => 90.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $appB = DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'B',
            'price_a' => 85.00,
            'price_b' => 90.00,
            'price_c' => 95.00,
            'purchase_price' => 75.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $appC = DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'C',
            'price_a' => 70.00,
            'price_b' => 75.00,
            'price_c' => 80.00,
            'purchase_price' => 60.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // Main Pricing update
        $this->actingAs($this->admin)->post(route('purchasing.prices.update'), [
            'date' => '2026-10-21',
            'products' => [
                [
                    'product_id' => $product->id,
                    'price_a' => 115.00,
                    'price_b' => 120.00,
                    'price_c' => 125.00,
                ],
            ],
        ]);

        // Grade B and C approvals must be unchanged
        $this->assertEquals(85.00, (float) $appB->fresh()->price_a);
        $this->assertEquals(90.00, (float) $appB->fresh()->price_b);
        $this->assertEquals(70.00, (float) $appC->fresh()->price_a);
        $this->assertEquals(75.00, (float) $appC->fresh()->price_b);

        // Purchaser Daily Prices update
        $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'purchase_price' => 135.00,
                ],
            ],
        ]);

        $this->assertEquals(85.00, (float) $appB->fresh()->price_a);
        $this->assertEquals(70.00, (float) $appC->fresh()->price_a);
    }

    /**
     * 8. Manual Grade B / Group B pricing is preserved.
     */
    public function test_08_manual_grade_b_group_b_pricing_is_preserved(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
        ]);

        DailyProductPrice::query()->create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->groupB->id,
            'grade' => 'B',
            'selling_price' => 92.50,
            'min_margin_percent' => 10.00,
            'is_active' => true,
        ]);

        // Purchaser updates Grade A selling price
        $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'purchase_price' => 120.00,
                ],
            ],
        ]);

        $priceBGroupB = DailyProductPrice::query()
            ->where('product_id', $product->id)
            ->where('shop_price_group_id', $this->groupB->id)
            ->where('grade', 'B')
            ->firstOrFail();

        $this->assertEquals(92.50, (float) $priceBGroupB->selling_price);
    }

    /**
     * 9. Other customer groups remain unchanged on Purchaser update.
     */
    public function test_09_other_customer_groups_remain_unchanged_on_purchaser_update(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'price_a' => 100.00,
            'price_b' => 108.00,
            'price_c' => 115.00,
            'purchase_price' => 90.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // Purchaser submits update for price_a only (value 125.00)
        $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'purchase_price' => 125.00,
                ],
            ],
        ]);

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'A')
            ->whereDate('business_date', '2026-10-21')
            ->firstOrFail();

        $this->assertEquals(125.00, (float) $approval->price_a);
        $this->assertEquals(108.00, (float) $approval->price_b);
        $this->assertEquals(115.00, (float) $approval->price_c);
    }

    /**
     * 10. Business dates remain isolated.
     */
    public function test_10_business_dates_remain_isolated(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
        ]);

        $appOct20 = DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-20',
            'grade' => 'A',
            'price_a' => 95.00,
            'price_b' => 100.00,
            'price_c' => 105.00,
            'purchase_price' => 85.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // Update 2026-10-21 price
        $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'purchase_price' => 130.00,
                ],
            ],
        ]);

        // Oct 20 must remain 95.00
        $this->assertEquals(95.00, (float) $appOct20->fresh()->price_a);
    }

    /**
     * 11. Correct price appears in shop ordering.
     */
    public function test_11_correct_price_appears_in_shop_ordering(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'price_a' => 110.00,
            'price_b' => 115.00,
            'price_c' => 120.00,
            'purchase_price' => 90.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'B',
            'price_a' => 88.00,
            'price_b' => 92.00,
            'price_c' => 96.00,
            'purchase_price' => 75.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'C',
            'price_a' => 66.00,
            'price_b' => 70.00,
            'price_c' => 74.00,
            'purchase_price' => 55.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // Resolving Grade A for Shop A
        $resA = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, '2026-10-21', ProductGrade::GradeA);
        $this->assertEquals(110.00, $resA['price']);

        // Resolving Grade B for Shop A
        $resB = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, '2026-10-21', ProductGrade::GradeB);
        $this->assertEquals(88.00, $resB['price']);

        // Resolving Grade C for Shop A
        $resC = $this->approvedDailyPriceResolver->resolve($product, $this->shopA, '2026-10-21', ProductGrade::GradeC);
        $this->assertEquals(66.00, $resC['price']);
    }

    /**
     * 12. Invoice repricing respects quality grades.
     */
    public function test_12_invoice_repricing_respects_quality_grades(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'price_a' => 100.00,
            'price_b' => 105.00,
            'price_c' => 110.00,
            'purchase_price' => 90.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'B',
            'price_a' => 85.00,
            'price_b' => 90.00,
            'price_c' => 95.00,
            'purchase_price' => 75.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => '2026-10-21',
            'status' => 'issued',
            'delivery_status' => 'pending',
            'final_total' => 1850.00,
        ]);

        $itemA = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'unit_price' => 100.00,
            'approved_qty' => 10,
            'delivered_qty' => 10,
            'final_line_total' => 1000.00,
        ]);

        $itemB = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_grade' => 'B',
            'unit_price' => 85.00,
            'approved_qty' => 10,
            'delivered_qty' => 10,
            'final_line_total' => 850.00,
        ]);

        // Purchaser updates Grade A selling price to 120.00 and triggers repricing
        $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'refresh_invoices_only' => false,
            'prices' => [
                [
                    'product_id' => $product->id,
                    'purchase_price' => 120.00,
                ],
            ],
        ]);

        // Item A should be repriced to 120.00
        $this->assertEquals(120.00, (float) $itemA->fresh()->unit_price);
        // Item B MUST remain 85.00 (not overwritten with Grade A price 120.00)
        $this->assertEquals(85.00, (float) $itemB->fresh()->unit_price);
    }

    /**
     * 13. Finalized historical invoices remain unchanged.
     */
    public function test_13_finalized_historical_invoices_remain_unchanged(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 100.00,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'price_a' => 100.00,
            'price_b' => 105.00,
            'price_c' => 110.00,
            'purchase_price' => 90.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $finalizedInvoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => '2026-10-21',
            'status' => 'finalized',
            'finalized_at' => now(),
            'final_total' => 1000.00,
        ]);

        $item = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $finalizedInvoice->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'unit_price' => 100.00,
            'approved_qty' => 10,
            'delivered_qty' => 10,
            'final_line_total' => 1000.00,
        ]);

        // Purchaser updates price
        $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'purchase_price' => 140.00,
                ],
            ],
        ]);

        // Finalized invoice item and invoice total MUST remain unchanged
        $this->assertEquals(100.00, (float) $item->fresh()->unit_price);
        $this->assertEquals(1000.00, (float) $finalizedInvoice->fresh()->final_total);
    }

    /**
     * 14. Existing role permissions remain intact.
     */
    public function test_14_existing_role_permissions_remain_intact(): void
    {
        // Unauthenticated access redirects to login
        $this->get(route('purchasing.prices.index'))->assertRedirect(route('login'));
        $this->get(route('purchaser.daily-prices'))->assertRedirect(route('login'));

        // Admin can access both
        $this->actingAs($this->admin)->get(route('purchasing.prices.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('purchaser.daily-prices'))->assertOk();

        // Purchaser can access purchaser daily prices
        $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices'))->assertOk();
    }

    /**
     * 15. Existing approval behavior remains intact.
     */
    public function test_15_existing_approval_behavior_remains_intact(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 50.00,
            'vendor_price' => 45.00,
        ]);

        $this->actingAs($this->admin)->post(route('purchasing.prices.update'), [
            'date' => '2026-10-21',
            'products' => [
                [
                    'product_id' => $product->id,
                    'price_a' => 60.00,
                    'price_b' => 65.00,
                    'price_c' => 70.00,
                    'purchase_price' => 45.00,
                ],
            ],
        ]);

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'A')
            ->whereDate('business_date', '2026-10-21')
            ->firstOrFail();

        $this->assertEquals('approved', $approval->status);
        $this->assertEquals($this->admin->id, $approval->approved_by);
        $this->assertNotNull($approval->approved_at);
        $this->assertEquals($this->admin->id, $approval->updated_by);
    }
}
