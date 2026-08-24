<?php

namespace App\Console\Commands;

use App\Models\PurchaserCart;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CancelOverduePurchaserWorkCommand extends Command
{
    protected $signature = 'purchaser:cancel-overdue-work';

    protected $description = 'Cancel overdue purchaser draft carts and eligible purchase orders';

    public function handle(PurchaserBusinessDayService $businessDayService): int
    {
        $operationalDate = $businessDayService->operationalDate();

        try {
            PurchaserCart::cancelOverdueCartsAndOrders($operationalDate);
        } catch (Throwable $throwable) {
            Log::error('purchaser.cleanup.failed', [
                'operational_date' => $operationalDate->toDateString(),
                'exception' => $throwable::class,
            ]);

            throw $throwable;
        }

        $this->info("Cancelled overdue purchaser work before {$operationalDate->toDateString()}.");

        return self::SUCCESS;
    }
}
