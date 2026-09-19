<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerClient;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopAccountingOpening;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Client;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Cashbook\MonthlyClosingSummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MonthlyClosingSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Client $client;

    private LedgerClient $ledgerClient;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'staff']);

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->staff = User::factory()->create(['email' => 'staff@greenleaf.test']);
        $this->staff->assignRole('staff');

        $this->client = Client::create([
            'name' => 'Aiswarya Group',
            'code' => 'AG01',
            'status' => 'active',
        ]);

        $this->ledgerClient = LedgerClient::create([
            'erp_client_id' => $this->client->id,
            'name' => 'Aiswarya Group',
            'slug' => 'ag01',
            'enabled' => true,
        ]);

        $this->shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'client_id' => $this->client->id,
            'status' => 'active',
            'code' => 'CS01',
            'name' => 'Casio Boutique',
        ]);

        $this->profile = ShopLedgerProfile::query()->firstOrCreate(
            ['shop_id' => $this->shop->id],
            [
                'profile_template' => 'owned_standard',
                'name' => 'Casio Boutique',
                'code' => 'CS01',
                'slug' => 'av-casio-boutique',
                'status' => 'active',
                'enabled' => true,
                'client_id' => $this->ledgerClient->id,
                'payment_configuration' => [
                    'enabled' => true,
                    'reconciliation_mode' => 'auto',
                ],
            ]
        );

        $parentAccount = Account::create([
            'name' => 'Current Assets',
            'code' => '1000',
            'type' => 'asset',
            'level' => 1,
            'is_active' => true,
        ]);

        $glAccount = Account::create([
            'name' => 'HDFC Bank Account',
            'code' => '1001',
            'type' => 'asset',
            'parent_id' => $parentAccount->id,
            'level' => 2,
            'is_active' => true,
        ]);

        $this->bankAccount = CompanyAccount::create([
            'name' => 'HDFC Operating',
            'account_number' => '50200012345678',
            'account_type' => 'bank',
            'gl_account_id' => $glAccount->id,
            'currency' => 'INR',
            'is_active' => true,
        ]);
    }

    public function test_unauthorized_user_cannot_access_monthly_closing_summary(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get(route('admin.cashbook.monthly-closing-summary.index'));
        $response->assertRedirect(route('dashboard'));

        $responseDetail = $this->get(route('admin.cashbook.monthly-closing-summary.show', ['shop' => $this->shop->id]));
        $responseDetail->assertRedirect(route('dashboard'));
    }

    public function test_main_admin_can_access_index_and_show_views(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.cashbook.monthly-closing-summary.index', ['month' => '2026-08']));
        $response->assertOk();
        $response->assertSee('Monthly Closing Summary');
        $response->assertSee('Casio Boutique');

        $responseDetail = $this->get(route('admin.cashbook.monthly-closing-summary.show', [
            'shop' => $this->shop->id,
            'month' => '2026-08',
        ]));
        $responseDetail->assertOk();
        $responseDetail->assertSee('Casio Boutique');
        $responseDetail->assertSee('OPENING POSITION');
        $responseDetail->assertSee('Month Activity');
        $responseDetail->assertSee('CURRENT POSITION');
        $responseDetail->assertSee('AFTER ALL VALID ALLOCATION');
        $responseDetail->assertSee('Available Company-Held Advance Credit');
    }

    public function test_monthly_closing_summary_is_strictly_read_only_and_never_writes_snapshots(): void
    {
        $this->actingAs($this->admin);

        $snapshotCountBefore = ShopDailyLedgerSnapshot::query()->count();

        $response = $this->get(route('admin.cashbook.monthly-closing-summary.index', ['month' => '2026-08']));
        $response->assertOk();

        $responseDetail = $this->get(route('admin.cashbook.monthly-closing-summary.show', [
            'shop' => $this->shop->id,
            'month' => '2026-08',
        ]));
        $responseDetail->assertOk();

        $this->assertSame($snapshotCountBefore, ShopDailyLedgerSnapshot::query()->count());
    }

    public function test_current_position_reflects_only_actual_db_state_and_projected_performs_zero_writes(): void
    {
        $settlementType = LedgerEntryType::firstOrCreate(
            ['code' => 'settlement_due'],
            ['name' => 'Settlement Due', 'category' => 'settlement', 'affects_balance' => true, 'normal_balance' => 'debit']
        );

        ShopLedgerEntrySetting::firstOrCreate(
            [
                'profile_id' => $this->profile->id,
                'shop_id' => $this->shop->id,
                'entry_type_id' => $settlementType->id,
            ],
            [
                'entry_direction' => 'debit',
                'effective_from' => '2026-01-01',
                'enabled' => true,
                'include_in_expense' => true,
                'is_active' => true,
            ]
        );

        // Create obligation of 1,000 on Aug 5
        $txn = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'profile_id' => $this->profile->id,
            'entry_type_id' => $settlementType->id,
            'business_date' => '2026-08-05',
            'amount' => 1000.00,
            'direction' => 'debit',
            'funding_source' => 'sales',
            'status' => 'posted',
            'affects_balance' => true,
        ]);

        // Verified payment of 1,200 received in August, with ONLY 400 allocated in DB
        $pr = ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-AUG-TEST',
            'requested_amount' => 1200.00,
            'approved_amount' => 1200.00,
            'status' => 'verified',
            'payment_method' => 'bank_transfer',
            'payment_date' => '2026-08-06',
        ]);

        ShopPaymentLedgerAllocation::create([
            'payment_request_id' => $pr->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $txn->id,
            'amount' => 400.00,
            'reconciled_by' => $this->admin->id,
        ]);

        // Pending unverified payment of 300
        ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-AUG-PENDING',
            'requested_amount' => 300.00,
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'payment_date' => '2026-08-07',
        ]);

        // Cancelled payment of 500
        ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-AUG-CANCELLED',
            'requested_amount' => 500.00,
            'status' => 'rejected',
            'payment_method' => 'bank_transfer',
            'payment_date' => '2026-08-08',
        ]);

        // Cancelled transaction of 800
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'profile_id' => $this->profile->id,
            'entry_type_id' => $settlementType->id,
            'business_date' => '2026-08-09',
            'amount' => 800.00,
            'direction' => 'debit',
            'funding_source' => 'sales',
            'status' => 'void',
            'voided_at' => now(),
            'affects_balance' => true,
        ]);

        $allocCountBefore = ShopPaymentLedgerAllocation::query()->count();
        $txnCountBefore = ShopLedgerTransaction::query()->count();

        /** @var MonthlyClosingSummaryService $service */
        $service = app(MonthlyClosingSummaryService::class);
        $detail = $service->getShopMonthlyDetail($this->shop, '2026-08');

        // 1. Current Position reflects actual DB state
        $this->assertSame(1200.00, $detail['current_position']['company_received']);
        $this->assertSame(400.00, $detail['current_position']['already_allocated']);
        $this->assertSame(800.00, $detail['current_position']['allocation_pending']);
        $this->assertSame(300.00, $detail['current_position']['pending_verification']);

        // 2. Projected Position: remaining obligation = 600, available money = 800
        // Additional valid allocation = min(800, 600) = 600
        // Projected total allocated = 400 + 600 = 1000
        // Remaining unallocated credit = 800 - 600 = 200
        $this->assertSame(600.00, $detail['projected_position']['additional_valid_allocation']);
        $this->assertSame(1000.00, $detail['projected_position']['projected_total_allocated']);
        $this->assertSame(200.00, $detail['projected_position']['remaining_unallocated_credit']);
        $this->assertSame(300.00, $detail['projected_position']['pending_verification']);
        $this->assertSame(0.00, $detail['projected_position']['projected_remaining_obligations']);

        // 3. Zero writes occurred
        $this->assertSame($allocCountBefore, ShopPaymentLedgerAllocation::query()->count());
        $this->assertSame($txnCountBefore, ShopLedgerTransaction::query()->count());
    }

    public function test_boundary_resolution_uses_latest_valid_snapshot_on_or_before_boundary(): void
    {
        // Snapshot on Aug 15 and Aug 28 (no snapshot on Aug 31)
        ShopDailyLedgerSnapshot::create([
            'shop_id' => $this->shop->id,
            'profile_id' => $this->profile->id,
            'business_date' => '2026-08-15',
            'opening_balance' => 0.00,
            'closing_balance' => 15000.00,
            'opening_shop_position' => 0.00,
            'closing_shop_position' => 15000.00,
            'settlement_due' => 0.00,
            'company_paid' => 0.00,
            'shop_paid' => 0.00,
            'status' => 'closed',
        ]);

        ShopDailyLedgerSnapshot::create([
            'shop_id' => $this->shop->id,
            'profile_id' => $this->profile->id,
            'business_date' => '2026-08-28',
            'opening_balance' => 15000.00,
            'closing_balance' => 29958.00,
            'opening_shop_position' => 15000.00,
            'closing_shop_position' => 29958.00,
            'settlement_due' => 0.00,
            'company_paid' => 0.00,
            'shop_paid' => 0.00,
            'status' => 'closed',
        ]);

        /** @var MonthlyClosingSummaryService $service */
        $service = app(MonthlyClosingSummaryService::class);

        // August Summary
        $augustData = $service->getShopMonthlyDetail($this->shop, '2026-08');
        $this->assertSame(0.00, $augustData['opening']['physical_position']);
        $this->assertSame(29958.00, $augustData['closing']['physical_position']);
        $this->assertSame('shop_owes_company', $augustData['closing']['direction']);
        $this->assertSame('Shop → Company', $augustData['closing']['direction_label']);

        // September Summary - Opening physical position must match August closing exactly
        $septemberData = $service->getShopMonthlyDetail($this->shop, '2026-09');
        $this->assertSame(29958.00, $septemberData['opening']['physical_position']);
        $this->assertSame('shop_owes_company', $septemberData['opening']['direction']);
        $this->assertSame(29958.00, $septemberData['closing']['physical_position']);
    }

    public function test_two_date_allocation_and_available_credit_utilization_flow(): void
    {
        $settlementType = LedgerEntryType::firstOrCreate(
            ['code' => 'settlement_due'],
            ['name' => 'Settlement Due', 'category' => 'settlement', 'affects_balance' => true, 'normal_balance' => 'debit']
        );

        ShopLedgerEntrySetting::firstOrCreate(
            [
                'profile_id' => $this->profile->id,
                'shop_id' => $this->shop->id,
                'entry_type_id' => $settlementType->id,
            ],
            [
                'entry_direction' => 'debit',
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ]
        );

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-TEST-001',
            'business_date' => '2026-08-10',
            'shop_daily_order_key' => 'shop:'.$this->shop->id.':2026-08-10',
        ]);

        // 1. August: Shop has settlement obligation ₹1,000 on 2026-08-10
        $augInvoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-AUG-001',
            'final_total' => 1000.00,
            'business_date' => '2026-08-10',
            'created_at' => Carbon::parse('2026-08-10 10:00:00'),
        ]);

        $augTxn = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'profile_id' => $this->profile->id,
            'entry_type_id' => $settlementType->id,
            'business_date' => '2026-08-10',
            'source_type' => 'invoice',
            'source_id' => $augInvoice->id,
            'description' => 'August GL Invoice',
            'amount' => 1000.00,
            'direction' => 'debit',
            'funding_source' => 'sales',
            'status' => 'posted',
            'affects_balance' => true,
        ]);

        // Company receives payment of ₹1,200 in August
        $pr = ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-AUG-001',
            'requested_amount' => 1200.00,
            'approved_amount' => 1200.00,
            'status' => 'verified',
            'payment_method' => 'bank_transfer',
            'payment_date' => '2026-08-12',
            'created_at' => Carbon::parse('2026-08-12 11:00:00'),
        ]);

        // Allocate ₹1,000 of August payment to August invoice
        ShopPaymentLedgerAllocation::create([
            'payment_request_id' => $pr->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $augTxn->id,
            'amount' => 1000.00,
            'reconciled_by' => $this->admin->id,
            'created_at' => Carbon::parse('2026-08-12 12:30:00'),
        ]);

        // Snapshot closing August with 0 physical
        ShopDailyLedgerSnapshot::create([
            'shop_id' => $this->shop->id,
            'profile_id' => $this->profile->id,
            'business_date' => '2026-08-31',
            'opening_balance' => 0.00,
            'closing_balance' => 0.00,
            'opening_shop_position' => 0.00,
            'closing_shop_position' => 0.00,
            'settlement_due' => 1000.00,
            'company_paid' => 0.00,
            'shop_paid' => 0.00,
            'status' => 'closed',
        ]);

        /** @var MonthlyClosingSummaryService $service */
        $service = app(MonthlyClosingSummaryService::class);

        // August Summary Verification
        $augustSummary = $service->getShopMonthlyDetail($this->shop, '2026-08');
        $this->assertSame(1000.00, $augustSummary['activity']['settlement_due']);
        $this->assertSame(1200.00, $augustSummary['activity']['company_received']);
        $this->assertSame(1000.00, $augustSummary['activity']['allocated_to_month']);
        $this->assertSame(200.00, $augustSummary['closing']['available_credit']);

        // 2. September: Shop has settlement obligation of ₹500 on 2026-09-05
        $order2 = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-TEST-002',
            'business_date' => '2026-09-05',
            'shop_daily_order_key' => 'shop:'.$this->shop->id.':2026-09-05',
        ]);

        $septInvoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order2->id,
            'invoice_number' => 'INV-SEPT-001',
            'final_total' => 500.00,
            'business_date' => '2026-09-05',
            'created_at' => Carbon::parse('2026-09-05 10:00:00'),
        ]);

        $septTxn = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'profile_id' => $this->profile->id,
            'entry_type_id' => $settlementType->id,
            'business_date' => '2026-09-05',
            'source_type' => 'invoice',
            'source_id' => $septInvoice->id,
            'description' => 'September GL Invoice',
            'amount' => 500.00,
            'direction' => 'debit',
            'funding_source' => 'sales',
            'status' => 'posted',
            'affects_balance' => true,
        ]);

        // On Sept 15, allocate the remaining ₹200 from the AUGUST payment to the September transaction
        ShopPaymentLedgerAllocation::create([
            'payment_request_id' => $pr->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $septTxn->id,
            'amount' => 200.00,
            'reconciled_by' => $this->admin->id,
            'created_at' => Carbon::parse('2026-09-15 14:00:00'),
        ]);

        // September Summary Verification
        $septemberSummary = $service->getShopMonthlyDetail($this->shop, '2026-09');

        // Opening Available Credit carried from August
        $this->assertSame(200.00, $septemberSummary['opening']['available_credit']);

        // New Payments received in September must be 0
        $this->assertSame(0.00, $septemberSummary['activity']['company_received']);

        // Previous credit utilized in September = 200
        $this->assertSame(200.00, $septemberSummary['activity']['previous_credit_utilized']);

        // Total allocated to September obligations = 200
        $this->assertSame(200.00, $septemberSummary['activity']['allocated_to_month']);

        // Closing Available Credit for September = 0 (200 opening + 0 new - 200 utilized)
        $this->assertSame(0.00, $septemberSummary['closing']['available_credit']);
    }

    public function test_only_active_client_shops_appear_in_monthly_closing_summary(): void
    {
        $this->actingAs($this->admin);

        // 1. Another Active Client Shop
        $client2 = Client::create(['name' => 'Metro Retail Group', 'code' => 'MRG', 'status' => 'active']);
        $clientShop2 = Shop::factory()->create([
            'name' => 'Metro Supermart',
            'code' => 'MS01',
            'client_id' => $client2->id,
            'accounting_enabled' => true,
            'status' => 'active',
        ]);

        // 2. Direct / Internal shop (no client_id)
        $internalShop = Shop::factory()->create([
            'name' => 'Internal Central Kitchen',
            'code' => 'ICK01',
            'client_id' => null,
            'accounting_enabled' => true,
            'status' => 'active',
        ]);

        // 3. Warehouse entity shop (warehouse_tag set, no client_id)
        $warehouseShop = Shop::factory()->create([
            'name' => 'Main Distribution Hub',
            'code' => 'WH01',
            'warehouse_tag' => 'MAIN_WH',
            'client_id' => null,
            'accounting_enabled' => false,
            'status' => 'active',
        ]);

        // 4. Inactive Client Shop
        $inactiveClientShop = Shop::factory()->create([
            'name' => 'Old Closed Boutique',
            'code' => 'OLD01',
            'client_id' => $this->client->id,
            'accounting_enabled' => true,
            'status' => 'inactive',
        ]);

        // 5. Test/demo non-client entity
        $testShop = Shop::factory()->create([
            'name' => 'Dev Test Entity',
            'code' => 'TEST01',
            'client_id' => null,
            'accounting_enabled' => false,
            'status' => 'active',
        ]);

        /** @var MonthlyClosingSummaryService $service */
        $service = app(MonthlyClosingSummaryService::class);
        $summary = $service->getAllShopsSummary('2026-08');

        $includedShopIds = collect($summary['shops'])->pluck('shop_id')->all();

        // Active client shops MUST be included
        $this->assertContains($this->shop->id, $includedShopIds);
        $this->assertContains($clientShop2->id, $includedShopIds);

        // Non-client, warehouse, inactive, test shops MUST NOT be included
        $this->assertNotContains($internalShop->id, $includedShopIds);
        $this->assertNotContains($warehouseShop->id, $includedShopIds);
        $this->assertNotContains($inactiveClientShop->id, $includedShopIds);
        $this->assertNotContains($testShop->id, $includedShopIds);

        // Web view verification
        $response = $this->get(route('admin.cashbook.monthly-closing-summary.index', ['month' => '2026-08']));
        $response->assertOk();
        $response->assertSee('Casio Boutique');
        $response->assertSee('Metro Supermart');
        $response->assertDontSee('Internal Central Kitchen');
        $response->assertDontSee('Main Distribution Hub');
        $response->assertDontSee('Old Closed Boutique');
        $response->assertDontSee('Dev Test Entity');
    }

    public function test_direct_url_access_to_ineligible_or_non_client_shop_is_rejected_with_404(): void
    {
        $this->actingAs($this->admin);

        // 1. Direct / Internal Shop (no client_id)
        $internalShop = Shop::factory()->create([
            'name' => 'Internal Shop',
            'code' => 'INT01',
            'client_id' => null,
            'accounting_enabled' => true,
            'status' => 'active',
        ]);

        $responseInternal = $this->get(route('admin.cashbook.monthly-closing-summary.show', ['shop' => $internalShop->id]));
        $responseInternal->assertNotFound();

        // 2. Inactive Client Shop
        $inactiveShop = Shop::factory()->create([
            'name' => 'Inactive Client Shop',
            'code' => 'INACT01',
            'client_id' => $this->client->id,
            'accounting_enabled' => true,
            'status' => 'inactive',
        ]);

        $responseInactive = $this->get(route('admin.cashbook.monthly-closing-summary.show', ['shop' => $inactiveShop->id]));
        $responseInactive->assertNotFound();

        // 3. Active Client Shop (valid) -> 200 OK
        $responseValid = $this->get(route('admin.cashbook.monthly-closing-summary.show', ['shop' => $this->shop->id]));
        $responseValid->assertOk();
    }

    public function test_historical_months_remain_immutable_and_allocation_caps_apply(): void
    {
        $settlementType = LedgerEntryType::firstOrCreate(
            ['code' => 'settlement_due'],
            ['name' => 'Settlement Due', 'category' => 'settlement', 'affects_balance' => true, 'normal_balance' => 'debit']
        );

        ShopLedgerEntrySetting::firstOrCreate(
            [
                'profile_id' => $this->profile->id,
                'shop_id' => $this->shop->id,
                'entry_type_id' => $settlementType->id,
            ],
            [
                'entry_direction' => 'debit',
                'effective_from' => '2026-01-01',
                'enabled' => true,
                'include_in_expense' => true,
                'is_active' => true,
            ]
        );

        // July obligation of 500
        $julyTxn = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'profile_id' => $this->profile->id,
            'entry_type_id' => $settlementType->id,
            'business_date' => '2026-07-20',
            'amount' => 500.00,
            'direction' => 'debit',
            'funding_source' => 'sales',
            'status' => 'posted',
            'affects_balance' => true,
        ]);

        // July payment of 500 fully allocated in July
        $julyPayment = ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-JUL-001',
            'requested_amount' => 500.00,
            'approved_amount' => 500.00,
            'status' => 'verified',
            'payment_method' => 'bank_transfer',
            'payment_date' => '2026-07-21',
        ]);

        ShopPaymentLedgerAllocation::create([
            'payment_request_id' => $julyPayment->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $julyTxn->id,
            'amount' => 500.00,
            'reconciled_by' => $this->admin->id,
        ]);

        /** @var MonthlyClosingSummaryService $service */
        $service = app(MonthlyClosingSummaryService::class);

        // Query July before and after August activity
        $julyBefore = $service->getShopMonthlyDetail($this->shop, '2026-07');
        $this->assertSame(500.00, $julyBefore['current_position']['already_allocated']);
        $this->assertSame(0.00, $julyBefore['current_position']['allocation_pending']);

        // August receives payment of 200 with open obligation of 100
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'profile_id' => $this->profile->id,
            'entry_type_id' => $settlementType->id,
            'business_date' => '2026-08-10',
            'amount' => 100.00,
            'direction' => 'debit',
            'funding_source' => 'sales',
            'status' => 'posted',
            'affects_balance' => true,
        ]);

        ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-AUG-SURPLUS',
            'requested_amount' => 200.00,
            'approved_amount' => 200.00,
            'status' => 'verified',
            'payment_method' => 'bank_transfer',
            'payment_date' => '2026-08-11',
        ]);

        // Query August: payment cannot be projected beyond remaining due (max 100 allocated, 100 remains credit)
        $augustDetail = $service->getShopMonthlyDetail($this->shop, '2026-08');
        $this->assertSame(100.00, $augustDetail['projected_position']['additional_valid_allocation']);
        $this->assertSame(100.00, $augustDetail['projected_position']['remaining_unallocated_credit']);

        // Historical July remains identical
        $julyAfter = $service->getShopMonthlyDetail($this->shop, '2026-07');
        $this->assertSame($julyBefore['current_position'], $julyAfter['current_position']);
        $this->assertSame($julyBefore['projected_position'], $julyAfter['projected_position']);
    }

    public function test_opening_balance_column_in_all_shops_summary_and_blade_view(): void
    {
        // 1. Create August 31 closing snapshot with ₹50,000 for Casio
        ShopDailyLedgerSnapshot::create([
            'shop_id' => $this->shop->id,
            'profile_id' => $this->profile->id,
            'business_date' => '2026-08-31',
            'opening_balance' => 0.00,
            'closing_balance' => 50000.00,
            'opening_shop_position' => 0.00,
            'closing_shop_position' => 50000.00,
            'closing_company_pending' => 0.00,
            'closing_petty' => 0.00,
            'settlement_due' => 0.00,
            'company_paid' => 0.00,
            'shop_paid' => 0.00,
            'status' => 'closed',
        ]);

        // 2. Set official accounting start date for September 1 as ₹0
        ShopAccountingOpening::create([
            'shop_id' => $this->shop->id,
            'accounting_start_date' => '2026-09-01',
            'opening_shop_company_balance' => 0.00,
            'opening_balance_direction' => 'settled',
            'opening_petty_balance' => 0.00,
        ]);

        // 3. Add September transaction
        $settlementType = LedgerEntryType::firstOrCreate(
            ['code' => 'settlement_due'],
            ['name' => 'Settlement Due', 'category' => 'settlement', 'affects_balance' => true, 'normal_balance' => 'debit']
        );

        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'profile_id' => $this->profile->id,
            'entry_type_id' => $settlementType->id,
            'business_date' => '2026-09-05',
            'amount' => 15000.00,
            'direction' => 'debit',
            'funding_source' => 'sales',
            'status' => 'posted',
            'affects_balance' => true,
        ]);

        /** @var MonthlyClosingSummaryService $service */
        $service = app(MonthlyClosingSummaryService::class);

        // A. Service level verification for September
        $septemberSummary = $service->getAllShopsSummary('2026-09');
        $casioRow = collect($septemberSummary['shops'])->firstWhere('shop_id', $this->shop->id);
        $this->assertNotNull($casioRow);
        $this->assertSame(0.00, $casioRow['opening_balance']);
        $this->assertSame('settled', $casioRow['opening_direction']);
        $this->assertSame('Settled', $casioRow['opening_direction_label']);

        // B. Service level verification for August (historical)
        $augustSummary = $service->getAllShopsSummary('2026-08');
        $casioAugRow = collect($augustSummary['shops'])->firstWhere('shop_id', $this->shop->id);
        $this->assertNotNull($casioAugRow);
        $this->assertSame(0.00, $casioAugRow['opening_balance']);
        $this->assertSame(50000.00, $casioAugRow['closing']['physical_position']);

        // C. Controller and Blade view verification
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.monthly-closing-summary.index', ['month' => '2026-09']));

        $response->assertOk();
        $response->assertSee('Opening Balance');
        $response->assertSee('₹0.00');
        $response->assertSee('Settled');

        // Verify read-only: no writes occurred
        $this->assertEquals(1, ShopAccountingOpening::count());
        $this->assertEquals(1, ShopLedgerTransaction::where('business_date', '>=', '2026-09-01')->count());
    }

    public function test_opening_balance_direction_display_when_non_zero(): void
    {
        // Set an opening with shop_owes_company
        ShopAccountingOpening::create([
            'shop_id' => $this->shop->id,
            'accounting_start_date' => '2026-10-01',
            'opening_shop_company_balance' => 25000.00,
            'opening_balance_direction' => 'shop_owes_company',
            'opening_petty_balance' => 0.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.monthly-closing-summary.index', ['month' => '2026-10']));

        $response->assertOk();
        $response->assertSee('₹25,000.00');
        $response->assertSee('Shop → Company');
    }
}
