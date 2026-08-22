<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\Warehouse\ApiWarehouseLoadoutController;
use App\Models\BusinessSetting;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

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

        $orders = ShopOrder::query()
            ->whereDate('business_date', $businessDate->toDateString())
            ->whereIn('delivery_status', ['pending_delivery', 'ready_for_dispatch'])
            ->where('order_source', '!=', 'admin_direct_purchase')
            ->withCount('items')
            ->withCount([
                'items as loaded_items_count' => fn ($query) => $query->where('sorting_status', 'loaded'),
            ])
            ->orderBy('id')
            ->get()
            ->filter(fn (ShopOrder $order): bool => $order->loaded_items_count < $order->items_count)
            ->values();

        $loaded = 0;
        $failed = 0;
        $delaySeconds = (int) ($settings->get('auto_load_all_delay_seconds') ?: 3);
        $controller = app(ApiWarehouseLoadoutController::class);

        foreach ($orders as $index => $order) {
            $request = Request::create('/internal/auto-load-all', 'POST');
            $request->setUserResolver(fn (): User => $actor);

            $result = $controller->loadAll($order, $request)->getData(true);

            if (($result['success'] ?? false) === true) {
                $loaded++;
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
                'skipped_orders' => 0,
                'failed_orders' => $failed,
                'notes' => $orders->isEmpty() ? 'No eligible orders found.' : null,
            ])
            ->log('Auto Load All run recorded');

        $this->info("Auto Load All completed for {$businessDate->toDateString()}: {$loaded} loaded, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
