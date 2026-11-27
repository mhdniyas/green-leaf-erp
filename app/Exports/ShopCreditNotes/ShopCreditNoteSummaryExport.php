<?php

declare(strict_types=1);

namespace App\Exports\ShopCreditNotes;

use App\Exports\ShopCreditNotes\Sheets\DailyShopSummarySheet;
use App\Exports\ShopCreditNotes\Sheets\DailySummarySheet;
use App\Exports\ShopCreditNotes\Sheets\OverviewSheet;
use App\Exports\ShopCreditNotes\Sheets\ShopSummarySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ShopCreditNoteSummaryExport implements WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(
        private readonly array $report,
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new OverviewSheet($this->report),
            new DailySummarySheet($this->report['daily_summary']),
            new ShopSummarySheet($this->report['shop_summary']),
            new DailyShopSummarySheet($this->report['daily_shop_rows'], 'Daily Shop-wise Summary'),
        ];
    }
}
