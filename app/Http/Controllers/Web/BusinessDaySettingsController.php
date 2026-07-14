<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateBusinessDayCutoffRequest;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\RedirectResponse;

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
}
