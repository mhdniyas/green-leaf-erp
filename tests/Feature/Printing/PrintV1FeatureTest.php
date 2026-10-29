<?php

declare(strict_types=1);

namespace Tests\Feature\Printing;

use App\Models\Category;
use App\Models\Printing\PrintV1Preset;
use App\Models\Printing\PrintV1PresetBatch;
use App\Models\Printing\PrintV1Setting;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\SortSheetPreset;
use App\Models\User;
use App\Services\Printing\PrintV1MatrixBuilderService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PrintV1FeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $authorizedUser;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo(['sort.sheet.view', 'sort.sheet.export']);

        $this->unauthorizedUser = User::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('print.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_gets_redirected_or_403(): void
    {
        // Web request redirects to dashboard per App exception handler
        $response = $this->actingAs($this->unauthorizedUser)->get(route('print.dashboard'));
        $response->assertRedirect(route('dashboard'));

        // JSON / API request receives 403 Forbidden
        $jsonResponse = $this->actingAs($this->unauthorizedUser)->getJson(route('print.dashboard'));
        $jsonResponse->assertStatus(403);
    }

    public function test_authorized_user_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('print.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Printing Operations Desk');
        $response->assertSee('Sort Print');
        $response->assertSee('Selection List');
        $response->assertSee('Segregation Grid');
        $response->assertSee('Shop Wise Portrait');
    }

    public function test_sort_print_renders_clean_view_without_filter_controls(): void
    {
        $shop = Shop::factory()->create(['status' => 'active', 'warehouse_tag' => 'WH-A']);
        $category = Category::factory()->create(['name' => 'Root Veg']);
        $product = Product::factory()->create(['name' => 'Carrot', 'sku' => 'CAR-01', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 12.0,
            'approved_qty' => 12.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => '2026-10-29']));

        $response->assertStatus(200);
        $response->assertSee('Carrot (A)');
        $response->assertSee('WH-A');

        // Verify clean output: NO filter checkboxes, NO inline filter script
        $response->assertDontSee('shops-filter-panel');
        $response->assertDontSee('selectAllShops');
        $response->assertDontSee('clearAllShops');
    }

    public function test_selection_renders_clean_view(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['name' => 'Cabbage', 'sku' => 'CAB-01', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'product_grade' => 'B',
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.selection', ['date' => '2026-10-29']));

        $response->assertStatus(200);
        $response->assertSee('Selection List');
        $response->assertSee('Cabbage (B)');
        $response->assertSee('30');
    }

    public function test_segregation_grid_renders_clean_view(): void
    {
        $shop = Shop::factory()->create(['status' => 'active', 'warehouse_tag' => 'WH-TAG-7']);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['name' => 'Onion', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 45.0,
            'approved_qty' => 45.0,
            'product_grade' => 'U',
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.segregation-grid', ['date' => '2026-10-29']));

        $response->assertStatus(200);
        $response->assertSee('Segregation Grid');
        $response->assertSee('Onion (Mixed)');
        $response->assertSee('WH-TAG-7');
    }

    public function test_shop_wise_portrait_renders_clean_view(): void
    {
        $shop = Shop::factory()->create(['status' => 'active', 'name' => 'Kochi Supermart']);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['name' => 'Potato', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 50.0,
            'approved_qty' => 50.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.shop-wise-portrait', ['date' => '2026-10-29']));

        $response->assertStatus(200);
        $response->assertSee('Kochi Supermart');
        $response->assertSee('Potato (A)');
        $response->assertSee('50');
    }

    public function test_settings_can_be_updated_independently(): void
    {
        // 1. Update Sort Print Settings
        $response = $this->actingAs($this->authorizedUser)
            ->put(route('print.settings.section.update', 'sort-sheet'), [
                'grades' => ['A', 'B'],
                'skip_empty_shops' => 1,
                'code_sort' => 1,
                'show_category_titles' => 1,
            ]);

        $response->assertRedirect(route('print.settings.index', ['tab' => 'sort-sheet']));
        $this->assertDatabaseHas('print_v1_settings', [
            'section' => 'sort-sheet',
            'user_id' => $this->authorizedUser->id,
        ]);

        $settings = PrintV1Setting::getForSection('sort-sheet', $this->authorizedUser->id);
        $this->assertEquals(['A', 'B'], $settings['grades']);
        $this->assertTrue($settings['skip_empty_shops']);

        // 2. Update Shop Settings
        $shop = Shop::factory()->create(['status' => 'inactive']);
        $responseShop = $this->actingAs($this->authorizedUser)
            ->put(route('print.settings.shops.update'), [
                'included_inactive_shop_ids' => [$shop->id],
                'default_skip_empty_shops' => 1,
            ]);

        $responseShop->assertRedirect(route('print.settings.index', ['tab' => 'shops']));
        $shopSettings = PrintV1Setting::getForSection('shops', $this->authorizedUser->id);
        $this->assertEquals([$shop->id], $shopSettings['included_inactive_shop_ids']);
    }

    public function test_preset_lifecycle_create_read_and_delete(): void
    {
        // Create preset
        $response = $this->actingAs($this->authorizedUser)
            ->post(route('print.settings.presets.store'), [
                'name' => 'Vegetables Morning Run',
                'section' => 'sort-sheet',
                'settings' => [
                    'grades' => ['A', 'B'],
                    'skip_empty_shops' => true,
                ],
            ]);

        $response->assertRedirect(route('print.settings.index', ['tab' => 'presets']));

        $preset = PrintV1Preset::where('name', 'Vegetables Morning Run')->first();
        $this->assertNotNull($preset);
        $this->assertNotEmpty($preset->uuid);

        // Delete preset
        $deleteResponse = $this->actingAs($this->authorizedUser)
            ->delete(route('print.settings.presets.destroy', $preset));

        $deleteResponse->assertRedirect(route('print.settings.index', ['tab' => 'presets']));
        $this->assertDatabaseMissing('print_v1_presets', ['id' => $preset->id]);
    }

    public function test_preset_batch_lifecycle(): void
    {
        $preset1 = PrintV1Preset::create([
            'user_id' => $this->authorizedUser->id,
            'name' => 'Preset 1',
            'section' => 'sort-sheet',
            'settings' => ['grades' => ['A']],
        ]);

        $preset2 = PrintV1Preset::create([
            'user_id' => $this->authorizedUser->id,
            'name' => 'Preset 2',
            'section' => 'sort-sheet',
            'settings' => ['grades' => ['B']],
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('print.settings.batches.store'), [
                'name' => 'Daily Morning Batch',
                'section' => 'sort-sheet',
                'preset_ids' => [$preset1->uuid, $preset2->uuid],
            ]);

        $response->assertRedirect(route('print.settings.index', ['tab' => 'batches']));

        $batch = PrintV1PresetBatch::where('name', 'Daily Morning Batch')->first();
        $this->assertNotNull($batch);

        // Test running batch print
        $batchPrintResponse = $this->actingAs($this->authorizedUser)
            ->get(route('print.batch-print', ['batch_id' => $batch->uuid, 'date' => '2026-10-29']));

        $batchPrintResponse->assertStatus(200);
        $batchPrintResponse->assertSee('Preset 1');
        $batchPrintResponse->assertSee('Preset 2');

        // Delete batch
        $deleteBatchResponse = $this->actingAs($this->authorizedUser)
            ->delete(route('print.settings.batches.destroy', $batch));

        $deleteBatchResponse->assertRedirect(route('print.settings.index', ['tab' => 'batches']));
        $this->assertDatabaseMissing('print_v1_preset_batches', ['id' => $batch->id]);
    }

    public function test_copy_legacy_presets_imports_without_modifying_legacy(): void
    {
        // Create a legacy preset
        $legacy = SortSheetPreset::create([
            'user_id' => $this->authorizedUser->id,
            'name' => 'Legacy Veggie Preset',
            'surface' => 'sort-sheet',
            'category_ids' => [1, 2],
            'separate_category_pages' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('print.settings.presets.copy-legacy'));

        $response->assertRedirect(route('print.settings.index', ['tab' => 'presets']));

        // Verify V1 preset created
        $v1Preset = PrintV1Preset::where('name', 'Legacy Veggie Preset (Imported)')->first();
        $this->assertNotNull($v1Preset);
        $this->assertEquals('sort-sheet', $v1Preset->section);
        $this->assertEquals([1, 2], $v1Preset->settings['category_ids']);

        // Verify legacy record is completely untouched
        $this->assertDatabaseHas('sort_sheet_presets', [
            'id' => $legacy->id,
            'name' => 'Legacy Veggie Preset',
        ]);
    }

    public function test_sidebar_renders_printing_v1_under_print_dashboard(): void
    {
        // 1. In printing layout (sort-sheet.index)
        $responsePrinting = $this->actingAs($this->authorizedUser)
            ->get(route('sort-sheet.index'));

        $responsePrinting->assertStatus(200);
        $responsePrinting->assertSee('Printing V1');
        $responsePrinting->assertSee(route('print.dashboard'));

        // 2. In main app layout
        auth()->login($this->authorizedUser);
        $viewApp = view('components.layouts.app', ['slot' => new HtmlString('Content')])
            ->with('errors', new ViewErrorBag)
            ->render();

        $this->assertStringContainsString('Print Dashboard', $viewApp);
        $this->assertStringContainsString('Printing V1', $viewApp);
        $this->assertStringContainsString(route('print.dashboard'), $viewApp);

        // 3. In admin workspace layout
        $viewAdmin = view('components.layouts.admin', ['slot' => new HtmlString('Content')])
            ->with('errors', new ViewErrorBag)
            ->render();

        $this->assertStringContainsString('Print Dashboard', $viewAdmin);
        $this->assertStringContainsString('Printing V1', $viewAdmin);
        $this->assertStringContainsString(route('print.dashboard'), $viewAdmin);
    }

    public function test_sidebar_does_not_render_printing_links_for_unauthorized_user(): void
    {
        auth()->login($this->unauthorizedUser);

        $viewApp = view('components.layouts.app', ['slot' => new HtmlString('Content')])
            ->with('errors', new ViewErrorBag)
            ->render();

        $this->assertStringNotContainsString(route('print.dashboard'), $viewApp);
        $this->assertStringNotContainsString(route('sort-sheet.index'), $viewApp);

        $viewAdmin = view('components.layouts.admin', ['slot' => new HtmlString('Content')])
            ->with('errors', new ViewErrorBag)
            ->render();

        $this->assertStringNotContainsString(route('print.dashboard'), $viewAdmin);
        $this->assertStringNotContainsString(route('sort-sheet.index'), $viewAdmin);
    }

    public function test_settings_page_loads_all_five_sections_and_renders_product_dropdown(): void
    {
        $category = Category::factory()->create(['name' => 'Leafy Greens', 'is_active' => true]);
        $activeProduct = Product::factory()->create([
            'name' => 'Spinach',
            'sku' => 'SPN-01',
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        $inactiveProduct = Product::factory()->create([
            'name' => 'Old Lettuce',
            'sku' => 'LET-OLD',
            'category_id' => $category->id,
            'is_active' => false,
        ]);
        $deletedProduct = Product::factory()->create([
            'name' => 'Deleted Kale',
            'sku' => 'KLE-DEL',
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        $deletedProduct->delete();

        // 1. Tab 1: Sort Print (Regression test for `products.status` Unknown Column error)
        $responseSort = $this->actingAs($this->authorizedUser)
            ->get(route('print.settings.index', ['tab' => 'sort-sheet']));
        $responseSort->assertStatus(200);
        $responseSort->assertSee('Sort Print Configuration');
        $responseSort->assertSee('Spinach');
        $responseSort->assertSee('Leafy Greens');
        $responseSort->assertDontSee('Old Lettuce');
        $responseSort->assertDontSee('Deleted Kale');

        // 2. Tab 2: Selection
        $responseSelection = $this->actingAs($this->authorizedUser)
            ->get(route('print.settings.index', ['tab' => 'selection']));
        $responseSelection->assertStatus(200);
        $responseSelection->assertSee('Selection List Configuration');
        $responseSelection->assertSee('Spinach');

        // 3. Tab 3: Segregation Grid
        $responseGrid = $this->actingAs($this->authorizedUser)
            ->get(route('print.settings.index', ['tab' => 'segregation-grid']));
        $responseGrid->assertStatus(200);
        $responseGrid->assertSee('Segregation Grid Configuration');
        $responseGrid->assertSee('Spinach');

        // 4. Tab 4: Shop Wise Portrait
        $responsePortrait = $this->actingAs($this->authorizedUser)
            ->get(route('print.settings.index', ['tab' => 'shop-wise-portrait']));
        $responsePortrait->assertStatus(200);
        $responsePortrait->assertSee('Shop Wise Portrait Configuration');
        $responsePortrait->assertSee('Spinach');

        // 5. Tab 5: Shop Printing Configuration
        $responseShops = $this->actingAs($this->authorizedUser)
            ->get(route('print.settings.index', ['tab' => 'shops']));
        $responseShops->assertStatus(200);
        $responseShops->assertSee('Shop Printing Configuration');
    }

    public function test_settings_loads_cleanly_when_no_eligible_products_exist(): void
    {
        Product::query()->forceDelete();

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.settings.index', ['tab' => 'sort-sheet']));

        $response->assertStatus(200);
        $response->assertSee('No active products found');
    }

    public function test_settings_category_and_product_filtering_persists_and_reloads(): void
    {
        $cat1 = Category::factory()->create(['name' => 'Fruits', 'is_active' => true]);
        $cat2 = Category::factory()->create(['name' => 'Vegetables', 'is_active' => true]);
        $p1 = Product::factory()->create(['name' => 'Apple', 'category_id' => $cat1->id, 'is_active' => true]);
        $p2 = Product::factory()->create(['name' => 'Tomato', 'category_id' => $cat2->id, 'is_active' => true]);

        $response = $this->actingAs($this->authorizedUser)
            ->put(route('print.settings.section.update', 'sort-sheet'), [
                'grades' => ['A', 'B'],
                'category_ids' => [$cat1->id],
                'product_ids' => [$p1->id],
                'skip_empty_shops' => 1,
            ]);

        $response->assertRedirect(route('print.settings.index', ['tab' => 'sort-sheet']));

        $saved = PrintV1Setting::getForSection('sort-sheet', $this->authorizedUser->id);
        $this->assertEquals([$cat1->id], $saved['category_ids']);
        $this->assertEquals([$p1->id], $saved['product_ids']);

        // Verify reloaded page shows saved selections
        $reloadResponse = $this->actingAs($this->authorizedUser)
            ->get(route('print.settings.index', ['tab' => 'sort-sheet']));
        $reloadResponse->assertStatus(200);
        $reloadResponse->assertSee('Apple');
    }

    public function test_matrix_builder_honors_category_and_product_filtering(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $catIncluded = Category::factory()->create(['name' => 'Included Cat', 'is_active' => true]);
        $catExcluded = Category::factory()->create(['name' => 'Excluded Cat', 'is_active' => true]);

        $productIncluded = Product::factory()->create([
            'name' => 'Green Beans',
            'category_id' => $catIncluded->id,
            'is_active' => true,
        ]);
        $productExcluded = Product::factory()->create([
            'name' => 'Red Apples',
            'category_id' => $catExcluded->id,
            'is_active' => true,
        ]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $productIncluded->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $productExcluded->id,
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        // Filter by category_id
        $matrixBuilder = app(PrintV1MatrixBuilderService::class);
        $result = $matrixBuilder->build('sort-sheet', '2026-10-29', [
            'category_ids' => [$catIncluded->id],
            'product_ids' => [$productIncluded->id],
            'grades' => ['A'],
        ], $this->authorizedUser->id);

        $this->assertArrayHasKey("{$productIncluded->id}:A", $result->matrix);
        $this->assertArrayNotHasKey("{$productExcluded->id}:A", $result->matrix);
    }

    public function test_settings_boolean_checkbox_persistence_and_reloads_true_and_false_transitions(): void
    {
        // 1. Transition to FALSE (uncheck all 3 settings)
        $responseOff = $this->actingAs($this->authorizedUser)
            ->put(route('print.settings.section.update', 'sort-sheet'), [
                'grades' => ['A', 'B'],
                'skip_empty_shops' => 0,
                'code_sort' => 0,
                'show_category_titles' => 0,
            ]);

        $responseOff->assertRedirect(route('print.settings.index', ['tab' => 'sort-sheet']));

        $savedOff = PrintV1Setting::getForSection('sort-sheet', $this->authorizedUser->id);
        $this->assertFalse($savedOff['skip_empty_shops']);
        $this->assertFalse($savedOff['code_sort']);
        $this->assertFalse($savedOff['show_category_titles']);

        // Verify HTML reload reflects unchecked state
        $pageOff = $this->actingAs($this->authorizedUser)
            ->get(route('print.settings.index', ['tab' => 'sort-sheet']));
        $pageOff->assertStatus(200);

        // Check that checkboxes do not have checked attribute
        $contentOff = $pageOff->getContent();
        $this->assertMatchesRegularExpression('/name="skip_empty_shops"\s+value="1"\s+class/', $contentOff);
        $this->assertMatchesRegularExpression('/name="code_sort"\s+value="1"\s+class/', $contentOff);
        $this->assertMatchesRegularExpression('/name="show_category_titles"\s+value="1"\s+class/', $contentOff);

        // 2. Transition to TRUE (check all 3 settings)
        $responseOn = $this->actingAs($this->authorizedUser)
            ->put(route('print.settings.section.update', 'sort-sheet'), [
                'grades' => ['A', 'B'],
                'skip_empty_shops' => 1,
                'code_sort' => 1,
                'show_category_titles' => 1,
            ]);

        $responseOn->assertRedirect(route('print.settings.index', ['tab' => 'sort-sheet']));

        $savedOn = PrintV1Setting::getForSection('sort-sheet', $this->authorizedUser->id);
        $this->assertTrue($savedOn['skip_empty_shops']);
        $this->assertTrue($savedOn['code_sort']);
        $this->assertTrue($savedOn['show_category_titles']);

        // Verify HTML reload reflects checked state
        $pageOn = $this->actingAs($this->authorizedUser)
            ->get(route('print.settings.index', ['tab' => 'sort-sheet']));
        $pageOn->assertStatus(200);
        $contentOn = $pageOn->getContent();
        $this->assertMatchesRegularExpression('/name="skip_empty_shops"\s+value="1"\s+checked/', $contentOn);
        $this->assertMatchesRegularExpression('/name="code_sort"\s+value="1"\s+checked/', $contentOn);
        $this->assertMatchesRegularExpression('/name="show_category_titles"\s+value="1"\s+checked/', $contentOn);
    }

    public function test_updating_one_setting_does_not_reset_unrelated_settings(): void
    {
        // Save initial state with code_sort = false
        $this->actingAs($this->authorizedUser)
            ->put(route('print.settings.section.update', 'sort-sheet'), [
                'grades' => ['A', 'B', 'C'],
                'code_sort' => 0,
                'show_category_titles' => 1,
                'skip_empty_shops' => 1,
            ]);

        // Update only grades
        $this->actingAs($this->authorizedUser)
            ->put(route('print.settings.section.update', 'sort-sheet'), [
                'grades' => ['A'],
            ]);

        $settings = PrintV1Setting::getForSection('sort-sheet', $this->authorizedUser->id);
        $this->assertEquals(['A'], $settings['grades']);
        // Verify unrelated code_sort remains false, not reset to default true!
        $this->assertFalse($settings['code_sort']);
        $this->assertTrue($settings['show_category_titles']);
        $this->assertTrue($settings['skip_empty_shops']);
    }

    public function test_system_default_and_user_override_precedence(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['sort.sheet.view', 'sort.sheet.export']);
        $admin->assignRole('admin');

        $regularUser = User::factory()->create();
        $regularUser->givePermissionTo(['sort.sheet.view', 'sort.sheet.export']);

        // 1. Admin saves system default with code_sort = false
        $this->actingAs($admin)->put(route('print.settings.section.update', 'sort-sheet'), [
            'code_sort' => 0,
            'save_as_system_default' => 1,
        ]);

        $systemSettings = PrintV1Setting::getForSection('sort-sheet', null);
        $this->assertFalse($systemSettings['code_sort']);

        // Regular user should inherit the system default (code_sort = false)
        $userEffective = PrintV1Setting::getForSection('sort-sheet', $regularUser->id);
        $this->assertFalse($userEffective['code_sort']);

        // 2. Regular user sets user override: code_sort = true
        $this->actingAs($regularUser)->put(route('print.settings.section.update', 'sort-sheet'), [
            'code_sort' => 1,
            'save_as_system_default' => 0,
        ]);

        $userOverridden = PrintV1Setting::getForSection('sort-sheet', $regularUser->id);
        $this->assertTrue($userOverridden['code_sort']);

        // System default should still be false
        $systemUnchanged = PrintV1Setting::getForSection('sort-sheet', null);
        $this->assertFalse($systemUnchanged['code_sort']);
    }

    public function test_category_headers_toggle_affects_print_output(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $cat = Category::factory()->create(['name' => 'Root Vegetables', 'is_active' => true]);
        $prod = Product::factory()->create(['name' => 'Beetroot', 'category_id' => $cat->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $prod->id,
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        // 1. Headers ON
        $this->actingAs($this->authorizedUser)->put(route('print.settings.section.update', 'sort-sheet'), [
            'show_category_titles' => 1,
        ]);

        $resOn = $this->actingAs($this->authorizedUser)->get(route('print.sort-sheet', ['date' => '2026-10-29']));
        $resOn->assertStatus(200);
        $resOn->assertSee('<tr class="category-header-row">', false);
        $resOn->assertSee('Root Vegetables');
        $resOn->assertSee('Beetroot (A)');

        // 2. Headers OFF
        $this->actingAs($this->authorizedUser)->put(route('print.settings.section.update', 'sort-sheet'), [
            'show_category_titles' => 0,
        ]);

        $resOff = $this->actingAs($this->authorizedUser)->get(route('print.sort-sheet', ['date' => '2026-10-29']));
        $resOff->assertStatus(200);
        $resOff->assertDontSee('<tr class="category-header-row">', false);
        $resOff->assertDontSee('Root Vegetables');
        $resOff->assertSee('Beetroot (A)');
    }

    public function test_sku_sorting_toggle_affects_matrix_and_print_output(): void
    {
        $cat = Category::factory()->create(['name' => 'Same Category', 'is_active' => true]);
        // Zucchini has smaller SKU ('001') but alphabetically later name ('Zucchini')
        $prodZucchini = Product::factory()->create(['name' => 'Zucchini', 'sku' => '001', 'category_id' => $cat->id, 'is_active' => true]);
        // Apple has larger SKU ('002') but alphabetically earlier name ('Apple')
        $prodApple = Product::factory()->create(['name' => 'Apple', 'sku' => '002', 'category_id' => $cat->id, 'is_active' => true]);

        $shop = Shop::factory()->create(['status' => 'active']);
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $prodZucchini->id,
            'requested_qty' => 1.0,
            'approved_qty' => 1.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $prodApple->id,
            'requested_qty' => 1.0,
            'approved_qty' => 1.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        $matrixBuilder = app(PrintV1MatrixBuilderService::class);

        // 1. SKU Sorting ON: Zucchini (001) must come before Apple (002)
        $resultSkuOn = $matrixBuilder->build('sort-sheet', '2026-10-29', ['code_sort' => true]);
        $matrixKeysOn = array_keys($resultSkuOn->matrix);
        $this->assertEquals("{$prodZucchini->id}:A", $matrixKeysOn[0]);
        $this->assertEquals("{$prodApple->id}:A", $matrixKeysOn[1]);

        // 2. SKU Sorting OFF: Apple ('A') must come before Zucchini ('Z')
        $resultSkuOff = $matrixBuilder->build('sort-sheet', '2026-10-29', ['code_sort' => false]);
        $matrixKeysOff = array_keys($resultSkuOff->matrix);
        $this->assertEquals("{$prodApple->id}:A", $matrixKeysOff[0]);
        $this->assertEquals("{$prodZucchini->id}:A", $matrixKeysOff[1]);
    }

    public function test_skip_empty_shops_toggle_affects_print_output(): void
    {
        $shopActiveWithOrders = Shop::factory()->create(['name' => 'Active Shop Ordered', 'status' => 'active']);
        $shopActiveEmpty = Shop::factory()->create(['name' => 'Active Shop Empty', 'status' => 'active']);

        $cat = Category::factory()->create();
        $prod = Product::factory()->create(['name' => 'Tomato', 'category_id' => $cat->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shopActiveWithOrders->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $prod->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        // 1. Skip Empty ON
        $this->actingAs($this->authorizedUser)->put(route('print.settings.section.update', 'sort-sheet'), [
            'skip_empty_shops' => 1,
        ]);
        $resOn = $this->actingAs($this->authorizedUser)->get(route('print.sort-sheet', ['date' => '2026-10-29']));
        $resOn->assertStatus(200);
        $resOn->assertSee('Active Shop Ordered');
        $resOn->assertDontSee('Active Shop Empty');

        // 2. Skip Empty OFF: empty shop must be retained
        $this->actingAs($this->authorizedUser)->put(route('print.settings.section.update', 'sort-sheet'), [
            'skip_empty_shops' => 0,
        ]);
        $resOff = $this->actingAs($this->authorizedUser)->get(route('print.sort-sheet', ['date' => '2026-10-29']));
        $resOff->assertStatus(200);
        $resOff->assertSee('Active Shop Ordered');
        $resOff->assertSee('Active Shop Empty');
    }

    public function test_preset_selection_does_not_override_persisted_settings(): void
    {
        // User saves code_sort = false
        $this->actingAs($this->authorizedUser)->put(route('print.settings.section.update', 'sort-sheet'), [
            'code_sort' => 0,
        ]);

        // Preset has code_sort = true
        $preset = PrintV1Preset::create([
            'user_id' => $this->authorizedUser->id,
            'name' => 'SKU Preset',
            'section' => 'sort-sheet',
            'settings' => ['code_sort' => true, 'grades' => ['A']],
        ]);

        // Output with preset uses preset's override
        $res = $this->actingAs($this->authorizedUser)->get(route('print.sort-sheet', [
            'date' => '2026-10-29',
            'preset' => $preset->uuid,
        ]));
        $res->assertStatus(200);

        // Verify user's persisted settings remain code_sort = false!
        $persisted = PrintV1Setting::getForSection('sort-sheet', $this->authorizedUser->id);
        $this->assertFalse($persisted['code_sort']);
    }

    public function test_sort_print_single_grade_a_shows_heading_and_no_suffix(): void
    {
        PrintV1Setting::setForSection('sort-sheet', ['grades' => ['A']], $this->authorizedUser->id);

        $shop = Shop::factory()->create(['status' => 'active', 'warehouse_tag' => 'WH-A']);
        $category = Category::factory()->create(['name' => 'Fruits']);
        $product = Product::factory()->create(['name' => 'Banana Yelakki Green', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        $res = $this->actingAs($this->authorizedUser)->get(route('print.sort-sheet', ['date' => '2026-10-29']));
        $res->assertStatus(200);

        // Grade heading is present
        $res->assertSee('GRADE A');
        // Product row shows base name without (A)
        $res->assertSee('Banana Yelakki Green');
        $res->assertDontSee('Banana Yelakki Green (A)');
    }

    public function test_selection_single_grade_b_shows_heading_and_no_suffix(): void
    {
        PrintV1Setting::setForSection('selection', ['grades' => ['B']], $this->authorizedUser->id);

        $shop = Shop::factory()->create(['status' => 'active', 'warehouse_tag' => 'WH-B']);
        $category = Category::factory()->create(['name' => 'Produce']);
        $product = Product::factory()->create(['name' => 'Coconut', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'product_grade' => 'B',
            'unit' => 'KG',
        ]);

        $res = $this->actingAs($this->authorizedUser)->get(route('print.selection', ['date' => '2026-10-29']));
        $res->assertStatus(200);

        $res->assertSee('GRADE B');
        $res->assertSee('Coconut');
        $res->assertDontSee('Coconut (B)');
    }

    public function test_segregation_grid_single_grade_c_shows_heading_and_no_suffix(): void
    {
        PrintV1Setting::setForSection('segregation-grid', ['grades' => ['C']], $this->authorizedUser->id);

        $shop = Shop::factory()->create(['status' => 'active', 'warehouse_tag' => 'WH-C']);
        $category = Category::factory()->create(['name' => 'Produce']);
        $product = Product::factory()->create(['name' => 'Peeled Garlic', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'product_grade' => 'C',
            'unit' => 'KG',
        ]);

        $res = $this->actingAs($this->authorizedUser)->get(route('print.segregation-grid', ['date' => '2026-10-29']));
        $res->assertStatus(200);

        $res->assertSee('GRADE C');
        $res->assertSee('Peeled Garlic');
        $res->assertDontSee('Peeled Garlic (C)');
    }

    public function test_shop_wise_portrait_single_grade_u_shows_heading_and_no_suffix(): void
    {
        PrintV1Setting::setForSection('shop-wise-portrait', ['grades' => ['U']], $this->authorizedUser->id);

        $shop = Shop::factory()->create(['status' => 'active', 'warehouse_tag' => 'WH-U']);
        $category = Category::factory()->create(['name' => 'Produce']);
        $product = Product::factory()->create(['name' => 'Ginger', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 8.0,
            'approved_qty' => 8.0,
            'product_grade' => 'U',
            'unit' => 'KG',
        ]);

        $res = $this->actingAs($this->authorizedUser)->get(route('print.shop-wise-portrait', ['date' => '2026-10-29']));
        $res->assertStatus(200);

        $res->assertSee('GRADE MIXED');
        $res->assertSee('Ginger');
        $res->assertDontSee('Ginger (Mixed)');
    }

    public function test_views_multiple_grades_show_combined_heading_and_individual_suffixes(): void
    {
        PrintV1Setting::setForSection('sort-sheet', ['grades' => ['A', 'B']], $this->authorizedUser->id);

        $shop = Shop::factory()->create(['status' => 'active', 'warehouse_tag' => 'WH-AB']);
        $category = Category::factory()->create(['name' => 'Fruits']);
        $product = Product::factory()->create(['name' => 'Banana Yelakki Green', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'product_grade' => 'B',
            'unit' => 'KG',
        ]);

        $res = $this->actingAs($this->authorizedUser)->get(route('print.sort-sheet', ['date' => '2026-10-29']));
        $res->assertStatus(200);

        $res->assertSee('GRADE A + B');
        $res->assertSee('Banana Yelakki Green (A)');
        $res->assertSee('Banana Yelakki Green (B)');
    }

    public function test_settings_view_renders_legacy_style_filter_components_across_all_sections(): void
    {
        $cat = Category::factory()->create(['name' => 'Leafy Greens', 'is_active' => true]);
        $prod = Product::factory()->create(['name' => 'Palak', 'sku' => 'PAL-01', 'category_id' => $cat->id, 'is_active' => true]);

        foreach (['sort-sheet', 'selection', 'segregation-grid', 'shop-wise-portrait'] as $tab) {
            $response = $this->actingAs($this->authorizedUser)
                ->get(route('print.settings.index', ['tab' => $tab]));

            $response->assertStatus(200);
            $response->assertSee('data-picker-root="categories-'.$tab.'"', false);
            $response->assertSee('data-picker-root="products-'.$tab.'"', false);
            $response->assertSee('Search categories');
            $response->assertSee('Search item code or name');
            $response->assertSee('Select all');
            $response->assertSee('Clear');
            $response->assertSee('Select visible');
            $response->assertSee('Clear products');
            $response->assertSee('Print Order:');
            $response->assertSee('printV1FilterPicker');
        }
    }

    public function test_clearing_categories_and_products_persists_empty_array_meaning_all(): void
    {
        $cat = Category::factory()->create(['name' => 'Tubers', 'is_active' => true]);
        $prod = Product::factory()->create(['name' => 'Potato', 'category_id' => $cat->id, 'is_active' => true]);

        // 1. Save specific category and product
        $this->actingAs($this->authorizedUser)
            ->put(route('print.settings.section.update', 'sort-sheet'), [
                'category_ids' => [$cat->id],
                'product_ids' => [$prod->id],
                'filter_scope_present' => 1,
            ]);

        $saved = PrintV1Setting::getForSection('sort-sheet', $this->authorizedUser->id);
        $this->assertEquals([$cat->id], $saved['category_ids']);
        $this->assertEquals([$prod->id], $saved['product_ids']);

        // 2. Submit with clear (filter_scope_present = 1, but no category_ids or product_ids)
        $this->actingAs($this->authorizedUser)
            ->put(route('print.settings.section.update', 'sort-sheet'), [
                'filter_scope_present' => 1,
            ]);

        $cleared = PrintV1Setting::getForSection('sort-sheet', $this->authorizedUser->id);
        $this->assertEquals([], $cleared['category_ids']);
        $this->assertEquals([], $cleared['product_ids']);
    }

    public function test_preset_with_category_and_product_filters_applies_to_print_output(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $cat1 = Category::factory()->create(['name' => 'Cat Alpha', 'is_active' => true]);
        $cat2 = Category::factory()->create(['name' => 'Cat Beta', 'is_active' => true]);

        $prod1 = Product::factory()->create(['name' => 'Alpha Product', 'category_id' => $cat1->id, 'is_active' => true]);
        $prod2 = Product::factory()->create(['name' => 'Beta Product', 'category_id' => $cat2->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-29',
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $prod1->id,
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $prod2->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        // Create preset scoping only to Cat Alpha and Alpha Product
        $preset = PrintV1Preset::create([
            'user_id' => $this->authorizedUser->id,
            'name' => 'Alpha Only',
            'section' => 'sort-sheet',
            'settings' => [
                'category_ids' => [$cat1->id],
                'product_ids' => [$prod1->id],
                'grades' => ['A'],
            ],
        ]);

        $res = $this->actingAs($this->authorizedUser)->get(route('print.sort-sheet', [
            'date' => '2026-10-29',
            'preset' => $preset->uuid,
        ]));

        $res->assertStatus(200);
        $res->assertSee('Alpha Product');
        $res->assertDontSee('Beta Product');
    }
}
