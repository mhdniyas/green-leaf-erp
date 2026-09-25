<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProductEntry;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\Cashbook\CashbookShopSyncService;
use Carbon\Carbon;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminExactShopCashbookTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $shopUser;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-15 12:00:00');

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->shop = Shop::query()->create([
            'name' => 'AV Casio',
            'code' => 'av-casio-casio',
            'warehouse_tag' => 'AVC',
            'status' => 'active',
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_purchasing_enabled' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->adminUser = User::factory()->create([
            'email' => 'admin@greenleaf.test',
        ]);
        $this->adminUser->assignRole('admin');

        $this->shopUser = User::factory()->create([
            'shop_id' => $this->shop->id,
        ]);
        $this->shopUser->assignRole('shop');
    }

    public function test_non_admin_cannot_access_or_mutate_financial_ledger(): void
    {
        $response = $this->actingAs($this->shopUser)->get(
            route('admin.cashbook.shop.financial-ledger', $this->shop->code)
        );
        $this->assertTrue($response->isRedirection() || $response->isForbidden());

        $mutationResponse = $this->actingAs($this->shopUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.update', $this->shop->code),
            ['transaction_id' => 1, 'amount' => 100]
        );
        $this->assertTrue($mutationResponse->isRedirection() || $mutationResponse->isForbidden());
    }

    public function test_admin_can_view_financial_ledger_page_with_canonical_entries(): void
    {
        $entryType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $entryType->id,
            'amount' => 12500.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'affects_income' => true,
            'affects_expense' => false,
            'affects_pl' => true,
            'pl_delta' => 12500.00,
            'settlement_delta' => 12500.00,
            'settlement_direction' => 'add',
            'petty_delta' => 0,
            'petty_direction' => 'none',
            'company_pending_delta' => 0,
            'company_pending_direction' => 'none',
            'status' => 'posted',
        ]);

        app(BalanceCalculator::class)->recalculate($this->shop->id, '2026-09-15');

        $response = $this->actingAs($this->adminUser)->get(
            route('admin.cashbook.shop.financial-ledger', ['shop' => $this->shop->code, 'date' => '2026-09-15'])
        );

        $response->assertOk();
        $response->assertSee('Shop Financial Ledger');
        $response->assertSee('12,500.00');
        $response->assertSee('Cash Sales');
    }

    public function test_admin_can_edit_canonical_entry_amount_and_recalculate_day(): void
    {
        $entryType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $entryType->id,
            'amount' => 12500.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'affects_income' => true,
            'affects_expense' => false,
            'affects_pl' => true,
            'pl_delta' => 12500.00,
            'settlement_delta' => 12500.00,
            'settlement_direction' => 'add',
            'status' => 'posted',
        ]);

        app(BalanceCalculator::class)->recalculate($this->shop->id, '2026-09-15');

        // Admin edits: ₹12,500 → ₹13,000
        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.update', $this->shop->code),
            [
                'transaction_id' => $tx->id,
                'business_date' => '2026-09-15',
                'entry_type_id' => $entryType->id,
                'amount' => 13000.00,
                'funding_source' => 'sales',
                'notes' => 'Corrected from physical register',
                'reason' => 'Register count adjustment',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Verify canonical model updated
        $tx->refresh();
        $this->assertEquals(13000.00, (float) $tx->amount);
        $this->assertEquals(13000.00, (float) $tx->pl_delta);
        $this->assertEquals('Corrected from physical register', $tx->notes);

        // Verify day snapshot recalculated
        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-15')
            ->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals(13000.00, (float) $snapshot->total_sales);
    }

    public function test_admin_can_change_funding_source_from_shop_balance_to_petty(): void
    {
        $entryType = LedgerEntryType::where('code', 'shop_expense')->first()
            ?: LedgerEntryType::where('category', 'expense')->firstOrFail();

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $entryType->id,
            'amount' => 500.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'affects_sales' => false,
            'affects_income' => false,
            'affects_expense' => true,
            'affects_pl' => true,
            'pl_delta' => -500.00,
            'settlement_delta' => -500.00,
            'settlement_direction' => 'subtract',
            'petty_delta' => 0,
            'petty_direction' => 'none',
            'status' => 'posted',
        ]);

        app(BalanceCalculator::class)->recalculate($this->shop->id, '2026-09-15');

        // Admin changes funding source: sales → petty
        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.update', $this->shop->code),
            [
                'transaction_id' => $tx->id,
                'business_date' => '2026-09-15',
                'entry_type_id' => $entryType->id,
                'amount' => 500.00,
                'funding_source' => 'petty',
                'reason' => 'Expense was paid from petty cash envelope',
            ]
        );

        $response->assertOk();
        $tx->refresh();
        $this->assertEquals('petty', $tx->funding_source);
        $this->assertEquals(0, (float) $tx->settlement_delta);
        $this->assertEquals(-500.00, (float) $tx->petty_delta);

        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-15')
            ->first();
        $this->assertEquals(-500.00, (float) $snapshot->closing_petty);
    }

    public function test_admin_can_change_company_bank_account_and_update_statement_entry(): void
    {
        $bankHdfc = CompanyAccount::create([
            'name' => 'HDFC Current',
            'bank_name' => 'HDFC',
            'account_number' => 'HDFC123',
            'account_type' => 'bank',
            'enabled' => true,
        ]);

        $bankKotak = CompanyAccount::create([
            'name' => 'Kotak Current',
            'bank_name' => 'Kotak',
            'account_number' => 'KOTAK456',
            'account_type' => 'bank',
            'enabled' => true,
        ]);

        $entryType = LedgerEntryType::where('code', 'upi_paytm')->first()
            ?: LedgerEntryType::where('category', 'income')->firstOrFail();

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $entryType->id,
            'amount' => 2000.00,
            'direction' => 'income',
            'funding_source' => 'bank',
            'company_account_id' => $bankHdfc->id,
            'affects_sales' => true,
            'affects_income' => true,
            'affects_expense' => false,
            'affects_pl' => true,
            'pl_delta' => 2000.00,
            'settlement_delta' => 0,
            'petty_delta' => 0,
            'company_pending_delta' => 0,
            'status' => 'approved',
        ]);

        $stmt = CompanyAccountStatementEntry::create([
            'company_account_id' => $bankHdfc->id,
            'transaction_date' => '2026-09-15',
            'value_date' => '2026-09-15',
            'direction' => 'in',
            'amount' => 2000.00,
            'reference' => 'SHOP-TX-'.$tx->id,
            'source' => 'shop_collection',
            'source_type' => ShopLedgerTransaction::class,
            'source_id' => $tx->id,
            'status' => 'unmatched',
            'is_finalized' => false,
        ]);

        // Admin changes Paytm bank from HDFC → Kotak
        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.update', $this->shop->code),
            [
                'transaction_id' => $tx->id,
                'business_date' => '2026-09-15',
                'entry_type_id' => $entryType->id,
                'amount' => 2000.00,
                'funding_source' => 'bank',
                'company_account_id' => $bankKotak->id,
                'reason' => 'Paytm settlement credited to Kotak bank',
            ]
        );

        $response->assertOk();
        $tx->refresh();
        $this->assertEquals($bankKotak->id, $tx->company_account_id);

        $stmt->refresh();
        $this->assertEquals($bankKotak->id, $stmt->company_account_id);
    }

    public function test_admin_can_change_business_date_and_recalculate_both_dates(): void
    {
        $entryType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-10',
            'entry_type_id' => $entryType->id,
            'amount' => 5000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'affects_income' => true,
            'affects_expense' => false,
            'affects_pl' => true,
            'pl_delta' => 5000.00,
            'settlement_delta' => 5000.00,
            'status' => 'posted',
        ]);

        app(BalanceCalculator::class)->recalculate($this->shop->id, '2026-09-10');

        // Admin shifts date: 2026-09-10 → 2026-09-12
        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.update', $this->shop->code),
            [
                'transaction_id' => $tx->id,
                'business_date' => '2026-09-12',
                'entry_type_id' => $entryType->id,
                'amount' => 5000.00,
                'funding_source' => 'sales',
                'reason' => 'Entry was posted with wrong date by cashier',
            ]
        );

        $response->assertOk();

        // Old date should be 0
        $oldSnap = ShopDailyLedgerSnapshot::where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-10')
            ->first();
        $this->assertEquals(0, (float) $oldSnap->total_sales);

        // New date should have 5000
        $newSnap = ShopDailyLedgerSnapshot::where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-12')
            ->first();
        $this->assertEquals(5000.00, (float) $newSnap->total_sales);
    }

    public function test_admin_cannot_reduce_amount_below_allocated_amount(): void
    {
        $entryType = LedgerEntryType::where('category', 'income')->firstOrFail();

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $entryType->id,
            'amount' => 10000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'affects_income' => true,
            'affects_expense' => false,
            'affects_pl' => true,
            'pl_delta' => 10000.00,
            'settlement_delta' => 10000.00,
            'status' => 'posted',
        ]);

        $paymentRequest = ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'payment_date' => '2026-09-15',
            'requested_amount' => 6000.00,
            'amount' => 6000.00,
            'payment_method' => 'bank_transfer',
            'status' => 'approved',
        ]);

        ShopPaymentLedgerAllocation::create([
            'shop_id' => $this->shop->id,
            'payment_request_id' => $paymentRequest->id,
            'shop_ledger_transaction_id' => $tx->id,
            'amount' => 6000.00,
        ]);

        // Attempting to reduce transaction amount to ₹4,000 (below ₹6,000 allocated) should fail
        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.update', $this->shop->code),
            [
                'transaction_id' => $tx->id,
                'business_date' => '2026-09-15',
                'entry_type_id' => $entryType->id,
                'amount' => 4000.00,
                'funding_source' => 'sales',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_admin_can_void_entry_and_log_audit(): void
    {
        $entryType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $entryType->id,
            'amount' => 3000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'affects_income' => true,
            'affects_expense' => false,
            'affects_pl' => true,
            'pl_delta' => 3000.00,
            'settlement_delta' => 3000.00,
            'status' => 'posted',
        ]);

        app(BalanceCalculator::class)->recalculate($this->shop->id, '2026-09-15');

        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.delete', $this->shop->code),
            [
                'transaction_id' => $tx->id,
                'reason' => 'Duplicate sale entry',
            ]
        );

        $response->assertOk();
        $this->assertDatabaseMissing('shop_ledger_transactions', [
            'id' => $tx->id,
        ]);

        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-15')
            ->first();
        $this->assertEquals(0.00, (float) $snapshot->total_sales);

        // Verify activity log
        $activity = Activity::where('log_name', 'exact_cashbook_edit')
            ->where('properties->transaction_id', $tx->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($activity);
        $this->assertEquals($this->adminUser->id, $activity->causer_id);
    }

    public function test_admin_cannot_delete_salary_or_gl_bill_entry(): void
    {
        $glBillType = LedgerEntryType::firstOrCreate(['code' => 'purchase_bill'], [
            'name' => 'GL Bill',
            'category' => 'expense',
        ]);
        $salaryType = LedgerEntryType::firstOrCreate(['code' => 'salary'], [
            'name' => 'Salary',
            'category' => 'expense',
        ]);

        $glTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $glBillType->id,
            'amount' => 5000.00,
            'direction' => 'expense',
            'funding_source' => 'company',
            'status' => 'posted',
        ]);

        $salaryTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $salaryType->id,
            'amount' => 12000.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        // Attempt to delete GL Bill
        $response1 = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.delete', $this->shop->code),
            ['transaction_id' => $glTx->id]
        );
        $response1->assertStatus(422);
        $this->assertDatabaseHas('shop_ledger_transactions', ['id' => $glTx->id]);

        // Attempt to delete Salary
        $response2 = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.delete', $this->shop->code),
            ['transaction_id' => $salaryTx->id]
        );
        $response2->assertStatus(422);
        $this->assertDatabaseHas('shop_ledger_transactions', ['id' => $salaryTx->id]);
    }

    public function test_admin_clear_day_deletes_all_eligible_entries_and_preserves_salary_and_gl_bill(): void
    {
        $salesType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();
        $expenseType = LedgerEntryType::where('category', 'expense')->whereNotIn('code', ['purchase_bill', 'salary'])->firstOrFail();
        $glBillType = LedgerEntryType::firstOrCreate(['code' => 'purchase_bill'], ['name' => 'GL Bill', 'category' => 'expense']);
        $salaryType = LedgerEntryType::firstOrCreate(['code' => 'salary'], ['name' => 'Salary', 'category' => 'expense']);

        // Other shop to test scoping
        $otherShop = Shop::create([
            'name' => 'Other Shop',
            'code' => 'SH-OTHER-99',
            'is_active' => true,
        ]);

        // 1. Current shop, current date entries
        $salesTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $salesType->id,
            'amount' => 10000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'affects_income' => true,
            'pl_delta' => 10000.00,
            'settlement_delta' => 10000.00,
            'status' => 'posted',
        ]);

        $voidedTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $salesType->id,
            'amount' => 11500.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'void',
        ]);

        $expenseTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $expenseType->id,
            'amount' => 500.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $glTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $glBillType->id,
            'amount' => 8000.00,
            'direction' => 'expense',
            'funding_source' => 'company',
            'status' => 'posted',
        ]);

        $salaryTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $salaryType->id,
            'amount' => 15000.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        // 2. Current shop, different date entry (must NOT be touched)
        $diffDateTx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-16',
            'entry_type_id' => $salesType->id,
            'amount' => 7000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        // 3. Other shop, same date entry (must NOT be touched)
        $otherShopTx = ShopLedgerTransaction::create([
            'shop_id' => $otherShop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $salesType->id,
            'amount' => 9000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        app(BalanceCalculator::class)->recalculate($this->shop->id, '2026-09-15');

        // Execute Clear Day
        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.clear-day', $this->shop->code),
            ['business_date' => '2026-09-15']
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Assert deleted
        $this->assertDatabaseMissing('shop_ledger_transactions', ['id' => $salesTx->id]);
        $this->assertDatabaseMissing('shop_ledger_transactions', ['id' => $voidedTx->id]);
        $this->assertDatabaseMissing('shop_ledger_transactions', ['id' => $expenseTx->id]);

        // Assert preserved
        $this->assertDatabaseHas('shop_ledger_transactions', ['id' => $glTx->id]);
        $this->assertDatabaseHas('shop_ledger_transactions', ['id' => $salaryTx->id]);
        $this->assertDatabaseHas('shop_ledger_transactions', ['id' => $diffDateTx->id]);
        $this->assertDatabaseHas('shop_ledger_transactions', ['id' => $otherShopTx->id]);
    }

    public function test_admin_clear_day_deletes_tagged_products_but_preserves_source_backed_purchase_records(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Daily Cash Expenditure',
            'type' => 'expense',
            'product_tagging_enabled' => true,
            'enabled' => true,
        ]);
        $product = Product::factory()->create(['unit' => 'kg']);
        $productEntry = ShopLedgerProductEntry::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'header_group_id' => $header->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit' => 'kg',
            'amount' => 120,
            'entered_by' => $this->adminUser->id,
        ]);
        $manual = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => LedgerEntryType::where('code', 'cash_sales')->firstOrFail()->id,
            'amount' => 500,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);
        $purchaseBill = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => LedgerEntryType::where('code', 'cash_sales')->firstOrFail()->id,
            'amount' => 900,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => 999999,
            'status' => 'posted',
        ]);

        $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.clear-day', $this->shop->code),
            ['business_date' => '2026-09-15']
        )->assertOk();

        $this->assertDatabaseMissing('shop_ledger_product_entries', ['id' => $productEntry->id]);
        $this->assertDatabaseMissing('shop_ledger_transactions', ['id' => $manual->id]);
        $this->assertDatabaseHas('shop_ledger_transactions', ['id' => $purchaseBill->id]);
    }

    public function test_admin_can_delete_only_the_selected_product_ledger_entry_for_its_shop_and_date(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Daily Cash Expenditure',
            'type' => 'expense',
            'product_tagging_enabled' => true,
            'enabled' => true,
        ]);
        $firstProduct = Product::factory()->create(['unit' => 'kg']);
        $secondProduct = Product::factory()->create(['unit' => 'kg']);
        $firstEntry = ShopLedgerProductEntry::create([
            'shop_id' => $this->shop->id, 'business_date' => '2026-09-15', 'header_group_id' => $header->id,
            'product_id' => $firstProduct->id, 'product_name' => $firstProduct->name, 'quantity' => 1,
            'unit' => 'kg', 'amount' => 60, 'entered_by' => $this->adminUser->id,
        ]);
        $secondEntry = ShopLedgerProductEntry::create([
            'shop_id' => $this->shop->id, 'business_date' => '2026-09-15', 'header_group_id' => $header->id,
            'product_id' => $secondProduct->id, 'product_name' => $secondProduct->name, 'quantity' => 1,
            'unit' => 'kg', 'amount' => 75, 'entered_by' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.product-entries.delete', [$this->shop->code, $firstEntry->id]),
            ['business_date' => '2026-09-15']
        )->assertOk();

        $this->assertDatabaseMissing('shop_ledger_product_entries', ['id' => $firstEntry->id]);
        $this->assertDatabaseHas('shop_ledger_product_entries', ['id' => $secondEntry->id]);

        $wrongShop = Shop::create(['name' => 'Wrong Shop', 'code' => 'WRONG-SHOP', 'is_active' => true]);
        $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.product-entries.delete', [$wrongShop->code, $secondEntry->id]),
            ['business_date' => '2026-09-15']
        )->assertStatus(422);

        $this->assertDatabaseHas('shop_ledger_product_entries', ['id' => $secondEntry->id]);
    }

    public function test_admin_can_change_category_from_income_to_expense(): void
    {
        $incomeType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();
        $expenseType = LedgerEntryType::where('code', 'shop_expense')->first()
            ?: LedgerEntryType::where('category', 'expense')->firstOrFail();

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $incomeType->id,
            'amount' => 1000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'affects_income' => true,
            'affects_expense' => false,
            'affects_pl' => true,
            'pl_delta' => 1000.00,
            'settlement_delta' => 1000.00,
            'status' => 'posted',
        ]);

        app(BalanceCalculator::class)->recalculate($this->shop->id, '2026-09-15');

        // Admin changes entry from Income (Cash Sales) to Expense (Shop Expense)
        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.update', $this->shop->code),
            [
                'transaction_id' => $tx->id,
                'business_date' => '2026-09-15',
                'entry_type_id' => $expenseType->id,
                'amount' => 1000.00,
                'funding_source' => 'sales',
                'reason' => 'Reclassified miscategorized sale as expense',
            ]
        );

        $response->assertOk();
        $tx->refresh();
        $this->assertEquals($expenseType->id, $tx->entry_type_id);
        $this->assertEquals('expense', $tx->direction);
        $this->assertEquals(-1000.00, (float) $tx->pl_delta);
        $this->assertEquals(-1000.00, (float) $tx->settlement_delta);

        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-15')
            ->first();
        $this->assertEquals(0, (float) $snapshot->total_sales);
        $this->assertEquals(1000.00, (float) $snapshot->total_expense);
    }

    public function test_admin_can_set_amount_to_zero(): void
    {
        $entryType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-15',
            'entry_type_id' => $entryType->id,
            'amount' => 2500.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'affects_sales' => true,
            'affects_income' => true,
            'affects_expense' => false,
            'affects_pl' => true,
            'pl_delta' => 2500.00,
            'settlement_delta' => 2500.00,
            'status' => 'posted',
        ]);

        app(BalanceCalculator::class)->recalculate($this->shop->id, '2026-09-15');

        // Admin sets amount to 0
        $response = $this->actingAs($this->adminUser)->postJson(
            route('admin.cashbook.shop.financial-ledger.update', $this->shop->code),
            [
                'transaction_id' => $tx->id,
                'business_date' => '2026-09-15',
                'entry_type_id' => $entryType->id,
                'amount' => 0.00,
                'funding_source' => 'sales',
                'reason' => 'Zeroed out invalid transaction',
            ]
        );

        $response->assertOk();
        $tx->refresh();
        $this->assertEquals(0.00, (float) $tx->amount);
        $this->assertEquals(0.00, (float) $tx->pl_delta);

        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-15')
            ->first();
        $this->assertEquals(0.00, (float) $snapshot->total_sales);
    }
}
