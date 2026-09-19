<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopVendorPayable;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Purchasing\ShopPurchaseService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ShopCashbookVendorPurchaseCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopUser;

    private Shop $shop;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shop = Shop::query()->create([
            'name' => 'Koramangala Greens',
            'code' => 'KOR_01',
            'warehouse_tag' => 'KOR',
            'status' => 'active',
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_purchasing_enabled' => true,
        ]);

        $this->shopUser = User::factory()->create(['shop_id' => $this->shop->id]);
        $this->shopUser->assignRole('shop');
        $this->shopUser->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);

        $this->supplier = Supplier::query()->create([
            'name' => 'Fresh Farm Direct',
            'mobile_number' => '9876543210',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => true,
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Country Tomatoes',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_existing_shop_purchasing_enabled_flag_is_reused_and_toggled_via_general_settings(): void
    {
        $this->assertTrue($this->shop->fresh()->isPurchasingEnabled());

        // Toggle OFF via General Settings route
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.settings.shop.toggle-purchasing', $this->shop->id)
        );

        $response->assertRedirect(route('admin.cashbook.settings'));
        $this->assertFalse($this->shop->fresh()->isPurchasingEnabled());

        // Toggle ON via General Settings route
        $response2 = $this->actingAs($this->admin)->post(
            route('admin.cashbook.settings.shop.toggle-purchasing', $this->shop->id)
        );

        $response2->assertRedirect(route('admin.cashbook.settings'));
        $this->assertTrue($this->shop->fresh()->isPurchasingEnabled());
    }

    public function test_vendor_purchase_ledger_entry_type_exists_globally_and_is_not_duplicated(): void
    {
        $typesCount = LedgerEntryType::query()->where('code', 'vendor_purchase')->count();
        $this->assertSame(1, $typesCount);

        $entryType = LedgerEntryType::query()->where('code', 'vendor_purchase')->firstOrFail();
        $this->assertSame('Vendor Purchase', $entryType->name);
        $this->assertSame('expense', $entryType->category);

        // Re-run seeder to verify idempotence
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->assertSame(1, LedgerEntryType::query()->where('code', 'vendor_purchase')->count());
    }

    public function test_vendor_purchase_appears_in_shop_categories_settings(): void
    {
        $cashSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'vendor_purchase_cash'))
            ->first();

        $creditSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'vendor_purchase_credit'))
            ->first();

        $this->assertNotNull($cashSetting);
        $this->assertTrue($cashSetting->enabled);
        $this->assertTrue($cashSetting->include_in_expense);

        $this->assertNotNull($creditSetting);
        $this->assertTrue($creditSetting->enabled);
        $this->assertTrue($creditSetting->include_in_expense);

        // View shop categories settings page as admin
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.settings.shop', $this->shop->id)
        );

        $response->assertOk();
        $response->assertSee('Vendor Purchase - Cash');
        $response->assertSee('Vendor Purchase - Credit');
    }

    public function test_vendor_purchase_can_be_placed_under_any_expense_header_group(): void
    {
        /** @var ShopLedgerHeaderGroup $customHeader */
        $customHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Store Operations & Sourcing',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
            'product_tagging_enabled' => true,
        ]);

        $setting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'vendor_purchase_cash'))
            ->firstOrFail();

        $setting->update([
            'header_group_id' => $customHeader->id,
            'default_funding_source' => 'sales',
        ]);

        $this->assertSame($customHeader->id, $setting->fresh()->header_group_id);
    }

    public function test_renaming_header_does_not_break_cash_purchase_posting(): void
    {
        /** @var ShopLedgerHeaderGroup $header */
        $header = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Original Header Name',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
            'product_tagging_enabled' => true,
        ]);

        $setting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'vendor_purchase_cash'))
            ->firstOrFail();

        $setting->update(['header_group_id' => $header->id]);

        // Rename the header group
        $header->update(['name' => 'Renamed Sourcing & Procurement Header']);

        // Record a Cash Purchase
        $service = app(ShopPurchaseService::class);
        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Cash',
            'discount_amount' => 0.0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 20,
                    'unit_price' => 15.50,
                    'unit' => 'kg',
                ],
            ],
        ], $this->shopUser);

        $this->assertInstanceOf(PurchaseInvoice::class, $invoice);
        $this->assertSame(310.0, (float) $invoice->amount);

        // Verify ShopLedgerTransaction was posted with vendor_purchase_cash code and net amount
        $transaction = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', (string) $invoice->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame('vendor_purchase_cash', $transaction->entryType?->code);
        $this->assertSame(310.0, (float) $transaction->amount);
    }

    public function test_cash_vendor_purchase_posts_to_shop_ledger_transaction_and_affects_shop_cash(): void
    {
        $service = app(ShopPurchaseService::class);
        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Cash',
            'discount_amount' => 10.0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_price' => 25.0,
                    'unit' => 'kg',
                ],
            ],
        ], $this->shopUser);

        $this->assertSame(250.0, (float) $invoice->amount);
        $this->assertSame(10.0, (float) $invoice->discount_amount);
        $this->assertSame(240.0, (float) $invoice->paid_amount);

        // Ensure ShopLedgerTransaction was created for 240.0 (net)
        $tx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', (string) $invoice->id)
            ->first();

        $this->assertNotNull($tx);
        $this->assertSame(240.0, (float) $tx->amount);
        $this->assertSame('sales', $tx->funding_source);
        $this->assertSame('vendor_purchase_cash', $tx->entryType?->code);

        // Ensure NO payable was created for cash purchase
        $this->assertDatabaseMissing('shop_vendor_payables', [
            'purchase_invoice_id' => $invoice->id,
        ]);
    }

    public function test_credit_vendor_purchase_creates_payable_only_and_no_cashbook_cash_movement(): void
    {
        $service = app(ShopPurchaseService::class);
        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Credit',
            'discount_amount' => 5.0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'unit_price' => 50.0,
                    'unit' => 'kg',
                ],
            ],
        ], $this->shopUser);

        $this->assertSame(250.0, (float) $invoice->amount);
        $this->assertSame(5.0, (float) $invoice->discount_amount);
        $this->assertSame(0.0, (float) $invoice->paid_amount);

        // Ensure ShopVendorPayable was created
        $payable = ShopVendorPayable::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->first();

        $this->assertNotNull($payable);
        $this->assertSame(250.0, (float) $payable->original_amount);
        $this->assertSame(245.0, (float) $payable->outstanding_amount);
        $this->assertSame(0.0, (float) $payable->paid_amount);

        // Ensure NO immediate ShopLedgerTransaction cash movement was created
        $this->assertDatabaseMissing('shop_ledger_transactions', [
            'shop_id' => $this->shop->id,
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => (string) $invoice->id,
        ]);
    }

    public function test_disabling_shop_purchasing_blocks_new_purchases_while_preserving_historical_records(): void
    {
        $service = app(ShopPurchaseService::class);

        // Create historical purchase while enabled
        $invoice = $service->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_price' => 20.0,
                ],
            ],
        ], $this->shopUser);

        $this->assertNotNull($invoice);

        // Disable Shop Purchasing
        $this->shop->update(['shop_purchasing_enabled' => false]);
        $this->assertFalse($this->shop->fresh()->isPurchasingEnabled());

        // Attempting a new purchase must throw RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Shop Purchasing is disabled for {$this->shop->name}.");

        $service->recordPurchase($this->shop->fresh(), [
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'payment_method' => 'Cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'unit_price' => 20.0,
                ],
            ],
        ], $this->shopUser);

        // Verify historical invoice and vendor link still exist
        $this->assertDatabaseHas('purchase_invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('shop_suppliers', [
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->supplier->id,
        ]);
    }
}
