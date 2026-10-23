<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Pricing\GradeSellingPriceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class PurchaserDailyPricesGradeABCManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Category $category;

    private ShopPriceGroup $groupA;

    private Shop $shopA;

    private GradeSellingPriceService $gradePriceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('purchase', 'web');
        Role::findOrCreate('purchaser', 'web');

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

        $this->shopA = Shop::factory()->create([
            'name' => 'Shop A',
            'shop_price_group_id' => $this->groupA->id,
            'status' => 'active',
        ]);

        $this->gradePriceService = app(GradeSellingPriceService::class);
    }

    /**
     * 1. Grade A can still be edited and saved.
     */
    public function test_01_grade_a_selling_price_can_be_edited_and_saved(): void
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
                    'purchase_price' => 100.00,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(100.00, (float) $response->json('selling_price_a'));
        $this->assertEquals(90.00, (float) $response->json('calc_price_b'));
        $this->assertEquals(80.00, (float) $response->json('calc_price_c'));

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'A')
            ->whereDate('business_date', '2026-10-21')
            ->firstOrFail();

        $this->assertEquals(100.00, (float) $approval->price_a);

        // Grade B & C approval rows should not be created since manual override was not enabled
        $this->assertEquals(0, DailyPriceApproval::where('product_id', $product->id)->where('grade', 'B')->count());
        $this->assertEquals(0, DailyPriceApproval::where('product_id', $product->id)->where('grade', 'C')->count());
    }

    /**
     * 2. Grade B and C auto prices display correctly on the daily prices page.
     */
    public function test_02_grade_b_and_c_auto_prices_display_correctly_on_index(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 50.00,
            'vendor_price' => 50.00,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'purchase_price' => 50.00,
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));
        $response->assertOk();

        $viewProducts = $response->viewData('products');
        $item = collect($viewProducts)->firstWhere('id', $product->id);

        $this->assertNotNull($item);
        $this->assertEquals(100.00, $item['selling_price_a']);
        $this->assertEquals(90.00, $item['calc_price_b']);
        $this->assertEquals(90.00, $item['selling_price_b']);
        $this->assertFalse($item['is_manual_b']);
        $this->assertEquals(80.00, $item['calc_price_c']);
        $this->assertEquals(80.00, $item['selling_price_c']);
        $this->assertFalse($item['is_manual_c']);
    }

    /**
     * 3. Manual B toggle & save creates manual override without changing Grade C.
     */
    public function test_03_manual_b_toggle_and_save_creates_override_without_changing_c(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 50.00,
        ]);

        // Set initial Grade A price to 100.00
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'purchase_price' => 50.00,
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        // Purchaser sets manual Grade B price to 95.00
        $response = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'grade' => 'B',
                    'price' => 95.00,
                    'is_manual' => true,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(95.00, (float) $response->json('selling_price_b'));
        $this->assertTrue((bool) $response->json('is_manual_b'));
        // Grade C remains auto-calculated at 80.00
        $this->assertEquals(80.00, (float) $response->json('calc_price_c'));
        $this->assertFalse((bool) $response->json('is_manual_c'));

        // Grade B DailyProductPrice has manual_override = true
        $dppB = DailyProductPrice::query()
            ->where('product_id', $product->id)
            ->where('shop_price_group_id', $this->groupA->id)
            ->where('grade', 'B')
            ->firstOrFail();

        $this->assertEquals(95.00, (float) $dppB->selling_price);
        $this->assertTrue((bool) $dppB->manual_override);

        // Grade B DailyPriceApproval created with price_a = 95.00
        $appB = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', 'B')
            ->whereDate('business_date', '2026-10-21')
            ->firstOrFail();

        $this->assertEquals(95.00, (float) $appB->price_a);

        // Grade C approval must not exist
        $this->assertEquals(0, DailyPriceApproval::where('product_id', $product->id)->where('grade', 'C')->count());
    }

    /**
     * 4. Manual C toggle & save creates manual override without changing Grade B.
     */
    public function test_04_manual_c_toggle_and_save_creates_override_without_changing_b(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 50.00,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'purchase_price' => 50.00,
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        // Purchaser sets manual Grade C price to 75.00
        $response = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'grade' => 'C',
                    'price' => 75.00,
                    'is_manual' => true,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(75.00, (float) $response->json('selling_price_c'));
        $this->assertTrue((bool) $response->json('is_manual_c'));
        // Grade B remains auto-calculated at 90.00
        $this->assertEquals(90.00, (float) $response->json('calc_price_b'));
        $this->assertFalse((bool) $response->json('is_manual_b'));

        // Grade C DailyProductPrice has manual_override = true
        $dppC = DailyProductPrice::query()
            ->where('product_id', $product->id)
            ->where('shop_price_group_id', $this->groupA->id)
            ->where('grade', 'C')
            ->firstOrFail();

        $this->assertEquals(75.00, (float) $dppC->selling_price);
        $this->assertTrue((bool) $dppC->manual_override);

        // Grade B approval must not exist
        $this->assertEquals(0, DailyPriceApproval::where('product_id', $product->id)->where('grade', 'B')->count());
    }

    /**
     * 5. Updating Grade A recalculates non-manual B/C while preserving manual overrides.
     */
    public function test_05_updating_grade_a_recalculates_only_non_manual_grades(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 50.00,
        ]);

        // Initial Grade A price = 100.00
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'purchase_price' => 50.00,
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        // Set manual override on Grade B = 92.00 (not the default 90.00)
        $this->gradePriceService->saveManualGradePrice($product, 'B', 92.00, '2026-10-21', $this->purchaser->id);

        // Purchaser now updates Grade A from 100.00 to 200.00
        $response = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'purchase_price' => 200.00,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(200.00, (float) $response->json('selling_price_a'));

        // Grade B MUST remain manual 92.00, not overwritten by 90% of 200
        $this->assertEquals(92.00, (float) $response->json('selling_price_b'));
        $this->assertTrue((bool) $response->json('is_manual_b'));

        // Grade C was NOT manual, so its calculated price updates to 160.00 (80% of 200)
        $this->assertEquals(160.00, (float) $response->json('calc_price_c'));
        $this->assertEquals(160.00, (float) $response->json('selling_price_c'));
        $this->assertFalse((bool) $response->json('is_manual_c'));
    }

    /**
     * 6. Reset to default removes manual override and returns to calculated price.
     */
    public function test_06_reset_to_default_removes_manual_override_and_returns_to_calculated_price(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 50.00,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'purchase_price' => 50.00,
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        // Set manual override on Grade B = 95.00
        $this->gradePriceService->saveManualGradePrice($product, 'B', 95.00, '2026-10-21', $this->purchaser->id);

        // Verify manual override is active
        $dpp = DailyProductPrice::query()
            ->where('product_id', $product->id)
            ->where('shop_price_group_id', $this->groupA->id)
            ->where('grade', 'B')
            ->first();
        $this->assertTrue((bool) $dpp->manual_override);

        // Now trigger reset to default for Grade B
        $response = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $product->id,
                    'grade' => 'B',
                    'reset_to_default' => true,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertFalse((bool) $response->json('is_manual_b'));
        $this->assertEquals(90.00, (float) $response->json('selling_price_b')); // 90% of 100.00

        // In database, manual_override must be false
        $this->assertFalse((bool) $dpp->fresh()->manual_override);
    }

    /**
     * 7. Product-specific percentage takes precedence over global default.
     */
    public function test_07_product_specific_percentage_takes_precedence_over_global(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'base_price' => 50.00,
        ]);

        // Configure product-specific percentage: Grade B = 85%, Grade C = 70%
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'product_grade_percentages'],
            ['value' => json_encode([$product->id => ['B' => 85, 'C' => 70]])],
        );

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'purchase_price' => 50.00,
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));
        $response->assertOk();

        $viewProducts = $response->viewData('products');
        $item = collect($viewProducts)->firstWhere('id', $product->id);

        $this->assertEquals(85.00, $item['calc_price_b']);
        $this->assertEquals(85.00, $item['selling_price_b']);
        $this->assertEquals(85.0, $item['percentage_b']);

        $this->assertEquals(70.00, $item['calc_price_c']);
        $this->assertEquals(70.00, $item['selling_price_c']);
        $this->assertEquals(70.0, $item['percentage_c']);
    }

    /**
     * 8. Authenticated HTTP GET of actual page renders non-empty HTML with all six columns and product rows.
     */
    public function test_08_authenticated_purchaser_gets_rendered_page_with_all_six_columns_and_products(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Premium Tomato',
            'base_price' => 45.00,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'purchase_price' => 40.00,
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();

        $content = $response->getContent();
        $this->assertNotEmpty($content);

        // Verify style tag is properly closed before body markup
        $this->assertStringContainsString('</style>', $content);
        $styleOpenPos = strpos($content, '<style>');
        $styleClosePos = strpos($content, '</style>');
        $this->assertNotFalse($styleOpenPos);
        $this->assertNotFalse($styleClosePos);
        $this->assertGreaterThan($styleOpenPos, $styleClosePos);

        // Verify product name and row markup appears AFTER the style block closes
        $productPos = strpos($content, 'Premium Tomato');
        $this->assertNotFalse($productPos);
        $this->assertGreaterThan($styleClosePos, $productPos, 'Product HTML markup must not be trapped inside an unclosed <style> tag');

        // Verify all six desktop column headers appear
        $response->assertSee('PRODUCT');
        $response->assertSee('PREV (A)');
        $response->assertSee('CHANGE');
        $response->assertSee('SELLING A');
        $response->assertSee('GRADE B');
        $response->assertSee('GRADE C');

        // Verify Grade A, B, C input values and controls
        $response->assertSee('id="price-'.$product->id.'"', false);
        $response->assertSee('id="price-b-'.$product->id.'"', false);
        $response->assertSee('id="price-c-'.$product->id.'"', false);
        $response->assertSee('id="updater-b-'.$product->id.'"', false);
        $response->assertSee('id="updater-c-'.$product->id.'"', false);
        $response->assertDontSee('grade-toggle-checkbox');
        $response->assertDontSee('↺ Reset');
        $response->assertDontSee('Manual B');
        $response->assertDontSee('Manual C');
        $response->assertSee('value="100"', false); // Grade A
        $response->assertSee('value="90"', false);  // Default 90% of 100 for Grade B
        $response->assertSee('value="80"', false);  // Default 80% of 100 for Grade C
    }

    /**
     * 9. Renders gracefully without Blade exception when Grade B/C values or settings are missing.
     */
    public function test_09_page_renders_gracefully_when_grade_bc_values_or_settings_are_missing(): void
    {
        // Ensure no settings exist in database
        BusinessSetting::query()->whereIn('key', [
            'grade_b_selling_price_percentage',
            'grade_c_selling_price_percentage',
            'product_grade_percentages',
        ])->delete();

        $productWithoutPrices = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Fresh Spinach',
            'base_price' => 30.00,
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices'));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertNotEmpty($content);

        // Ensure product is rendered with empty price states and no buttons
        $response->assertSee('Fresh Spinach');
        $response->assertSee('id="price-'.$productWithoutPrices->id.'"', false);
        $response->assertSee('id="price-b-'.$productWithoutPrices->id.'"', false);
        $response->assertSee('id="price-c-'.$productWithoutPrices->id.'"', false);
        $response->assertDontSee('grade-toggle-checkbox');
        $response->assertDontSee('↺ Reset');
    }
}
