<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\Shop;
use App\Models\ShopSupplier;
use App\Models\ShopVendorPayable;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Purchasing\ShopPurchaseService;
use Carbon\Carbon;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCashbookVendorPurchaseCategoryFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopUser;

    private Shop $shop;

    private Shop $otherShop;

    private Supplier $activeVendor1;

    private Supplier $activeVendor2;

    private Supplier $inactiveVendor;

    private Supplier $otherShopVendor;

    private ShopSupplier $shopSupplier1;

    private ShopSupplier $shopSupplier2;

    private ShopSupplier $shopSupplierInactive;

    private ShopSupplier $otherShopSupplier;

    private Product $product;

    private ShopLedgerHeaderGroup $headerGroup;

    private ShopLedgerEntrySetting $normalCategory;

    private ShopLedgerEntrySetting $vendorCategoryLinkedCreate;

    private ShopLedgerEntrySetting $vendorCategoryLinkedOnly;

    private ShopLedgerEntrySetting $vendorCategoryDefinedOnly;

    private ShopCashbookRelation $settlementRelation;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-15 12:00:00');

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

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

        $this->shopUser = User::factory()->create(['shop_id' => $this->shop->id]);
        $this->shopUser->assignRole('shop');
        $this->shopUser->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);

        $this->activeVendor1 = Supplier::query()->create([
            'name' => 'Farm Fresh Onions',
            'mobile_number' => '9800000001',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
        ]);

        $this->activeVendor2 = Supplier::query()->create([
            'name' => 'Green Valley Potatoes',
            'mobile_number' => '9800000002',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => false,
        ]);

        $this->inactiveVendor = Supplier::query()->create([
            'name' => 'Inactive Vendor',
            'mobile_number' => '9800000003',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => false,
        ]);

        $this->otherShopVendor = Supplier::query()->create([
            'name' => 'Other Shop Vendor',
            'mobile_number' => '9800000004',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
        ]);

        $this->shopSupplier1 = ShopSupplier::query()->create([
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->activeVendor1->id,
            'is_active' => true,
            'credit_approved' => true,
        ]);

        $this->shopSupplier2 = ShopSupplier::query()->create([
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->activeVendor2->id,
            'is_active' => true,
            'credit_approved' => false,
        ]);

        $this->shopSupplierInactive = ShopSupplier::query()->create([
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->inactiveVendor->id,
            'is_active' => false,
            'credit_approved' => false,
        ]);

        $this->otherShopSupplier = ShopSupplier::query()->create([
            'shop_id' => $this->otherShop->id,
            'supplier_id' => $this->otherShopVendor->id,
            'is_active' => true,
            'credit_approved' => true,
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Red Onions',
            'sku' => 'ONI-100',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->headerGroup = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Local Cash Purchase',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
            'product_tagging_enabled' => false,
        ]);

        $this->settlementRelation = ShopCashbookRelation::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'APMC Mandi Settlement',
            'settlement_type' => 'bank_settlement',
            'enabled' => true,
        ]);

        $expenseTypes = LedgerEntryType::query()
            ->where('category', 'expense')
            ->whereNotIn('code', ['vendor_purchase', 'vendor_purchase_cash', 'vendor_purchase_credit', 'purchase_bill'])
            ->take(4)
            ->get();

        // 1. Normal category
        $this->normalCategory = ShopLedgerEntrySetting::query()->updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $expenseTypes[0]->id],
            [
                'header_group_id' => $this->headerGroup->id,
                'display_name' => 'Tea & Snacks',
                'effective_from' => '2026-01-01',
                'enabled' => true,
                'include_in_expense' => true,
                'default_funding_source' => 'sales',
                'is_vendor_purchase' => false,
            ]
        );

        // 2. Vendor Purchase category: linked_create
        $this->vendorCategoryLinkedCreate = ShopLedgerEntrySetting::query()->updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $expenseTypes[1]->id],
            [
                'header_group_id' => $this->headerGroup->id,
                'display_name' => 'Local Mandi Purchase (Create)',
                'effective_from' => '2026-01-01',
                'enabled' => true,
                'include_in_expense' => true,
                'default_funding_source' => 'sales',
                'is_vendor_purchase' => true,
                'vendor_access_mode' => 'linked_create',
                'vendor_settlement_relation_id' => $this->settlementRelation->id,
            ]
        );

        // 3. Vendor Purchase category: linked_only
        $this->vendorCategoryLinkedOnly = ShopLedgerEntrySetting::query()->updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $expenseTypes[2]->id],
            [
                'header_group_id' => $this->headerGroup->id,
                'display_name' => 'Fixed Suppliers (Linked Only)',
                'effective_from' => '2026-01-01',
                'enabled' => true,
                'include_in_expense' => true,
                'default_funding_source' => 'sales',
                'is_vendor_purchase' => true,
                'vendor_access_mode' => 'linked_only',
                'vendor_settlement_relation_id' => $this->settlementRelation->id,
            ]
        );

        // 4. Vendor Purchase category: defined_only (mapped only to activeVendor1)
        $this->vendorCategoryDefinedOnly = ShopLedgerEntrySetting::query()->updateOrCreate(
            ['shop_id' => $this->shop->id, 'entry_type_id' => $expenseTypes[3]->id],
            [
                'header_group_id' => $this->headerGroup->id,
                'display_name' => 'Onion Suppliers (Defined Only)',
                'effective_from' => '2026-01-01',
                'enabled' => true,
                'include_in_expense' => true,
                'default_funding_source' => 'sales',
                'is_vendor_purchase' => true,
                'vendor_access_mode' => 'defined_only',
                'vendor_settlement_relation_id' => $this->settlementRelation->id,
            ]
        );
        $this->vendorCategoryDefinedOnly->definedShopSuppliers()->sync([$this->shopSupplier1->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_normal_category_and_vendor_purchase_categories_remain_under_existing_header(): void
    {
        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.show'));

        $response->assertOk();
        // Header is rendered
        $response->assertSee('Local Cash Purchase');
        // Normal category is rendered
        $response->assertSee('Tea & Snacks');
        // Vendor Purchase categories are rendered under existing header
        $response->assertSee('Local Mandi Purchase (Create)');
        $response->assertSee('Fixed Suppliers (Linked Only)');
        $response->assertSee('Onion Suppliers (Defined Only)');
        // Settlement info is present
        $response->assertSee('APMC Mandi Settlement');
    }

    public function test_vendor_category_is_serialized_with_access_mode_and_defined_vendors(): void
    {
        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.show'));

        $response->assertOk();
        $response->assertSee('is_vendor_purchase');
        $response->assertSee('vendor_access_mode');
        $response->assertSee('defined_supplier_ids');
    }

    public function test_backend_validation_rejects_purchase_with_cross_shop_category(): void
    {
        $otherShopSetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->otherShop->id,
            'entry_type_id' => $this->normalCategory->entry_type_id,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'is_vendor_purchase' => true,
            'vendor_access_mode' => 'linked_create',
        ]);

        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $otherShopSetting->id,
            'supplier_id' => $this->activeVendor1->id,
            'payment_method' => 'Cash',
            'business_date' => today()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'total_price' => 200.0,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'The selected category does not belong to this shop.',
        ]);
    }

    public function test_backend_validation_rejects_purchase_with_inactive_category(): void
    {
        $this->vendorCategoryLinkedCreate->update(['enabled' => false]);

        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategoryLinkedCreate->id,
            'supplier_id' => $this->activeVendor1->id,
            'payment_method' => 'Cash',
            'business_date' => today()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'total_price' => 200.0,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'The selected category is disabled.',
        ]);
    }

    public function test_backend_validation_rejects_inactive_vendor_for_purchase(): void
    {
        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategoryLinkedCreate->id,
            'supplier_id' => $this->inactiveVendor->id,
            'payment_method' => 'Cash',
            'business_date' => today()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'total_price' => 200.0,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'The selected vendor is not active for this shop.',
        ]);
    }

    public function test_backend_validation_rejects_other_shop_vendor(): void
    {
        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategoryLinkedCreate->id,
            'supplier_id' => $this->otherShopVendor->id,
            'payment_method' => 'Cash',
            'business_date' => today()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'total_price' => 200.0,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'The selected vendor is not active for this shop.',
        ]);
    }

    public function test_backend_validation_rejects_unmapped_vendor_for_defined_only_mode(): void
    {
        // activeVendor2 is linked to shop, but NOT mapped to vendorCategoryDefinedOnly (which only has activeVendor1)
        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategoryDefinedOnly->id,
            'supplier_id' => $this->activeVendor2->id,
            'payment_method' => 'Cash',
            'business_date' => today()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'total_price' => 200.0,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => "The selected vendor is not permitted for category 'Onion Suppliers (Defined Only)'.",
        ]);
    }

    public function test_backend_rejects_vendor_creation_for_linked_only_and_defined_only_modes(): void
    {
        // linked_only attempt
        $response1 = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.vendors.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategoryLinkedOnly->id,
            'name' => 'New Unauthorized Vendor',
            'mobile_number' => '9999999999',
        ]);
        $response1->assertStatus(422);
        $response1->assertJson(['success' => false, 'message' => 'Vendor creation is not permitted for this category.']);

        // defined_only attempt
        $response2 = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.vendors.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategoryDefinedOnly->id,
            'name' => 'New Defined Unauthorized Vendor',
            'mobile_number' => '9999999998',
        ]);
        $response2->assertStatus(422);
        $response2->assertJson(['success' => false, 'message' => 'Vendor creation is not permitted for this category.']);
    }

    public function test_backend_allows_vendor_creation_for_linked_create_mode(): void
    {
        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.vendors.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategoryLinkedCreate->id,
            'name' => 'Brand New Mandi Vendor',
            'mobile_number' => '9999999991',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $newVendorId = $response->json('vendor.id');
        $this->assertDatabaseHas('suppliers', ['id' => $newVendorId, 'name' => 'Brand New Mandi Vendor']);
        $this->assertDatabaseHas('shop_suppliers', ['shop_id' => $this->shop->id, 'supplier_id' => $newVendorId, 'is_active' => 1]);
    }

    public function test_cash_purchase_authoritatively_routes_to_cash_category_and_posts_to_cash_entry_type(): void
    {
        $cashSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('vendor_purchase_payment_type', 'cash')
            ->firstOrFail();

        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategoryDefinedOnly->id,
            'supplier_id' => $this->activeVendor1->id,
            'payment_method' => 'Cash',
            'business_date' => '2026-09-16',
            'bill_number' => 'BILL-ONION-01',
            'notes' => 'Grade A Onions',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 50,
                    'total_price' => 750.0,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $invoiceId = $response->json('invoice.id');
        $invoice = PurchaseInvoice::query()->find($invoiceId);
        $this->assertNotNull($invoice);

        // 1. Category context routed authoritatively to Vendor Purchase - Cash
        $this->assertSame($cashSetting->id, $invoice->shop_ledger_entry_setting_id);
        $this->assertSame($cashSetting->header_group_id, $invoice->original_header_group_id);
        $this->assertSame(750.0, (float) $invoice->amount);

        // 2. Category context preserved on PurchaserCart
        $cart = PurchaserCart::query()->where('purchase_invoice_id', $invoice->id)->first();
        $this->assertNotNull($cart);
        $this->assertSame($cashSetting->id, $cart->shop_ledger_entry_setting_id);

        // 3. Cashbook posting uses the Cash category's specific entry type and header
        $transaction = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', (string) $invoice->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame($cashSetting->entry_type_id, $transaction->entry_type_id);
        $this->assertSame(750.0, (float) $transaction->amount);
    }

    public function test_credit_purchase_authoritatively_routes_to_credit_category_and_creates_payable_without_cash_movement(): void
    {
        $creditSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('vendor_purchase_payment_type', 'credit')
            ->firstOrFail();

        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategoryDefinedOnly->id,
            'supplier_id' => $this->activeVendor1->id, // Credit approved
            'payment_method' => 'Credit',
            'business_date' => '2026-09-16',
            'bill_number' => 'CREDIT-ONION-02',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 100,
                    'total_price' => 1500.0,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $invoiceId = $response->json('invoice.id');
        $invoice = PurchaseInvoice::query()->find($invoiceId);
        $this->assertNotNull($invoice);
        $this->assertSame($creditSetting->id, $invoice->shop_ledger_entry_setting_id);
        $this->assertSame($creditSetting->header_group_id, $invoice->original_header_group_id);

        // ShopVendorPayable created with Credit category context and original header snapshot
        $payable = ShopVendorPayable::query()->where('purchase_invoice_id', $invoice->id)->first();
        $this->assertNotNull($payable);
        $this->assertSame($creditSetting->id, $payable->shop_ledger_entry_setting_id);
        $this->assertSame($creditSetting->header_group_id, $payable->original_header_group_id);
        $this->assertSame(1500.0, (float) $payable->outstanding_amount);

        // No cash movement transaction for credit purchase
        $this->assertDatabaseMissing('shop_ledger_transactions', [
            'shop_id' => $this->shop->id,
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => (string) $invoice->id,
        ]);
    }

    public function test_legacy_vendor_purchase_records_remain_untouched_after_split_migration(): void
    {
        $legacyType = LedgerEntryType::where('code', 'vendor_purchase')->firstOrFail();

        // Create a historical legacy setting and invoice
        $legacySetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $legacyType->id,
            'display_name' => 'Legacy Vendor Purchase',
            'header_group_id' => $this->headerGroup->id,
            'is_vendor_purchase' => true,
            'vendor_purchase_payment_type' => null,
            'vendor_access_mode' => 'linked_create',
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
        ]);

        $service = app(ShopPurchaseService::class);
        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->activeVendor1->id,
            'payment_method' => 'Cash',
            'business_date' => '2026-09-10',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 10.0],
            ],
        ], $this->shopUser);

        // Re-run sync to ensure migration preserves the legacy record
        app(CashbookShopSyncService::class)->ensureVendorPurchaseForShop((int) $this->shop->id);

        $this->assertDatabaseHas('shop_ledger_entry_settings', [
            'id' => $legacySetting->id,
            'shop_id' => $this->shop->id,
            'entry_type_id' => $legacyType->id,
        ]);

        $this->assertDatabaseHas('purchase_invoices', [
            'id' => $invoice->id,
        ]);
    }

    public function test_defined_only_vendor_mappings_carry_forward_to_both_split_categories(): void
    {
        $newShop = Shop::query()->create([
            'name' => 'Koramangala Fresh 2',
            'code' => 'KOR_02',
            'warehouse_tag' => 'KOR2',
            'status' => 'active',
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_purchasing_enabled' => true,
        ]);

        $newSupplier = Supplier::query()->create([
            'name' => 'Exclusive Tomato Supplier',
            'mobile_number' => '9800000099',
            'type' => 'local',
            'category' => 'shop_vendor',
        ]);

        $newShopSupplier = ShopSupplier::query()->create([
            'shop_id' => $newShop->id,
            'supplier_id' => $newSupplier->id,
            'is_active' => true,
        ]);

        $legacyType = LedgerEntryType::where('code', 'vendor_purchase')->firstOrFail();
        $legacySetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $newShop->id,
            'entry_type_id' => $legacyType->id,
            'display_name' => 'Legacy Other Shop VP',
            'header_group_id' => null,
            'is_vendor_purchase' => true,
            'vendor_access_mode' => 'defined_only',
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
        ]);
        $legacySetting->definedShopSuppliers()->sync([$newShopSupplier->id]);

        // Sync split categories for newShop
        app(CashbookShopSyncService::class)->ensureVendorPurchaseForShop((int) $newShop->id);

        $cashSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $newShop->id)
            ->where('vendor_purchase_payment_type', 'cash')
            ->firstOrFail();

        $creditSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $newShop->id)
            ->where('vendor_purchase_payment_type', 'credit')
            ->firstOrFail();

        $this->assertSame('defined_only', $cashSetting->vendor_access_mode);
        $this->assertSame('defined_only', $creditSetting->vendor_access_mode);

        $this->assertTrue($cashSetting->definedShopSuppliers->contains('id', $newShopSupplier->id));
        $this->assertTrue($creditSetting->definedShopSuppliers->contains('id', $newShopSupplier->id));
    }

    public function test_credit_category_visible_under_header_without_cash_reduction(): void
    {
        $creditHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Credit Liabilities',
            'type' => 'expense',
            'display_order' => 2,
            'enabled' => true,
        ]);

        $creditSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('vendor_purchase_payment_type', 'credit')
            ->firstOrFail();

        $creditSetting->update([
            'header_group_id' => $creditHeader->id,
            'display_name' => 'Vendor Purchase - Credit',
        ]);

        // Create a Credit purchase
        $service = app(ShopPurchaseService::class);
        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->activeVendor1->id,
            'payment_method' => 'Credit',
            'business_date' => '2026-09-16',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 50.0],
            ],
        ], $this->shopUser);

        // Load cashbook view
        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.show', ['date' => '2026-09-16']));
        $response->assertOk();
        $response->assertSee('Credit Liabilities');
        $response->assertSee('Vendor Purchase - Credit');

        // Confirm no cash transaction exists
        $this->assertDatabaseMissing('shop_ledger_transactions', [
            'shop_id' => $this->shop->id,
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => (string) $invoice->id,
        ]);
    }

    public function test_changing_category_header_preserves_historical_purchase_header_snapshot(): void
    {
        $oldHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Old Procurement Header',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $newHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'New Central Header',
            'type' => 'expense',
            'display_order' => 2,
            'enabled' => true,
        ]);

        $cashSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('vendor_purchase_payment_type', 'cash')
            ->firstOrFail();

        $cashSetting->update(['header_group_id' => $oldHeader->id]);

        $service = app(ShopPurchaseService::class);
        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->activeVendor1->id,
            'payment_method' => 'Cash',
            'business_date' => '2026-09-16',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 20.0],
            ],
        ], $this->shopUser);

        $this->assertSame($oldHeader->id, $invoice->original_header_group_id);

        // Change category header to newHeader
        $cashSetting->update(['header_group_id' => $newHeader->id]);

        // Historical invoice's original_header_group_id must remain oldHeader->id
        $this->assertSame($oldHeader->id, $invoice->fresh()->original_header_group_id);
    }

    public function test_settled_or_partially_settled_credit_purchase_cannot_convert_to_cash(): void
    {
        $service = app(ShopPurchaseService::class);
        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->activeVendor1->id,
            'payment_method' => 'Credit',
            'business_date' => '2026-09-16',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 100.0],
            ],
        ], $this->shopUser);

        $payable = ShopVendorPayable::query()->where('purchase_invoice_id', $invoice->id)->firstOrFail();
        $payable->update([
            'paid_amount' => 200.0,
            'outstanding_amount' => 800.0,
        ]);

        // Attempting to switch payment_method to Cash must fail with 422
        $response = $this->actingAs($this->shopUser)->putJson(route('shop-owner.cashbook.vendor-purchases.update', $invoice->id), [
            'supplier_id' => $this->activeVendor1->id,
            'payment_method' => 'Cash',
            'business_date' => '2026-09-16',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'total_price' => 1000.0],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Cannot switch payment method to Cash because payments have already been recorded on this payable.',
        ]);
    }

    public function test_repeated_sync_is_idempotent_and_does_not_create_duplicate_categories(): void
    {
        $syncService = app(CashbookShopSyncService::class);

        $syncService->ensureVendorPurchaseForShop((int) $this->shop->id);
        $syncService->ensureVendorPurchaseForShop((int) $this->shop->id);
        $syncService->ensureVendorPurchaseForShop((int) $this->shop->id);

        $cashCount = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('vendor_purchase_payment_type', 'cash')
            ->count();

        $creditCount = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('vendor_purchase_payment_type', 'credit')
            ->count();

        $this->assertSame(1, $cashCount);
        $this->assertSame(1, $creditCount);
    }

    public function test_admin_routing_endpoint_updates_cash_and_credit_headers_independently(): void
    {
        $cashHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Admin Direct Cash Header',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $creditHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Admin Payables Header',
            'type' => 'expense',
            'display_order' => 2,
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('admin.cashbook.settings.shop.vendors.index', $this->shop->id))
            ->post(
                route('admin.cashbook.settings.shop.vendors.update-routing', $this->shop->id),
                [
                    'cash_header_group_id' => $cashHeader->id,
                    'credit_header_group_id' => $creditHeader->id,
                ]
            );

        $response->assertRedirect(route('admin.cashbook.settings.shop.vendors.index', $this->shop->id));
        $response->assertSessionHas('success');

        $cashSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('vendor_purchase_payment_type', 'cash')
            ->firstOrFail();

        $creditSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('vendor_purchase_payment_type', 'credit')
            ->firstOrFail();

        $this->assertSame($cashHeader->id, $cashSetting->header_group_id);
        $this->assertSame($creditHeader->id, $creditSetting->header_group_id);
    }

    public function test_shop_owner_cashbook_and_vendor_purchases_succeed_when_previous_purchasing_day_is_unfinalized(): void
    {
        $day1 = '2026-09-14';
        $day2 = '2026-09-15';

        // 1. Create an unfinalized purchase bill on Day 1 by a purchaser
        $purchaserUser = User::factory()->create(['name' => 'Field Purchaser']);
        $purchaserUser->assignRole('purchaser');

        $purchaseService = app(ShopPurchaseService::class);
        $purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->activeVendor1->id,
            'business_date' => $day1,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 30],
            ],
        ], $purchaserUser);

        // 2. Shop Owner Cashbook loads successfully on Day 2
        $cashbookResponse = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.show', [
            'date' => $day2,
        ]));
        $cashbookResponse->assertOk();

        // 3. Shop Owner Cash Vendor Purchase succeeds on Day 2
        $cashPurchaseResponse = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategoryLinkedCreate->id,
            'supplier_id' => $this->activeVendor1->id,
            'payment_method' => 'Cash',
            'business_date' => $day2,
            'bill_number' => 'SHOP-CASH-DAY2',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 50, 'total_price' => 1500.0],
            ],
        ]);
        $cashPurchaseResponse->assertOk();
        $cashPurchaseResponse->assertJson(['success' => true]);

        // 4. Shop Owner Credit Vendor Purchase succeeds on Day 2
        $creditPurchaseResponse = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategoryLinkedCreate->id,
            'supplier_id' => $this->activeVendor1->id,
            'payment_method' => 'Credit',
            'business_date' => $day2,
            'bill_number' => 'SHOP-CREDIT-DAY2',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 30, 'total_price' => 900.0],
            ],
        ]);
        $creditPurchaseResponse->assertOk();
        $creditPurchaseResponse->assertJson(['success' => true]);

        // 5. Central Purchaser attempting to record purchase on Day 2 is STILL blocked because Day 1 is not finalized
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Previous purchasing day ({$day1}) is not finalized");

        $purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->activeVendor1->id,
            'business_date' => $day2,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 30],
            ],
        ], $purchaserUser);
    }
}
