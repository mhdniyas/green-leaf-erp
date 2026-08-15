<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Shop;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAutoLoadAllController extends Controller
{
    public function __construct(
        private readonly PurchaserBusinessDayService $businessDayService,
    ) {}

    public function create(Request $request): View
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $shops = Shop::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'warehouse_tag']);

        $operationalDate = $this->businessDayService->operationalDate()->toDateString();

        $settings = BusinessSetting::query()
            ->whereIn('key', ['auto_load_all_delay_seconds'])
            ->pluck('value', 'key');

        $delaySeconds = (int) ($settings->get('auto_load_all_delay_seconds') ?: 3);

        return view('admin.auto-load-all.run', compact('shops', 'operationalDate', 'delaySeconds'));
    }
}
