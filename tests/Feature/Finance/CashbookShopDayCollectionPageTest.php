<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashbookShopDayCollectionPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $sana;

    private ShopLedgerProfile $sanaProfile;

    private CompanyAccount $kotakBank;

    private CompanyAccount $hdfcBank;

    private CompanyAccount $cashBox;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $cashSalesType;

    private LedgerEntryType $rentExpenseType;

    private LedgerEntryType $vehicleExpenseType;

    private DailyLedgerService $dailyLedgerService;

    private CompanyPaymentReconciliationService $reconciliationService;

    private CompanyMoneyPositionService $moneyPositionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true]);

        $this->sana = Shop::factory()->create(['name' => 'Sana', 'code' => 'SANA', 'status' => 'active']);
        $this->sanaProfile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->sana->id,
            'uuid' => (string) str()->uuid(),
            'slug' => 'sana-store',
            'code' => $this->sana->code,
            'name' => $this->sana->name,
            'enabled' => true,
        ]);

        $this->kotakBank = CompanyAccount::create([
            'name' => 'Kotak Bank',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => '9988776655',
            'account_type' => 'bank',
            'current_balance' => 500000.00,
            'enabled' => true,
        ]);

        $this->hdfcBank = CompanyAccount::create([
            'name' => 'HDFC Bank',
            'bank_name' => 'HDFC Bank Ltd',
            'account_number' => '1122334455',
            'account_type' => 'bank',
            'current_balance' => 150000.00,
            'enabled' => true,
        ]);

        $this->cashBox = CompanyAccount::create([
            'name' => 'Main Cash Box',
            'bank_name' => 'Company Vault',
            'account_number' => 'CASH-MAIN',
            'account_type' => 'cash',
            'current_balance' => 30000.00,
            'enabled' => true,
        ]);

        $this->paytmType = LedgerEntryType::firstOrCreate(
            ['code' => 'paytm_sales'],
            ['name' => 'Paytm Collection', 'category' => 'income', 'display_order' => 10, 'active' => true, 'is_system' => true]
        );

        $this->cardType = LedgerEntryType::firstOrCreate(
            ['code' => 'card_sales'],
            ['name' => 'Card Collection', 'category' => 'income', 'display_order' => 11, 'active' => true, 'is_system' => true]
        );

        $this->cashSalesType = LedgerEntryType::firstOrCreate(
            ['code' => 'cash_sales'],
            ['name' => 'Cash Sales', 'category' => 'income', 'display_order' => 1, 'active' => true, 'is_system' => true]
        );

        $this->rentExpenseType = LedgerEntryType::firstOrCreate(
            ['code' => 'rent_expense'],
            ['name' => 'Shop Rent', 'category' => 'expense', 'display_order' => 20, 'active' => true, 'is_system' => false]
        );

        $this->vehicleExpenseType = LedgerEntryType::firstOrCreate(
            ['code' => 'vehicle_expense'],
            ['name' => 'Vehicle Expense', 'category' => 'expense', 'display_order' => 21, 'active' => true, 'is_system' => false]
        );

        $shopPaidCompanyType = LedgerEntryType::firstOrCreate(
            ['code' => 'shop_paid_company'],
            ['name' => 'Shop Remittance to Company', 'category' => 'expense', 'display_order' => 99, 'active' => true, 'is_system' => true]
        );

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->kotakBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'settlement_behavior' => 'increase',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->cardType->id,
            'company_account_id' => $this->hdfcBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'settlement_behavior' => 'increase',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->cashSalesType->id,
            'company_account_id' => $this->cashBox->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'settlement_behavior' => 'increase',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->sana->id,
            'entry_type_id' => $this->rentExpenseType->id,
            'company_account_id' => $this->kotakBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => false,
            'include_in_income' => false,
            'include_in_expense' => true,
            'include_in_pl' => true,
            'settlement_behavior' => 'decrease',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
        ]);

        $this->dailyLedgerService = app(DailyLedgerService::class);
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
        $this->moneyPositionService = app(CompanyMoneyPositionService::class);
    }

    public function test_shop_day_page_renders_target_settlement_and_collections(): void
    {
        $businessDate = '2026-08-29';

        // 1. Sana records Paytm ₹48,962, Card ₹2,780, Cash ₹16,550 (Gross Sales = ₹68,292)
        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $card = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cardType->code,
            'amount' => 2780.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($card['transaction'], $this->admin->id);

        $cash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 16550.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($cash['transaction'], $this->admin->id);

        // 2. Rent ₹2,000 from sales
        $rent = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->rentExpenseType->code,
            'amount' => 2000.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($rent['transaction'], $this->admin->id);

        // 3. Vehicle expense ₹500 paid by company
        $vehicle = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->vehicleExpenseType->code,
            'amount' => 500.00,
            'funding_source' => 'company',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($vehicle['transaction'], $this->admin->id);

        // 4. Admin verifies Paytm ₹48,962
        $stmtPaytm = CompanyAccountStatementEntry::where('source_id', $paytm['transaction']->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($stmtPaytm, $this->admin->id);

        // Request shop day view
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->sanaProfile->slug,
            'date' => $businessDate,
        ]));

        $response->assertOk()
            ->assertSee("TODAY'S SETTLEMENT", false)
            ->assertSee('HOW SALES WERE COLLECTED')
            ->assertSee('SETTLEMENT ADJUSTMENTS')
            ->assertSee('SETTLEMENT SUMMARY')
            ->assertSee('BREAKDOWN OF STILL TO SETTLE')
            ->assertSee('₹68,292.00') // Gross Sales
            ->assertSee('₹48,962.00') // Company Received
            ->assertSee('₹2,780.00')  // Needs Verification
            ->assertSee('₹14,550.00') // Cash With Shop (16550 - 2000 rent deduction)
            ->assertSee('₹17,330.00') // Still To Settle (66292 - 48962 = 17330)
            ->assertSee('→ Kotak Bank')
            ->assertSee('→ HDFC Bank')
            ->assertSee('📍 Sana Shop')
            ->assertSee('RECEIVED')
            ->assertSee('NEEDS VERIFICATION')
            ->assertSee('CASH WITH SHOP')
            ->assertSee('FROM SALES')
            ->assertSee('PAID BY COMPANY');
    }

    public function test_collection_rows_only_expose_view_and_do_not_expose_verify_or_reconcile(): void
    {
        $businessDate = '2026-08-29';

        $paytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->sana->id,
            'business_date' => $businessDate,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 48962.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($paytm['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->sanaProfile->slug,
            'date' => $businessDate,
        ]));

        $response->assertOk()
            ->assertSee('View')
            ->assertDontSee('>Verify<', false)
            ->assertDontSee('>Reconcile<', false)
            ->assertDontSee('>Match<', false)
            ->assertDontSee('>Finalize<', false)
            ->assertDontSee('>Verify Cash<', false);
    }

    public function test_query_count_is_bounded_and_creates_zero_mutations(): void
    {
        $businessDate = '2026-08-29';

        $initialTxCount = ShopLedgerTransaction::count();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->sanaProfile->slug,
            'date' => $businessDate,
        ]));

        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        $this->assertLessThan(60, $queryCount);

        // Ensure zero DB mutation on read
        $this->assertSame($initialTxCount, ShopLedgerTransaction::count());
    }
}
