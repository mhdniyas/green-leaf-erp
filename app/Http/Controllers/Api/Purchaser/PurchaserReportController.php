<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Purchaser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchaser\PurchaserReportRequest;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Reports\PurchaserReportService;
use Illuminate\Http\JsonResponse;

class PurchaserReportController extends Controller
{
    public function __construct(
        private readonly PurchaserReportService $reports,
        private readonly PurchaserBusinessDayService $businessDay,
    ) {}

    public function salesSummary(PurchaserReportRequest $request): JsonResponse
    {
        $filters = $this->filters($request);

        return response()->json([
            'success' => true,
            'data' => array_merge($this->context($filters), $this->reports->salesSummary($filters)),
        ]);
    }

    public function itemSummary(PurchaserReportRequest $request): JsonResponse
    {
        $filters = $this->filters($request);

        return response()->json([
            'success' => true,
            'data' => array_merge($this->context($filters), $this->reports->itemSummary($filters)),
        ]);
    }

    /** @return array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int} */
    private function filters(PurchaserReportRequest $request): array
    {
        $validated = $request->validated();
        $operationalDate = $this->businessDay->operationalDate()->toDateString();

        return [
            'date_from' => (string) ($validated['date_from'] ?? $operationalDate),
            'date_to' => (string) ($validated['date_to'] ?? $validated['date_from'] ?? $operationalDate),
            'shop_id' => isset($validated['shop_id']) ? (int) $validated['shop_id'] : null,
            'status' => isset($validated['status']) ? (string) $validated['status'] : null,
            'search' => trim((string) ($validated['search'] ?? '')),
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 25),
        ];
    }

    /** @param array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int} $filters */
    private function context(array $filters): array
    {
        return [
            'period' => [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
            ],
            'operational_date' => $this->businessDay->operationalDate()->toDateString(),
            'applied_filters' => [
                'shop_id' => $filters['shop_id'],
                'status' => $filters['status'] ?? 'all',
                'search' => $filters['search'],
            ],
        ];
    }
}
