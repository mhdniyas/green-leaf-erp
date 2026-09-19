<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopAccountingOpening;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\Cashbook\MonthlyClosingSummaryService;
use App\Services\Cashbook\ShopPaymentLedgerReconciliationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SeptemberAccountingOpeningTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $user;

    private LedgerEntryType $salesEntryType;

    private LedgerEntryType $expenseEntryType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->shop = Shop::create([
            'name' => 'Casio Test Shop',
            'code' => 'TEST_CASIO',
            'status' => 'active',
            'currency_code' => 'INR',
        ]);

        $this->salesEntryType = LedgerEntryType::create([
            'code' => 'sales_cash',
            'name' => 'Sales Cash',
            'category' => 'income',
            'active' => true,
        ]);

        $this->expenseEntryType = LedgerEntryType::create([
            'code' => 'gl_bill',
            'name' => 'GL Bill',
            'category' => 'expense',
            'active' => true,
        ]);
    }

    public function test_shop_accounting_opening_unique_constraint(): void
    {
        ShopAccountingOpening::create([
            'shop_id' => $this->shop->id,
            'accounting_start_date' => '2026-09-01',
            'opening_shop_company_balance' => 0.00,
            'opening_balance_direction' => 'settled',
            'opening_petty_balance' => 0.00,
        ]);

        $this->expectException(QueryException::class);

        ShopAccountingOpening::create([
            'shop_id' => $this->shop->id,
            'accounting_start_date' => '2026-09-01',
            'opening_shop_company_balance' => 100.00,
            'opening_balance_direction' => 'shop_owes_company',
            'opening_petty_balance' => 50.00,
        ]);
    }

    public function test_balance_calculator_starts_at_zero_on_and_after_accounting_start_date(): void
    {
        // 1. Create August 31 closing snapshot with old balance ₹50,000
        ShopDailyLedgerSnapshot::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-31',
            'opening_shop_position' => 0.00,
            'closing_shop_position' => 50000.00,
            'closing_company_pending' => 0.00,
            'closing_petty' => 1000.00,
        ]);

        // 2. Before creating opening record, Sep 1 would use Aug 31 closing (50000)
        $calculator = app(BalanceCalculator::class);
        $beforeOpening = $calculator->openingBalances($this->shop->id, '2026-09-01');
        $this->assertEquals(50000.00, $beforeOpening['shop_position']);

        // 3. Create official accounting opening for 2026-09-01 as ₹0
        ShopAccountingOpening::create([
            'shop_id' => $this->shop->id,
            'accounting_start_date' => '2026-09-01',
            'opening_shop_company_balance' => 0.00,
            'opening_balance_direction' => 'settled',
            'opening_petty_balance' => 0.00,
        ]);

        // 4. After opening record, Sep 1 starts at ₹0
        $afterOpening = $calculator->openingBalances($this->shop->id, '2026-09-01');
        $this->assertEquals(0.00, $afterOpening['shop_position']);
        $this->assertEquals(0.00, $afterOpening['company_pending']);
        $this->assertEquals(0.00, $afterOpening['petty']);

        // 5. Pre-opening date (e.g. 2026-08-31) still sees pre-opening balances
        $augOpening = $calculator->openingBalances($this->shop->id, '2026-08-31');
        $this->assertEquals(0.00, $augOpening['shop_position']); // (No snapshot before Aug 31)
    }

    public function test_cross_period_allocation_is_prevented_by_reconciliation_service(): void
    {
        // Set accounting start date
        ShopAccountingOpening::create([
            'shop_id' => $this->shop->id,
            'accounting_start_date' => '2026-09-01',
            'opening_shop_company_balance' => 0.00,
            'opening_balance_direction' => 'settled',
            'opening_petty_balance' => 0.00,
        ]);

        // Pre-opening August payment
        $augustPayment = ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-AUG-001',
            'payment_date' => '2026-08-31',
            'payment_method' => 'bank_transfer',
            'requested_amount' => 10000.00,
            'approved_amount' => 10000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'requested_by_user_id' => $this->user->id,
        ]);

        // September transaction
        $septemberTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-02',
            'entry_type_id' => $this->expenseEntryType->id,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'amount' => 2000.00,
            'settlement_delta' => 2000.00,
            'status' => 'approved',
            'entered_by_user_id' => $this->user->id,
        ]);

        $reconciliationService = app(ShopPaymentLedgerReconciliationService::class);

        // Attempt to allocate August payment to September transaction -> should throw ValidationException
        $this->expectException(ValidationException::class);

        $reconciliationService->allocatePayment(
            $augustPayment,
            [['ledger_transaction_id' => $septemberTx->id, 'amount' => 2000.00]],
            $this->user->id
        );
    }

    public function test_allocation_reversal_is_auditable_and_non_destructive(): void
    {
        $payment = ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'payment_reference' => 'PAY-TEST-002',
            'payment_date' => '2026-08-31',
            'payment_method' => 'bank_transfer',
            'requested_amount' => 5000.00,
            'approved_amount' => 5000.00,
            'status' => 'approved',
            'reconciliation_status' => 'reconciled',
            'requested_by_user_id' => $this->user->id,
        ]);

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-01',
            'entry_type_id' => $this->expenseEntryType->id,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'amount' => 5000.00,
            'settlement_delta' => 5000.00,
            'status' => 'approved',
            'entered_by_user_id' => $this->user->id,
        ]);

        // Create legacy allocation row directly
        $allocation = ShopPaymentLedgerAllocation::create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $payment->id,
            'shop_ledger_transaction_id' => $tx->id,
            'amount' => 5000.00,
            'status' => 'active',
            'allocated_by' => $this->user->id,
            'allocated_at' => now(),
        ]);

        $this->assertTrue($allocation->isActive());
        $this->assertFalse($allocation->isReversed());

        // Reverse allocation
        $reconciliationService = app(ShopPaymentLedgerReconciliationService::class);
        $reversed = $reconciliationService->reverseAllocation(
            $allocation,
            $this->user->id,
            'Cutoff reversal for test'
        );

        $this->assertTrue($reversed->isReversed());
        $this->assertEquals('reversed', $reversed->status);
        $this->assertEquals($this->user->id, $reversed->reversed_by);
        $this->assertEquals('Cutoff reversal for test', $reversed->reversal_reason);
        $this->assertNotNull($reversed->reversed_at);

        // Verify active scope excludes it
        $this->assertEquals(0, ShopPaymentLedgerAllocation::active()->count());
        // Verify row still exists in database for audit
        $this->assertDatabaseHas('shop_payment_ledger_allocations', [
            'id' => $allocation->id,
            'status' => 'reversed',
        ]);
    }

    public function test_monthly_closing_summary_respects_accounting_start_date(): void
    {
        // Set accounting start date
        ShopAccountingOpening::create([
            'shop_id' => $this->shop->id,
            'accounting_start_date' => '2026-09-01',
            'opening_shop_company_balance' => 0.00,
            'opening_balance_direction' => 'settled',
            'opening_petty_balance' => 0.00,
        ]);

        // Create August 31 snapshot
        ShopDailyLedgerSnapshot::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-31',
            'opening_shop_position' => 0.00,
            'closing_shop_position' => 75000.00,
            'closing_company_pending' => 0.00,
            'closing_petty' => 0.00,
        ]);

        // Create September transaction
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-05',
            'entry_type_id' => $this->salesEntryType->id,
            'direction' => 'income',
            'funding_source' => 'none',
            'amount' => 15000.00,
            'settlement_delta' => 15000.00,
            'status' => 'approved',
            'entered_by_user_id' => $this->user->id,
        ]);

        $service = app(MonthlyClosingSummaryService::class);

        // August summary should show is_pre_opening = true
        $augustSummary = $service->getShopMonthlyDetail($this->shop->id, '2026-08');
        $this->assertTrue($augustSummary['period']['is_pre_opening']);

        // September summary should show is_pre_opening = false and opening balance = ₹0
        $septemberSummary = $service->getShopMonthlyDetail($this->shop->id, '2026-09');
        $this->assertFalse($septemberSummary['period']['is_pre_opening']);
        $this->assertEquals(0.00, $septemberSummary['opening']['physical_position']);
        $this->assertEquals(0.00, $septemberSummary['opening']['available_credit']);
    }

    public function test_initialize_command_dry_run_makes_no_changes(): void
    {
        $this->artisan('cashbook:initialize-accounting-opening', [
            '--date' => '2026-09-01',
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertDatabaseEmpty('shop_accounting_openings');
    }

    public function test_initialize_command_is_idempotent(): void
    {
        // Run first time
        $this->artisan('cashbook:initialize-accounting-opening', [
            '--date' => '2026-09-01',
            '--shop' => $this->shop->code,
        ])->assertSuccessful();

        $this->assertDatabaseHas('shop_accounting_openings', [
            'shop_id' => $this->shop->id,
            'opening_shop_company_balance' => 0.00,
        ]);

        // Run second time (idempotent)
        $this->artisan('cashbook:initialize-accounting-opening', [
            '--date' => '2026-09-01',
            '--shop' => $this->shop->code,
        ])->assertSuccessful();

        $this->assertEquals(1, ShopAccountingOpening::where('shop_id', $this->shop->id)->count());
    }
}
