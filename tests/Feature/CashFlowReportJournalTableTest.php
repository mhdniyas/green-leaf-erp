<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Shop;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\User;
use App\Services\Finance\AdminFinancePillarService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CashFlowReportJournalTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cash_flow_report_reads_approved_daily_sale_payments_from_journal_table(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $user = User::factory()->create();
        $cashAccount = Account::query()->where('code', '1010')->firstOrFail();
        $salesRevenueAccount = Account::query()->where('code', '4100')->firstOrFail();

        foreach ([1258.00, 1500.00] as $index => $amount) {
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => '2026-07-14',
                'reference' => 'Daily sale payment '.($index + 1),
                'description' => 'Approved daily sale payment '.($index + 1),
                'source_type' => ShopInvoice::class,
                'source_id' => $index + 1,
                'source_event' => 'daily_sale_payment:'.($index + 1),
                'created_by' => $user->id,
            ]);

            $journalEntry->transactions()->createMany([
                [
                    'account_id' => $cashAccount->id,
                    'type' => 'debit',
                    'amount' => $amount,
                ],
                [
                    'account_id' => $salesRevenueAccount->id,
                    'type' => 'credit',
                    'amount' => $amount,
                ],
            ]);
        }

        $report = app(AdminFinancePillarService::class)->cashFlowReport(Carbon::parse('2026-07-14'));

        $this->assertSame(2758.00, $report['summary']['total_in']);
        $this->assertSame(0.00, $report['summary']['total_out']);
        $this->assertSame(2758.00, $report['summary']['closing_balance']);

        $dailySalesRows = $report['journal_rows']
            ->where('date', '2026-07-14')
            ->where('source', 'daily_sales')
            ->values();

        $this->assertCount(2, $dailySalesRows);
        $this->assertSame(2758.00, round((float) $dailySalesRows->sum('amount'), 2));
        $this->assertSame(['IN'], $dailySalesRows->pluck('direction')->unique()->values()->all());
        $this->assertSame(['Daily Sales Income'], $dailySalesRows->pluck('category')->unique()->values()->all());
        $this->assertContains('Sales Revenue', $dailySalesRows->pluck('journal')->all());
    }

    public function test_cash_flow_report_includes_company_payment_only_after_admin_approval(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Owned Shop',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $payment = ShopCredit::query()->create([
            'shop_id' => $shop->id,
            'type' => 'out',
            'is_petty_cash' => true,
            'amount' => 700,
            'description' => 'Cash paid to office',
            'business_date' => '2026-07-18',
            'status' => 'pending',
        ]);

        $pendingReport = app(AdminFinancePillarService::class)->cashFlowReport(Carbon::parse('2026-07-18'));

        $this->assertSame(0, $pendingReport['journal_rows']
            ->where('source', 'owned_shop_petty_cash')
            ->where('remarks', 'Cash paid to office')
            ->count());

        $payment->forceFill([
            'status' => 'approved',
            'reviewed_at' => now(),
        ])->save();

        $approvedRows = app(AdminFinancePillarService::class)
            ->cashFlowReport(Carbon::parse('2026-07-18'))['journal_rows']
            ->where('source', 'owned_shop_petty_cash')
            ->where('remarks', 'Cash paid to office')
            ->values();

        $this->assertCount(1, $approvedRows);
        $this->assertSame('IN', $approvedRows->first()['direction']);
        $this->assertSame(700.00, $approvedRows->first()['amount']);
        $this->assertSame('Shop Cash Return - Owned Shop', $approvedRows->first()['journal']);
    }
}
