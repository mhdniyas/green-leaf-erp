<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProductEntry;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopSupplier;
use App\Models\ShopVendorPayable;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Purchasing\ShopPurchaseService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopOwnerVendorPurchaseCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopOwner;

    private User $otherShopOwner;

    private User $purchaserUser;

    private Shop $shop;

    private Shop $otherShop;

    private Supplier $vendorA;

    private Supplier $vendorB;

    private Product $mango;

    private Product $blackGrapes;

    private ShopLedgerHeaderGroup $cashPurchaseHeader;

    private ShopLedgerHeaderGroup $expensesHeader;

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

        $this->shopOwner = User::factory()->create([
            'email_verified_at' => now(),
            'shop_id' => $this->shop->id,
        ]);
        $this->shopOwner->assignRole('shop');

        $this->otherShopOwner = User::factory()->create([
            'email_verified_at' => now(),
            'shop_id' => $this->otherShop->id,
        ]);
        $this->otherShopOwner->assignRole('shop');

        $this->purchaserUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->purchaserUser->assignRole('purchaser');

        $this->vendorA = Supplier::query()->create([
            'name' => 'Alpha Fruits',
            'mobile_number' => '9876543210',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
            'is_active' => true,
        ]);

        $this->vendorB = Supplier::query()->create([
            'name' => 'Beta Farms',
            'mobile_number' => '9876543211',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
            'is_active' => true,
        ]);

        ShopSupplier::query()->create([
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->vendorA->id,
            'is_active' => true,
            'credit_approved' => true,
        ]);

        ShopSupplier::query()->create([
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->vendorB->id,
            'is_active' => true,
            'credit_approved' => true,
        ]);

        $this->mango = Product::factory()->create([
            'name' => 'Alphonso Mango',
            'sku' => 'MNG_01',
            'unit' => 'box',
            'base_price' => 10.02,
            'is_active' => true,
        ]);

        $this->blackGrapes = Product::factory()->create([
            'name' => 'Akash Black',
            'sku' => 'GRP_01',
            'unit' => 'kg',
            'base_price' => 101.00,
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->cashPurchaseHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Cash Purchase',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
            'is_active' => true,
            'product_tagging_enabled' => true,
        ]);

        $this->expensesHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Other Expenses',
            'type' => 'expense',
            'display_order' => 2,
            'enabled' => true,
            'is_active' => true,
            'product_tagging_enabled' => false,
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
                'header_group_id' => $this->cashPurchaseHeader->id,
                'display_name' => 'Local Vendor Purchase',
                'display_order' => 1,
                'effective_from' => '2026-01-01',
                'enabled' => true,
                'default_funding_source' => 'sales',
                'funding_source' => 'sales',
                'affects_closing_balance' => true,
                'is_vendor_purchase' => true,
                'mirror_to_cashbook' => true,
                'vendor_access_mode' => 'linked_create',
                'vendor_settlement_relation_id' => $this->settlementRelation->id,
            ]
        );
    }

    public function test_create_vendor_purchase_works_and_mirrored_total_matches(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        $response = $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 121, 'unit_price' => 10.0165, 'unit' => 'box'],
                ['product_id' => $this->blackGrapes->id, 'quantity' => 12, 'unit_price' => 101.00, 'unit' => 'kg'],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        /** @var PurchaseInvoice $invoice */
        $invoice = PurchaseInvoice::query()->where('shop_id', $this->shop->id)->firstOrFail();
        $this->assertEquals(2424.00, (float) $invoice->amount);
        $this->assertEquals('paid', $invoice->status->value);

        // Mirrored cashbook transaction exists for cash purchase
        $mirroredTx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', (string) $invoice->id)
            ->where('status', '!=', 'void')
            ->first();

        $this->assertNotNull($mirroredTx);
        $this->assertEquals(2424.00, (float) $mirroredTx->amount);

        // Individual products must NOT be added to ShopLedgerProductEntry
        $productEntriesCount = ShopLedgerProductEntry::query()->where('shop_id', $this->shop->id)->count();
        $this->assertEquals(0, $productEntriesCount);
    }

    public function test_mirror_off_does_not_create_cashbook_transaction(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        $this->vendorCategory->update(['mirror_to_cashbook' => false]);

        $response = $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 10, 'unit_price' => 100],
            ],
        ]);

        $response->assertOk();

        $invoice = PurchaseInvoice::query()->where('shop_id', $this->shop->id)->firstOrFail();
        $this->assertEquals(1000.00, (float) $invoice->amount);

        // No mirrored transaction in cashbook
        $mirroredTx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', (string) $invoice->id)
            ->where('status', '!=', 'void')
            ->first();

        $this->assertNull($mirroredTx);
    }

    public function test_dedicated_vendor_purchases_page_loads_with_filters_and_totals(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 121, 'unit_price' => 10.0165, 'unit' => 'box'],
                ['product_id' => $this->blackGrapes->id, 'quantity' => 12, 'unit_price' => 101.00, 'unit' => 'kg'],
            ],
        ]);

        $response = $this->actingAs($this->shopOwner)->get(route('shop-owner.cashbook.vendor-purchases', [
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Vendor Purchases');
        $response->assertSee('Alpha Fruits');
        $response->assertSee('2,424.00');
        $response->assertSee('Cash');
        $response->assertSee('2 items');
        $response->assertSee('View');
        $response->assertSee('Edit');
        $response->assertSee('Delete');
    }

    public function test_view_purchase_details_returns_avg_buy_and_items(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 121, 'unit_price' => 10.0165, 'unit' => 'box'],
                ['product_id' => $this->blackGrapes->id, 'quantity' => 12, 'unit_price' => 101.00, 'unit' => 'kg'],
            ],
        ]);

        $invoice = PurchaseInvoice::query()->where('shop_id', $this->shop->id)->firstOrFail();

        $response = $this->actingAs($this->shopOwner)->getJson(route('shop-owner.cashbook.vendor-purchases.show', $invoice));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'purchase' => [
                'id' => $invoice->id,
                'supplier_name' => 'Alpha Fruits',
                'net_amount' => 2424.00,
                'payment_method' => 'Cash',
            ],
        ]);

        $data = $response->json('purchase.items');
        $this->assertCount(2, $data);
        $this->assertEquals('Alphonso Mango', $data[0]['product_name']);
        $this->assertEquals(121, $data[0]['quantity']);
        $this->assertEquals(10.02, $data[0]['avg_buy']); // line_total / qty rounded
    }

    public function test_edit_qty_and_total_synchronizes_cashbook_total(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 100, 'unit_price' => 10],
            ],
        ]);

        $invoice = PurchaseInvoice::query()->where('shop_id', $this->shop->id)->firstOrFail();
        $this->assertEquals(1000.00, (float) $invoice->amount);

        // Edit purchase: quantity 150 @ ₹12 = ₹1,800
        $response = $this->actingAs($this->shopOwner)->putJson(route('shop-owner.cashbook.vendor-purchases.update', $invoice), [
            'supplier_id' => $this->vendorA->id,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 150, 'unit_price' => 12],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $invoice->refresh();
        $this->assertEquals(1800.00, (float) $invoice->amount);

        // Mirrored cashbook transaction must be updated to 1800
        $mirroredTx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', (string) $invoice->id)
            ->where('status', '!=', 'void')
            ->first();

        $this->assertNotNull($mirroredTx);
        $this->assertEquals(1800.00, (float) $mirroredTx->amount);
    }

    public function test_edit_vendor_and_credit_vendor_reassignment_updates_payable_cleanly(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // 1. Create Credit purchase with Vendor A
        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 50, 'unit_price' => 20],
            ],
        ]);

        $invoice = PurchaseInvoice::query()->where('shop_id', $this->shop->id)->firstOrFail();
        $payable = ShopVendorPayable::query()->where('purchase_invoice_id', $invoice->id)->firstOrFail();
        $this->assertEquals($this->vendorA->id, $payable->supplier_id);
        $this->assertEquals(1000.00, (float) $payable->outstanding_amount);

        // 2. Edit purchase to change Vendor A -> Vendor B
        $response = $this->actingAs($this->shopOwner)->putJson(route('shop-owner.cashbook.vendor-purchases.update', $invoice), [
            'supplier_id' => $this->vendorB->id,
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 50, 'unit_price' => 20],
            ],
        ]);

        $response->assertOk();

        $invoice->refresh();
        $this->assertEquals($this->vendorB->id, $invoice->supplier_id);

        $payable->refresh();
        // Payable must now belong to Vendor B, not Vendor A
        $this->assertEquals($this->vendorB->id, $payable->supplier_id);

        // Check no orphaned payables for Vendor A
        $orphanedCount = ShopVendorPayable::query()->where('supplier_id', $this->vendorA->id)->count();
        $this->assertEquals(0, $orphanedCount);
    }

    public function test_cash_to_credit_payment_switch_synchronizes_payable_and_cashbook(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // 1. Create Cash purchase
        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 100, 'unit_price' => 10],
            ],
        ]);

        $invoice = PurchaseInvoice::query()->where('shop_id', $this->shop->id)->firstOrFail();
        $this->assertEquals(1, ShopLedgerTransaction::where('reference_type', PurchaseInvoice::class)->where('reference_id', (string) $invoice->id)->where('status', '!=', 'void')->count());
        $this->assertEquals(0, ShopVendorPayable::where('purchase_invoice_id', $invoice->id)->count());

        // 2. Switch Cash -> Credit
        $response = $this->actingAs($this->shopOwner)->putJson(route('shop-owner.cashbook.vendor-purchases.update', $invoice), [
            'supplier_id' => $this->vendorA->id,
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 100, 'unit_price' => 10],
            ],
        ]);

        $response->assertOk();

        $invoice->refresh();
        $this->assertEquals('Credit', $invoice->payment_method);
        $this->assertEquals(InvoiceStatus::Pending, $invoice->status);

        // Mirrored Cashbook transaction must be removed
        $this->assertEquals(0, ShopLedgerTransaction::where('reference_type', PurchaseInvoice::class)->where('reference_id', (string) $invoice->id)->where('status', '!=', 'void')->count());

        // Vendor Payable must be created
        $payable = ShopVendorPayable::query()->where('purchase_invoice_id', $invoice->id)->first();
        $this->assertNotNull($payable);
        $this->assertEquals(1000.00, (float) $payable->outstanding_amount);
    }

    public function test_credit_to_cash_payment_switch_synchronizes_payable_and_cashbook(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // 1. Create Credit purchase
        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 50, 'unit_price' => 20],
            ],
        ]);

        $invoice = PurchaseInvoice::query()->where('shop_id', $this->shop->id)->firstOrFail();
        $this->assertEquals(1, ShopVendorPayable::where('purchase_invoice_id', $invoice->id)->count());
        $this->assertEquals(0, ShopLedgerTransaction::where('reference_type', PurchaseInvoice::class)->where('reference_id', (string) $invoice->id)->where('status', '!=', 'void')->count());

        // 2. Switch Credit -> Cash
        $response = $this->actingAs($this->shopOwner)->putJson(route('shop-owner.cashbook.vendor-purchases.update', $invoice), [
            'supplier_id' => $this->vendorA->id,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 50, 'unit_price' => 20],
            ],
        ]);

        $response->assertOk();

        $invoice->refresh();
        $this->assertEquals('Cash', $invoice->payment_method);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);

        // Vendor Payable must be deleted
        $this->assertEquals(0, ShopVendorPayable::where('purchase_invoice_id', $invoice->id)->count());

        // Mirrored Cashbook transaction must be recorded
        $mirroredTx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', (string) $invoice->id)
            ->where('status', '!=', 'void')
            ->first();

        $this->assertNotNull($mirroredTx);
        $this->assertEquals(1000.00, (float) $mirroredTx->amount);
    }

    public function test_delete_or_cancel_safely_reverses_mirrored_accounting(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 100, 'unit_price' => 10],
            ],
        ]);

        $invoice = PurchaseInvoice::query()->where('shop_id', $this->shop->id)->firstOrFail();

        // Mirrored transaction exists
        $this->assertEquals(1, ShopLedgerTransaction::where('reference_type', PurchaseInvoice::class)->where('reference_id', (string) $invoice->id)->where('status', '!=', 'void')->count());

        // Delete / Cancel purchase
        $response = $this->actingAs($this->shopOwner)->deleteJson(route('shop-owner.cashbook.vendor-purchases.destroy', $invoice), [
            'reason' => 'Wrong entry',
        ]);

        $response->assertOk();

        // Mirrored transaction is deleted/voided
        $this->assertEquals(0, ShopLedgerTransaction::where('reference_type', PurchaseInvoice::class)->where('reference_id', (string) $invoice->id)->where('status', '!=', 'void')->count());

        // Invoice is soft deleted and marked cancelled
        $trashedInvoice = PurchaseInvoice::withTrashed()->find($invoice->id);
        $this->assertNotNull($trashedInvoice->deleted_at);
        $this->assertEquals(InvoiceStatus::Cancelled, $trashedInvoice->status);
    }

    public function test_shop_owner_cannot_access_or_modify_other_shop_purchase(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // Create purchase for other shop
        $otherInvoice = app(ShopPurchaseService::class)->recordPurchase($this->otherShop, [
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 10, 'unit_price' => 10],
            ],
        ], $this->admin);

        // ShopOwner of Shop 1 tries to access / edit Shop 2 invoice
        $showRes = $this->actingAs($this->shopOwner)->getJson(route('shop-owner.cashbook.vendor-purchases.show', $otherInvoice));
        $showRes->assertForbidden();

        $updateRes = $this->actingAs($this->shopOwner)->putJson(route('shop-owner.cashbook.vendor-purchases.update', $otherInvoice), [
            'supplier_id' => $this->vendorA->id,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 20, 'unit_price' => 10],
            ],
        ]);
        $updateRes->assertForbidden();

        $deleteRes = $this->actingAs($this->shopOwner)->deleteJson(route('shop-owner.cashbook.vendor-purchases.destroy', $otherInvoice));
        $deleteRes->assertForbidden();
    }

    public function test_purchaser_role_cannot_access_vendor_purchases_page_or_crud(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        $response = $this->actingAs($this->purchaserUser)->get(route('shop-owner.cashbook.vendor-purchases'));
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');

        $storeRes = $this->actingAs($this->purchaserUser)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 10, 'unit_price' => 10],
            ],
        ]);
        $storeRes->assertForbidden();
    }

    public function test_cashbook_renders_compact_summary_and_no_old_entry_ui(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        // Create 2 purchases (one cash, one credit)
        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 121, 'unit_price' => 10.0165, 'unit' => 'box'],
                ['product_id' => $this->blackGrapes->id, 'quantity' => 12, 'unit_price' => 101.00, 'unit' => 'kg'],
            ],
        ]);

        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorB->id,
            'business_date' => $today,
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 10, 'unit_price' => 50.00, 'unit' => 'box'],
            ],
        ]);

        $response = $this->actingAs($this->shopOwner)->get(route('shop-owner.cashbook.show', ['date' => $today]));

        $response->assertOk();
        // Check old vendor purchase modal/button is NOT present on cashbook page
        $response->assertDontSee('openVendorPurchaseModalForCategory');
        $response->assertDontSee('id="vendor-purchase-modal"', false);

        // Check compact summary content in settingsJson
        $response->assertSee('Local Vendor Purchase');
        $response->assertSee('vendor-purchases');

        // Check cashbookData API returns correct vendor_purchase_summaries
        $dataResponse = $this->actingAs($this->shopOwner)->getJson(route('shop-owner.cashbook.api.shop-data', ['date' => $today]));
        $dataResponse->assertOk();
        $summary = $dataResponse->json('vendor_purchase_summaries.'.$this->vendorCategory->id);
        $this->assertNotNull($summary);
        $this->assertEquals(2, $summary['count']);
        $this->assertEquals(2424.00, (float) $summary['cash_amount']);
        $this->assertEquals(500.00, (float) $summary['credit_amount']);
        $this->assertEquals(2924.00, (float) $summary['total_amount']);
    }

    public function test_cashbook_open_vendor_purchase_query_redirects_to_dedicated_page(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        $response = $this->actingAs($this->shopOwner)->get(route('shop-owner.cashbook.show', [
            'date' => $today,
            'open' => 'vendor_purchase',
            'category_id' => $this->vendorCategory->id,
        ]));

        $response->assertRedirect(route('shop-owner.cashbook.vendor-purchases', [
            'date' => $today,
            'open' => 'new',
            'category_id' => $this->vendorCategory->id,
        ]));
    }

    public function test_dedicated_vendor_purchases_page_has_modal_and_scripts(): void
    {
        $response = $this->actingAs($this->shopOwner)->get(route('shop-owner.cashbook.vendor-purchases'));

        $response->assertOk();
        $response->assertSee('New Vendor Purchase');
        $response->assertSee('id="vendor-purchase-modal"', false);
        $response->assertSee('openVendorPurchaseModal');
        $response->assertSee('submitVendorPurchase');
    }

    public function test_shop_owner_can_edit_and_delete_purchase_within_3_days(): void
    {
        $date2DaysAgo = now('Asia/Kolkata')->subDays(2)->toDateString();

        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $date2DaysAgo,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 10, 'unit_price' => 50.00, 'unit' => 'box'],
            ],
        ]);

        $invoice = PurchaseInvoice::query()->where('shop_id', $this->shop->id)->firstOrFail();

        // 1. Show purchase returns is_edit_allowed = true
        $showResponse = $this->actingAs($this->shopOwner)->getJson(route('shop-owner.cashbook.vendor-purchases.show', $invoice));
        $showResponse->assertOk();
        $this->assertTrue($showResponse->json('purchase.is_edit_allowed'));

        // 2. Shop owner can update
        $updateResponse = $this->actingAs($this->shopOwner)->putJson(route('shop-owner.cashbook.vendor-purchases.update', $invoice), [
            'supplier_id' => $this->vendorA->id,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 15, 'unit_price' => 50.00, 'unit' => 'box'],
            ],
        ]);
        $updateResponse->assertOk();

        // 3. Shop owner can delete
        $deleteResponse = $this->actingAs($this->shopOwner)->deleteJson(route('shop-owner.cashbook.vendor-purchases.destroy', $invoice));
        $deleteResponse->assertOk();
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Cancelled, $invoice->status);
    }

    public function test_shop_owner_cannot_edit_or_delete_purchase_older_than_3_days(): void
    {
        $date5DaysAgo = now('Asia/Kolkata')->subDays(5)->toDateString();

        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $date5DaysAgo,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 10, 'unit_price' => 50.00, 'unit' => 'box'],
            ],
        ]);

        $invoice = PurchaseInvoice::query()->where('shop_id', $this->shop->id)->firstOrFail();

        // 1. Show purchase returns is_edit_allowed = false
        $showResponse = $this->actingAs($this->shopOwner)->getJson(route('shop-owner.cashbook.vendor-purchases.show', $invoice));
        $showResponse->assertOk();
        $this->assertFalse($showResponse->json('purchase.is_edit_allowed'));

        // 2. Shop owner cannot update (403)
        $updateResponse = $this->actingAs($this->shopOwner)->putJson(route('shop-owner.cashbook.vendor-purchases.update', $invoice), [
            'supplier_id' => $this->vendorA->id,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 15, 'unit_price' => 50.00, 'unit' => 'box'],
            ],
        ]);
        $updateResponse->assertStatus(403);

        // 3. Shop owner cannot delete (403)
        $deleteResponse = $this->actingAs($this->shopOwner)->deleteJson(route('shop-owner.cashbook.vendor-purchases.destroy', $invoice));
        $deleteResponse->assertStatus(403);

        // 4. Dedicated page shows Locked for older invoice
        $pageResponse = $this->actingAs($this->shopOwner)->get(route('shop-owner.cashbook.vendor-purchases', ['period' => 'all']));
        $pageResponse->assertOk();
        $pageResponse->assertSee('Locked');
    }

    public function test_admin_can_edit_and_delete_purchase_regardless_of_age(): void
    {
        $date10DaysAgo = now('Asia/Kolkata')->subDays(10)->toDateString();

        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $date10DaysAgo,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 10, 'unit_price' => 50.00, 'unit' => 'box'],
            ],
        ]);

        $invoice = PurchaseInvoice::query()->where('shop_id', $this->shop->id)->firstOrFail();

        // 1. Admin show purchase returns is_edit_allowed = true
        $showResponse = $this->actingAs($this->admin)->getJson(route('shop-owner.cashbook.vendor-purchases.show', [
            'invoice' => $invoice,
            'shop_id' => $this->shop->id,
        ]));
        $showResponse->assertOk();
        $this->assertTrue($showResponse->json('purchase.is_edit_allowed'));

        // 2. Admin can update older purchase
        $updateResponse = $this->actingAs($this->admin)->putJson(route('shop-owner.cashbook.vendor-purchases.update', [
            'invoice' => $invoice,
            'shop_id' => $this->shop->id,
        ]), [
            'supplier_id' => $this->vendorA->id,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 20, 'unit_price' => 50.00, 'unit' => 'box'],
            ],
        ]);
        $updateResponse->assertOk();

        // 3. Admin can delete older purchase
        $deleteResponse = $this->actingAs($this->admin)->deleteJson(route('shop-owner.cashbook.vendor-purchases.destroy', [
            'invoice' => $invoice,
            'shop_id' => $this->shop->id,
        ]));
        $deleteResponse->assertOk();

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Cancelled, $invoice->status);
    }

    public function test_numeric_invoice_id_resolves_route_for_show_update_and_delete(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        $this->actingAs($this->shopOwner)->postJson(route('shop-owner.purchasing.store'), [
            'shop_ledger_entry_setting_id' => $this->vendorCategory->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 5, 'unit_price' => 10.00, 'unit' => 'box'],
            ],
        ]);

        $invoice = PurchaseInvoice::query()->where('shop_id', $this->shop->id)->firstOrFail();
        $numericId = (int) $invoice->id;

        // 1. Direct GET /shop-owner/cashbook/vendor-purchases/{id}
        $showResponse = $this->actingAs($this->shopOwner)->getJson("/shop-owner/cashbook/vendor-purchases/{$numericId}");
        $showResponse->assertOk();
        $showResponse->assertJson([
            'success' => true,
            'purchase' => [
                'id' => $numericId,
                'invoice_number' => $invoice->invoice_number,
            ],
        ]);

        // 2. Direct PUT /shop-owner/cashbook/vendor-purchases/{id}
        $updateResponse = $this->actingAs($this->shopOwner)->putJson("/shop-owner/cashbook/vendor-purchases/{$numericId}", [
            'supplier_id' => $this->vendorA->id,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->mango->id, 'quantity' => 12, 'unit_price' => 10.00, 'unit' => 'box'],
            ],
        ]);
        $updateResponse->assertOk();
        $updateResponse->assertJson(['success' => true]);

        // 3. Direct DELETE /shop-owner/cashbook/vendor-purchases/{id}
        $deleteResponse = $this->actingAs($this->shopOwner)->deleteJson("/shop-owner/cashbook/vendor-purchases/{$numericId}");
        $deleteResponse->assertOk();
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Cancelled, $invoice->status);
    }
}
