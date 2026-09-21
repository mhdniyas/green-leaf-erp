<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing\V2;

use App\Queries\Purchasing\V2\PurchaserV2DashboardQuery;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Support\Purchasing\V2\PurchaserV2Telemetry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PurchaserV2DashboardController extends PurchaserV2BaseController
{
    public function __construct(
        PurchaserBusinessDayService $businessDayService,
        private readonly PurchaserV2DashboardQuery $dashboardQuery,
    ) {
        parent::__construct($businessDayService);
    }

    /**
     * Render the ultra-lightweight V2 Purchaser Dashboard.
     */
    public function index(Request $request): Response
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);
        $date = $this->resolveBusinessDate($request);
        $grade = $this->resolveGrade($request);

        $summary = $this->dashboardQuery->getSummary($date, $grade, $user);

        if ($request->wantsJson()) {
            $response = response()->json([
                'status' => 'success',
                'data' => $summary,
                '_telemetry' => $telemetry->metrics(),
            ]);

            return $this->attachTelemetry($response, $telemetry, $user);
        }

        $view = view('purchasing.purchaser-v2.dashboard', [
            'summary' => $summary,
            'date' => $date->toDateString(),
            'grade' => $grade,
            'user' => $user,
        ]);

        $response = response($view);

        return $this->attachTelemetry($response, $telemetry, $user);
    }
}
