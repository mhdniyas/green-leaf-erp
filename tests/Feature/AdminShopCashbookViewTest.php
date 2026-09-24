<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookUiLayout;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProductEntry;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\ShopCashbookUiLayoutService;
use Carbon\Carbon;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShopCashbookViewTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $shopUser;

    private Shop $shop1;

    private Shop $shop2;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-15 12:00:00');

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->shop1 = Shop::query()->create([
            'name' => 'AV Casio',
            'code' => 'av-casio-casio',
            'warehouse_tag' => 'AVC',
            'status' => 'active',
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_purchasing_enabled' => true,
        ]);

        $this->shop2 = Shop::query()->create([
            'name' => 'AV Market',
            'code' => 'av-market',
            'warehouse_tag' => 'AVM',
            'status' => 'active',
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_purchasing_enabled' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->adminUser = User::factory()->create([
            'email' => 'admin@greenleaf.test',
        ]);
        $this->adminUser->assignRole('admin');

        $this->shopUser = User::factory()->create([
            'shop_id' => $this->shop1->id,
        ]);
        $this->shopUser->assignRole('shop');
    }

    public function test_financial_ledger_has_link_to_manage_shop_cashbook_layout(): void
    {
        $response = $this->actingAs($this->adminUser)->get(
            route('admin.cashbook.shop.financial-ledger', $this->shop1->code)
        );

        $response->assertOk();
        $response->assertSee('Manage Shop Cashbook Layout');
        $response->assertSee('cashbook-layout');
    }

    public function test_non_admin_cannot_access_or_mutate_cashbook_layout(): void
    {
        $response = $this->actingAs($this->shopUser)->get(
            route('admin.cashbook.shop.cashbook-layout', $this->shop1->code)
        );
        $this->assertTrue($response->isRedirection() || $response->isForbidden());

        $saveResponse = $this->actingAs($this->shopUser)->postJson(
            route('admin.cashbook.shop.cashbook-layout.save', $this->shop1->code),
            ['headers' => []]
        );
        $this->assertTrue($saveResponse->isRedirection() || $saveResponse->isForbidden());
    }

    public function test_admin_can_view_cashbook_layout_page_with_original_names_and_display_names(): void
    {
        $header = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop1->id,
            'name' => 'Daily Cash Expenditure',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $cashType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();
        $cashSetting = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)
            ->where('entry_type_id', $cashType->id)
            ->firstOrFail();
        $cashSetting->update([
            'header_group_id' => $header->id,
            'header_display_order' => 1,
        ]);

        $response = $this->actingAs($this->adminUser)->get(
            route('admin.cashbook.shop.cashbook-layout', $this->shop1->code)
        );

        $response->assertOk();
        $response->assertSee('AV Casio');
        $response->assertSee('Shop Cashbook Layout');
        $response->assertSee('Daily Cash Expenditure');
        $response->assertSee('Original Header:');
        $response->assertSee('Reset Name');
        $response->assertSee('Financial Ledger');
        $response->assertSee('Save Layout');
        $response->assertSee('Reset Layout');
        $response->assertSee('Preview Shop View');
    }

    public function test_admin_can_change_display_name_and_original_name_remains_untouched(): void
    {
        $header = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop1->id,
            'name' => 'Daily Cash Expenditure',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $payload = [
            'headers' => [
                [
                    'id' => (string) $header->id,
                    'source_id' => $header->id,
                    'original_name' => 'Daily Cash Expenditure',
                    'display_name' => 'Daily Expenses',
                    'custom_display_name' => 'Daily Expenses',
                    'type' => 'expense',
                    'setting_ids' => [],
                    'sub_headers' => [],
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.cashbook-layout.save', $this->shop1->code),
            $payload
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Real database header name MUST remain untouched
        $header->refresh();
        $this->assertEquals('Daily Cash Expenditure', $header->name);

        // UI layout service resolves the custom display name
        $service = app(ShopCashbookUiLayoutService::class);
        $settings = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)->get();
        $headerGroups = ShopLedgerHeaderGroup::where('shop_id', $this->shop1->id)->get();

        $resolved = $service->getResolvedLayout($this->shop1->id, $headerGroups, $settings);
        $this->assertTrue($resolved['is_custom_layout']);
        $this->assertEquals('Daily Expenses', $resolved['headers'][0]['display_name']);
        $this->assertEquals('Daily Cash Expenditure', $resolved['headers'][0]['original_name']);
    }

    public function test_admin_can_nest_sub_headers_under_headers(): void
    {
        $parentHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop1->id,
            'name' => 'Daily Expenses',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $purchaseHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop1->id,
            'name' => 'Purchase',
            'type' => 'expense',
            'display_order' => 2,
            'enabled' => true,
        ]);

        $otherHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop1->id,
            'name' => 'Expenses',
            'type' => 'expense',
            'display_order' => 3,
            'enabled' => true,
        ]);

        $payload = [
            'headers' => [
                [
                    'id' => (string) $parentHeader->id,
                    'source_id' => $parentHeader->id,
                    'original_name' => 'Daily Expenses',
                    'display_name' => 'Daily Expenses',
                    'custom_display_name' => null,
                    'type' => 'expense',
                    'setting_ids' => [],
                    'sub_headers' => [
                        [
                            'id' => (string) $purchaseHeader->id,
                            'source_id' => $purchaseHeader->id,
                            'original_name' => 'Purchase',
                            'display_name' => 'Purchase',
                            'custom_display_name' => null,
                            'type' => 'expense',
                            'setting_ids' => [],
                        ],
                        [
                            'id' => (string) $otherHeader->id,
                            'source_id' => $otherHeader->id,
                            'original_name' => 'Expenses',
                            'display_name' => 'Other Expenses',
                            'custom_display_name' => 'Other Expenses',
                            'type' => 'expense',
                            'setting_ids' => [],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.cashbook-layout.save', $this->shop1->code),
            $payload
        );

        $response->assertOk();

        // Check resolved structure
        $service = app(ShopCashbookUiLayoutService::class);
        $settings = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)->get();
        $headerGroups = ShopLedgerHeaderGroup::where('shop_id', $this->shop1->id)->get();

        $resolved = $service->getResolvedLayout($this->shop1->id, $headerGroups, $settings);
        $root = $resolved['headers'][0];
        $this->assertEquals('Daily Expenses', $root['display_name']);
        $this->assertCount(2, $root['sub_headers']);
        $this->assertEquals('Purchase', $root['sub_headers'][0]['display_name']);
        $this->assertEquals('Other Expenses', $root['sub_headers'][1]['display_name']);
        $this->assertEquals('Expenses', $root['sub_headers'][1]['original_name']);
    }

    public function test_admin_can_reset_layout_to_default(): void
    {
        // Save custom layout first
        ShopCashbookUiLayout::create([
            'shop_id' => $this->shop1->id,
            'layout_data' => ['headers' => []],
        ]);

        $this->assertDatabaseHas('shop_cashbook_ui_layouts', [
            'shop_id' => $this->shop1->id,
        ]);

        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.cashbook-layout.reset', $this->shop1->code)
        );

        $response->assertOk();
        $this->assertDatabaseMissing('shop_cashbook_ui_layouts', [
            'shop_id' => $this->shop1->id,
        ]);
    }

    public function test_shop_owner_cashbook_view_reflects_saved_custom_layout(): void
    {
        $salesHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop1->id,
            'name' => 'Daily Sales Source',
            'type' => 'income',
            'display_order' => 1,
            'enabled' => true,
        ]);

        ShopCashbookUiLayout::create([
            'shop_id' => $this->shop1->id,
            'layout_data' => [
                'headers' => [
                    [
                        'id' => (string) $salesHeader->id,
                        'source_id' => $salesHeader->id,
                        'original_name' => 'Daily Sales Source',
                        'display_name' => 'Store Collections',
                        'custom_display_name' => 'Store Collections',
                        'type' => 'income',
                        'setting_ids' => [],
                        'sub_headers' => [],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($this->shopUser)->get(
            route('shop-owner.cashbook.show')
        );

        $response->assertOk();
        $response->assertSee('Store Collections');
    }

    public function test_saving_layout_does_not_alter_financial_data_or_settings(): void
    {
        $entryType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop1->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $entryType->id,
            'amount' => 15000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'affects_income' => true,
            'affects_expense' => false,
            'affects_pl' => true,
            'pl_delta' => 15000.00,
            'settlement_delta' => 15000.00,
            'settlement_direction' => 'add',
            'status' => 'posted',
        ]);

        app(BalanceCalculator::class)->recalculate($this->shop1->id, '2026-09-15');

        $header = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop1->id,
            'name' => 'Sales',
            'type' => 'income',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)->where('entry_type_id', $entryType->id)->firstOrFail();
        $origGroupId = $setting->header_group_id;

        // Admin saves layout
        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.cashbook-layout.save', $this->shop1->code),
            [
                'headers' => [
                    [
                        'id' => (string) $header->id,
                        'source_id' => $header->id,
                        'original_name' => 'Sales',
                        'display_name' => 'Retail Sales',
                        'custom_display_name' => 'Retail Sales',
                        'type' => 'income',
                        'setting_ids' => [$setting->id],
                        'sub_headers' => [],
                    ],
                ],
            ]
        );

        $response->assertOk();

        // Verify transaction unchanged
        $tx->refresh();
        $this->assertEquals(15000.00, (float) $tx->amount);
        $this->assertEquals(15000.00, (float) $tx->pl_delta);
        $this->assertEquals(15000.00, (float) $tx->settlement_delta);
        $this->assertEquals('posted', $tx->status);

        // Verify snapshot unchanged
        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->shop1->id)
            ->where('business_date', '2026-09-15')
            ->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals(15000.00, (float) $snapshot->total_sales);

        // Verify DB header group name unchanged
        $header->refresh();
        $this->assertEquals('Sales', $header->name);

        // Verify setting DB record unchanged
        $setting->refresh();
        $this->assertEquals($origGroupId, $setting->header_group_id);
    }

    public function test_layout_resolver_includes_products_for_product_tagged_headers_and_sub_headers(): void
    {
        $category = Category::create(['name' => 'Vegetables']);
        $product1 = Product::create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'category_id' => $category->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);
        $product2 = Product::create([
            'name' => 'Onion',
            'sku' => 'ONI-001',
            'category_id' => $category->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $header = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop1->id,
            'name' => 'Purchase',
            'type' => 'expense',
            'product_tagging_enabled' => true,
            'display_order' => 1,
            'enabled' => true,
        ]);

        $service = app(ShopCashbookUiLayoutService::class);
        $settings = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)->get();
        $headerGroups = ShopLedgerHeaderGroup::where('shop_id', $this->shop1->id)->get();

        $resolved = $service->getResolvedLayout($this->shop1->id, $headerGroups, $settings);
        $purchaseSection = collect($resolved['headers'])->firstWhere('id', (string) $header->id);

        $this->assertNotNull($purchaseSection);
        $this->assertTrue($purchaseSection['product_tagging_enabled']);
        $this->assertNotEmpty($purchaseSection['products']);
        $productNames = collect($purchaseSection['products'])->pluck('name')->all();
        $this->assertContains('Tomato', $productNames);
        $this->assertContains('Onion', $productNames);
    }

    public function test_dynamic_product_auto_merge_and_stale_product_pruning(): void
    {
        $category = Category::create(['name' => 'Vegetables']);
        $product1 = Product::create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'category_id' => $category->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $header = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop1->id,
            'name' => 'Purchase',
            'type' => 'expense',
            'product_tagging_enabled' => true,
            'display_order' => 1,
            'enabled' => true,
        ]);

        // Save layout with product1 and a deleted product 99999
        $payload = [
            'headers' => [
                [
                    'id' => (string) $header->id,
                    'source_id' => $header->id,
                    'original_name' => 'Purchase',
                    'display_name' => 'Store Purchases',
                    'custom_display_name' => 'Store Purchases',
                    'type' => 'expense',
                    'product_tagging_enabled' => true,
                    'setting_ids' => [],
                    'product_ids' => [$product1->id, 99999],
                    'sub_headers' => [],
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.cashbook-layout.save', $this->shop1->code),
            $payload
        );
        $response->assertOk();

        // Now a new product is added to the system
        $product2 = Product::create([
            'name' => 'Banana',
            'sku' => 'BAN-001',
            'category_id' => $category->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $service = app(ShopCashbookUiLayoutService::class);
        $settings = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)->get();
        $headerGroups = ShopLedgerHeaderGroup::where('shop_id', $this->shop1->id)->get();

        $resolved = $service->getResolvedLayout($this->shop1->id, $headerGroups, $settings);
        $purchase = $resolved['headers'][0];

        $productIds = collect($purchase['products'])->pluck('id')->all();
        // product1 is present
        $this->assertContains($product1->id, $productIds);
        // product2 was auto-merged
        $this->assertContains($product2->id, $productIds);
        // stale product 99999 was pruned
        $this->assertNotContains(99999, $productIds);
    }

    public function test_financial_ledger_displays_product_ledger_breakdown(): void
    {
        $category = Category::create(['name' => 'Vegetables']);
        $product = Product::create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'category_id' => $category->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $header = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop1->id,
            'name' => 'Purchase',
            'type' => 'expense',
            'product_tagging_enabled' => true,
            'display_order' => 1,
            'enabled' => true,
        ]);

        ShopLedgerProductEntry::create([
            'shop_id' => $this->shop1->id,
            'header_group_id' => $header->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'business_date' => '2026-09-15',
            'quantity' => 25.5,
            'unit' => 'kg',
            'amount' => 1275.00,
            'entered_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get(
            route('admin.cashbook.shop.financial-ledger', ['shop' => $this->shop1->code, 'date' => '2026-09-15'])
        );

        $response->assertOk();
        $response->assertSee('Product Ledger Daily Breakdown');
        $response->assertSee('Tomato');
        $response->assertSee('TOM-001');
        $response->assertSee('25.50');
        $response->assertSee('1,275.00');
    }

    public function test_shop_owner_cashbook_page_renders_with_valid_javascript(): void
    {
        $response = $this->actingAs($this->shopUser)->get(
            route('shop-owner.cashbook.show', ['date' => '2026-09-15'])
        );

        $response->assertOk();
        $content = $response->getContent();

        preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $idx => $scriptContent) {
            if (trim($scriptContent) === '') {
                continue;
            }
            $tmpFile = tempnam(sys_get_temp_dir(), 'js_test_').'.js';
            file_put_contents($tmpFile, $scriptContent);

            $output = [];
            $returnVar = 0;
            exec('node -c '.escapeshellarg($tmpFile).' 2>&1', $output, $returnVar);
            $err = implode("\n", $output);
            @unlink($tmpFile);

            $this->assertSame(0, $returnVar, "JavaScript syntax error in script block #{$idx}:\n{$err}\nScript:\n".substr($scriptContent, 0, 500));
        }
    }
}
