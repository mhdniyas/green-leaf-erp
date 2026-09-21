<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing\V2;

use App\Models\Product;
use App\Queries\Purchasing\V2\PurchaserV2DailyQuery;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Support\Purchasing\V2\PurchaserV2Telemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PurchaserV2DailyController extends PurchaserV2BaseController
{
    public function __construct(
        PurchaserBusinessDayService $businessDayService,
        private readonly PurchaserV2DailyQuery $dailyQuery,
    ) {
        parent::__construct($businessDayService);
    }

    /**
     * Initial view: Loads only the first 20 intended products for the day.
     */
    public function index(Request $request): Response
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);
        $date = $this->resolveBusinessDate($request);
        $grade = $this->resolveGrade($request);

        $search = $request->query('search');
        $statusFilter = (string) $request->query('status', 'all');

        $result = $this->dailyQuery->getProducts(
            date: $date,
            grade: $grade,
            user: $user,
            search: is_string($search) ? $search : null,
            statusFilter: in_array($statusFilter, ['all', 'pending', 'fulfilled'], true) ? $statusFilter : 'all',
            perPage: 20,
            page: 1,
        );

        $view = view('purchasing.purchaser-v2.daily', [
            'initialItems' => $result['items'],
            'pagination' => $result['pagination'],
            'metrics' => $result['metrics'],
            'date' => $date->toDateString(),
            'grade' => $grade,
            'search' => is_string($search) ? $search : '',
            'statusFilter' => $statusFilter,
            'user' => $user,
        ]);

        $response = response($view);

        return $this->attachTelemetry($response, $telemetry, $user);
    }

    /**
     * Server-side paginated/filtered search API for daily items (bounded to max 20-30 rows).
     */
    public function products(Request $request): JsonResponse
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);
        $date = $this->resolveBusinessDate($request);
        $grade = $this->resolveGrade($request);

        $search = $request->query('q', $request->query('search'));
        $statusFilter = (string) $request->query('status', 'all');
        $page = max(1, $request->integer('page', 1));
        $perPage = min(30, max(5, $request->integer('per_page', 20)));

        $result = $this->dailyQuery->getProducts(
            date: $date,
            grade: $grade,
            user: $user,
            search: is_string($search) ? $search : null,
            statusFilter: in_array($statusFilter, ['all', 'pending', 'fulfilled'], true) ? $statusFilter : 'all',
            perPage: $perPage,
            page: $page,
        );

        $response = response()->json([
            'status' => 'success',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
            'metrics' => $result['metrics'],
            '_telemetry' => $telemetry->metrics(),
        ]);

        return $this->attachTelemetry($response, $telemetry, $user);
    }

    /**
     * Single product demand breakdown loaded on click ONLY.
     */
    public function productDetail(Request $request, Product $product): JsonResponse
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);
        $date = $this->resolveBusinessDate($request);
        $grade = $this->resolveGrade($request);

        $detail = $this->dailyQuery->getProductDemandDetail(
            product: $product,
            date: $date,
            grade: $grade,
            user: $user,
        );

        $response = response()->json([
            'status' => 'success',
            'data' => $detail,
            '_telemetry' => $telemetry->metrics(),
        ]);

        return $this->attachTelemetry($response, $telemetry, $user);
    }
}
