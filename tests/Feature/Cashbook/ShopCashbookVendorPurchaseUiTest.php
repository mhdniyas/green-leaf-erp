<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopVendorPayable;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Purchasing\ShopVendorReportService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCashbookVendorPurchaseUiTest extends TestCase
{
    use RefreshDatabase;

    private User $shopUser;

    private Shop $shop;

    private Supplier $activeVendorWithCredit;

    private Supplier $activeVendorWithoutCredit;

    private Supplier $disabledVendor;

    private Supplier $unlinkedVendor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->shop = Shop::query()->create([
            'name' => 'Indiranagar Fresh',
            'code' => 'IND_01',
            'warehouse_tag' => 'IND',
            'status' => 'active',
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_purchasing_enabled' => true,
        ]);

        $this->shopUser = User::factory()->create(['shop_id' => $this->shop->id]);
        $this->shopUser->assignRole('shop');
        $this->shopUser->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);

        $this->activeVendorWithCredit = Supplier::query()->create([
            'name' => 'Kisan Organic Supplies',
            'mobile_number' => '9845011111',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
        ]);
        $this->shop->suppliers()->attach($this->activeVendorWithCredit->id, ['is_active' => true]);

        $this->activeVendorWithoutCredit = Supplier::query()->create([
            'name' => 'Cash Only Mandi',
            'mobile_number' => '9845022222',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => false,
        ]);
        $this->shop->suppliers()->attach($this->activeVendorWithoutCredit->id, ['is_active' => true]);

        $this->disabledVendor = Supplier::query()->create([
            'name' => 'Disabled Vendor Ltd',
            'mobile_number' => '9845033333',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
        ]);
        $this->shop->suppliers()->attach($this->disabledVendor->id, ['is_active' => false]);

        $this->unlinkedVendor = Supplier::query()->create([
            'name' => 'Unlinked Vendor External',
            'mobile_number' => '9845044444',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Fresh Spinach',
            'sku' => 'SPN-001',
            'unit' => 'bunch',
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_vendor_purchase_button_is_visible_when_purchasing_is_enabled(): void
    {
        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.vendor-purchases'));

        $response->assertOk();
        $response->assertSee('New Vendor Purchase');
        $response->assertSee('VENDOR PURCHASE');
        $response->assertSee('Kisan Organic Supplies');
        $response->assertSee('Cash Only Mandi');
        // Disabled vendor and unlinked vendor must NOT be in the active vendor dropdown
        $response->assertDontSee('Disabled Vendor Ltd (9845033333)');
        $response->assertDontSee('Unlinked Vendor External (9845044444)');
    }

    public function test_vendor_purchase_button_is_hidden_when_purchasing_is_disabled(): void
    {
        $this->shop->update(['shop_purchasing_enabled' => false]);

        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.show'));

        $response->assertOk();
        $response->assertDontSee('+ Vendor Purchase');
        $response->assertDontSee('id="vendor-purchase-modal"', false);
    }

    public function test_cashbook_cash_purchase_creates_invoice_and_cashbook_entry_without_liability(): void
    {
        $businessDate = '2026-09-15';

        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $this->activeVendorWithCredit->id,
            'payment_method' => 'Cash',
            'business_date' => $businessDate,
            'bill_number' => 'BILL-CSH-001',
            'notes' => 'Cash purchase for morning greens',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 15,
                    'rate' => 20.0,
                    'unit' => 'bunch',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Invoice created
        $invoice = PurchaseInvoice::query()->where('invoice_number', 'BILL-CSH-001')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(300.0, (float) $invoice->amount);
        $this->assertSame('Cash', $invoice->payment_method);

        // Shop ledger transaction recorded
        $tx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', (string) $invoice->id)
            ->first();

        $this->assertNotNull($tx);
        $this->assertSame(300.0, (float) $tx->amount);

        // Zero vendor liability
        $this->assertDatabaseMissing('shop_vendor_payables', [
            'purchase_invoice_id' => $invoice->id,
        ]);

        // Appears in vendor report
        $reportService = app(ShopVendorReportService::class);
        $summary = $reportService->getShopVendorSummary($this->shop, ['supplier_id' => $this->activeVendorWithCredit->id]);
        $this->assertSame(300.0, $summary['summary']['total_purchase']);
        $this->assertSame(300.0, $summary['summary']['cash_purchase']);
        $this->assertSame(0.0, $summary['summary']['credit_outstanding']);
    }

    public function test_cashbook_credit_purchase_creates_shop_vendor_liability_and_no_cash_movement(): void
    {
        $businessDate = '2026-09-15';

        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $this->activeVendorWithCredit->id,
            'payment_method' => 'Credit',
            'business_date' => $businessDate,
            'bill_number' => 'BILL-CRD-002',
            'notes' => 'Credit purchase from Kisan Organics',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 25,
                    'unit_price' => 18.0,
                    'unit' => 'bunch',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $invoice = PurchaseInvoice::query()->where('invoice_number', 'BILL-CRD-002')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(450.0, (float) $invoice->amount);
        $this->assertSame('Credit', $invoice->payment_method);

        // ShopVendorPayable created with shop scope
        $payable = ShopVendorPayable::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->where('shop_id', $this->shop->id)
            ->first();

        $this->assertNotNull($payable);
        $this->assertSame(450.0, (float) $payable->outstanding_amount);
        $this->assertSame(450.0, (float) $payable->original_amount);

        // Zero cash ledger transaction
        $this->assertDatabaseMissing('shop_ledger_transactions', [
            'shop_id' => $this->shop->id,
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => (string) $invoice->id,
        ]);

        // Appears in vendor report as credit purchase with outstanding liability
        $reportService = app(ShopVendorReportService::class);
        $summary = $reportService->getShopVendorSummary($this->shop, ['supplier_id' => $this->activeVendorWithCredit->id]);
        $this->assertSame(450.0, $summary['summary']['total_purchase']);
        $this->assertSame(450.0, $summary['summary']['credit_purchase']);
        $this->assertSame(450.0, $summary['summary']['credit_outstanding']);
    }

    public function test_credit_purchase_is_rejected_when_vendor_has_credit_disabled(): void
    {
        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $this->activeVendorWithoutCredit->id,
            'payment_method' => 'Credit',
            'business_date' => '2026-09-15',
            'bill_number' => 'BILL-REJECT-001',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'rate' => 25.0,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => "Credit purchases are not enabled for vendor 'Cash Only Mandi'.",
        ]);

        // Verify invoice was NOT created
        $this->assertDatabaseMissing('purchase_invoices', [
            'invoice_number' => 'BILL-REJECT-001',
        ]);
    }

    public function test_credit_purchase_checks_shop_specific_pivot_credit_approval(): void
    {
        // Vendor has global credit_approved = false, but shop has overridden credit_approved = true on pivot
        $this->shop->suppliers()->updateExistingPivot($this->activeVendorWithoutCredit->id, [
            'credit_approved' => true,
        ]);

        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $this->activeVendorWithoutCredit->id,
            'payment_method' => 'Credit',
            'business_date' => '2026-09-15',
            'bill_number' => 'BILL-PIVOT-001',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'rate' => 30.0,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('shop_vendor_payables', [
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->activeVendorWithoutCredit->id,
            'outstanding_amount' => 300.0,
        ]);
    }

    public function test_credit_purchase_is_rejected_when_shop_pivot_disables_credit_even_if_global_is_true(): void
    {
        // Vendor has global credit_approved = true, but shop explicitly set credit_approved = false on pivot
        $this->shop->suppliers()->updateExistingPivot($this->activeVendorWithCredit->id, [
            'credit_approved' => false,
        ]);

        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $this->activeVendorWithCredit->id,
            'payment_method' => 'Credit',
            'business_date' => '2026-09-15',
            'bill_number' => 'BILL-PIVOT-REJECT-002',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'rate' => 30.0,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => "Credit purchases are not enabled for vendor '{$this->activeVendorWithCredit->name}'.",
        ]);
    }

    public function test_shop_owner_cashbook_shows_vendor_purchase_only_when_purchasing_is_enabled(): void
    {
        // 1. When disabled
        $this->shop->update(['shop_purchasing_enabled' => false]);
        $responseDisabled = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.show'));
        $responseDisabled->assertOk();
        $responseDisabled->assertDontSee('+ Vendor Purchase');
        $responseDisabled->assertDontSee('id="vendor-purchase-modal"', false);

        // 2. Dedicated page when enabled
        $this->shop->update(['shop_purchasing_enabled' => true]);
        $responseEnabled = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.vendor-purchases'));
        $responseEnabled->assertOk();
        $responseEnabled->assertSee('New Vendor Purchase');
        $responseEnabled->assertSee('id="vendor-purchase-modal"', false);
        $responseEnabled->assertSee($this->activeVendorWithCredit->name);
    }

    public function test_shop_owner_vendor_purchases_hides_new_vendor_button_when_vendor_creation_is_disabled(): void
    {
        $this->shop->update(['shop_purchasing_enabled' => true, 'allow_vendor_creation' => false]);
        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.vendor-purchases'));
        $response->assertOk();
        $response->assertDontSee('id="vp-new-vendor-btn-container"', false);
        $response->assertDontSee('id="vp-new-vendor-modal"', false);
    }

    public function test_shop_owner_vendor_purchases_shows_new_vendor_button_when_vendor_creation_is_enabled(): void
    {
        $this->shop->update(['shop_purchasing_enabled' => true, 'allow_vendor_creation' => true]);
        $user = User::find($this->shopUser->id);
        $response = $this->actingAs($user)->get(route('shop-owner.cashbook.vendor-purchases'));
        $response->assertOk();
        $response->assertSee('id="vp-new-vendor-btn-container"', false);
        $response->assertSee('id="vp-new-vendor-modal"', false);
    }

    public function test_shop_owner_creates_vendor_via_purchasing_endpoint_and_records_cash_purchase(): void
    {
        $this->shop->update(['shop_purchasing_enabled' => true, 'allow_vendor_creation' => true]);

        // 1. Create new vendor via shop owner endpoint
        $createResponse = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.vendors.store'), [
            'name' => 'Green Valley Produce',
            'mobile_number' => '9845099999',
            'contact' => 'Gst: 29AAAAA0000A1Z5',
            'notes' => 'Fresh direct deliveries',
        ]);

        $createResponse->assertOk();
        $createResponse->assertJson([
            'success' => true,
            'vendor' => [
                'name' => 'Green Valley Produce',
                'mobile_number' => '9845099999',
                'credit_approved' => false,
            ],
        ]);

        $vendorId = $createResponse->json('vendor.id');
        $this->assertNotNull($vendorId);

        // 2. Perform Cash purchase -> succeeds
        $cashPurchaseResponse = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $vendorId,
            'payment_method' => 'Cash',
            'business_date' => '2026-09-15',
            'bill_number' => 'BILL-GV-001',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'rate' => 25.0,
                ],
            ],
        ]);

        $cashPurchaseResponse->assertOk();
        $cashPurchaseResponse->assertJson(['success' => true]);
    }

    public function test_shop_owner_cannot_record_credit_purchase_on_newly_created_vendor_until_admin_approves(): void
    {
        $this->shop->update(['shop_purchasing_enabled' => true, 'allow_vendor_creation' => true]);

        $createResponse = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.vendors.store'), [
            'name' => 'Credit Test Vendor',
            'mobile_number' => '9845088888',
        ]);
        $createResponse->assertOk();
        $vendorId = $createResponse->json('vendor.id');

        // Credit purchase rejected initially
        $creditResponse = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $vendorId,
            'payment_method' => 'Credit',
            'business_date' => '2026-09-15',
            'bill_number' => 'BILL-CRED-REJECT-001',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'rate' => 50.0,
                ],
            ],
        ]);

        $creditResponse->assertStatus(422);
        $creditResponse->assertJson([
            'success' => false,
            'message' => "Credit purchases are not enabled for vendor 'Credit Test Vendor'.",
        ]);

        // After admin approves credit on pivot
        $this->shop->suppliers()->updateExistingPivot($vendorId, ['credit_approved' => true]);

        $creditApprovedResponse = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $vendorId,
            'payment_method' => 'Credit',
            'business_date' => '2026-09-15',
            'bill_number' => 'BILL-CRED-APPROVED-001',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'rate' => 50.0,
                ],
            ],
        ]);

        $creditApprovedResponse->assertOk();
        $creditApprovedResponse->assertJson(['success' => true]);
    }

    public function test_cashbook_show_provides_purchasable_products_list(): void
    {
        $this->shop->update(['shop_purchasing_enabled' => true]);

        $prod = Product::factory()->create([
            'name' => 'Fresh Tomato',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'base_price' => 20.0,
        ]);

        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.show', ['date' => '2026-09-15']));
        $response->assertOk();

        $products = $response->viewData('purchasableProducts');
        $this->assertNotEmpty($products);

        $p1 = collect($products)->firstWhere('id', $prod->id);
        $this->assertNotNull($p1);
        $this->assertEquals('TOM-001', $p1['sku']);
        $this->assertEquals('kg', $p1['unit']);
        $this->assertEquals('Fresh Tomato', $p1['name']);
    }

    public function test_cashbook_purchase_with_total_price_derives_unit_price_server_side(): void
    {
        $businessDate = '2026-09-15';

        // 100 kg Tomato for ₹1,000 Total Price -> Server derives rate = ₹10.00
        $response = $this->actingAs($this->shopUser)->postJson(route('shop-owner.purchasing.store'), [
            'supplier_id' => $this->activeVendorWithCredit->id,
            'payment_method' => 'Cash',
            'business_date' => $businessDate,
            'bill_number' => 'BILL-TOTAL-001',
            'notes' => 'Bulk Tomato Purchase',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 100,
                    'total_price' => 1000.0,
                    'unit' => 'kg',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $invoice = PurchaseInvoice::query()->where('invoice_number', 'BILL-TOTAL-001')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(1000.0, (float) $invoice->amount);

        // Cart item derived unit_price
        $cartItem = $invoice->purchaserCart->items->first();
        $this->assertNotNull($cartItem);
        $this->assertSame(100.0, (float) $cartItem->quantity);
        $this->assertSame(10.0, (float) $cartItem->unit_price);
        $this->assertSame(1000.0, (float) $cartItem->line_total);
    }

    public function test_vendor_purchase_modal_contains_custom_searchable_dropdown_and_average_price_ui(): void
    {
        $this->shop->update(['shop_purchasing_enabled' => true]);

        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.vendor-purchases', ['date' => '2026-09-15']));
        $response->assertOk();

        $response->assertSee('toggleVpProductDropdown', false);
        $response->assertSee('renderVpProductOptions', false);
        $response->assertSee('updateRowAveragePriceDisplay', false);
        $response->assertSee('Avg Buy', false);
        $response->assertSee('Avg Buy: —', false);
        $response->assertSee('closeAllVpProductDropdowns', false);
        $response->assertSee('vp-prod-search-', false);
        $response->assertSee('vp-prod-menu-', false);
        $response->assertSee('vp-item-product', false);
        $response->assertSee('vp-item-qty', false);
        $response->assertSee('vp-item-total', false);
        $response->assertSee('Total Price (₹)', false);
    }

    public function test_vendor_purchase_modal_contains_custom_searchable_vendor_dropdown(): void
    {
        $this->shop->update(['shop_purchasing_enabled' => true]);

        $response = $this->actingAs($this->shopUser)->get(route('shop-owner.cashbook.vendor-purchases', ['date' => '2026-09-15']));
        $response->assertOk();

        // Custom vendor dropdown markup
        $response->assertSee('vp-vendor-dropdown-container', false);
        $response->assertSee('vp-vendor-btn', false);
        $response->assertSee('vp-vendor-menu', false);
        $response->assertSee('vp-vendor-search', false);
        $response->assertSee('vp-vendor-options', false);
        $response->assertSee('type="hidden" id="vp-vendor-select"', false);
        $response->assertDontSee('<select id="vp-vendor-select"', false);

        // Custom vendor dropdown JS methods
        $response->assertSee('toggleVpVendorDropdown', false);
        $response->assertSee('renderVpVendorOptions', false);
        $response->assertSee('selectVpVendor', false);
        $response->assertSee('closeVpVendorDropdown', false);
    }
}
