<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Warehouse\AutoLoadAllService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('greenleaf:auto-load-all {--force : Run now, ignoring configured enable state and trigger time}')]
#[Description('Run scheduled warehouse Auto Load All for eligible shop orders')]
class RunAutoLoadAllCommand extends Command
{
    public function handle(): int
    {
        $settings = BusinessSetting::query()
            ->whereIn('key', [
                'auto_load_all_enabled',
                'auto_load_all_time',
                'auto_load_all_next_business_day',
                'auto_load_all_delay_seconds',
            ])
            ->pluck('value', 'key');

        $enabled = filter_var($settings->get('auto_load_all_enabled') ?? false, FILTER_VALIDATE_BOOLEAN);
        $triggerTime = $settings->get('auto_load_all_time') ?: '00:15';
        $now = now('Asia/Kolkata');

        if (! $this->option('force') && (! $enabled || $now->format('H:i') !== $triggerTime)) {
            return self::SUCCESS;
        }

        $actor = User::role('admin')->orderBy('id')->first();
        if (! $actor) {
            $this->error('Auto Load All needs an admin account to record warehouse changes.');

            return self::FAILURE;
        }

        $businessDate = app(PurchaserBusinessDayService::class)->operationalDate();
        if (filter_var($settings->get('auto_load_all_next_business_day') ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $businessDate = $businessDate->addDay();
        }

        $service = app(AutoLoadAllService::class);
        $orders = $service->eligibleOrders($businessDate->toDateString());

        $loaded = 0;
        $failed = 0;
        $skipped = 0;
        $delaySeconds = (int) ($settings->get('auto_load_all_delay_seconds') ?: 3);

        foreach ($orders as $index => $order) {
            $result = $service->loadOrder($order, $actor);

            if (($result['success'] ?? false) === true) {
                if ($result['skipped'] ?? false) {
                    $skipped++;
                } else {
                    $loaded++;
                }
            } else {
                $failed++;
            }

            if ($index < $orders->count() - 1) {
                sleep($delaySeconds);
            }
        }

        activity('auto_load_all')
            ->causedBy($actor)
            ->withProperties([
                'trigger_mode' => 'automatic',
                'status' => $failed > 0 ? 'failed' : 'completed',
                'business_date' => $businessDate->toDateString(),
                'delay_seconds' => $delaySeconds,
                'selected_shops' => $orders->pluck('shop_id')->filter()->unique()->count(),
                'processed_orders' => $orders->count(),
                'loaded_orders' => $loaded,
                'skipped_orders' => $skipped,
                'failed_orders' => $failed,
                'notes' => $orders->isEmpty() ? 'No eligible orders found.' : null,
            ])
            ->log('Auto Load All run recorded');

        $this->info("Auto Load All completed for {$businessDate->toDateString()}: {$loaded} loaded, {$skipped} skipped, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
