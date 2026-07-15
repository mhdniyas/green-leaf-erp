<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Finance\AdminFinancePillarService;
use Database\Seeders\JulyFourteenDailySalesSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CashFlowReportJournalTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cash_flow_report_reads_approved_daily_sale_payments_from_journal_table(): void
    {
        $this->seed(JulyFourteenDailySalesSeeder::class);

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
}
