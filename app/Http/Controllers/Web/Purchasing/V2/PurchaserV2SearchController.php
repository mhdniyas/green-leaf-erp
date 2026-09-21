<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing\V2;

use App\Queries\Purchasing\V2\PurchaserProductSearchQuery;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Support\Purchasing\V2\PurchaserV2Telemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaserV2SearchController extends PurchaserV2BaseController
{
    public function __construct(
        PurchaserBusinessDayService $businessDayService,
        private readonly PurchaserProductSearchQuery $searchQuery,
    ) {
        parent::__construct($businessDayService);
    }

    /**
     * Reusable product search (bounded to 20-30 rows, minimal payload, scoped permissions).
     */
    public function products(Request $request): JsonResponse
    {
        $telemetry = PurchaserV2Telemetry::start();
        $user = $this->ensurePurchaser($request);

        $keyword = (string) $request->query('q', $request->query('search', ''));
        $categoryId = $request->filled('category_id') ? $request->integer('category_id') : null;
        $page = max(1, $request->integer('page', 1));
        $perPage = min(30, max(5, $request->integer('per_page', 25)));

        $result = $this->searchQuery->search(
            keyword: $keyword,
            user: $user,
            categoryId: $categoryId,
            perPage: $perPage,
            page: $page,
        );

        $response = response()->json([
            'status' => 'success',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
            '_telemetry' => $telemetry->metrics(),
        ]);

        return $this->attachTelemetry($response, $telemetry, $user);
    }
}
