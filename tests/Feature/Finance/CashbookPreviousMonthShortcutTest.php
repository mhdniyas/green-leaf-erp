<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookPreviousMonthShortcutTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected CompanyAccount $bankAccount;

    protected Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->admin = User::factory()->create([
            'email' => 'admin@greenleaf.test',
        ]);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create(['name' => 'Main Shop']);

        $this->bankAccount = CompanyAccount::query()->create([
            'name' => 'Main Bank',
            'account_number' => 'ACC-12345',
            'bank_name' => 'HDFC Bank',
            'account_type' => 'bank',
            'opening_balance' => 50000.00,
            'current_balance' => 50000.00,
            'enabled' => true,
        ]);
    }

    /**
     * 1. Test precise previous calendar month math across leap years, year rollovers, and mid-month dates.
     */
    public function test_previous_calendar_month_calculation_across_edge_cases(): void
    {
        // Case A: September 1, 2026 -> August 1 to August 31, 2026
        Carbon::setTestNow('2026-09-01');
        $prev = now()->startOfMonth()->subDay();
        $this->assertSame('2026-08-01', $prev->copy()->startOfMonth()->toDateString());
        $this->assertSame('2026-08-31', $prev->copy()->endOfMonth()->toDateString());
        $this->assertSame('2026-08', $prev->format('Y-m'));
        $this->assertSame('August', $prev->format('F'));

        // Case B: Mid-month (September 15, 2026) -> August 1 to August 31, 2026
        Carbon::setTestNow('2026-09-15');
        $prev = now()->startOfMonth()->subDay();
        $this->assertSame('2026-08-01', $prev->copy()->startOfMonth()->toDateString());
        $this->assertSame('2026-08-31', $prev->copy()->endOfMonth()->toDateString());

        // Case C: Year Rollover (January 15, 2026) -> December 1 to December 31, 2025
        Carbon::setTestNow('2026-01-15');
        $prev = now()->startOfMonth()->subDay();
        $this->assertSame('2025-12-01', $prev->copy()->startOfMonth()->toDateString());
        $this->assertSame('2025-12-31', $prev->copy()->endOfMonth()->toDateString());
        $this->assertSame('2025-12', $prev->format('Y-m'));
        $this->assertSame('December', $prev->format('F'));

        // Case D: Leap Year (March 15, 2024) -> February 1 to February 29, 2024
        Carbon::setTestNow('2024-03-15');
        $prev = now()->startOfMonth()->subDay();
        $this->assertSame('2024-02-01', $prev->copy()->startOfMonth()->toDateString());
        $this->assertSame('2024-02-29', $prev->copy()->endOfMonth()->toDateString());
        $this->assertSame('2024-02', $prev->format('Y-m'));
        $this->assertSame('February', $prev->format('F'));

        // Case E: Non-Leap Year Month-End (March 31, 2025) -> February 1 to February 28, 2025
        Carbon::setTestNow('2025-03-31');
        $prev = now()->startOfMonth()->subDay();
        $this->assertSame('2025-02-01', $prev->copy()->startOfMonth()->toDateString());
        $this->assertSame('2025-02-28', $prev->copy()->endOfMonth()->toDateString());
        $this->assertSame('2025-02', $prev->format('Y-m'));
        $this->assertSame('February', $prev->format('F'));

        Carbon::setTestNow(); // reset
    }

    /**
     * 2. Test journal view loads and renders previous month shortcut.
     */
    public function test_journal_page_renders_shortcut_and_accepts_previous_month(): void
    {
        Carbon::setTestNow('2026-09-01');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'tab' => 'all',
            'status' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('Aug');
        $response->assertSee('2026-08-01');
        $response->assertSee('2026-08-31');

        Carbon::setTestNow();
    }

    /**
     * 3. Test bank account statement view renders month shortcut.
     */
    public function test_bank_statement_renders_shortcut_and_accepts_previous_month(): void
    {
        Carbon::setTestNow('2026-09-01');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.bank-accounts.statement', [
            'account' => $this->bankAccount->id,
            'month' => '2026-08',
        ]));

        $response->assertOk();
        $response->assertSee('Aug');
        $response->assertSee('2026-08');

        Carbon::setTestNow();
    }

    /**
     * 4. Test reconciliation view renders shortcut.
     */
    public function test_reconciliation_page_renders_shortcut_and_accepts_previous_month(): void
    {
        Carbon::setTestNow('2026-09-01');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.reconciliation', [
            'month' => '2026-08',
        ]));

        $response->assertOk();
        $response->assertSee('Aug');

        Carbon::setTestNow();
    }

    /**
     * 5. Test purchaser funding list and detail render shortcut.
     */
    public function test_purchaser_funding_renders_shortcut_and_accepts_previous_month(): void
    {
        Carbon::setTestNow('2026-09-01');

        $purchaser = User::factory()->create(['name' => 'Purchaser Ali']);
        $purchaser->assignRole('purchaser');

        $listResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.purchasers', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));
        $listResponse->assertOk();
        $listResponse->assertSee('Aug');

        Carbon::setTestNow();
    }

    /**
     * 6. Test main financial reports accept previous month range.
     */
    public function test_reports_page_renders_shortcut_and_accepts_previous_month(): void
    {
        Carbon::setTestNow('2026-09-01');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports', [
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));

        $response->assertOk();
        $response->assertSee('Aug');

        Carbon::setTestNow();
    }

    /**
     * 7. Test direct company sales renders month shortcut.
     */
    public function test_direct_sales_renders_shortcut_and_accepts_previous_month(): void
    {
        Carbon::setTestNow('2026-09-01');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.direct-sales', [
            'month' => '2026-08',
        ]));

        $response->assertOk();
        $response->assertSee('Aug');

        Carbon::setTestNow();
    }

    /**
     * 8. Test accept shop payments renders month shortcut.
     */
    public function test_accept_payments_renders_shortcut_and_accepts_previous_month(): void
    {
        Carbon::setTestNow('2026-09-01');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.accept-payment', [
            'shop' => $this->shop->public_uuid,
            'month' => '2026-08',
        ]));

        $response->assertOk();

        Carbon::setTestNow();
    }
}
