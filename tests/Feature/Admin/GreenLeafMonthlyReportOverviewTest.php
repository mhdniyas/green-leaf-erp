<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookMonthConfigSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Category;
use App\Models\Client;
use App\Models\DirectCompanySale;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GreenLeafMonthlyReportOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $clientShop;

    private ShopLedgerProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $client = Client::create([
            'name' => 'Overview Client',
            'code' => 'OC-01',
            'status' => 'active',
        ]);
        $this->clientShop = Shop::factory()->create([
            'client_id' => $client->id,
            'accounting_enabled' => true,
            'status' => 'active',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $this->profile = ShopLedgerProfile::where('shop_id', $this->clientShop->id)->firstOrFail();
    }

    public function test_overview_page_loads_with_correct_structure(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.overview', [
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.monthly-report.overview');
        $response->assertViewHas(['summary', 'daily_rows', 'period', 'reconciliation']);
    }

    public function test_overview_calculates_client_and_other_sales_correctly(): void
    {
        // 1. Client shop sale ledger transaction (Credit/income type with positive amount)
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->clientShop->id)->firstOrFail();
        $setting->update(['monthly_report_bucket' => 'fruits_sale', 'enabled' => true]);
        $salesType = $setting->entryType;

        ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $salesType->id,
            'business_date' => '2026-09-15',
            'amount' => 5000.00,
            'direction' => 'credit',
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
            'status' => 'approved',
        ]);

        // 2. Direct company sale (All other sales)
        DirectCompanySale::create([
            'business_date' => '2026-09-15',
            'amount' => 2500.00,
            'sale_status' => 'confirmed',
            'payment_status' => 'paid',
            'notes' => 'Direct B2B Sale',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.overview', [
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');

        $this->assertEquals(5000.00, $summary['client_sales']);
        $this->assertEquals(2500.00, $summary['all_other_sales']);
        $this->assertEquals(7500.00, $summary['total_sales']);
    }

    public function test_drilldown_modal_json_endpoint(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.drilldown', [
            'metric' => 'total_sales',
            'month' => '2026-09',
            'date' => '2026-09-15',
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'period',
            'metric',
            'metric_label',
            'total',
            'rows',
            'row_count',
        ]);
    }

    public function test_drilldown_modal_total_expenses_endpoint(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.drilldown', [
            'metric' => 'total_expenses',
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'period',
            'metric',
            'metric_label',
            'total',
            'rows',
            'row_count',
        ]);
        $this->assertSame('Total Expenses (Purchases + Operating)', $response->json('metric_label'));
    }

    public function test_historical_snapshots_isolate_past_month_reports(): void
    {
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->clientShop->id)->firstOrFail();
        $setting->update(['monthly_report_bucket' => 'fruits_sale', 'enabled' => true]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $setting->entry_type_id,
            'business_date' => '2026-08-10',
            'amount' => 12000.00,
            'direction' => 'credit',
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
            'status' => 'approved',
        ]);

        // Create a historical snapshot for 2026-08 capturing fruits_sale
        ShopCashbookMonthConfigSnapshot::create([
            'shop_id' => $this->clientShop->id,
            'month' => '2026-08',
            'status' => 'live_frozen',
            'source' => 'monthly_close',
            'config_data' => [
                'settings' => [
                    [
                        'id' => $setting->id,
                        'entry_type_id' => $setting->entry_type_id,
                        'monthly_report_bucket' => 'fruits_sale',
                        'header_group_id' => $setting->header_group_id,
                    ],
                ],
            ],
            'captured_by' => $this->admin->id,
        ]);

        // Now modify live setting today to ignore
        $setting->update(['monthly_report_bucket' => 'ignore']);

        // Historical report for 2026-08 must still calculate the 12000.00 fruits_sale from the snapshot!
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.overview', [
            'month' => '2026-08',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEquals(12000.00, $summary['client_sales']);
        $this->assertEquals(12000.00, $summary['total_sales']);
    }

    public function test_gl_bill_is_excluded_from_consolidated_expenses_while_cash_purchase_and_central_purchases_are_included(): void
    {
        $purchaseBillType = LedgerEntryType::where('code', 'purchase_bill')->firstOrFail();
        $cashPurchaseType = LedgerEntryType::where('code', 'cash_purchase')->first()
            ?? LedgerEntryType::create([
                'code' => 'cash_purchase',
                'name' => 'Cash Purchase',
                'category' => 'expense',
                'active' => true,
            ]);

        $shopOrder = ShopOrder::create([
            'shop_id' => $this->clientShop->id,
            'order_number' => 'ORD-TEST-001',
            'business_date' => '2026-09-01',
            'status' => 'confirmed',
            'created_by' => $this->admin->id,
            'subtotal' => 10000.00,
            'total' => 10000.00,
        ]);

        $shopInvoice = ShopInvoice::create([
            'shop_id' => $this->clientShop->id,
            'shop_order_id' => $shopOrder->id,
            'invoice_number' => 'SINV-TEST-001',
            'business_date' => '2026-09-01',
            'status' => 'approved',
            'subtotal' => 10000.00,
            'final_total' => 10000.00,
        ]);

        // 1. GL Bill transaction (internal projection)
        $glBillTx = ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $purchaseBillType->id,
            'business_date' => '2026-09-01',
            'amount' => 10000.00,
            'direction' => 'expense',
            'funding_source' => 'company',
            'reference_type' => ShopInvoice::class,
            'reference_id' => $shopInvoice->id,
            'notes' => 'Auto from invoice SINV-TEST-001',
            'entered_by' => $this->admin->id,
            'status' => 'posted',
        ]);

        // 2. Standalone shop cash purchase
        $cashPurchaseTx = ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $cashPurchaseType->id,
            'business_date' => '2026-09-01',
            'amount' => 500.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
            'status' => 'posted',
        ]);

        // 3. Central procurement purchase invoice
        $category = Category::create(['name' => 'Vegetables Cat']);
        $product = Product::create([
            'name' => 'Test Tomato',
            'sku' => 'TOM-01',
            'category_id' => $category->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);
        $supplier = Supplier::create([
            'name' => 'Test Farmer',
            'type' => 'farmer',
            'phone' => '9876543210',
        ]);
        $purchaserUser = User::factory()->create();
        $purchaserCart = PurchaserCart::create([
            'cart_number' => 'CART-001',
            'user_id' => $purchaserUser->id,
            'business_date' => '2026-09-01',
            'status' => 'completed',
        ]);
        PurchaserCartItem::create([
            'purchaser_cart_id' => $purchaserCart->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_price' => 20.00,
            'line_total' => 2000.00,
        ]);
        PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $purchaserCart->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PINV-TEST-001',
            'amount' => 2000.00,
            'discount_amount' => 0.00,
            'status' => 'approved',
            'payment_method' => 'cash',
        ]);

        // Query the overview
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.overview', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');

        // GL Bill (10,000) must be excluded from product_expenses.
        // Product expenses should be Central Purchase (2,000) + Shop Cash Purchase (500) = 2,500.
        $this->assertEquals(2500.00, $summary['product_expenses']);
        $this->assertEquals(2500.00, $summary['total_expenses']);

        // Verify GL Bill is still present in ShopLedgerTransaction
        $this->assertDatabaseHas('shop_ledger_transactions', [
            'id' => $glBillTx->id,
            'entry_type_id' => $purchaseBillType->id,
            'amount' => 10000.00,
        ]);
    }

    public function test_voided_and_reversed_transactions_remain_excluded(): void
    {
        $cashPurchaseType = LedgerEntryType::where('code', 'cash_purchase')->first()
            ?? LedgerEntryType::create([
                'code' => 'cash_purchase',
                'name' => 'Cash Purchase',
                'category' => 'expense',
                'active' => true,
            ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $cashPurchaseType->id,
            'business_date' => '2026-09-02',
            'amount' => 1500.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
            'status' => 'void',
            'voided_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.overview', [
            'start_date' => '2026-09-02',
            'end_date' => '2026-09-02',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEquals(0.00, $summary['total_expenses']);
    }

    public function test_shop_level_breakdown_includes_gl_bills_and_local_purchases_while_consolidated_excludes_gl_bills(): void
    {
        $purchaseBillType = LedgerEntryType::where('code', 'purchase_bill')->firstOrFail();
        $cashPurchaseType = LedgerEntryType::where('code', 'cash_purchase')->first()
            ?? LedgerEntryType::create([
                'code' => 'cash_purchase',
                'name' => 'Cash Purchase',
                'category' => 'expense',
                'active' => true,
            ]);
        $rentType = LedgerEntryType::where('code', 'rent_expense')->first()
            ?? LedgerEntryType::create([
                'code' => 'rent_expense',
                'name' => 'Rent',
                'category' => 'expense',
                'active' => true,
            ]);
        $tomattoType = LedgerEntryType::where('code', 'tomatto')->first()
            ?? LedgerEntryType::create([
                'code' => 'tomatto',
                'name' => 'Tomatto',
                'category' => 'expense',
                'active' => true,
            ]);
        $salesType = LedgerEntryType::where('code', 'cash_sales')->first()
            ?? LedgerEntryType::create([
                'code' => 'cash_sales',
                'name' => 'Cash Sales',
                'category' => 'income',
                'active' => true,
            ]);

        // 1. Shop Sales
        ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $salesType->id,
            'business_date' => '2026-09-10',
            'amount' => 50000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
            'status' => 'approved',
        ]);

        // 2. GL Bill (15,000)
        ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $purchaseBillType->id,
            'business_date' => '2026-09-10',
            'amount' => 15000.00,
            'direction' => 'expense',
            'funding_source' => 'company',
            'reference_type' => ShopInvoice::class,
            'reference_id' => 999,
            'notes' => 'GL Bill Test',
            'entered_by' => $this->admin->id,
            'status' => 'posted',
        ]);

        // 3. Local Cash Purchase (2,000)
        ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $cashPurchaseType->id,
            'business_date' => '2026-09-10',
            'amount' => 2000.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
            'status' => 'posted',
        ]);

        // 4. Tomatto local produce purchase (1,500)
        ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $tomattoType->id,
            'business_date' => '2026-09-10',
            'amount' => 1500.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
            'status' => 'posted',
        ]);

        // 5. Rent operating expense (5,000)
        ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-09-10',
            'amount' => 5000.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
            'status' => 'posted',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.overview', [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
        ]));

        $response->assertOk();
        $summary = $response->viewData('summary');
        $clients = $response->viewData('clients');

        // 1. Consolidated Summary Check: GL Bill MUST be excluded
        // Product Expenses = Local cash purchase (2,000) + Tomatto (1,500) = 3,500
        $this->assertEquals(3500.00, $summary['product_expenses']);
        $this->assertEquals(5000.00, $summary['operating_expenses']);
        $this->assertEquals(8500.00, $summary['total_expenses']);
        $this->assertEquals(50000.00, $summary['total_sales']);
        $this->assertEquals(41500.00, $summary['balance']);

        // 2. Shop Breakdown Check: GL Bill MUST be included
        $this->assertNotEmpty($clients);
        $client = $clients[0];
        $shop = $client['shops'][0];

        // Shop Sales = 50,000
        $this->assertEquals(50000.00, $shop['sales']);
        // Shop Product Expense = GL Bill (15,000) + Cash Purchase (2,000) + Tomatto (1,500) = 18,500
        $this->assertEquals(18500.00, $shop['product_expenses']);
        // Shop Operating Expense = Rent (5,000)
        $this->assertEquals(5000.00, $shop['operating_expenses']);
        // Shop Total Expenses = 18,500 + 5,000 = 23,500
        $this->assertEquals(23500.00, $shop['total_expenses']);
        // Shop Net Balance = 50,000 - 23,500 = 26,500
        $this->assertEquals(26500.00, $shop['balance']);

        // 3. Client Totals equal sum of shops
        $this->assertEquals($shop['sales'], $client['sales']);
        $this->assertEquals($shop['product_expenses'], $client['product_expenses']);
        $this->assertEquals($shop['operating_expenses'], $client['operating_expenses']);
        $this->assertEquals($shop['balance'], $client['balance']);
    }

    public function test_drilldown_modal_operating_expenses_for_shop(): void
    {
        $rentType = LedgerEntryType::where('code', 'rent_expense')->firstOrFail();

        ShopLedgerTransaction::create([
            'shop_id' => $this->clientShop->id,
            'shop_ledger_profile_id' => $this->profile->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-09-12',
            'amount' => 7500.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
            'status' => 'posted',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.monthly-report.drilldown', [
            'metric' => 'operating_expenses',
            'month' => '2026-09',
            'shop_id' => $this->clientShop->id,
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'metric',
            'metric_label',
            'total',
            'rows',
            'row_count',
        ]);

        $data = $response->json();
        $this->assertEquals(7500.00, $data['total']);
        $this->assertCount(1, $data['rows']);
        $this->assertEquals('Shop Cashbook', $data['rows'][0]['source_type']);
    }
}
