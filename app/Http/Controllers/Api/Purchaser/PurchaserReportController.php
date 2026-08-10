<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Purchaser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchaser\PurchaserReportRequest;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Reports\PurchaserReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

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

    /** @return array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int,category_ids:?array<int, int>} */
    private function filters(PurchaserReportRequest $request): array
    {
        $validated = $request->validated();
        $range = (string) ($validated['range'] ?? (isset($validated['date_from']) ? 'custom' : 'today'));
        [$dateFrom, $dateTo] = $this->dates($range, $validated);
        $user = $request->user();

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'shop_id' => isset($validated['shop_id']) ? (int) $validated['shop_id'] : null,
            'status' => isset($validated['status']) ? (string) $validated['status'] : null,
            'search' => trim((string) ($validated['search'] ?? '')),
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 25),
            'category_ids' => $user?->hasAssignedCategoryFilter() ? $user->assignedCategoryIds() : null,
        ];
    }

    /** @param array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int,category_ids:?array<int, int>} $filters */
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
                'category_ids' => $filters['category_ids'] ?? null,
            ],
        ];
    }

    /** @param array<string, mixed> $validated @return array{Carbon, Carbon} */
    private function dates(string $range, array $validated): array
    {
        $today = $this->businessDay->operationalDate();

        return match ($range) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'week' => [$today->copy()->startOfWeek(), $today],
            'month' => [$today->copy()->startOfMonth(), $today],
            'custom' => [
                Carbon::createFromFormat('Y-m-d', (string) ($validated['date_from'] ?? $today->toDateString())),
                Carbon::createFromFormat('Y-m-d', (string) ($validated['date_to'] ?? $validated['date_from'] ?? $today->toDateString())),
            ],
            default => [$today, $today],
        };
    }
}
