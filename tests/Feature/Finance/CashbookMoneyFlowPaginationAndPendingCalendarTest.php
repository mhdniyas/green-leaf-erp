<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashbookMoneyFlowPaginationAndPendingCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cashType;

    private CompanyAccount $bankAccount;

    private CompanyAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'finance-calendar-admin@greenleaf.test',
        ]);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'name' => 'Kochi Central Store',
            'code' => 'KCH01',
            'status' => 'active',
        ]);

        $this->paytmType = LedgerEntryType::firstOrCreate(
            ['code' => 'shop_paytm'],
            [
                'name' => 'Paytm / UPI Collection',
                'category' => 'income',
                'direction' => 'income',
                'is_active' => true,
                'affects_cash' => false,
            ]
        );

        $this->cashType = LedgerEntryType::firstOrCreate(
            ['code' => 'shop_cash_sales'],
            [
                'name' => 'Cash Sales Collection',
                'category' => 'income',
                'direction' => 'income',
                'is_active' => true,
                'affects_cash' => true,
            ]
        );

        $this->bankAccount = CompanyAccount::create([
            'name' => 'HDFC Current Account',
            'bank_name' => 'HDFC Bank',
            'account_type' => 'bank',
            'account_number' => '50200099881122',
            'current_balance' => 100000.00,
            'enabled' => true,
            'is_default' => true,
        ]);

        $this->cashAccount = CompanyAccount::create([
            'name' => 'Main Company Cash Box',
            'account_type' => 'cash',
            'account_number' => 'CASH-VAULT-01',
            'current_balance' => 20000.00,
            'enabled' => true,
        ]);
    }

    private function createTransaction(array $attributes): ShopLedgerTransaction
    {
        return ShopLedgerTransaction::create(array_merge([
            'shop_id' => $this->shop->id,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ], $attributes));
    }

    public function test_money_flow_transactions_are_paginated_and_page_2_works(): void
    {
        $date = '2026-08-15';

        // Create 30 transactions for this single business date
        for ($i = 1; $i <= 30; $i++) {
            $this->createTransaction([
                'business_date' => $date,
                'entry_type_id' => $this->paytmType->id,
                'direction' => 'income',
                'amount' => 100.00 + $i,
                'status' => 'posted',
                'notes' => 'Collection #'.$i,
            ]);
        }

        // Page 1: Should show 25 items and have pagination links
        $resPage1 = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', [
            'date' => $date,
            'page' => 1,
        ]));

        $resPage1->assertOk();
        $itemsPage1 = $resPage1->viewData('items');
        $this->assertInstanceOf(LengthAwarePaginator::class, $itemsPage1);
        $this->assertSame(30, $itemsPage1->total());
        $this->assertSame(25, $itemsPage1->count());
        $this->assertSame(1, $itemsPage1->currentPage());
        $this->assertTrue($itemsPage1->hasMorePages());

        // Page 2: Should show remaining 5 items
        $resPage2 = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', [
            'date' => $date,
            'page' => 2,
        ]));

        $resPage2->assertOk();
        $itemsPage2 = $resPage2->viewData('items');
        $this->assertSame(30, $itemsPage2->total());
        $this->assertSame(5, $itemsPage2->count());
        $this->assertSame(2, $itemsPage2->currentPage());
        $this->assertFalse($itemsPage2->hasMorePages());
    }

    public function test_calendar_pending_counts_are_accurate_and_not_affected_by_page_parameter(): void
    {
        $date1 = '2026-08-05';
        $date2 = '2026-08-12';
        $date3 = '2026-08-20';

        // On 2026-08-05: 3 pending posted transactions
        for ($i = 1; $i <= 3; $i++) {
            $this->createTransaction([
                'business_date' => $date1,
                'entry_type_id' => $this->paytmType->id,
                'direction' => 'income',
                'amount' => 500.00 * $i,
                'status' => 'posted',
            ]);
        }

        // On 2026-08-12: 1 floating cheque + 1 unverified approved transaction = 2 pending
        ShopInvoicePaymentRequest::create([
            'shop_id' => $this->shop->id,
            'payment_date' => $date2,
            'payment_method' => 'cheque',
            'requested_amount' => 15000.00,
            'floating_amount' => 15000.00,
            'cheque_status' => 'pending',
            'status' => 'submitted',
        ]);

        $this->createTransaction([
            'business_date' => $date2,
            'entry_type_id' => $this->paytmType->id,
            'direction' => 'income',
            'amount' => 2500.00,
            'status' => 'approved',
        ]);

        // On 2026-08-20: 1 verified/received transaction (NOT pending) + 1 void transaction (NOT pending)
        $txVerified = $this->createTransaction([
            'business_date' => $date3,
            'entry_type_id' => $this->paytmType->id,
            'direction' => 'income',
            'amount' => 8000.00,
            'status' => 'approved',
        ]);

        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->bankAccount->id,
            'public_uuid' => (string) Str::uuid(),
            'transaction_date' => $date3,
            'direction' => 'credit',
            'amount' => 8000.00,
            'source_type' => ShopLedgerTransaction::class,
            'source_id' => $txVerified->id,
            'status' => 'reconciled',
            'is_finalized' => true,
            'finalized_at' => now(),
            'matched_amount' => 8000.00,
        ]);

        $this->createTransaction([
            'business_date' => $date3,
            'entry_type_id' => $this->paytmType->id,
            'direction' => 'income',
            'amount' => 4000.00,
            'status' => 'void',
        ]);

        // Query Money Flow with page=1 vs page=2: calendar data must be identical
        $res = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', [
            'date' => $date1,
            'calendar_month' => '2026-08',
        ]));

        $res->assertOk();
        $calendarData = $res->viewData('calendarData');

        $this->assertSame('August 2026', $calendarData['month_title']);
        $this->assertSame('2026-08', $calendarData['calendar_month']);
        $this->assertSame('2026-07', $calendarData['prev_month']);
        $this->assertSame('2026-09', $calendarData['next_month']);

        // Flatten days
        $allDays = collect($calendarData['weeks'])->flatten(1)->filter()->keyBy('date');

        $this->assertSame(3, $allDays->get($date1)['pending_count']);
        $this->assertSame(2, $allDays->get($date2)['pending_count']);
        $this->assertSame(0, $allDays->get($date3)['pending_count']); // verified and void not counted!

        // Page HTML assertions
        $res->assertSee('August 2026');
        $res->assertSee('3 pending on 2026-08-05');
        $res->assertSee('2 pending on 2026-08-12');
        $res->assertDontSee('pending on 2026-08-20');
    }

    public function test_date_and_pending_badge_clicks_and_month_navigation(): void
    {
        $selectedDate = '2026-08-05';

        // 1 pending transaction on 2026-08-05
        $this->createTransaction([
            'business_date' => $selectedDate,
            'entry_type_id' => $this->paytmType->id,
            'direction' => 'income',
            'amount' => 1200.00,
            'status' => 'posted',
        ]);

        $res = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', [
            'date' => $selectedDate,
            'calendar_month' => '2026-08',
        ]));

        $res->assertOk();

        // Selected date highlight
        $calendarData = $res->viewData('calendarData');
        $allDays = collect($calendarData['weeks'])->flatten(1)->filter()->keyBy('date');
        $this->assertTrue($allDays->get($selectedDate)['is_selected']);

        // Check date click link structure
        $expectedDateUrl = route('admin.cashbook.money-flow', [
            'date' => $selectedDate,
            'calendar_month' => '2026-08',
        ]);
        $res->assertSee(e($expectedDateUrl), false);

        // Check pending badge click link structure
        $expectedPendingUrl = route('admin.cashbook.money-flow', [
            'date' => $selectedDate,
            'status' => 'pending',
            'calendar_month' => '2026-08',
        ]);
        $res->assertSee(e($expectedPendingUrl), false);

        // Check previous and next month navigation links
        $expectedPrevMonthUrl = route('admin.cashbook.money-flow', [
            'date' => $selectedDate,
            'calendar_month' => '2026-07',
            'status' => 'all',
        ]);
        $expectedNextMonthUrl = route('admin.cashbook.money-flow', [
            'date' => $selectedDate,
            'calendar_month' => '2026-09',
            'status' => 'all',
        ]);
        $res->assertSee(e($expectedPrevMonthUrl), false);
        $res->assertSee(e($expectedNextMonthUrl), false);

        // Filtering with status=pending returns only pending items
        $resPending = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', [
            'date' => $selectedDate,
            'status' => 'pending',
        ]));
        $resPending->assertOk();
        $this->assertSame(1, $resPending->viewData('items')->total());
    }

    public function test_calendar_query_is_bounded_and_creates_no_n_plus_one(): void
    {
        $date = '2026-08-10';

        // Seed 10 transactions on various dates of the month
        for ($d = 1; $d <= 10; $d++) {
            $this->createTransaction([
                'business_date' => sprintf('2026-08-%02d', $d),
                'entry_type_id' => $this->paytmType->id,
                'direction' => 'income',
                'amount' => 500.00,
                'status' => 'posted',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $res = $this->actingAs($this->admin)->get(route('admin.cashbook.money-flow', [
            'date' => $date,
            'calendar_month' => '2026-08',
        ]));

        $res->assertOk();
        $queries = DB::getQueryLog();

        // Ensure total queries is small and bounded (<= 30 queries, far below a 31-day loop)
        $this->assertLessThanOrEqual(30, count($queries));
    }
}
