<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Enums\Purchasing\POStatus;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\Shop;
use App\Models\ShopSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShopVendorPurchaseReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Shop $shop;

    private Shop $otherShop;

    private Supplier $vendorA;

    private Supplier $vendorB;

    private Supplier $otherShopVendor;

    private Product $tomato;

    private Product $onion;

    private ShopLedgerHeaderGroup $headerGroup;

    private ShopLedgerEntrySetting $vendorCategory;

    private ShopCashbookRelation $settlementRelation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('admin');

        $this->unauthorizedUser = User::factory()->create(['email_verified_at' => now()]);

        $this->shop = Shop::query()->create([
            'name' => 'Indiranagar Fresh',
            'code' => 'IND_01',
            'warehouse_tag' => 'IND',
            'status' => 'active',
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_purchasing_enabled' => true,
            'allow_vendor_creation' => true,
        ]);

        $this->otherShop = Shop::query()->create([
            'name' => 'Whitefield Fresh',
            'code' => 'WHI_01',
            'warehouse_tag' => 'WHI',
            'status' => 'active',
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_purchasing_enabled' => true,
            'allow_vendor_creation' => true,
        ]);

        $this->vendorA = Supplier::query()->create([
            'name' => 'ABC Vegetables',
            'mobile_number' => '9876543210',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
            'is_active' => true,
        ]);

        $this->vendorB = Supplier::query()->create([
            'name' => 'XYZ Traders',
            'mobile_number' => '9876543211',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => false,
            'is_active' => true,
        ]);

        $this->otherShopVendor = Supplier::query()->create([
            'name' => 'Other Shop Supplier',
            'mobile_number' => '9876543299',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
            'is_active' => true,
        ]);

        ShopSupplier::query()->create([
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->vendorA->id,
            'is_active' => true,
        ]);

        ShopSupplier::query()->create([
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->vendorB->id,
            'is_active' => true,
        ]);

        ShopSupplier::query()->create([
            'shop_id' => $this->otherShop->id,
            'supplier_id' => $this->otherShopVendor->id,
            'is_active' => true,
        ]);

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'TOM_01',
            'unit' => 'kg',
            'base_price' => 10.00,
            'is_active' => true,
        ]);

        $this->onion = Product::factory()->create([
            'name' => 'Onion',
            'sku' => 'ONI_01',
            'unit' => 'kg',
            'base_price' => 25.00,
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->headerGroup = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Cash Purchase',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
            'is_active' => true,
        ]);

        $this->settlementRelation = ShopCashbookRelation::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Casio Settlement',
            'counterparty_type' => 'company',
            'settlement_type' => 'balance_netting',
            'balance_direction' => 'shop_owes_company',
            'status' => 'active',
            'sync_status' => 'synced',
        ]);

        $entryType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'local_vendor_purchase'],
            [
                'name' => 'local_vendor_purchase',
                'label' => 'Local Vendor Purchase',
                'category' => 'expense',
                'direction' => 'expense',
                'affects_cash' => true,
                'affects_income' => false,
                'affects_sales' => false,
                'is_active' => true,
            ]
        );

        $this->vendorCategory = ShopLedgerEntrySetting::query()->updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $entryType->id],
            [
                'header_group_id' => $this->headerGroup->id,
                'display_name' => 'Local Vendor Purchase',
                'display_order' => 1,
                'effective_from' => '2026-01-01',
                'enabled' => true,
                'funding_source' => 'shop_cash',
                'affects_closing_balance' => true,
                'is_vendor_purchase' => true,
                'vendor_access_mode' => 'linked_create',
                'vendor_settlement_relation_id' => $this->settlementRelation->id,
            ]
        );
    }

    private function createPurchase(
        Shop $shop,
        Supplier $vendor,
        string $businessDate,
        string $paymentMethod,
        array $items,
        ?ShopLedgerEntrySetting $category = null,
        float $discount = 0.0,
    ): PurchaseInvoice {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->admin->id,
            'supplier_id' => $vendor->id,
            'destination_shop_id' => $shop->id,
            'shop_ledger_entry_setting_id' => $category?->id,
            'business_date' => $businessDate,
            'status' => 'submitted',
            'purchase_source' => 'shop',
            'purchase_grade' => 'A',
            'cart_number' => 'CART-'.uniqid(),
            'bill_number' => 'BILL-'.uniqid(),
            'discount_amount' => $discount,
            'payment_method' => $paymentMethod,
            'payment_status' => strcasecmp($paymentMethod, 'Cash') === 0 ? 'paid' : 'pending',
            'paid_amount' => strcasecmp($paymentMethod, 'Cash') === 0 ? 1000 : 0,
        ]);

        $grossTotal = 0.0;
        foreach ($items as $item) {
            $qty = (float) $item['quantity'];
            $price = (float) $item['unit_price'];
            $lineTotal = round($qty * $price, 2);
            $grossTotal += $lineTotal;

            $cart->items()->create([
                'product_id' => $item['product_id'],
                'grade' => $item['grade'] ?? 'A',
                'quantity' => $qty,
                'unit_price' => $price,
                'line_total' => $lineTotal,
            ]);
        }

        $po = PurchaseOrder::query()->create([
            'supplier_id' => $vendor->id,
            'destination_shop_id' => $shop->id,
            'purchaser_cart_id' => $cart->id,
            'po_number' => 'SPO-'.uniqid(),
            'status' => POStatus::Received,
            'fulfillment_type' => 'shop',
            'order_date' => $businessDate,
            'created_by' => $this->admin->id,
            'purchase_grade' => 'A',
        ]);

        $grn = GoodsReceived::query()->create([
            'purchase_order_id' => $po->id,
            'purchaser_cart_id' => $cart->id,
            'grn_number' => 'SGRN-'.uniqid(),
            'status' => 'approved',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->admin->id,
            'received_at' => $businessDate,
            'purchase_grade' => 'A',
        ]);

        return PurchaseInvoice::query()->create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $vendor->id,
            'shop_id' => $shop->id,
            'shop_ledger_entry_setting_id' => $category?->id,
            'purchaser_cart_id' => $cart->id,
            'purchase_source' => 'shop',
            'invoice_number' => 'INV-'.uniqid(),
            'amount' => $grossTotal,
            'discount_amount' => $discount,
            'status' => 'paid',
            'payment_method' => $paymentMethod,
        ]);
    }

    public function test_main_cashbook_shows_vendor_purchases_summary_card_and_cash_credit_totals(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // 1. Cash Purchase of ₹1,000 (100 kg * ₹10)
        $this->createPurchase($this->shop, $this->vendorA, $today, 'Cash', [
            ['product_id' => $this->tomato->id, 'quantity' => 100, 'unit_price' => 10],
        ], $this->vendorCategory);

        // 2. Credit Purchase of ₹1,250 (50 kg * ₹25)
        $this->createPurchase($this->shop, $this->vendorB, $today, 'Credit', [
            ['product_id' => $this->onion->id, 'quantity' => 50, 'unit_price' => 25],
        ], $this->vendorCategory);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->shop->id,
            'date' => $today,
        ]));

        $response->assertOk();
        $response->assertSee('Vendor Purchases');
        $response->assertSee('2,250.00'); // Total: 1000 + 1250 = 2250
        $response->assertSee('1,000.00'); // Cash: 1000
        $response->assertSee('1,250.00'); // Credit: 1250
        $response->assertSee('View Vendor Purchases');
    }

    public function test_vendor_purchases_report_page_loads_with_correct_shop_scoping(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        $this->createPurchase($this->shop, $this->vendorA, $today, 'Cash', [
            ['product_id' => $this->tomato->id, 'quantity' => 100, 'unit_price' => 10],
        ], $this->vendorCategory);

        // Other shop purchase should NEVER appear
        $this->createPurchase($this->otherShop, $this->otherShopVendor, $today, 'Cash', [
            ['product_id' => $this->tomato->id, 'quantity' => 200, 'unit_price' => 10],
        ], null);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Indiranagar Fresh');
        $response->assertSee('ABC Vegetables');
        $response->assertDontSee('Other Shop Supplier');
        $response->assertSee('1,000.00');
        $response->assertDontSee('2,000.00');
    }

    public function test_unauthorized_users_cannot_access_vendor_purchases_report(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
        ]));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_today_yesterday_month_and_custom_period_filters_work(): void
    {
        $today = now('Asia/Kolkata')->toDateString();
        $yesterday = now('Asia/Kolkata')->subDay()->toDateString();
        $prevMonthDate = now('Asia/Kolkata')->subMonths(2)->startOfMonth()->toDateString();

        // Today purchase: ₹1,000
        $this->createPurchase($this->shop, $this->vendorA, $today, 'Cash', [
            ['product_id' => $this->tomato->id, 'quantity' => 100, 'unit_price' => 10],
        ]);

        // Yesterday purchase: ₹500
        $this->createPurchase($this->shop, $this->vendorA, $yesterday, 'Cash', [
            ['product_id' => $this->tomato->id, 'quantity' => 50, 'unit_price' => 10],
        ]);

        // 2 months ago purchase: ₹300
        $this->createPurchase($this->shop, $this->vendorA, $prevMonthDate, 'Cash', [
            ['product_id' => $this->tomato->id, 'quantity' => 30, 'unit_price' => 10],
        ]);

        // 1. Today filter
        $resToday = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
            'period' => 'today',
        ]));
        $resToday->assertOk();
        $resToday->assertSee('1,000.00');
        $resToday->assertDontSee('1,500.00');

        // 2. Yesterday filter
        $resYesterday = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
            'period' => 'yesterday',
        ]));
        $resYesterday->assertOk();
        $resYesterday->assertSee('500.00');
        $resYesterday->assertDontSee('1,000.00');

        // 3. Custom date range filter covering today and yesterday
        $resCustom = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
            'period' => 'custom',
            'start_date' => $yesterday,
            'end_date' => $today,
        ]));
        $resCustom->assertOk();
        $resCustom->assertSee('1,500.00'); // 1000 + 500
        $resCustom->assertDontSee('1,800.00');
    }

    public function test_vendor_and_category_and_payment_filters(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // Vendor A: ₹1,000 Cash with Category
        $this->createPurchase($this->shop, $this->vendorA, $today, 'Cash', [
            ['product_id' => $this->tomato->id, 'quantity' => 100, 'unit_price' => 10],
        ], $this->vendorCategory);

        // Vendor B: ₹2,000 Credit without Category (Legacy)
        $this->createPurchase($this->shop, $this->vendorB, $today, 'Credit', [
            ['product_id' => $this->onion->id, 'quantity' => 80, 'unit_price' => 25],
        ], null);

        // 1. Filter by Vendor A
        $resVendor = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
            'period' => 'today',
            'vendor_id' => $this->vendorA->id,
        ]));
        $resVendor->assertOk();
        $resVendor->assertSee('1,000.00');
        $resVendor->assertDontSee('3,000.00');
        $resVendor->assertDontSee('2,000.00');

        // 2. Filter by Category
        $resCategory = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
            'period' => 'today',
            'category_id' => $this->vendorCategory->id,
        ]));
        $resCategory->assertOk();
        $resCategory->assertSee('Local Vendor Purchase');
        $resCategory->assertSee('1,000.00');
        $resCategory->assertDontSee('3,000.00');
        $resCategory->assertDontSee('2,000.00');

        // 3. Filter by Payment = Credit
        $resCredit = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
            'period' => 'today',
            'payment' => 'credit',
        ]));
        $resCredit->assertOk();
        $resCredit->assertSee('2,000.00');
        $resCredit->assertDontSee('3,000.00');
    }

    public function test_vendor_summary_and_mixed_units_and_cash_credit_columns(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // Vendor A: 2 purchases (1 Cash: 100 kg Tomato = ₹1,000; 1 Credit: 50 kg Onion = ₹1,250)
        $this->createPurchase($this->shop, $this->vendorA, $today, 'Cash', [
            ['product_id' => $this->tomato->id, 'quantity' => 100, 'unit_price' => 10],
        ], $this->vendorCategory);

        $this->createPurchase($this->shop, $this->vendorA, $today, 'Credit', [
            ['product_id' => $this->onion->id, 'quantity' => 50, 'unit_price' => 25],
        ], $this->vendorCategory);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Vendor Summary');
        $response->assertSee('ABC Vegetables');
        $response->assertSee('150 kg'); // 100 + 50 kg
        $response->assertSee('2,250.00'); // Total
        $response->assertSee('1,000.00'); // Cash
        $response->assertSee('1,250.00'); // Credit
    }

    public function test_product_summary_and_period_weighted_avg_buy_formula(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // Purchase 1: 100 kg Tomato @ ₹10/kg = ₹1,000
        $this->createPurchase($this->shop, $this->vendorA, $today, 'Cash', [
            ['product_id' => $this->tomato->id, 'quantity' => 100, 'unit_price' => 10],
        ]);

        // Purchase 2: 400 kg Tomato @ ₹12.50/kg = ₹5,000
        $this->createPurchase($this->shop, $this->vendorB, $today, 'Cash', [
            ['product_id' => $this->tomato->id, 'quantity' => 400, 'unit_price' => 12.50],
        ]);

        // Total Tomato = 500 kg, Total Purchase = ₹6,000.
        // Period-weighted Avg Buy = 6,000 / 500 = ₹12.00/kg.
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Product Summary');
        $response->assertSee('Tomato');
        $response->assertSee('500 kg');
        $response->assertSee('6,000.00');
        $response->assertSee('₹12.00/kg');
    }

    public function test_daily_details_grouping_and_category_and_legacy_display(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // Categorized Purchase
        $this->createPurchase($this->shop, $this->vendorA, $today, 'Cash', [
            ['product_id' => $this->tomato->id, 'quantity' => 100, 'unit_price' => 10],
        ], $this->vendorCategory);

        // Legacy Null-Category Purchase
        $this->createPurchase($this->shop, $this->vendorB, $today, 'Credit', [
            ['product_id' => $this->onion->id, 'quantity' => 50, 'unit_price' => 25],
        ], null);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Daily Purchase Details');
        // Categorized badge
        $response->assertSee('Local Vendor Purchase');
        $response->assertSee('Cash Purchase');
        $response->assertSee('Casio Settlement');
        // Legacy fallback badge
        $response->assertSee('Legacy Vendor Purchase');
    }

    public function test_shop_owner_cannot_access_admin_vendor_purchases_report(): void
    {
        $shopOwner = User::factory()->create([
            'email_verified_at' => now(),
            'shop_id' => $this->shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
        ]));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_purchaser_role_cannot_access_admin_vendor_purchases_report(): void
    {
        $purchaserUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $purchaserUser->assignRole('purchaser');

        $response = $this->actingAs($purchaserUser)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
        ]));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_purchaser_role_gets_no_vendor_purchase_settings_access(): void
    {
        $purchaserUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $purchaserUser->assignRole('purchaser');

        $response = $this->actingAs($purchaserUser)->get(route('admin.cashbook.settings.shop', [
            'shop' => $this->shop->id,
        ]));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_vendor_report_excludes_normal_company_purchaser_purchases_and_includes_shop_vendor_purchases(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // 1. Shop Vendor Purchase (purchase_source = 'shop') -> MUST be included
        $this->createPurchase($this->shop, $this->vendorA, $today, 'Cash', [
            ['product_id' => $this->tomato->id, 'quantity' => 100, 'unit_price' => 10],
        ], $this->vendorCategory);

        // 2. Normal Company Purchaser Purchase (purchase_source = 'shop_order') -> MUST be excluded
        $purchaserCart = PurchaserCart::query()->create([
            'user_id' => $this->admin->id,
            'supplier_id' => $this->vendorB->id,
            'destination_shop_id' => $this->shop->id,
            'business_date' => $today,
            'status' => 'submitted',
            'purchase_source' => 'shop_order', // Normal purchaser source
            'cart_number' => 'CART-PURCHASER-01',
            'bill_number' => 'BILL-PURCHASER-01',
            'discount_amount' => 0,
            'payment_method' => 'Cash',
        ]);
        $purchaserCart->items()->create([
            'product_id' => $this->onion->id,
            'grade' => 'A',
            'quantity' => 50,
            'unit_price' => 25,
            'line_total' => 1250,
        ]);
        $po = PurchaseOrder::query()->create([
            'supplier_id' => $this->vendorB->id,
            'destination_shop_id' => $this->shop->id,
            'purchaser_cart_id' => $purchaserCart->id,
            'po_number' => 'PO-PURCHASER-01',
            'status' => POStatus::Received,
            'fulfillment_type' => 'shop_order',
            'order_date' => $today,
            'created_by' => $this->admin->id,
        ]);
        $grn = GoodsReceived::query()->create([
            'purchase_order_id' => $po->id,
            'purchaser_cart_id' => $purchaserCart->id,
            'grn_number' => 'GRN-PURCHASER-01',
            'status' => 'approved',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->admin->id,
            'received_at' => $today,
        ]);
        PurchaseInvoice::query()->create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->vendorB->id,
            'shop_id' => $this->shop->id,
            'purchaser_cart_id' => $purchaserCart->id,
            'purchase_source' => 'shop_order', // Normal purchaser invoice
            'invoice_number' => 'INV-PURCHASER-01',
            'amount' => 1250,
            'discount_amount' => 0,
            'status' => 'paid',
            'payment_method' => 'Cash',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.purchases.vendors', [
            'shop' => $this->shop->id,
            'period' => 'today',
        ]));

        $response->assertOk();
        // Shop Vendor purchase is included
        $response->assertSee('ABC Vegetables');
        $response->assertSee('1,000.00');
        // Normal company purchaser purchase is excluded
        $response->assertDontSee('INV-PURCHASER-01');
        $response->assertDontSee('BILL-PURCHASER-01');
        $response->assertDontSee('1,250.00');
    }
}
