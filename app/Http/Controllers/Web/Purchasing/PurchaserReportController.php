<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Reports\PurchaserReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaserReportController extends Controller
{
    public function __construct(
        private readonly PurchaserReportService $reports,
        private readonly PurchaserBusinessDayService $businessDay,
    ) {}

    public function salesSummary(Request $request): View
    {
        $filters = $this->filters($request);

        return view('purchasing.purchaser.reports.sales-summary', $this->viewData(
            $request,
            $filters,
            $this->reports->salesSummary($filters),
        ));
    }

    public function itemSummary(Request $request): View
    {
        $filters = $this->filters($request);

        return view('purchasing.purchaser.reports.item-summary', $this->viewData(
            $request,
            $filters,
            $this->reports->itemSummary($filters),
        ));
    }

    /** @return array{date_from:string,date_to:string,shop_id:?int,status:?string,search:string,page:int,per_page:int} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(['today', 'yesterday', 'week', 'month', 'custom'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'status' => ['nullable', Rule::in(['all', 'finalized', 'payment_pending', 'paid'])],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $range = (string) ($validated['range'] ?? 'today');
        [$dateFrom, $dateTo] = $this->dates($range, $validated);

        if ($dateFrom->diffInDays($dateTo) > 366) {
            abort(422, 'The report period may not exceed 366 days.');
        }

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'shop_id' => isset($validated['shop_id']) ? (int) $validated['shop_id'] : null,
            'status' => isset($validated['status']) ? (string) $validated['status'] : null,
            'search' => trim((string) ($validated['search'] ?? '')),
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 25),
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

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function viewData(Request $request, array $filters, array $report): array
    {
        return [
            'report' => $report,
            'filters' => $filters,
            'selectedRange' => $request->string('range')->toString() ?: 'today',
            'shops' => Shop::query()->orderBy('name')->get(['id', 'name', 'code']),
        ];
    }
}
