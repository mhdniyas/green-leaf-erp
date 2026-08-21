<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyPriceApproval;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SeedDailyPriceMatrixNextDayCommand extends Command
{
    protected $signature = 'greenleaf:seed-daily-price-matrix {--date= : Target business date to seed} {--force : Overwrite existing target-day approvals}';

    protected $description = 'Seed the next business day daily price matrix from the previous approved day';

    public function handle(): int
    {
        $targetDate = Carbon::parse($this->option('date') ?: today()->toDateString())->toDateString();
        $sourceDate = Carbon::parse($targetDate)->subDay()->toDateString();

        $sourceApprovals = DailyPriceApproval::query()
            ->whereDate('business_date', $sourceDate)
            ->where('status', 'approved')
            ->get();

        if ($sourceApprovals->isEmpty()) {
            $this->info("No approved daily prices found for {$sourceDate}.");

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($sourceApprovals as $sourceApproval) {
            $targetApproval = DailyPriceApproval::query()->firstOrNew([
                'product_id' => $sourceApproval->product_id,
                'business_date' => $targetDate,
            ]);

            if ($targetApproval->exists && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            $targetApproval->fill([
                'purchase_price' => $sourceApproval->purchase_price,
                'price_unit' => $sourceApproval->price_unit,
                'price_a' => $sourceApproval->price_a,
                'price_b' => $sourceApproval->price_b ?? $sourceApproval->price_a,
                'price_c' => $sourceApproval->price_c ?? $sourceApproval->price_a,
                'status' => 'approved',
                'approved_by' => null,
                'approved_at' => now(),
                'locked_at' => null,
                'locked_by' => null,
                'updated_by' => null,
            ]);
            $targetApproval->save();

            $created++;
        }

        $this->info("Seeded {$created} daily price row(s) for {$targetDate} from {$sourceDate}. Skipped {$skipped} existing row(s).");

        return self::SUCCESS;
    }
}
