<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Cashbook\ShopPaymentLedgerReconciliationService;
use App\Services\Cashbook\ShopSettlementService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPayableDynamicSettlementAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $cashAccount;

    private CompanyAccount $bankAccount;

    private ShopSettlementService $settlementService;

    private ShopPaymentLedgerReconciliationService $reconciliationService;

    private DailyLedgerService $dailyLedgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'name' => 'Casio Veg',
            'code' => 'AV_CASIO',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $this->profile = ShopLedgerProfile::where('shop_id', $this->shop->id)->firstOrFail();

        $this->cashAccount = CompanyAccount::create([
            'name' => 'Main Cash Safe',
            'account_type' => 'cash',
            'account_number' => 'CASH-01',
            'enabled' => true,
            'current_balance' => 50000.00,
        ]);

        $this->bankAccount = CompanyAccount::create([
            'name' => 'Kotak Bank',
            'account_type' => 'bank',
            'account_number' => 'BANK-01',
            'enabled' => true,
            'current_balance' => 100000.00,
        ]);

        $this->settlementService = app(ShopSettlementService::class);
        $this->reconciliationService = app(ShopPaymentLedgerReconciliationService::class);
        $this->dailyLedgerService = app(DailyLedgerService::class);
    }

    private function getCategory(string $code, ?Shop $targetShop = null): ShopLedgerEntrySetting
    {
        $targetShop = $targetShop ?? $this->shop;
        $type = LedgerEntryType::where('code', $code)->firstOrFail();

        return ShopLedgerEntrySetting::firstOrCreate([
            'shop_id' => $targetShop->id,
            'entry_type_id' => $type->id,
        ], [
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'display_order' => 10,
        ]);
    }

    private function recordLedgerEntry(ShopLedgerEntrySetting $setting, string $date, float $amount, string $status = 'posted'): ShopLedgerTransaction
    {
        return ShopLedgerTransaction::create([
            'shop_id' => $setting->shop_id,
            'entry_type_id' => $setting->entry_type_id,
            'business_date' => $date,
            'amount' => $amount,
            'funding_source' => 'sales',
            'direction' => $setting->entryType?->direction ?? 'income',
            'status' => $status,
        ]);
    }

    public function test_dynamic_formula_with_category_add_and_subtract_terms(): void
    {
        $sales = $this->getCategory('cash_sales');
        $otherIncome = $this->getCategory('card');
        $expenses = $this->getCategory('salary');
        $returns = $this->getCategory('cash_purchase');

        // Dynamic settlement: Sales + Other Income - Expenses - Returns = Company Payable
        $payable = $this->settlementService->save($this->profile, [
            'name' => 'Dynamic Company Payable',
            'is_company_payable' => true,
            'enabled' => true,
            'items' => [
                ['setting_id' => $sales->id, 'role' => 'add'],
                ['setting_id' => $otherIncome->id, 'role' => 'add'],
                ['setting_id' => $expenses->id, 'role' => 'subtract'],
                ['setting_id' => $returns->id, 'role' => 'subtract'],
            ],
        ], null, $this->admin->id);

        $this->assertTrue($payable->is_company_payable);
        $this->assertCount(4, $payable->items);

        // Record transactions for 2026-09-06
        $this->recordLedgerEntry($sales, '2026-09-06', 50000.00);
        $this->recordLedgerEntry($otherIncome, '2026-09-06', 20000.00);
        $this->recordLedgerEntry($expenses, '2026-09-06', 5000.00);
        $this->recordLedgerEntry($returns, '2026-09-06', 3000.00);

        $summary = $this->settlementService->calculateCompanyPayable($this->shop->id, '2026-09-06', '2026-09-06');

        // Net = 50,000 + 20,000 - 5,000 - 3,000 = 62,000
        $this->assertEquals(62000.00, $summary['formula_net']);
        $this->assertEquals(0.00, $summary['verified_payments_received']);
        $this->assertEquals(62000.00, $summary['remaining_company_payable']);
        $this->assertCount(4, $summary['category_breakdown']);
    }

    public function test_exactly_one_company_payable_settlement_per_shop(): void
    {
        $sales = $this->getCategory('cash_sales');

        $firstPayable = $this->settlementService->save($this->profile, [
            'name' => 'First Payable',
            'is_company_payable' => true,
            'enabled' => true,
            'items' => [['setting_id' => $sales->id, 'role' => 'add']],
        ], null, $this->admin->id);

        $this->assertTrue($firstPayable->fresh()->is_company_payable);

        // Create second settlement marked as Company Payable
        $secondPayable = $this->settlementService->save($this->profile, [
            'name' => 'Second Payable',
            'is_company_payable' => true,
            'enabled' => true,
            'items' => [['setting_id' => $sales->id, 'role' => 'add']],
        ], null, $this->admin->id);

        $this->assertFalse($firstPayable->fresh()->is_company_payable);
        $this->assertTrue($secondPayable->fresh()->is_company_payable);

        // Verify only 1 relation is marked as company payable
        $payableCount = ShopCashbookRelation::where('shop_id', $this->shop->id)
            ->where('is_company_payable', true)
            ->count();

        $this->assertSame(1, $payableCount);
    }

    public function test_repeated_default_synchronization_without_duplicates(): void
    {
        $syncService = app(CashbookShopSyncService::class);

        // Run sync multiple times
        $syncService->syncAndGetProfiles();
        $syncService->syncAndGetProfiles();
        $syncService->syncAndGetProfiles();

        $payableCount = ShopCashbookRelation::where('shop_id', $this->shop->id)
            ->where('is_company_payable', true)
            ->count();

        $this->assertSame(1, $payableCount);

        // Check payments header exists and is protected
        $paymentSettings = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'shop_paid_company'))
            ->get();

        $this->assertSame(1, $paymentSettings->count());
    }

    public function test_casio_example_302646_minus_10000_equals_292646(): void
    {
        $sales = $this->getCategory('cash_sales');

        $this->settlementService->save($this->profile, [
            'name' => 'Company Payable',
            'is_company_payable' => true,
            'enabled' => true,
            'items' => [['setting_id' => $sales->id, 'role' => 'add']],
        ], null, $this->admin->id);

        // Record 302,646 cash sales
        $this->recordLedgerEntry($sales, '2026-09-06', 302646.00);

        $beforePayment = $this->settlementService->calculateCompanyPayable($this->shop->id, '2026-09-06', '2026-09-06');
        $this->assertEquals(302646.00, $beforePayment['remaining_company_payable']);

        // Receive ₹10,000 cash payment today
        $payment = $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 10000.00,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'company_account_id' => $this->cashAccount->id,
            'notes' => 'Received as Cash',
        ], $this->admin->id);

        $this->assertNotNull($payment);

        // Payments section in cashbook should reflect the transaction
        $cashbookTx = ShopLedgerTransaction::where('reference_type', ShopInvoicePaymentRequest::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($cashbookTx);
        $this->assertEquals(10000.00, (float) $cashbookTx->amount);
        $this->assertSame('posted', $cashbookTx->status);
        $this->assertSame('Received as Cash', $cashbookTx->notes);

        // Recalculate Company Payable
        $afterPayment = $this->settlementService->calculateCompanyPayable($this->shop->id, '2026-09-06', '2026-09-06');
        $this->assertEquals(302646.00, $afterPayment['formula_net']);
        $this->assertEquals(10000.00, $afterPayment['verified_payments_received']);
        $this->assertEquals(292646.00, $afterPayment['remaining_company_payable']);
    }

    public function test_other_payment_methods_bank_transfer_and_upi(): void
    {
        $sales = $this->getCategory('cash_sales');
        $this->settlementService->save($this->profile, [
            'name' => 'Company Payable',
            'is_company_payable' => true,
            'enabled' => true,
            'items' => [['setting_id' => $sales->id, 'role' => 'add']],
        ], null, $this->admin->id);

        $this->recordLedgerEntry($sales, '2026-09-06', 50000.00);

        // 1. Bank transfer
        $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 15000.00,
            'payment_method' => 'bank_transfer',
            'payment_date' => '2026-09-06',
            'payment_reference' => 'NEFT998877',
            'company_account_id' => $this->bankAccount->id,
        ], $this->admin->id);

        // 2. Online UPI
        $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 5000.00,
            'payment_method' => 'online_upi',
            'payment_date' => '2026-09-06',
            'payment_reference' => 'UPI112233',
            'company_account_id' => $this->bankAccount->id,
        ], $this->admin->id);

        $summary = $this->settlementService->calculateCompanyPayable($this->shop->id, '2026-09-06', '2026-09-06');
        $this->assertEquals(20000.00, $summary['verified_payments_received']);
        $this->assertEquals(30000.00, $summary['remaining_company_payable']);
    }

    public function test_pending_cheque_does_not_reduce_payable_until_verified(): void
    {
        $sales = $this->getCategory('cash_sales');
        $this->settlementService->save($this->profile, [
            'name' => 'Company Payable',
            'is_company_payable' => true,
            'enabled' => true,
            'items' => [['setting_id' => $sales->id, 'role' => 'add']],
        ], null, $this->admin->id);

        $this->recordLedgerEntry($sales, '2026-09-06', 100000.00);

        // Record cheque payment (pending)
        $chequePayment = $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 25000.00,
            'payment_method' => 'cheque',
            'payment_date' => '2026-09-06',
            'cheque_bank_name' => 'HDFC Bank',
            'payment_reference' => 'CHQ123456',
            'company_account_id' => $this->bankAccount->id,
        ], $this->admin->id);

        $this->assertSame('pending', $chequePayment->cheque_status);

        // Pending cheque must NOT appear in cashbook transactions
        $tx = ShopLedgerTransaction::where('reference_type', ShopInvoicePaymentRequest::class)
            ->where('reference_id', $chequePayment->id)
            ->first();
        $this->assertNull($tx);

        // Payable remains 100,000
        $summary = $this->settlementService->calculateCompanyPayable($this->shop->id, '2026-09-06', '2026-09-06');
        $this->assertEquals(100000.00, $summary['remaining_company_payable']);
        $this->assertEquals(0.00, $summary['verified_payments_received']);

        // Clear the cheque via reconciliation / status update
        $chequePayment->update([
            'cheque_status' => 'cleared',
            'reconciliation_status' => 'reconciled',
            'last_reconciled_at' => now(),
        ]);
        $this->reconciliationService->syncPaymentToCashbook($chequePayment, $this->admin->id);

        // Now payable is reduced by 25,000
        $summaryAfterClear = $this->settlementService->calculateCompanyPayable($this->shop->id, '2026-09-06', '2026-09-06');
        $this->assertEquals(25000.00, $summaryAfterClear['verified_payments_received']);
        $this->assertEquals(75000.00, $summaryAfterClear['remaining_company_payable']);
    }

    public function test_repeated_reconciliation_remains_idempotent(): void
    {
        $sales = $this->getCategory('cash_sales');
        $this->settlementService->save($this->profile, [
            'name' => 'Company Payable',
            'is_company_payable' => true,
            'enabled' => true,
            'items' => [['setting_id' => $sales->id, 'role' => 'add']],
        ], null, $this->admin->id);

        $this->recordLedgerEntry($sales, '2026-09-06', 50000.00);

        $payment = $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 10000.00,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'company_account_id' => $this->cashAccount->id,
        ], $this->admin->id);

        // Call syncPaymentToCashbook multiple times
        $this->reconciliationService->syncPaymentToCashbook($payment, $this->admin->id);
        $this->reconciliationService->syncPaymentToCashbook($payment, $this->admin->id);
        $this->reconciliationService->syncPaymentToCashbook($payment, $this->admin->id);

        $txCount = ShopLedgerTransaction::where('reference_type', ShopInvoicePaymentRequest::class)
            ->where('reference_id', $payment->id)
            ->count();

        $this->assertSame(1, $txCount);

        $summary = $this->settlementService->calculateCompanyPayable($this->shop->id, '2026-09-06', '2026-09-06');
        $this->assertEquals(10000.00, $summary['verified_payments_received']);
        $this->assertEquals(40000.00, $summary['remaining_company_payable']);
    }

    public function test_cross_shop_and_cross_company_isolation(): void
    {
        $otherShop = Shop::factory()->create([
            'name' => 'Foreign Shop',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $otherProfile = ShopLedgerProfile::where('shop_id', $otherShop->id)->firstOrFail();

        $sales1 = $this->getCategory('cash_sales');
        $sales2 = $this->getCategory('cash_sales', $otherShop);

        $this->settlementService->save($this->profile, [
            'name' => 'Shop 1 Payable',
            'is_company_payable' => true,
            'enabled' => true,
            'items' => [['setting_id' => $sales1->id, 'role' => 'add']],
        ], null, $this->admin->id);

        $this->settlementService->save($otherProfile, [
            'name' => 'Shop 2 Payable',
            'is_company_payable' => true,
            'enabled' => true,
            'items' => [['setting_id' => $sales2->id, 'role' => 'add']],
        ], null, $this->admin->id);

        // Record for shop 1
        $this->recordLedgerEntry($sales1, '2026-09-06', 50000.00);
        $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 10000.00,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'company_account_id' => $this->cashAccount->id,
        ], $this->admin->id);

        // Record for shop 2
        $this->recordLedgerEntry($sales2, '2026-09-06', 80000.00);
        $this->reconciliationService->recordReceivedPayment($otherShop, [
            'amount' => 30000.00,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'company_account_id' => $this->cashAccount->id,
        ], $this->admin->id);

        // Assert Shop 1
        $summary1 = $this->settlementService->calculateCompanyPayable($this->shop->id, '2026-09-06', '2026-09-06');
        $this->assertEquals(50000.00, $summary1['formula_net']);
        $this->assertEquals(10000.00, $summary1['verified_payments_received']);
        $this->assertEquals(40000.00, $summary1['remaining_company_payable']);

        // Assert Shop 2
        $summary2 = $this->settlementService->calculateCompanyPayable($otherShop->id, '2026-09-06', '2026-09-06');
        $this->assertEquals(80000.00, $summary2['formula_net']);
        $this->assertEquals(30000.00, $summary2['verified_payments_received']);
        $this->assertEquals(50000.00, $summary2['remaining_company_payable']);
    }

    public function test_payment_deletion_and_reversal_cleans_cashbook(): void
    {
        $sales = $this->getCategory('cash_sales');
        $this->settlementService->save($this->profile, [
            'name' => 'Company Payable',
            'is_company_payable' => true,
            'enabled' => true,
            'items' => [['setting_id' => $sales->id, 'role' => 'add']],
        ], null, $this->admin->id);

        $payment = $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 12000.00,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'company_account_id' => $this->cashAccount->id,
        ], $this->admin->id);

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'reference_type' => ShopInvoicePaymentRequest::class,
            'reference_id' => $payment->id,
        ]);

        // Delete the payment
        $this->reconciliationService->deletePayment($payment, $this->admin->id);

        $this->assertDatabaseMissing('shop_ledger_transactions', [
            'reference_type' => ShopInvoicePaymentRequest::class,
            'reference_id' => $payment->id,
        ]);

        $summary = $this->settlementService->calculateCompanyPayable($this->shop->id, '2026-09-06', '2026-09-06');
        $this->assertEquals(0.00, $summary['verified_payments_received']);
    }

    public function test_date_filtered_calculations(): void
    {
        $sales = $this->getCategory('cash_sales');
        $this->settlementService->save($this->profile, [
            'name' => 'Company Payable',
            'is_company_payable' => true,
            'enabled' => true,
            'items' => [['setting_id' => $sales->id, 'role' => 'add']],
        ], null, $this->admin->id);

        // Day 1
        $this->recordLedgerEntry($sales, '2026-09-01', 10000.00);
        $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 4000.00,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-01',
            'company_account_id' => $this->cashAccount->id,
        ], $this->admin->id);

        // Day 2
        $this->recordLedgerEntry($sales, '2026-09-02', 20000.00);
        $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 8000.00,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-02',
            'company_account_id' => $this->cashAccount->id,
        ], $this->admin->id);

        // Check Day 1 only
        $day1 = $this->settlementService->calculateCompanyPayable($this->shop->id, '2026-09-01', '2026-09-01');
        $this->assertEquals(10000.00, $day1['formula_net']);
        $this->assertEquals(4000.00, $day1['verified_payments_received']);
        $this->assertEquals(6000.00, $day1['remaining_company_payable']);

        // Check Month range (Day 1 + Day 2)
        $month = $this->settlementService->calculateCompanyPayable($this->shop->id, '2026-09-01', '2026-09-30');
        $this->assertEquals(30000.00, $month['formula_net']);
        $this->assertEquals(12000.00, $month['verified_payments_received']);
        $this->assertEquals(18000.00, $month['remaining_company_payable']);
    }

    public function test_ui_endpoints_render_correct_company_payable_values(): void
    {
        $sales = $this->getCategory('cash_sales');
        $this->settlementService->save($this->profile, [
            'name' => 'Company Payable',
            'is_company_payable' => true,
            'enabled' => true,
            'items' => [['setting_id' => $sales->id, 'role' => 'add']],
        ], null, $this->admin->id);

        $this->recordLedgerEntry($sales, '2026-09-06', 302646.00);

        $this->reconciliationService->recordReceivedPayment($this->shop, [
            'amount' => 10000.00,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-06',
            'company_account_id' => $this->cashAccount->id,
            'notes' => 'Received as Cash',
        ], $this->admin->id);

        // 1. Settlement settings index page
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.settlements.index', $this->profile->slug))
            ->assertOk()
            ->assertSee('Company Payable');

        // 2. Main Cashbook shop page (where summary.blade.php renders calculated values)
        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.shop.show', ['shop' => $this->profile->slug, 'date' => '2026-09-06']))
            ->assertOk()
            ->assertSee('Company Payable')
            ->assertSee('302,646.00')
            ->assertSee('10,000.00')
            ->assertSee('292,646.00');
    }
}
