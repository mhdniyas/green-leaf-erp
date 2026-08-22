<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Shop;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\JsonResponse;
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
            ->whereIn('key', ['auto_load_all_delay_seconds', 'auto_load_all_allow_manual'])
            ->pluck('value', 'key');

        abort_unless(filter_var($settings->get('auto_load_all_allow_manual') ?? true, FILTER_VALIDATE_BOOLEAN), 403);

        $delaySeconds = (int) ($settings->get('auto_load_all_delay_seconds') ?: 3);

        return view('admin.auto-load-all.run', compact('shops', 'operationalDate', 'delaySeconds'));
    }

    public function storeRunSummary(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'trigger_mode' => ['required', 'string', 'in:manual,automatic'],
            'status' => ['required', 'string', 'in:completed,stopped,failed'],
            'business_date' => ['required', 'date'],
            'delay_seconds' => ['required', 'integer', 'min:1', 'max:60'],
            'selected_shops' => ['required', 'integer', 'min:0'],
            'processed_orders' => ['required', 'integer', 'min:0'],
            'loaded_orders' => ['required', 'integer', 'min:0'],
            'skipped_orders' => ['required', 'integer', 'min:0'],
            'failed_orders' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        activity('auto_load_all')
            ->causedBy($request->user())
            ->withProperties([
                'trigger_mode' => $validated['trigger_mode'],
                'status' => $validated['status'],
                'business_date' => $validated['business_date'],
                'delay_seconds' => $validated['delay_seconds'],
                'selected_shops' => $validated['selected_shops'],
                'processed_orders' => $validated['processed_orders'],
                'loaded_orders' => $validated['loaded_orders'],
                'skipped_orders' => $validated['skipped_orders'],
                'failed_orders' => $validated['failed_orders'],
                'notes' => $validated['notes'] ?? null,
            ])
            ->log('Auto Load All run recorded');

        return response()->json([
            'success' => true,
        ]);
    }
}
