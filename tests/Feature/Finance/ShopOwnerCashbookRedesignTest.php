<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProductEntry;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShopOwnerCashbookRedesignTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Permission::findOrCreate('sales.order.create');

        $this->shop = Shop::factory()->create([
            'name' => 'Casio Veg Market',
            'code' => 'CASIO-SO-01',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->owner = User::factory()->create([
            'email' => 'owner@casio.com',
            'shop_id' => $this->shop->id,
        ]);
        $this->owner->assignRole('shop');
        $this->owner->givePermissionTo('sales.order.create');

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_shop_owner_cashbook_renders_direct_header_sections_without_top_level_income_or_expense_headings(): void
    {
        $salesHeader = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'SALES',
            'type' => 'income',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $expenseHeader = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'SHOP EXPENSES',
            'type' => 'expense',
            'display_order' => 2,
            'enabled' => true,
        ]);

        $salesType = LedgerEntryType::firstOrCreate(['code' => 'cash_sales_so'], ['name' => 'Cash Sales', 'category' => 'income', 'active' => true]);
        $rentType = LedgerEntryType::firstOrCreate(['code' => 'rent_exp_so'], ['name' => 'Rent Expense', 'category' => 'expense', 'active' => true]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'header_group_id' => $salesHeader->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'header_group_id' => $expenseHeader->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->get(route('shop-owner.cashbook.show', ['date' => '2026-09-04']));

        $response->assertOk();
        $response->assertSee('SALES');
        $response->assertSee('SHOP EXPENSES');
        $response->assertSee('Cash Sales');
        $response->assertSee('Rent Expense');

        // DO NOT show top-level INCOME or EXPENSE headings in Shop Owner view
        $response->assertDontSee('<h2 class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-950">INCOME</h2>', false);
        $response->assertDontSee('<h2 class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-950">EXPENSE</h2>', false);
    }

    public function test_shop_owner_cashbook_submits_entries_to_bulk_record_endpoint(): void
    {
        $salesType = LedgerEntryType::firstOrCreate(['code' => 'cash_sales_bulk'], ['name' => 'Cash Sales Bulk', 'category' => 'income', 'active' => true]);

        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->postJson(route('shop-owner.cashbook.api.bulk-record-entries'), [
                'business_date' => '2026-09-04',
                'entries' => [
                    [
                        'entry_type_code' => 'cash_sales_bulk',
                        'amount' => 15000.50,
                        'funding_source' => 'sales',
                        'notes' => 'Bulk entry test',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-04',
            'entry_type_id' => $salesType->id,
            'amount' => 15000.50,
            'notes' => 'Bulk entry test',
        ]);
    }

    public function test_shop_owner_cashbook_calculation_parity_with_demo_engine(): void
    {
        $salesHeader = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'SALES',
            'type' => 'income',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $salesType = LedgerEntryType::firstOrCreate(['code' => 'counter_sales'], ['name' => 'Counter Sales', 'category' => 'income', 'active' => true]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'header_group_id' => $salesHeader->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-04',
            'entry_type_id' => $salesType->id,
            'entry_type_code' => 'counter_sales',
            'direction' => 'income',
            'funding_source' => 'sales',
            'amount' => 25000.00,
            'entered_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->get(route('shop-owner.cashbook.show', ['date' => '2026-09-04']));

        $response->assertOk();
        $response->assertSee('Counter Sales');
        $response->assertSee('25000');
    }

    public function test_shop_owner_cashbook_renders_summary_dashboard_and_bill_sections(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->get(route('shop-owner.cashbook.show', ['date' => '2026-09-04']));

        $response->assertOk();
        $response->assertSee('SETTLEMENTS & BALANCE MOVEMENTS', false);
        $response->assertSee('View Cashbook Report');
        // Bottom IN/OUT navbar is completely removed
        $response->assertDontSee('id="cashbook-bottom-action-bar"', false);
    }

    public function test_shop_owner_cashbook_in_and_out_modals_isolate_headers_and_mirrored_headers_stay_in_out_only(): void
    {
        $salesHeader = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'SALES REVENUE',
            'type' => 'income',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $cashPurchaseHeader = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'CASH PURCHASE',
            'type' => 'expense',
            'display_order' => 2,
            'show_both_sides' => true,
            'product_tagging_enabled' => true,
            'enabled' => true,
        ]);

        $expenseHeader = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'STORE EXPENSES',
            'type' => 'expense',
            'display_order' => 3,
            'enabled' => true,
        ]);

        $salesType = LedgerEntryType::firstOrCreate(['code' => 'sales_rev_test'], ['name' => 'Sales Rev Test', 'category' => 'income', 'active' => true]);
        $rentType = LedgerEntryType::firstOrCreate(['code' => 'store_rent_test'], ['name' => 'Store Rent Test', 'category' => 'expense', 'active' => true]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'header_group_id' => $salesHeader->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'header_group_id' => $expenseHeader->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->get(route('shop-owner.cashbook.show', ['date' => '2026-09-04']));

        $response->assertOk();

        // IN modal must contain income header
        $response->assertSee('id="in-header-modal"', false);
        $response->assertSee('SALES REVENUE');

        // OUT modal must contain expense headers
        $response->assertSee('id="out-header-modal"', false);
        $response->assertSee('CASH PURCHASE');
        $response->assertSee('STORE EXPENSES');
    }

    public function test_shop_owner_cashbook_report_tab_renders_report_view(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->get(route('shop-owner.cashbook.show', ['date' => '2026-09-04', 'tab' => 'reports']));

        $response->assertOk();
        $response->assertSee('CASHBOOK REPORT');
        $response->assertSee('Net Activity');
        $response->assertSee('Back to Cashbook');
        $response->assertSee('PDF Report');
        $response->assertSee('id="report-headers-breakdown"', false);
        $response->assertDontSee('id="report-income-breakdown"', false);
        $response->assertDontSee('id="report-expense-breakdown"', false);
    }

    public function test_shop_owner_cashbook_modals_have_accessible_close_buttons(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->get(route('shop-owner.cashbook.show', ['date' => '2026-09-04']));

        $response->assertOk();
        $response->assertSee('aria-label="Close"', false);
        $response->assertSee('onclick="closeInHeaderModal()"', false);
        $response->assertSee('onclick="closeOutHeaderModal()"', false);
        $response->assertSee('onclick="closeHeaderEntrySheet()"', false);
        $response->assertSee('onclick="closeOwnerProductModal()"', false);
    }

    public function test_shop_owner_can_save_and_reload_cashbook_with_product_rows(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'CASH PURCHASE',
            'type' => 'expense',
            'product_tagging_enabled' => true,
            'display_order' => 1,
            'enabled' => true,
        ]);

        $product = Product::factory()->create([
            'name' => 'Akash Black',
            'sku' => '177',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->postJson(route('shop-owner.cashbook.api.bulk-record-entries'), [
                'business_date' => '2026-09-04',
                'header_group_id' => $header->id,
                'entries' => [],
                'product_rows' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 11.0,
                        'unit' => 'kg',
                        'amount' => 10000.00,
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'product_rows' => [
                (string) $header->id => [
                    '*' => ['product_id', 'quantity', 'unit', 'amount'],
                ],
            ],
        ]);

        $this->assertDatabaseHas('shop_ledger_product_entries', [
            'shop_id' => $this->shop->id,
            'header_group_id' => $header->id,
            'business_date' => '2026-09-04',
            'product_id' => $product->id,
            'product_name' => 'Akash Black',
            'product_sku' => '177',
            'quantity' => '11.0000',
            'unit' => 'kg',
            'amount' => '10000.00',
        ]);

        // Reload cashbook view and verify product row is rendered
        $reloadResponse = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->get(route('shop-owner.cashbook.show', ['date' => '2026-09-04']));

        $reloadResponse->assertOk();
        $reloadResponse->assertSee('Akash Black');
        $reloadResponse->assertSee('10000');
    }

    public function test_shop_owner_can_replace_product_preserving_quantity_and_amount(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'CASH PURCHASE',
            'type' => 'expense',
            'product_tagging_enabled' => true,
            'display_order' => 1,
            'enabled' => true,
        ]);

        $productA = Product::factory()->create(['name' => 'Old Product', 'sku' => 'OLD1', 'unit' => 'kg']);
        $productB = Product::factory()->create(['name' => 'New Product', 'sku' => 'NEW2', 'unit' => 'kg']);

        // Save initial row with Product A
        $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->postJson(route('shop-owner.cashbook.api.bulk-record-entries'), [
                'business_date' => '2026-09-04',
                'header_group_id' => $header->id,
                'entries' => [],
                'product_rows' => [
                    [
                        'product_id' => $productA->id,
                        'quantity' => 15.0,
                        'unit' => 'kg',
                        'amount' => 7500.00,
                    ],
                ],
            ])->assertOk();

        $this->assertDatabaseHas('shop_ledger_product_entries', [
            'shop_id' => $this->shop->id,
            'header_group_id' => $header->id,
            'product_id' => $productA->id,
            'quantity' => '15.0000',
            'amount' => '7500.00',
        ]);

        // Replace Product A with Product B while preserving quantity and amount
        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->postJson(route('shop-owner.cashbook.api.bulk-record-entries'), [
                'business_date' => '2026-09-04',
                'header_group_id' => $header->id,
                'entries' => [],
                'product_rows' => [
                    [
                        'product_id' => $productB->id,
                        'quantity' => 15.0,
                        'unit' => 'kg',
                        'amount' => 7500.00,
                    ],
                ],
            ]);

        $response->assertOk();

        // Product B exists with same qty and amount; Product A deleted
        $this->assertDatabaseHas('shop_ledger_product_entries', [
            'shop_id' => $this->shop->id,
            'header_group_id' => $header->id,
            'product_id' => $productB->id,
            'product_name' => 'New Product',
            'quantity' => '15.0000',
            'amount' => '7500.00',
        ]);

        $this->assertDatabaseMissing('shop_ledger_product_entries', [
            'shop_id' => $this->shop->id,
            'header_group_id' => $header->id,
            'product_id' => $productA->id,
        ]);
    }

    public function test_shop_owner_can_delete_product_rows_via_synchronization(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'CASH PURCHASE',
            'type' => 'expense',
            'product_tagging_enabled' => true,
            'display_order' => 1,
            'enabled' => true,
        ]);

        $product = Product::factory()->create();

        ShopLedgerProductEntry::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-04',
            'header_group_id' => $header->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 5.0,
            'unit' => 'kg',
            'amount' => 1200.00,
            'entered_by' => $this->owner->id,
        ]);

        // Submit empty product_rows to synchronize deletion
        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->postJson(route('shop-owner.cashbook.api.bulk-record-entries'), [
                'business_date' => '2026-09-04',
                'header_group_id' => $header->id,
                'entries' => [],
                'product_rows' => [],
            ]);

        $response->assertOk();

        $this->assertDatabaseMissing('shop_ledger_product_entries', [
            'shop_id' => $this->shop->id,
            'header_group_id' => $header->id,
            'business_date' => '2026-09-04',
        ]);
    }

    public function test_cross_shop_header_save_is_rejected(): void
    {
        $otherShop = Shop::factory()->create([
            'name' => 'Other Shop',
            'code' => 'OTHER-01',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $otherHeader = ShopLedgerHeaderGroup::create([
            'shop_id' => $otherShop->id,
            'name' => 'OTHER CASH PURCHASE',
            'type' => 'expense',
            'product_tagging_enabled' => true,
            'display_order' => 1,
            'enabled' => true,
        ]);

        $product = Product::factory()->create();

        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->postJson(route('shop-owner.cashbook.api.bulk-record-entries'), [
                'business_date' => '2026-09-04',
                'header_group_id' => $otherHeader->id,
                'entries' => [],
                'product_rows' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1.0,
                        'unit' => 'unit',
                        'amount' => 100.00,
                    ],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_saving_product_rows_for_header_with_disabled_product_tagging_is_rejected(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'OFFICE EXPENSE',
            'type' => 'expense',
            'product_tagging_enabled' => false,
            'display_order' => 1,
            'enabled' => true,
        ]);

        $product = Product::factory()->create();

        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->postJson(route('shop-owner.cashbook.api.bulk-record-entries'), [
                'business_date' => '2026-09-04',
                'header_group_id' => $header->id,
                'entries' => [],
                'product_rows' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1.0,
                        'unit' => 'unit',
                        'amount' => 100.00,
                    ],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_saving_product_rows_with_disallowed_product_is_rejected(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'RESTRICTED PURCHASE',
            'type' => 'expense',
            'product_tagging_enabled' => true,
            'display_order' => 1,
            'enabled' => true,
        ]);

        $allowedProduct = Product::factory()->create();
        $disallowedProduct = Product::factory()->create();

        $header->allowedProducts()->attach($allowedProduct->id);

        $response = $this->actingAs($this->owner)
            ->withSession(['active_shop_id' => $this->shop->id])
            ->postJson(route('shop-owner.cashbook.api.bulk-record-entries'), [
                'business_date' => '2026-09-04',
                'header_group_id' => $header->id,
                'entries' => [],
                'product_rows' => [
                    [
                        'product_id' => $disallowedProduct->id,
                        'quantity' => 2.0,
                        'unit' => 'kg',
                        'amount' => 200.00,
                    ],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }
}
