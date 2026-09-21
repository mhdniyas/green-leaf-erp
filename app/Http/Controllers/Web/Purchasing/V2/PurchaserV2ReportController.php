<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing\V2;

use App\Queries\Purchasing\V2\PurchaserV2ReportQuery;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Support\Purchasing\V2\PurchaserV2Telemetry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PurchaserV2ReportController extends PurchaserV2BaseController
{
    public function __construct(
        PurchaserBusinessDayService $businessDayService,
        private readonly PurchaserV2ReportQuery $reportQuery,
    ) {
        parent::__construct($businessDayService);
    }

    /**
     * Display the isolated Purchaser V2 Daily Report for the operational day.
     */
    public function index(Request $request): Response
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);
        $date = $this->resolveBusinessDate($request);
        $grade = $this->resolveGrade($request);
        $search = $request->query('search');

        $report = $this->reportQuery->getDailyReport(
            date: $date,
            grade: $grade,
            user: $user,
            search: is_string($search) ? $search : null,
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'date' => $date->toDateString(),
                'grade' => $grade,
                'metrics' => $report['metrics'],
                'items' => $report['items'],
                'bills' => $report['bills'],
            ]);
        }

        $view = view('purchasing.purchaser-v2.report', [
            'metrics' => $report['metrics'],
            'items' => $report['items'],
            'bills' => $report['bills'],
            'date' => $date->toDateString(),
            'grade' => $grade,
            'search' => is_string($search) ? $search : '',
            'user' => $user,
        ]);

        $response = response($view);

        return $this->attachTelemetry($response, $telemetry, $user);
    }
}
