<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Purchasing\ShopPurchaseService;
use App\Services\Purchasing\ShopVendorReportService;
use Carbon\Carbon;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopPurchasingCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $shopUser;

    private Shop $shop;

    private Supplier $supplier;

    private Product $product;

    private LedgerEntryType $cashPurchaseType;

    private ShopLedgerEntrySetting $cashPurchaseSetting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->shop = Shop::query()->create([
            'name' => 'Alpha Shop',
            'code' => 'ALPHA',
            'shop_purchasing_enabled' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->shopUser = User::factory()->create([
            'shop_id' => $this->shop->id,
        ]);
        $this->shopUser->assignRole('shop');
        $this->shopUser->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Green Valley Farms',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Fresh Tomatoes',
            'unit' => 'kg',
        ]);

        $this->cashPurchaseType = LedgerEntryType::firstOrCreate(
            ['code' => 'vendor_purchase'],
            [
                'name' => 'Vendor Purchase',
                'category' => 'expense',
            ]
        );

        $this->cashPurchaseSetting = ShopLedgerEntrySetting::firstOrCreate(
            [
                'shop_id' => $this->shop->id,
                'entry_type_id' => $this->cashPurchaseType->id,
            ],
            [
                'enabled' => true,
                'display_name' => 'Vendor Purchase',
                'default_funding_source' => 'sales',
                'edit_policy' => 'past_days_allowed',
                'include_in_expense' => true,
                'effective_from' => '2026-01-01',
            ]
        );
    }

    public function test_purchasing_disabled_shop_cannot_access_routes(): void
    {
        $disabledShop = Shop::query()->create([
            'name' => 'Disabled Shop',
            'code' => 'DIS_01',
            'shop_purchasing_enabled' => false,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'status' => 'active',
            'is_active' => true,
        ]);
        $disabledUser = User::factory()->create([
            'shop_id' => $disabledShop->id,
        ]);
        $disabledUser->assignRole('shop');
        $disabledUser->ownedShopAssignments()->create(['shop_id' => $disabledShop->id]);

        // JSON requests receive explicit 403 Forbidden
        $responseJson = $this->actingAs($disabledUser)->getJson(route('shop-owner.purchasing.index'));
        $responseJson->assertForbidden();

        // Web GET requests are redirected to dashboard with error banner per bootstrap exception handler
        $response = $this->actingAs($disabledUser)->get(route('shop-owner.purchasing.index'));
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');

        $createResponse = $this->actingAs($disabledUser)->get(route('shop-owner.purchasing.create'));
        $createResponse->assertRedirect(route('dashboard'));

        // Direct POST mutations are blocked with 403 Forbidden
        $storeResponse = $this->actingAs($disabledUser)->post(route('shop-owner.purchasing.store'), [
            'supplier_id' => $this->supplier->id,
            'business_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit' => 'kg',
                    'unit_price' => 50,
                ],
            ],
        ]);
        $storeResponse->assertForbidden();
    }

    public function test_purchasing_enabled_shop_can_create_purchase(): void
    {
        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.purchasing.index'));
        $response->assertOk();

        $createResponse = $this->actingAs($this->shopUser)->get(route('shop-owner.purchasing.create'));
        $createResponse->assertOk();

        $storeResponse = $this->actingAs($this->shopUser)->post(route('shop-owner.purchasing.store'), [
            'supplier_id' => $this->supplier->id,
            'business_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 20,
                    'unit' => 'kg',
                    'unit_price' => 30,
                ],
            ],
        ]);

        $storeResponse->assertRedirect();
        $this->assertDatabaseHas('purchase_invoices', [
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->supplier->id,
            'amount' => 600.00,
            'payment_method' => 'Cash',
        ]);
    }

    public function test_shop_purchase_keeps_correct_shop_scope(): void
    {
        $service = app(ShopPurchaseService::class);

        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 15,
                    'unit' => 'kg',
                    'unit_price' => 40,
                ],
            ],
        ], $this->shopUser);

        $this->assertEquals($this->shop->id, $invoice->shop_id);
        $this->assertEquals('shop', $invoice->purchase_source);
        $this->assertEquals($this->shop->id, $invoice->goodsReceived?->purchaseOrder?->destination_shop_id);
        $this->assertTrue($invoice->isShopPurchase());
    }

    public function test_existing_supplier_can_be_reused_by_multiple_shops(): void
    {
        $shopB = Shop::factory()->create([
            'shop_purchasing_enabled' => true,
        ]);

        $service = app(ShopPurchaseService::class);

        $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'unit' => 'kg', 'unit_price' => 20],
            ],
        ], $this->shopUser);

        $service->recordPurchase($shopB, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 25],
            ],
        ], $this->shopUser);

        $this->assertDatabaseCount('suppliers', 1);
        $this->assertDatabaseHas('shop_suppliers', [
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->supplier->id,
        ]);
        $this->assertDatabaseHas('shop_suppliers', [
            'shop_id' => $shopB->id,
            'supplier_id' => $this->supplier->id,
        ]);
    }

    public function test_cash_purchase_creates_linked_shop_cashbook_entry(): void
    {
        $service = app(ShopPurchaseService::class);

        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 50],
            ],
        ], $this->shopUser);

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'shop_id' => $this->shop->id,
            'entry_type_id' => $this->cashPurchaseType->id,
            'amount' => 500.00,
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => $invoice->id,
        ]);

        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-14')
            ->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals(500.00, (float) $snapshot->total_expense);
    }

    public function test_cash_purchase_creates_zero_vendor_liability(): void
    {
        $service = app(ShopPurchaseService::class);

        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 50],
            ],
        ], $this->shopUser);

        $this->assertDatabaseMissing('shop_vendor_payables', [
            'purchase_invoice_id' => $invoice->id,
        ]);
        $this->assertEquals(0, VendorSettlement::count());
        $this->assertEquals(0, CompanyAccountStatementEntry::count());
    }

    public function test_credit_purchase_creates_shop_liability_and_zero_cash_movement(): void
    {
        $service = app(ShopPurchaseService::class);

        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 50],
            ],
        ], $this->shopUser);

        $this->assertDatabaseHas('shop_vendor_payables', [
            'purchase_invoice_id' => $invoice->id,
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->supplier->id,
            'original_amount' => 500.00,
            'outstanding_amount' => 500.00,
            'paid_amount' => 0.00,
            'status' => 'unpaid',
        ]);

        $this->assertDatabaseMissing('shop_ledger_transactions', [
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_credit_purchase_does_not_create_company_liability(): void
    {
        $service = app(ShopPurchaseService::class);

        $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 50],
            ],
        ], $this->shopUser);

        $this->assertEquals(0, VendorSettlement::count());
        $this->assertEquals(0, CompanyAccountStatementEntry::count());
    }

    public function test_cash_purchase_identification_does_not_depend_on_category_name(): void
    {
        // Change display name completely away from "cash purchase"
        $this->cashPurchaseSetting->update([
            'display_name' => 'Fresh Vegetables Direct Settlement',
        ]);

        $service = app(ShopPurchaseService::class);
        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'unit' => 'kg', 'unit_price' => 20],
            ],
        ], $this->shopUser);

        $transaction = ShopLedgerTransaction::where('reference_id', $invoice->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals($this->cashPurchaseType->id, $transaction->entry_type_id);
    }

    public function test_shop_purchase_uses_shop_active_business_day_not_central_purchaser_rollover_date(): void
    {
        // Shop cashbook is operating on 2026-09-14 with an open snapshot
        ShopDailyLedgerSnapshot::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-14',
            'status' => 'open',
            'total_sales' => 0,
            'total_income' => 0,
            'total_expense' => 0,
            'net_pl' => 0,
            'opening_petty' => 0,
            'closing_petty' => 0,
            'opening_shop_position' => 0,
            'closing_shop_position' => 0,
            'opening_company_pending' => 0,
            'closing_company_pending' => 0,
        ]);

        $service = app(ShopPurchaseService::class);

        // Record a cash purchase without specifying business_date in payload
        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 25],
            ],
        ], $this->shopUser);

        // Verify dates across all touchpoints match the shop active day '2026-09-14'
        $this->assertEquals('2026-09-14', $invoice->purchaserCart->business_date->toDateString());
        $this->assertEquals('2026-09-14', $invoice->goodsReceived->purchaseOrder->order_date->toDateString());
        $this->assertEquals('2026-09-14', $invoice->goodsReceived->received_at->toDateString());

        $tx = ShopLedgerTransaction::where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', $invoice->id)
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals('2026-09-14', $tx->business_date->toDateString());

        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-14')
            ->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals(250.00, (float) $snapshot->total_expense);
    }

    public function test_today_only_category_obeys_shop_active_business_day(): void
    {
        $customType = LedgerEntryType::firstOrCreate(
            ['code' => 'test_expense_today_only'],
            ['name' => 'Test Expense Today Only', 'category' => 'expense']
        );
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $customType->id,
            'edit_policy' => 'today_only',
            'enabled' => true,
            'effective_from' => '2026-01-01',
        ]);

        // Create open snapshot for shop on 2026-09-14
        ShopDailyLedgerSnapshot::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-14',
            'status' => 'open',
            'total_sales' => 0,
            'total_income' => 0,
            'total_expense' => 0,
            'net_pl' => 0,
            'opening_petty' => 0,
            'closing_petty' => 0,
            'opening_shop_position' => 0,
            'closing_shop_position' => 0,
            'opening_company_pending' => 0,
            'closing_company_pending' => 0,
        ]);

        $dailyLedgerService = app(DailyLedgerService::class);
        $activeDate = $dailyLedgerService->resolveActiveBusinessDate($this->shop);
        $this->assertEquals('2026-09-14', $activeDate);

        // Editing on current active shop business day is allowed
        $result = $dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $activeDate,
            'entry_type_id' => $customType->id,
            'amount' => 100.00,
            'funding_source' => 'sales',
            'created_by' => $this->shopUser->id,
        ]);
        $this->assertNotNull($result['transaction']);
        $this->assertEquals(100.00, (float) $result['transaction']->amount);

        // Attempting to record on past date with 'today_only' policy throws RuntimeException
        $pastDate = Carbon::parse($activeDate)->subDays(4)->toDateString();

        $this->expectException(\RuntimeException::class);
        $dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $pastDate,
            'entry_type_id' => $customType->id,
            'amount' => 50.00,
            'funding_source' => 'sales',
            'created_by' => $this->shopUser->id,
        ]);
    }

    public function test_past_days_category_can_edit_previous_open_day(): void
    {
        $customType = LedgerEntryType::firstOrCreate(
            ['code' => 'test_expense_past_allowed'],
            ['name' => 'Test Expense Past Allowed', 'category' => 'expense']
        );
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $customType->id,
            'edit_policy' => 'past_days_allowed',
            'enabled' => true,
            'effective_from' => '2026-01-01',
        ]);

        $dailyLedgerService = app(DailyLedgerService::class);
        $activeDate = $dailyLedgerService->resolveActiveBusinessDate($this->shop);
        $pastDate = Carbon::parse($activeDate)->subDays(2)->toDateString();

        $result = $dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $pastDate,
            'entry_type_id' => $customType->id,
            'amount' => 75.00,
            'funding_source' => 'sales',
            'created_by' => $this->shopUser->id,
        ]);

        $this->assertNotNull($result['transaction']);
        $this->assertEquals(75.00, (float) $result['transaction']->amount);
    }

    public function test_closed_day_blocks_edits_and_reopened_day_permits_edits(): void
    {
        $customType = LedgerEntryType::firstOrCreate(
            ['code' => 'test_expense_closed_reopen'],
            ['name' => 'Test Expense Closed Reopen', 'category' => 'expense']
        );
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $customType->id,
            'edit_policy' => 'past_days_allowed',
            'enabled' => true,
            'effective_from' => '2026-01-01',
        ]);

        $dailyLedgerService = app(DailyLedgerService::class);
        $targetDate = '2026-09-10';

        // Close the day
        $dailyLedgerService->closeDay($this->shop->id, $targetDate, $this->shopUser->id);

        // Attempting to record entry on closed day throws RuntimeException
        try {
            $dailyLedgerService->recordEntry([
                'shop_id' => $this->shop->id,
                'business_date' => $targetDate,
                'entry_type_id' => $customType->id,
                'amount' => 50.00,
                'funding_source' => 'sales',
                'created_by' => $this->shopUser->id,
            ]);
            $this->fail('Expected RuntimeException on closed day was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('is closed', $e->getMessage());
        }

        // Reopen the day
        $dailyLedgerService->reopenDay($this->shop->id, $targetDate);

        // Now recording on the reopened day succeeds
        $result = $dailyLedgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => $targetDate,
            'entry_type_id' => $customType->id,
            'amount' => 50.00,
            'funding_source' => 'sales',
            'created_by' => $this->shopUser->id,
        ]);

        $this->assertNotNull($result['transaction']);
        $this->assertEquals(50.00, (float) $result['transaction']->amount);
    }

    public function test_reports_separate_cash_and_credit_correctly(): void
    {
        $service = app(ShopPurchaseService::class);

        // 1 Cash Purchase = 300
        $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->shopUser);

        // 1 Credit Purchase = 500
        $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 50],
            ],
        ], $this->shopUser);

        $reportService = app(ShopVendorReportService::class);
        $data = $reportService->getShopVendorSummary($this->shop, [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $this->assertEquals(800.00, $data['summary']['total_purchase']);
        $this->assertEquals(300.00, $data['summary']['cash_purchase']);
        $this->assertEquals(500.00, $data['summary']['credit_purchase']);
        $this->assertEquals(500.00, $data['summary']['credit_outstanding']);
        $this->assertEquals(2, $data['summary']['invoice_count']);

        $row = $data['vendor_rows']->first();
        $this->assertEquals($this->supplier->id, $row['supplier_id']);
        $this->assertEquals(300.00, $row['cash_purchase']);
        $this->assertEquals(500.00, $row['credit_purchase']);
        $this->assertEquals(500.00, $row['credit_outstanding']);
    }

    public function test_no_duplicate_financial_counting(): void
    {
        $service = app(ShopPurchaseService::class);

        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 45],
            ],
        ], $this->shopUser);

        // Verify exactly one ledger transaction exists for this invoice
        $txCount = ShopLedgerTransaction::where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', $invoice->id)
            ->count();
        $this->assertEquals(1, $txCount);

        // Verify ShopDailyLedgerSnapshot expense matches invoice total without duplicates
        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-14')
            ->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals(450.00, (float) $snapshot->total_expense);
    }
}
