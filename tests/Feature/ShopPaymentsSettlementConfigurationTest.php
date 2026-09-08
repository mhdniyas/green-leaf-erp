<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\ShopSettlementService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopPaymentsSettlementConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopOwner;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private ShopSettlementService $settlementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        Permission::findOrCreate('sales.order.create');
        Role::findOrCreate('shop');

        $this->shopOwner = User::factory()->create(['name' => 'Shop Owner User']);
        $this->shopOwner->givePermissionTo('sales.order.create');
        $this->shopOwner->assignRole('shop');

        $this->shop = Shop::factory()->create([
            'name' => 'Casio Veg',
            'code' => 'AV_CASIO',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopOwnerAssignment::query()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->shopOwner->id,
            'status' => 'active',
        ]);

        $syncService = app(CashbookShopSyncService::class);
        $syncService->syncAndGetProfiles();

        $this->profile = ShopLedgerProfile::where('shop_id', $this->shop->id)->firstOrFail();
        $this->settlementService = app(ShopSettlementService::class);
        $this->settlementService->ensureDefaults($this->profile);
    }

    public function test_ensure_defaults_initializes_default_payment_payable_and_paid(): void
    {
        $payable = $this->settlementService->getDefaultPaymentPayable((int) $this->shop->id);
        $paid = $this->settlementService->getDefaultPaymentPaid((int) $this->shop->id);

        $this->assertNotNull($payable);
        $this->assertNotNull($paid);
        $this->assertTrue((bool) $payable->is_payment_payable);
        $this->assertTrue((bool) $paid->is_payment_paid);
    }

    public function test_admin_can_set_default_payment_payable_and_paid_via_endpoint(): void
    {
        // Create new settlements
        $newPayable = ShopCashbookRelation::create([
            'shop_id' => $this->shop->id,
            'name' => 'Custom Payable Settlement',
            'relation_type' => 'formula',
            'enabled' => true,
        ]);

        $newPaid = ShopCashbookRelation::create([
            'shop_id' => $this->shop->id,
            'name' => 'Custom Paid Settlement',
            'relation_type' => 'formula',
            'enabled' => true,
        ]);

        $shopKey = $this->profile->slug ?: $this->profile->shop_id;

        // Set as Default Payable
        $resPayable = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.settings.shop.settlements.set-payment-payable', [$shopKey, $newPayable->public_uuid])
        );
        $resPayable->assertOk()->assertJson(['success' => true]);
        $this->assertTrue($newPayable->fresh()->is_payment_payable);

        // Set as Default Paid
        $resPaid = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.settings.shop.settlements.set-payment-paid', [$shopKey, $newPaid->public_uuid])
        );
        $resPaid->assertOk()->assertJson(['success' => true]);
        $this->assertTrue($newPaid->fresh()->is_payment_paid);

        // Verify exactly one is active
        $this->assertEquals(1, ShopCashbookRelation::where('shop_id', $this->shop->id)->where('is_payment_payable', true)->count());
        $this->assertEquals(1, ShopCashbookRelation::where('shop_id', $this->shop->id)->where('is_payment_paid', true)->count());
    }

    public function test_calculate_shop_payments_evaluates_payable_and_paid_formulas_with_zero_hardcoding(): void
    {
        // Configure custom categories for payable: Sales (add), Rent (subtract)
        $salesType = LedgerEntryType::firstOrCreate(['code' => 'daily_sales_cash'], ['name' => 'Cash Sales', 'category' => 'income']);
        $rentType = LedgerEntryType::firstOrCreate(['code' => 'rent_expense'], ['name' => 'Shop Rent', 'category' => 'expense']);
        $paytmType = LedgerEntryType::firstOrCreate(['code' => 'paytm_collection'], ['name' => 'Paytm', 'category' => 'income']);

        $salesSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $rentSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $paytmSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $paytmType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        // Create custom payable settlement: Sales - Rent
        $payableRelation = ShopCashbookRelation::create([
            'shop_id' => $this->shop->id,
            'name' => 'Shop Payable Rules',
            'relation_type' => 'formula',
            'enabled' => true,
            'is_payment_payable' => true,
        ]);
        ShopCashbookRelation::where('shop_id', $this->shop->id)->where('id', '!=', $payableRelation->id)->update(['is_payment_payable' => false]);

        $payableRelation->items()->createMany([
            ['shop_ledger_entry_setting_id' => $salesSetting->id, 'role' => 'add', 'display_order' => 0],
            ['shop_ledger_entry_setting_id' => $rentSetting->id, 'role' => 'subtract', 'display_order' => 1],
        ]);

        // Create custom paid settlement: Paytm
        $paidRelation = ShopCashbookRelation::create([
            'shop_id' => $this->shop->id,
            'name' => 'Shop Paid Rules',
            'relation_type' => 'formula',
            'enabled' => true,
            'is_payment_paid' => true,
        ]);
        ShopCashbookRelation::where('shop_id', $this->shop->id)->where('id', '!=', $paidRelation->id)->update(['is_payment_paid' => false]);

        $paidRelation->items()->createMany([
            ['shop_ledger_entry_setting_id' => $paytmSetting->id, 'role' => 'add', 'display_order' => 0],
        ]);

        // Record transactions for September 2026: Sales 100,000, Rent 20,000, Paytm 30,000
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'entry_type_code' => $salesType->code,
            'business_date' => '2026-09-05',
            'amount' => 100000,
            'direction' => 'income',
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'entry_type_code' => $rentType->code,
            'business_date' => '2026-09-06',
            'amount' => 20000,
            'direction' => 'expense',
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $paytmType->id,
            'entry_type_code' => $paytmType->code,
            'business_date' => '2026-09-07',
            'amount' => 30000,
            'direction' => 'income',
            'funding_source' => 'direct_bank',
            'status' => 'posted',
        ]);

        $res = $this->settlementService->calculateShopPayments((int) $this->shop->id, '2026-09-01', '2026-09-30');

        // Shop Balance = Money Kept (100,000) - Expenses Paid (20,000) = 80,000
        $this->assertEquals(80000.0, $res['payable']);
        $this->assertEquals(30000.0, $res['paid']);
        $this->assertEquals(80000.0, $res['shop_balance']);
    }

    public function test_shop_owner_payments_page_renders_direct_to_company_and_manual_payments(): void
    {
        // Authenticate shop owner and hit payments index
        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.payments.index', ['month' => '2026-09']));

        $response->assertOk();
        $response->assertSee('SHOP BALANCE');
        $response->assertSee('EXPENSE PAYABLES');
        $response->assertSee('Direct to Company');
        $response->assertSee('PAYMENTS TO COMPANY');
        $response->assertSee('MONEY SPLIT');
        $response->assertSee('Pay to Company');
        $response->assertDontSee('Default Payable');
        $response->assertDontSee('Default Paid');
    }

    public function test_direct_to_company_excludes_pure_cash_and_resolves_company_bank(): void
    {
        $companyAccount = CompanyAccount::create([
            'name' => 'HDFC Company Main',
            'bank_name' => 'HDFC Bank',
            'account_number' => '9988776655',
            'account_type' => 'current',
            'opening_balance' => 0,
            'current_balance' => 0,
            'enabled' => true,
        ]);

        $cardType = LedgerEntryType::firstOrCreate(['code' => 'card_pos'], ['name' => 'Card POS', 'category' => 'income']);
        $cashType = LedgerEntryType::firstOrCreate(['code' => 'daily_sales_cash'], ['name' => 'Cash Sales', 'category' => 'income']);

        $cardSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $cardType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);
        $cardSetting->update(['company_account_id' => $companyAccount->id]);

        $cashSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $cashType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01', 'company_account_id' => null, 'default_funding_source' => 'shop_cash']);

        // Configure direct_to_company with both card and cash settings
        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payable' => [
                'source' => 'categories',
                'category_ids' => [],
            ],
            'direct_to_company' => [
                'source' => 'categories',
                'category_ids' => [$cardSetting->id, $cashSetting->id],
            ],
        ]);

        // Post transactions: Card = 25,000, Cash = 40,000
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $cardType->id,
            'entry_type_code' => $cardType->code,
            'business_date' => '2026-09-15',
            'amount' => 25000,
            'direction' => 'income',
            'funding_source' => 'direct_bank',
            'status' => 'posted',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $cashType->id,
            'entry_type_code' => $cashType->code,
            'business_date' => '2026-09-15',
            'amount' => 40000,
            'direction' => 'income',
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);

        $res = $this->settlementService->calculateShopPayments((int) $this->shop->id, '2026-09-01', '2026-09-30');

        // Card POS should be counted under direct_to_company (25,000), cash MUST be excluded
        $this->assertEquals(25000.0, $res['direct_to_company']);
        $this->assertEquals(25000.0, $res['paid']);

        // Direct items should only contain Card POS and have bank_name HDFC Bank
        $this->assertCount(1, $res['direct_items']);
        $this->assertEquals('Card POS', $res['direct_items'][0]['name']);
        $this->assertEquals('HDFC Bank', $res['direct_items'][0]['bank_name']);
        $this->assertTrue($res['direct_items'][0]['is_direct']);
    }

    public function test_sales_collections_calculates_total_sales_with_direct_and_cash_split(): void
    {
        $idfcAccount = CompanyAccount::create([
            'name' => 'IDFC BANK',
            'bank_name' => 'IDFC BANK',
            'account_number' => '1122334455',
            'account_type' => 'current',
            'opening_balance' => 0,
            'current_balance' => 0,
            'enabled' => true,
        ]);

        $shaanuAccount = CompanyAccount::create([
            'name' => 'Shaanu Account',
            'bank_name' => 'Shaanu Account',
            'account_number' => '5544332211',
            'account_type' => 'savings',
            'opening_balance' => 0,
            'current_balance' => 0,
            'enabled' => true,
        ]);

        $cardType = LedgerEntryType::firstOrCreate(['code' => 'card_idfc'], ['name' => 'Card', 'category' => 'income']);
        $paytmType = LedgerEntryType::firstOrCreate(['code' => 'paytm_shaanu'], ['name' => 'Paytm', 'category' => 'income']);
        $cashType = LedgerEntryType::firstOrCreate(['code' => 'sales_cash'], ['name' => 'Cash', 'category' => 'income']);

        $cardSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $cardType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01', 'company_account_id' => $idfcAccount->id]);

        $paytmSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $paytmType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01', 'company_account_id' => $shaanuAccount->id]);

        $cashSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $cashType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01', 'company_account_id' => null, 'default_funding_source' => 'shop_cash']);

        // Save payments settings with Sales Collections
        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payable' => [
                'source' => 'categories',
                'category_ids' => [],
            ],
            'sales_collections' => [
                'source' => 'categories',
                'direct_category_ids' => [$cardSetting->id, $paytmSetting->id],
                'cash_category_ids' => [$cashSetting->id],
            ],
        ]);

        // Post transactions
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $cardType->id,
            'entry_type_code' => $cardType->code,
            'business_date' => '2026-09-10',
            'amount' => 251242,
            'direction' => 'income',
            'funding_source' => 'direct_bank',
            'status' => 'posted',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $paytmType->id,
            'entry_type_code' => $paytmType->code,
            'business_date' => '2026-09-11',
            'amount' => 760364,
            'direction' => 'income',
            'funding_source' => 'direct_bank',
            'status' => 'posted',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $cashType->id,
            'entry_type_code' => $cashType->code,
            'business_date' => '2026-09-12',
            'amount' => 180000,
            'direction' => 'income',
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);

        $res = $this->settlementService->calculateShopPayments((int) $this->shop->id, '2026-09-01', '2026-09-30');

        $this->assertEquals(1011606.0, $res['direct_to_company']);
        $this->assertEquals(180000.0, $res['cash_in_shop']);
        $this->assertEquals(1191606.0, $res['total_sales']);

        $this->assertCount(3, $res['sales_collection_items']);

        // Check Shop Owner Payments view
        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.payments.index', ['month' => '2026-09']));

        $response->assertOk();
        $response->assertSee('SALES COLLECTIONS');
        $response->assertSee('TOTAL SALES');
        $response->assertSee('Direct to Company · IDFC BANK');
        $response->assertSee('Direct to Company · Shaanu Account');
        $response->assertSee('Stays with Shop');
        $response->assertSee('Cash in Shop');
    }

    public function test_manual_payments_from_shop_invoice_payment_request_reduces_shop_balance(): void
    {
        $expenseType = LedgerEntryType::firstOrCreate(['code' => 'power_bill'], ['name' => 'Electricity', 'category' => 'expense']);
        $expenseSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $expenseType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        // Configure payable: Electricity
        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payable' => [
                'source' => 'categories',
                'category_ids' => [$expenseSetting->id],
            ],
            'direct_to_company' => [
                'source' => 'categories',
                'category_ids' => [],
            ],
        ]);

        // Payable transaction: 50,000
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $expenseType->id,
            'entry_type_code' => $expenseType->code,
            'business_date' => '2026-09-05',
            'amount' => 50000,
            'direction' => 'expense',
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);

        // Create Manual Payment Requests: 1 Approved (15,000) and 1 Pending (5,000)
        ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->shopOwner->id,
            'requested_amount' => 15000.00,
            'reconciled_amount' => 15000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'created_at' => '2026-09-10 12:00:00',
        ]);

        ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->shopOwner->id,
            'requested_amount' => 5000.00,
            'reconciled_amount' => 0.00,
            'status' => 'pending',
            'reconciliation_status' => 'pending',
            'created_at' => '2026-09-12 12:00:00',
        ]);

        $res = $this->settlementService->calculateShopPayments((int) $this->shop->id, '2026-09-01', '2026-09-30');

        $this->assertEquals(50000.0, $res['payable']);
        $this->assertEquals(0.0, $res['direct_to_company']);
        $this->assertEquals(20000.0, $res['manual_total']);
        $this->assertEquals(15000.0, $res['manual_received']);
        $this->assertEquals(5000.0, $res['manual_pending']);

        // Shop Balance = Money Kept by Shop (0) - Expenses Paid (50,000) - Manual Received (15,000) = -65,000
        $this->assertEquals(-65000.0, $res['shop_balance']);
    }

    public function test_admin_can_save_payments_configuration_via_endpoint(): void
    {
        $shopKey = $this->profile->slug ?: $this->profile->shop_id;

        $settlement = ShopCashbookRelation::create([
            'shop_id' => $this->shop->id,
            'name' => 'Existing Settlement Example',
            'relation_type' => 'formula',
            'enabled' => true,
        ]);

        $payload = [
            'payable' => [
                'source' => 'categories',
                'category_ids' => [1, 2],
                'settlement_id' => null,
            ],
            'paid' => [
                'source' => 'settlement',
                'category_ids' => [],
                'settlement_id' => $settlement->id,
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.settings.shop.payments-configuration.save', $shopKey),
            $payload
        );

        $response->assertOk()->assertJson(['success' => true]);

        $config = $this->profile->fresh()->getPaymentConfiguration();
        $this->assertEquals('categories', $config['payable']['source']);
        $this->assertEquals([1, 2], $config['payable']['category_ids']);
        $this->assertEquals('settlement', $config['paid']['source']);
        $this->assertEquals($settlement->id, $config['paid']['settlement_id']);
    }

    public function test_calculate_shop_payments_supports_categories_source_for_payable_and_paid(): void
    {
        $expenseType1 = LedgerEntryType::firstOrCreate(['code' => 'cleaning_expense'], ['name' => 'Cleaning', 'category' => 'expense']);
        $expenseType2 = LedgerEntryType::firstOrCreate(['code' => 'stationery_expense'], ['name' => 'Stationery', 'category' => 'expense']);
        $paidType = LedgerEntryType::firstOrCreate(['code' => 'bank_deposit'], ['name' => 'Bank Deposit', 'category' => 'income']);

        $setting1 = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $expenseType1->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $setting2 = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $expenseType2->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $settingPaid = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $paidType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        // Save payments configuration to use categories for both
        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payable' => [
                'source' => 'categories',
                'category_ids' => [$setting1->id, $setting2->id],
            ],
            'paid' => [
                'source' => 'categories',
                'category_ids' => [$settingPaid->id],
            ],
        ]);

        // Create transactions: Cleaning 5,000, Stationery 2,000, Bank Deposit 4,000
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $expenseType1->id,
            'entry_type_code' => $expenseType1->code,
            'business_date' => '2026-09-10',
            'amount' => 5000,
            'direction' => 'expense',
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $expenseType2->id,
            'entry_type_code' => $expenseType2->code,
            'business_date' => '2026-09-11',
            'amount' => 2000,
            'direction' => 'expense',
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $paidType->id,
            'entry_type_code' => $paidType->code,
            'business_date' => '2026-09-12',
            'amount' => 4000,
            'direction' => 'income',
            'funding_source' => 'direct_bank',
            'status' => 'posted',
        ]);

        $res = $this->settlementService->calculateShopPayments((int) $this->shop->id, '2026-09-01', '2026-09-30');

        // Payable = 5000 + 2000 = 7000
        // Paid = 4000
        // Shop Balance = Money Kept (0) - Expenses Paid (7000) = -7000
        $this->assertEquals(7000.0, $res['payable']);
        $this->assertEquals(4000.0, $res['paid']);
        $this->assertEquals(-7000.0, $res['shop_balance']);
        $this->assertEquals('categories', $res['payable_source']);
        $this->assertEquals('categories', $res['paid_source']);
        $this->assertCount(2, $res['payable_items']);
        $this->assertCount(1, $res['paid_items']);
    }

    public function test_admin_can_access_dedicated_payments_settings_page(): void
    {
        $shopKey = $this->profile->slug ?: $this->profile->shop_id;

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.settings.shop.payments.index', $shopKey)
        );

        $response->assertOk();
        $response->assertSee('SHOP PAYMENTS');
        $response->assertSee('PAYABLE');
        $response->assertSee('SALES COLLECTIONS');
        $response->assertSee('Direct to Company');
        $response->assertSee('MANUAL PAYMENTS');
        $response->assertSee('Save Payments Configuration');
        $response->assertSee('General');
        $response->assertSee('Categories');
        $response->assertSee('Settlements');
        $response->assertSee('Payments');
    }

    public function test_settlements_settings_page_does_not_contain_payments_configuration_block(): void
    {
        $shopKey = $this->profile->slug ?: $this->profile->shop_id;

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.settings.shop.settlements.index', $shopKey)
        );

        $response->assertOk();
        $response->assertSee('Configure formulas and Net Balance');
        $response->assertSee('Create Settlement');
        $response->assertDontSee('OFFICIAL SOURCE FOR SHOP PAYMENTS');
        $response->assertDontSee('PAYABLE CONFIGURATION');
        $response->assertDontSee('DIRECT TO COMPANY CONFIGURATION');
        $response->assertSee('Payments');
    }

    public function test_admin_can_save_payment_settlement_mapping_per_shop(): void
    {
        $shopKey = $this->profile->slug ?: $this->profile->shop_id;

        $settlement = ShopCashbookRelation::create([
            'shop_id' => $this->shop->id,
            'name' => 'Monthly Shop Settlement',
            'relation_type' => 'formula',
            'enabled' => true,
        ]);

        $payload = [
            'payment_settlement_id' => $settlement->id,
            'payable' => [
                'source' => 'settlement',
                'category_ids' => [],
                'settlement_id' => $settlement->id,
            ],
            'direct_to_company' => [
                'source' => 'categories',
                'category_ids' => [],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.settings.shop.payments-configuration.save', $shopKey),
            $payload
        );

        $response->assertOk()->assertJson(['success' => true]);

        $config = $this->profile->fresh()->getPaymentConfiguration();
        $this->assertEquals($settlement->id, $config['payment_settlement_id']);
        $this->assertTrue($settlement->fresh()->is_payment_payable);
    }

    public function test_allocate_all_uses_selected_payment_settlement_and_updates_shop_owner_payments(): void
    {
        $salesType = LedgerEntryType::firstOrCreate(['code' => 'daily_sales_cash_v2'], ['name' => 'Cash Sales V2', 'category' => 'income']);
        $rentType = LedgerEntryType::firstOrCreate(['code' => 'rent_expense_v2'], ['name' => 'Rent Expense V2', 'category' => 'expense']);

        $salesSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $rentSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $settlement = ShopCashbookRelation::create([
            'shop_id' => $this->shop->id,
            'name' => 'Shop Allocation Settlement',
            'relation_type' => 'formula',
            'enabled' => true,
            'is_payment_payable' => true,
        ]);

        $settlement->items()->createMany([
            ['shop_ledger_entry_setting_id' => $salesSetting->id, 'role' => 'add', 'display_order' => 0],
            ['shop_ledger_entry_setting_id' => $rentSetting->id, 'role' => 'subtract', 'display_order' => 1],
        ]);

        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payment_settlement_id' => $settlement->id,
            'payable' => ['source' => 'settlement', 'category_ids' => [], 'settlement_id' => $settlement->id],
            'direct_to_company' => ['source' => 'categories', 'category_ids' => []],
        ]);

        // Transactions: Sales 50,000, Rent 10,000 => Net Settlement 40,000
        $salesTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'entry_setting_id' => $salesSetting->id,
            'business_date' => '2026-09-05',
            'amount' => 50000,
            'direction' => 'income',
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'entry_setting_id' => $rentSetting->id,
            'business_date' => '2026-09-05',
            'amount' => 25000,
            'direction' => 'expense',
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);

        // Payment Request from Shop Owner: 25,000 approved/reconciled
        $paymentRequest = ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->shopOwner->id,
            'requested_amount' => 25000.00,
            'reconciled_amount' => 25000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'payment_date' => '2026-09-05',
        ]);

        // Submit Allocate All
        $uuid = (string) Str::uuid();
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.allocate-payments.bulk', ['shop' => $this->shop->slug ?: $this->shop->id]),
            [
                'month' => '2026-09',
                'expected_total' => 25000.00,
                'submission_uuid' => $uuid,
            ]
        );

        $response->assertRedirect();

        // Verify allocation created linking request to representative transaction
        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'shop_id' => $this->shop->id,
            'payment_request_id' => $paymentRequest->id,
            'amount' => 25000.00,
        ]);

        // Verify Shop Owner Payments page reflects updated numbers
        $shopResponse = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.payments.index', ['month' => '2026-09']));

        $shopResponse->assertOk();
        $shopResponse->assertSee('SHOP BALANCE');
        $shopResponse->assertSee('25,000');
    }

    public function test_payable_selected_categories_become_allocation_targets(): void
    {
        $glType = LedgerEntryType::firstOrCreate(['code' => 'gl_bill_cat_target'], ['name' => 'GL Bill Cat', 'category' => 'expense']);
        $rentType = LedgerEntryType::firstOrCreate(['code' => 'rent_cat_target'], ['name' => 'Rent Cat', 'category' => 'expense']);

        $glSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $rentSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payable' => [
                'source' => 'categories',
                'category_ids' => [$glSetting->id, $rentSetting->id],
            ],
            'direct_to_company' => [
                'source' => 'categories',
                'category_ids' => [],
            ],
        ]);

        $targets = $this->settlementService->resolvePayableAllocationTargets($this->shop->id);
        $this->assertCount(2, $targets);
        $names = $targets->pluck('name')->all();
        $this->assertContains('GL Bill Cat', $names);
        $this->assertContains('Rent Cat', $names);
    }

    public function test_payable_existing_settlement_categories_become_allocation_targets(): void
    {
        $vehicleType = LedgerEntryType::firstOrCreate(['code' => 'vehicle_settle_target'], ['name' => 'Vehicle Expense Settle', 'category' => 'expense']);
        $vehicleSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $vehicleType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $settlement = ShopCashbookRelation::create([
            'shop_id' => $this->shop->id,
            'name' => 'Payable Settle Target Relation',
            'relation_type' => 'formula',
            'enabled' => true,
        ]);

        $settlement->items()->create([
            'shop_ledger_entry_setting_id' => $vehicleSetting->id,
            'role' => 'subtract',
            'display_order' => 0,
        ]);

        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payable' => [
                'source' => 'settlement',
                'settlement_id' => $settlement->id,
                'category_ids' => [],
            ],
            'direct_to_company' => [
                'source' => 'categories',
                'category_ids' => [],
            ],
        ]);

        $targets = $this->settlementService->resolvePayableAllocationTargets($this->shop->id);
        $this->assertCount(1, $targets);
        $this->assertEquals('Vehicle Expense Settle', $targets->first()['name']);
    }

    public function test_auto_allocate_uses_payable_selected_categories_directly_without_payment_settlement_id(): void
    {
        $salaryType = LedgerEntryType::firstOrCreate(['code' => 'salary_direct_p2'], ['name' => 'Staff Salary P2', 'category' => 'expense']);
        $salarySetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $otherType = LedgerEntryType::firstOrCreate(['code' => 'other_unrelated_p2'], ['name' => 'Unrelated Expense P2', 'category' => 'expense']);
        $otherSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $otherType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        // PAYABLE configured with Selected Categories (Staff Salary only)
        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payable' => [
                'source' => 'categories',
                'category_ids' => [$salarySetting->id],
            ],
            'direct_to_company' => [
                'source' => 'categories',
                'category_ids' => [],
            ],
        ]);

        // Check that payment_settlement_id is NOT set or used
        $config = $this->profile->fresh()->getPaymentConfiguration();
        $this->assertNull($config['payment_settlement_id'] ?? null);

        $salaryTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryType->id,
            'business_date' => '2026-08-01',
            'amount' => 12000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $otherTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $otherType->id,
            'business_date' => '2026-08-02',
            'amount' => 5000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $paymentRequest = ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->shopOwner->id,
            'requested_amount' => 12000.00,
            'reconciled_amount' => 12000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'payment_date' => '2026-08-15',
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.allocate-payments.bulk', ['shop' => $this->shop->slug ?: $this->shop->id]),
            [
                'month' => '2026-08',
                'expected_total' => 12000.00,
                'submission_uuid' => (string) Str::uuid(),
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'shop_id' => $this->shop->id,
            'payment_request_id' => $paymentRequest->id,
            'shop_ledger_transaction_id' => $salaryTx->id,
            'amount' => 12000.00,
        ]);

        $this->assertDatabaseMissing('shop_payment_ledger_allocations', [
            'shop_ledger_transaction_id' => $otherTx->id,
        ]);
    }

    public function test_allocate_all_only_allocates_against_configured_target_categories_and_leaves_unrelated_untouched(): void
    {
        $glType = LedgerEntryType::firstOrCreate(['code' => 'gl_bill_p2'], ['name' => 'GL Bill P2', 'category' => 'expense']);
        $miscType = LedgerEntryType::firstOrCreate(['code' => 'misc_exp_p2'], ['name' => 'Misc Expense P2', 'category' => 'expense']);

        $glSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $miscSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $miscType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $settlement = ShopCashbookRelation::create([
            'shop_id' => $this->shop->id,
            'name' => 'GL Bill Only Target Settlement',
            'relation_type' => 'formula',
            'enabled' => true,
            'is_payment_payable' => true,
        ]);

        $settlement->items()->create([
            'shop_ledger_entry_setting_id' => $glSetting->id,
            'role' => 'subtract',
            'display_order' => 0,
        ]);

        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payable' => ['source' => 'settlement', 'category_ids' => [], 'settlement_id' => $settlement->id],
            'direct_to_company' => ['source' => 'categories', 'category_ids' => []],
        ]);

        $glTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
            'business_date' => '2026-08-01',
            'amount' => 5000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $miscTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $miscType->id,
            'business_date' => '2026-08-02',
            'amount' => 8000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $paymentRequest = ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->shopOwner->id,
            'requested_amount' => 10000.00,
            'reconciled_amount' => 10000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'payment_date' => '2026-08-15',
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.allocate-payments.bulk', ['shop' => $this->shop->slug ?: $this->shop->id]),
            [
                'month' => '2026-08',
                'expected_total' => 5000.00,
                'submission_uuid' => (string) Str::uuid(),
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'shop_id' => $this->shop->id,
            'payment_request_id' => $paymentRequest->id,
            'shop_ledger_transaction_id' => $glTx->id,
            'amount' => 5000.00,
        ]);

        $this->assertDatabaseMissing('shop_payment_ledger_allocations', [
            'shop_ledger_transaction_id' => $miscTx->id,
        ]);
    }

    public function test_allocate_all_allocates_oldest_open_expense_rows_first_and_supports_partial_allocation(): void
    {
        $glType = LedgerEntryType::firstOrCreate(['code' => 'gl_bill_p2_oldest'], ['name' => 'GL Bill Oldest', 'category' => 'expense']);
        $glSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $settlement = ShopCashbookRelation::create([
            'shop_id' => $this->shop->id,
            'name' => 'Oldest Target Settlement',
            'relation_type' => 'formula',
            'enabled' => true,
            'is_payment_payable' => true,
        ]);

        $settlement->items()->create([
            'shop_ledger_entry_setting_id' => $glSetting->id,
            'role' => 'subtract',
            'display_order' => 0,
        ]);

        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payment_settlement_id' => $settlement->id,
            'payable' => ['source' => 'settlement', 'category_ids' => [], 'settlement_id' => $settlement->id],
            'direct_to_company' => ['source' => 'categories', 'category_ids' => []],
        ]);

        $glBill1 = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
            'business_date' => '2026-08-01',
            'amount' => 5000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $glBill2 = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
            'business_date' => '2026-08-05',
            'amount' => 8000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $glBill3 = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
            'business_date' => '2026-08-10',
            'amount' => 7000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $paymentRequest = ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->shopOwner->id,
            'requested_amount' => 15000.00,
            'reconciled_amount' => 15000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'payment_date' => '2026-08-15',
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.allocate-payments.bulk', ['shop' => $this->shop->slug ?: $this->shop->id]),
            [
                'month' => '2026-08',
                'expected_total' => 15000.00,
                'submission_uuid' => (string) Str::uuid(),
            ]
        );

        $response->assertRedirect();

        // GL Bill #1: 5,000 allocated (Paid)
        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'payment_request_id' => $paymentRequest->id,
            'shop_ledger_transaction_id' => $glBill1->id,
            'amount' => 5000.00,
        ]);

        // GL Bill #2: 8,000 allocated (Paid)
        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'payment_request_id' => $paymentRequest->id,
            'shop_ledger_transaction_id' => $glBill2->id,
            'amount' => 8000.00,
        ]);

        // GL Bill #3: 2,000 allocated (Partial)
        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'payment_request_id' => $paymentRequest->id,
            'shop_ledger_transaction_id' => $glBill3->id,
            'amount' => 2000.00,
        ]);

        // Verify remaining due on GL Bill #3 is 5,000
        $alreadyAllocated3 = ShopPaymentLedgerAllocation::where('shop_ledger_transaction_id', $glBill3->id)->sum('amount');
        $this->assertEquals(2000.00, $alreadyAllocated3);
        $this->assertEquals(5000.00, 7000.00 - $alreadyAllocated3);
    }

    public function test_allocate_all_excludes_direct_to_company_entries(): void
    {
        $rentType = LedgerEntryType::firstOrCreate(['code' => 'rent_p2_direct'], ['name' => 'Rent Direct Test', 'category' => 'expense']);
        $rentSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $settlement = ShopCashbookRelation::create([
            'shop_id' => $this->shop->id,
            'name' => 'Rent Direct Settlement',
            'relation_type' => 'formula',
            'enabled' => true,
            'is_payment_payable' => true,
        ]);

        $settlement->items()->create([
            'shop_ledger_entry_setting_id' => $rentSetting->id,
            'role' => 'subtract',
            'display_order' => 0,
        ]);

        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payment_settlement_id' => $settlement->id,
            'payable' => ['source' => 'settlement', 'category_ids' => [], 'settlement_id' => $settlement->id],
            'direct_to_company' => ['source' => 'categories', 'category_ids' => []],
        ]);

        $manualRent = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-08-01',
            'amount' => 6000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'company_account_id' => null,
            'status' => 'posted',
        ]);

        $companyAccount = CompanyAccount::create([
            'name' => 'Direct Bank Account',
            'account_type' => 'bank',
            'enabled' => true,
            'is_default' => false,
        ]);

        $directRent = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-08-02',
            'amount' => 4000,
            'direction' => 'expense',
            'funding_source' => 'bank',
            'company_account_id' => $companyAccount->id,
            'status' => 'posted',
        ]);

        $paymentRequest = ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->shopOwner->id,
            'requested_amount' => 10000.00,
            'reconciled_amount' => 10000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'payment_date' => '2026-08-15',
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.allocate-payments.bulk', ['shop' => $this->shop->slug ?: $this->shop->id]),
            [
                'month' => '2026-08',
                'expected_total' => 6000.00,
                'submission_uuid' => (string) Str::uuid(),
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'payment_request_id' => $paymentRequest->id,
            'shop_ledger_transaction_id' => $manualRent->id,
            'amount' => 6000.00,
        ]);

        $this->assertDatabaseMissing('shop_payment_ledger_allocations', [
            'shop_ledger_transaction_id' => $directRent->id,
        ]);
    }

    public function test_repeat_allocate_all_continues_from_remaining_balances(): void
    {
        $glType = LedgerEntryType::firstOrCreate(['code' => 'gl_bill_p2_repeat'], ['name' => 'GL Bill Repeat', 'category' => 'expense']);
        $glSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $settlement = ShopCashbookRelation::create([
            'shop_id' => $this->shop->id,
            'name' => 'Repeat Settlement',
            'relation_type' => 'formula',
            'enabled' => true,
            'is_payment_payable' => true,
        ]);

        $settlement->items()->create([
            'shop_ledger_entry_setting_id' => $glSetting->id,
            'role' => 'subtract',
            'display_order' => 0,
        ]);

        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payment_settlement_id' => $settlement->id,
            'payable' => ['source' => 'settlement', 'category_ids' => [], 'settlement_id' => $settlement->id],
            'direct_to_company' => ['source' => 'categories', 'category_ids' => []],
        ]);

        $glBill1 = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
            'business_date' => '2026-08-01',
            'amount' => 5000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $glBill2 = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
            'business_date' => '2026-08-05',
            'amount' => 8000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $glBill3 = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
            'business_date' => '2026-08-10',
            'amount' => 7000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        // First payment: 15,000
        $payment1 = ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->shopOwner->id,
            'requested_amount' => 15000.00,
            'reconciled_amount' => 15000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'payment_date' => '2026-08-15',
        ]);

        $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.allocate-payments.bulk', ['shop' => $this->shop->slug ?: $this->shop->id]),
            [
                'month' => '2026-08',
                'expected_total' => 15000.00,
                'submission_uuid' => (string) Str::uuid(),
            ]
        );

        $this->assertEquals(5000.00, ShopPaymentLedgerAllocation::where('shop_ledger_transaction_id', $glBill1->id)->sum('amount'));
        $this->assertEquals(8000.00, ShopPaymentLedgerAllocation::where('shop_ledger_transaction_id', $glBill2->id)->sum('amount'));
        $this->assertEquals(2000.00, ShopPaymentLedgerAllocation::where('shop_ledger_transaction_id', $glBill3->id)->sum('amount'));

        // Second payment: 5,000 (should allocate to remaining 5,000 of GL Bill #3)
        $payment2 = ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->shopOwner->id,
            'requested_amount' => 5000.00,
            'reconciled_amount' => 5000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'payment_date' => '2026-08-20',
        ]);

        $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.allocate-payments.bulk', ['shop' => $this->shop->slug ?: $this->shop->id]),
            [
                'month' => '2026-08',
                'expected_total' => 5000.00,
                'submission_uuid' => (string) Str::uuid(),
            ]
        );

        // GL Bill #1 and #2 remain at original allocated amounts
        $this->assertEquals(5000.00, ShopPaymentLedgerAllocation::where('shop_ledger_transaction_id', $glBill1->id)->sum('amount'));
        $this->assertEquals(8000.00, ShopPaymentLedgerAllocation::where('shop_ledger_transaction_id', $glBill2->id)->sum('amount'));

        // GL Bill #3 now has 2000 + 5000 = 7000 allocated (Fully Paid)
        $this->assertEquals(7000.00, ShopPaymentLedgerAllocation::where('shop_ledger_transaction_id', $glBill3->id)->sum('amount'));

        $allocationsPayment2 = ShopPaymentLedgerAllocation::where('payment_request_id', $payment2->id)->get();
        $this->assertCount(1, $allocationsPayment2);
        $this->assertEquals($glBill3->id, $allocationsPayment2->first()->shop_ledger_transaction_id);
        $this->assertEquals(5000.00, $allocationsPayment2->first()->amount);
    }

    public function test_individual_expense_transactions_in_same_category_split_into_settled_and_open_correctly(): void
    {
        $glType = LedgerEntryType::firstOrCreate(['code' => 'gl_bill_split_test'], ['name' => 'GL Bill Split', 'category' => 'expense']);
        $glSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $rentType = LedgerEntryType::firstOrCreate(['code' => 'rent_split_test'], ['name' => 'Rent Expense', 'category' => 'expense']);
        $rentSetting = ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
        ], ['enabled' => true, 'effective_from' => '2026-01-01']);

        $this->settlementService->savePaymentConfiguration($this->profile, [
            'payable' => [
                'source' => 'categories',
                'category_ids' => [$glSetting->id, $rentSetting->id],
            ],
            'direct_to_company' => [
                'source' => 'categories',
                'category_ids' => [],
            ],
        ]);

        // Category 1: GL Bill (Two transactions: 50,000 + 30,000 = 80,000)
        $tx1 = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
            'entry_setting_id' => $glSetting->id,
            'business_date' => '2026-08-05',
            'amount' => 50000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $tx2 = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $glType->id,
            'entry_setting_id' => $glSetting->id,
            'business_date' => '2026-08-10',
            'amount' => 30000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        // Category 2: Rent (One transaction: 20,000, will be fully settled)
        $tx3 = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'entry_setting_id' => $rentSetting->id,
            'business_date' => '2026-08-02',
            'amount' => 20000,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        // Payments: 85,000 (20,000 will settle Rent, 65,000 will partially settle GL Bill)
        $paymentRequest = ShopInvoicePaymentRequest::factory()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->shopOwner->id,
            'requested_amount' => 85000.00,
            'reconciled_amount' => 85000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'payment_date' => '2026-08-15',
        ]);

        // Run Allocate All
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.allocate-payments.bulk', ['shop' => $this->shop->slug ?: $this->shop->id]),
            [
                'month' => '2026-08',
                'expected_total' => 85000.00,
                'submission_uuid' => (string) Str::uuid(),
            ]
        );

        $res = $this->settlementService->calculateShopPayments((int) $this->shop->id, '2026-08-01', '2026-08-31');

        // Settled expenses must contain GL Bill Split (50,000 settled) and Rent Expense (20,000 settled)
        $this->assertCount(2, $res['settled_expenses']);
        $settledRent = collect($res['settled_expenses'])->firstWhere('id', $rentSetting->id);
        $this->assertNotNull($settledRent);
        $this->assertEquals(20000.0, $settledRent['settled_amount']);
        $this->assertEquals(1, $settledRent['settled_count']);

        $settledGL = collect($res['settled_expenses'])->firstWhere('id', $glSetting->id);
        $this->assertNotNull($settledGL);
        $this->assertEquals(50000.0, $settledGL['settled_amount']);
        $this->assertEquals(1, $settledGL['settled_count']);

        // Expense payables must contain GL Bill Split category (80,000 recorded, 65,000 paid, 15,000 remaining)
        $this->assertCount(1, $res['expense_payables']);
        $this->assertEquals($glSetting->id, $res['expense_payables'][0]['id']);
        $this->assertEquals(80000.0, $res['expense_payables'][0]['recorded_amount']);
        $this->assertEquals(65000.0, $res['expense_payables'][0]['paid_amount']);
        $this->assertEquals(15000.0, $res['expense_payables'][0]['remaining_amount']);
        $this->assertEquals('partial', $res['expense_payables'][0]['status']);

        // Check Shop Owner view
        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.payments.index', ['month' => '2026-08']));

        $response->assertOk();
        $response->assertSee('2 settled');
        $response->assertSee('1 active');
        $response->assertSee('15,000');
    }
}
