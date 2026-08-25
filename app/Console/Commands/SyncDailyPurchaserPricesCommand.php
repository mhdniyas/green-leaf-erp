<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Purchasing\DailyPurchaserPriceSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncDailyPurchaserPricesCommand extends Command
{
    protected $signature = 'greenleaf:sync-daily-purchaser-prices {--date= : Specific business date to sync (Y-m-d)} {--from= : Start date of range to sync (Y-m-d)} {--to= : End date of range to sync (Y-m-d)}';

    protected $description = 'Synchronize actual weighted average purchase prices into daily price approvals snapshot';

    public function handle(DailyPurchaserPriceSyncService $syncService): int
    {
        $date = $this->option('date');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($date) {
            $dateStr = Carbon::parse($date)->toDateString();
            $this->info("Syncing purchaser prices for date: {$dateStr}...");
            $updated = $syncService->syncForBusinessDate($dateStr);
            $this->info("Completed. Updated {$updated} price snapshot record(s).");

            return self::SUCCESS;
        }

        $startDate = $from ? Carbon::parse($from)->toDateString() : today()->subDays(14)->toDateString();
        $endDate = $to ? Carbon::parse($to)->toDateString() : today()->toDateString();

        $this->info("Syncing purchaser prices from {$startDate} to {$endDate}...");
        $updated = $syncService->syncRange($startDate, $endDate);
        $this->info("Completed. Updated {$updated} price snapshot record(s) across date range.");

        return self::SUCCESS;
    }
}
