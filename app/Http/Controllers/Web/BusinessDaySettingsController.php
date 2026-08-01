<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateBusinessDayCutoffRequest;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessDaySettingsController extends Controller
{
    public function __construct(
        private readonly PurchaserBusinessDayService $businessDayService,
    ) {}

    public function updateCutoff(UpdateBusinessDayCutoffRequest $request): RedirectResponse
    {
        $this->businessDayService->updateCutoffTime($request->validated('cutoff_time'));

        return redirect()
            ->back()
            ->with('success', 'Business-day cutoff updated to '.$this->businessDayService->cutoffLabel().'.');
    }

    public function updatePurchaserEntryCutoff(UpdateBusinessDayCutoffRequest $request): RedirectResponse
    {
        $this->businessDayService->updatePurchaserEntryCutoffTime($request->validated('cutoff_time'));

        return redirect()
            ->back()
            ->with('success', 'Purchaser entry cutoff updated to '.$this->businessDayService->purchaserEntryCutoffLabel().'.');
    }

    public function updateAutoApprove(Request $request): RedirectResponse
    {
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'auto_approve_shop_orders' => ['nullable', 'boolean'],
        ]);

        $enabled = (bool) ($validated['auto_approve_shop_orders'] ?? false);

        $this->businessDayService->updateAutoApproveShopOrders($enabled);

        return redirect()
            ->back()
            ->with('success', $enabled
                ? 'Automatic shop-order approval enabled. New on-time shop orders will go directly to Approved Board.'
                : 'Automatic shop-order approval disabled. New shop orders will wait on the review board.');
    }
}
