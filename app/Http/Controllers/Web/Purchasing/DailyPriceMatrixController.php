<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\DailyProductPriceRevision;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\VendorPriceService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyPriceMatrixController extends Controller
{
    public function __construct(
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly VendorPriceService $vendorPriceService,
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeBoardAccess();

        $purchaseDate = $request->input('date', $this->businessDayService->operationalDate()->toDateString());
        $targetBusinessDate = Carbon::parse($purchaseDate)->toDateString();
        $selectedDate = Carbon::parse($purchaseDate);
        $search = trim((string) $request->input('search', ''));
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;

        $matrixCategory = strtolower((string) $request->input('matrix_category', 'a'));
        if (! in_array($matrixCategory, ['a', 'b', 'c'], true)) {
            $matrixCategory = 'a';
        }

        $weekStart = $request->filled('week_start')
            ? Carbon::parse((string) $request->input('week_start'))->startOfWeek(Carbon::MONDAY)
            : $selectedDate->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $matrixDates = [];
        $currentDate = $weekStart->copy();
        while ($currentDate->lte($weekEnd)) {
            $matrixDates[$currentDate->toDateString()] = [
                'date_string' => $currentDate->toDateString(),
                'label' => $currentDate->format('d-M'),
                'day_num' => $currentDate->format('d'),
                'is_selected' => $currentDate->toDateString() === $targetBusinessDate,
            ];
            $currentDate->addDay();
        }

        $user = $request->user();
        $productQuery = Product::query()
            ->active()
            ->with(['category', 'orderUnits'])
            ->ordered();

        if ($user && ! $user->hasRole('admin')) {
            $assignedCatIds = $user->assignedCategoryIds();
            $purchasedProductIds = GoodsReceivedItem::query()
                ->whereHas('goodsReceived', fn ($grnQuery) => $grnQuery->where('received_by', $user->id))
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->toArray();

            if (! empty($assignedCatIds) || ! empty($purchasedProductIds)) {
                $productQuery->where(function ($q) use ($assignedCatIds, $purchasedProductIds): void {
                    if (! empty($assignedCatIds)) {
                        $q->whereIn('category_id', $assignedCatIds);
                    }
                    if (! empty($purchasedProductIds)) {
                        $q->orWhereIn('id', $purchasedProductIds);
                    }
                });
            }
        }

        if ($categoryId) {
            $productQuery->where('category_id', $categoryId);
        }

        if ($search !== '') {
            $productQuery->where(function ($q) use ($search): void {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $products = $productQuery->get(['id', 'category_id', 'name', 'sku', 'unit', 'base_price']);
        $productIds = $products->pluck('id')->values();

        $monthApprovals = DailyPriceApproval::query()
            ->whereIn('product_id', $productIds)
            ->whereBetween('business_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->groupBy('product_id');

        $matrixProducts = [];
        $slNo = 1;

        // Get previous day's prices for comparison and copying
        $previousDate = $weekStart->copy()->subDay()->toDateString();
        $previousDayApprovals = DailyPriceApproval::query()
            ->whereIn('product_id', $productIds)
            ->where('business_date', $previousDate)
            ->get()
            ->keyBy('product_id');

        foreach ($products as $product) {
            $pId = (int) $product->id;
            $productMonthApprovals = ($monthApprovals->get($pId) ?? collect())
                ->keyBy(fn (DailyPriceApproval $item): string => $item->business_date->toDateString());
            $unitOptions = $this->measurementUnitOptionsForProduct($product);

            $dailyPrices = [];
            $prevPriceA = null;
            $prevPriceB = null;
            $prevPriceC = null;

            foreach ($matrixDates as $dateStr => $dateInfo) {
                /** @var DailyPriceApproval|null $app */
                $app = $productMonthApprovals->get($dateStr);
                $priceA = $app && $app->price_a !== null ? (float) $app->price_a : null;
                $priceB = $app && $app->price_b !== null ? (float) $app->price_b : null;
                $priceC = $app && $app->price_c !== null ? (float) $app->price_c : null;
                $purchasePrice = $app && $app->purchase_price !== null ? (float) $app->purchase_price : null;

                $changedA = $priceA !== null && $prevPriceA !== null && abs($priceA - $prevPriceA) > 0.001;
                $changedB = $priceB !== null && $prevPriceB !== null && abs($priceB - $prevPriceB) > 0.001;
                $changedC = $priceC !== null && $prevPriceC !== null && abs($priceC - $prevPriceC) > 0.001;

                if ($priceA !== null) {
                    $prevPriceA = $priceA;
                }
                if ($priceB !== null) {
                    $prevPriceB = $priceB;
                }
                if ($priceC !== null) {
                    $prevPriceC = $priceC;
                }

                $dailyPrices[$dateStr] = [
                    'approval_id' => $app?->id,
                    'price_a' => $priceA,
                    'price_b' => $priceB,
                    'price_c' => $priceC,
                    'purchase_price' => $purchasePrice,
                    'unit' => ProductUnit::normalizeUnit((string) ($app?->price_unit ?? $product->unit ?? 'kg')),
                    'changed_a' => $changedA,
                    'changed_b' => $changedB,
                    'changed_c' => $changedC,
                    'status' => $app?->status ?? 'none',
                ];
            }

            /** @var DailyPriceApproval|null $prevDayApp */
            $prevDayApp = $previousDayApprovals->get($pId);

            $matrixProducts[] = [
                'sl_no' => $slNo++,
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'unit_options' => $unitOptions,
                'base_price' => (float) $product->base_price,
                'prices' => $dailyPrices,
                'previous_day' => [
                    'date' => $previousDate,
                    'price_a' => $prevDayApp && $prevDayApp->price_a !== null ? (float) $prevDayApp->price_a : null,
                    'price_b' => $prevDayApp && $prevDayApp->price_b !== null ? (float) $prevDayApp->price_b : null,
                    'price_c' => $prevDayApp && $prevDayApp->price_c !== null ? (float) $prevDayApp->price_c : null,
                    'purchase_price' => $prevDayApp && $prevDayApp->purchase_price !== null ? (float) $prevDayApp->purchase_price : null,
                    'unit' => ProductUnit::normalizeUnit((string) ($prevDayApp?->price_unit ?? $product->unit ?? 'kg')),
                ],
            ];
        }

        return view('purchase-manager.prices.matrix', [
            'purchaseDate' => $purchaseDate,
            'targetBusinessDate' => $targetBusinessDate,
            'search' => $search,
            'categoryId' => $categoryId,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'matrixDates' => $matrixDates,
            'matrixProducts' => $matrixProducts,
            'matrixCategory' => $matrixCategory,
            'weekStartDate' => $weekStart->toDateString(),
            'weekEndDate' => $weekEnd->toDateString(),
            'previousWeekStartDate' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeekStartDate' => $weekStart->copy()->addWeek()->toDateString(),
            'previousDate' => $previousDate,
        ]);
    }

    public function updateCell(Request $request): mixed
    {
        $this->authorizeBoardAccess();
        $this->authorizePurchaserUpdate();

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'date' => ['required', 'date'],
            'price_category' => ['required', 'string', 'in:a,b,c,A,B,C'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'price_unit' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $userId = (int) $user->id;

        $product = Product::query()->findOrFail((int) $validated['product_id']);
        $dateStr = Carbon::parse($validated['date'])->toDateString();
        $cat = strtolower($validated['price_category']);
        $submittedUnit = ProductUnit::normalizeUnit((string) ($validated['price_unit'] ?? ''));
        $newPrice = isset($validated['price']) && $validated['price'] !== '' && $validated['price'] !== null
            ? round((float) $validated['price'], 2)
            : null;

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', $dateStr)
            ->first();

        $previousApproval = $this->previousApprovedApprovalFor($product->id, $dateStr);

        if (! $approval) {
            $approval = new DailyPriceApproval([
                'product_id' => $product->id,
                'business_date' => $dateStr,
                'purchase_price' => (float) ($previousApproval?->purchase_price ?? ($product->vendor_price > 0 ? $product->vendor_price : $product->base_price)),
                'price_unit' => $submittedUnit !== ''
                    ? $submittedUnit
                    : ProductUnit::normalizeUnit((string) ($this->detectPrimaryOrderedUnit((int) $product->id, $dateStr) ?? $product->unit ?: 'kg')),
                'price_a' => $newPrice ?? (float) ($previousApproval?->price_a ?? $product->base_price),
                'price_b' => $newPrice ?? (float) ($previousApproval?->price_b ?? $product->base_price),
                'price_c' => $newPrice ?? (float) ($previousApproval?->price_c ?? $product->base_price),
                'status' => 'approved',
            ]);
        }

        if ($submittedUnit !== '') {
            $approval->price_unit = $submittedUnit;
        }

        if ($cat === 'a') {
            $approval->price_a = $newPrice;
            if ($approval->price_b === null || (float) $approval->price_b <= 0) {
                $approval->price_b = (float) ($previousApproval?->price_b ?? $newPrice);
            }
            if ($approval->price_c === null || (float) $approval->price_c <= 0) {
                $approval->price_c = (float) ($previousApproval?->price_c ?? $newPrice);
            }
        } elseif ($cat === 'b') {
            $approval->price_b = $newPrice;
        } elseif ($cat === 'c') {
            $approval->price_c = $newPrice;
        }

        $approval->status = 'approved';
        $approval->approved_by = $userId;
        $approval->approved_at = now();

        $approval->save();

        $this->publishDailyPriceApproval($product, $approval, $userId);

        $this->shopInvoiceService->generateForBusinessDate($dateStr, $userId);
        $this->shopInvoiceService->repriceAllForBusinessDate(
            $dateStr,
            $userId,
            "Purchaser updated matrix cell price for {$product->name} on {$dateStr}."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$product->name} price for {$dateStr} saved.",
                'product_id' => $product->id,
                'date' => $dateStr,
                'price_category' => $cat,
                'price_a' => $approval->price_a,
                'price_b' => $approval->price_b,
                'price_c' => $approval->price_c,
                'price_unit' => $approval->price_unit,
            ]);
        }

        return redirect()->back()->with('success', "Price for {$product->name} on {$dateStr} saved.");
    }

    public function updateMatrix(Request $request): RedirectResponse
    {
        $this->authorizeBoardAccess();
        $this->authorizePurchaserUpdate();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'week_start' => ['nullable', 'date'],
            'matrix_category' => ['required', 'string', 'in:a,b,c,A,B,C'],
            'action' => ['required', 'string', 'in:update'],
            'matrix_prices' => ['nullable', 'array'],
            'matrix_prices.*' => ['nullable', 'array'],
            'matrix_prices.*.*' => ['nullable', 'numeric', 'min:0'],
            'matrix_price_units' => ['nullable', 'array'],
            'matrix_price_units.*' => ['nullable', 'array'],
            'matrix_price_units.*.*' => ['nullable', 'string', 'max:20'],
            'all_product_ids' => ['nullable', 'array'],
            'all_product_ids.*' => ['nullable', 'integer'],
            'all_dates' => ['nullable', 'array'],
            'all_dates.*' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $userId = (int) $user->id;
        $matrixCategory = strtolower((string) $validated['matrix_category']);
        $rawMatrixPrices = collect((array) ($validated['matrix_prices'] ?? []));
        $rawMatrixPriceUnits = collect((array) ($validated['matrix_price_units'] ?? []));

        if ($rawMatrixPrices->isEmpty() && $rawMatrixPriceUnits->isEmpty()) {
            return redirect()
                ->route('purchasing.prices.matrix.index', [
                    'date' => $validated['date'],
                    'search' => $validated['search'] ?? null,
                    'category_id' => $validated['category_id'] ?? null,
                    'week_start' => $validated['week_start'] ?? null,
                    'matrix_category' => $matrixCategory,
                ])
                ->with('warning', 'No matrix changes were submitted.');
        }

        $productIds = $rawMatrixPrices
            ->keys()
            ->merge($rawMatrixPriceUnits->keys())
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $updatedCells = 0;
        $publishedDates = [];

        DB::transaction(function () use (
            $productIds,
            $rawMatrixPrices,
            $rawMatrixPriceUnits,
            $products,
            $matrixCategory,
            $userId,
            &$publishedDates,
            &$updatedCells
        ): void {
            foreach ($productIds as $productId) {
                $datePrices = (array) ($rawMatrixPrices->get((int) $productId) ?? []);
                $dateUnits = (array) ($rawMatrixPriceUnits->get((int) $productId) ?? []);
                $product = $products->get((int) $productId);

                if (! $product) {
                    continue;
                }

                $allDateKeys = collect(array_keys($datePrices))
                    ->merge(array_keys($dateUnits))
                    ->unique()
                    ->values();

                foreach ($allDateKeys as $dateStr) {
                    $priceValue = $datePrices[$dateStr] ?? null;

                    $submittedUnit = ProductUnit::normalizeUnit((string) ($dateUnits[$dateStr] ?? ''));

                    if (($priceValue === null || $priceValue === '') && $submittedUnit === '') {
                        continue;
                    }

                    $businessDate = Carbon::parse((string) $dateStr)->toDateString();
                    $newPrice = ($priceValue === null || $priceValue === '')
                        ? null
                        : round((float) $priceValue, 2);
                    $previousApproval = $this->previousApprovedApprovalFor((int) $product->id, $businessDate);

                    $approval = DailyPriceApproval::query()
                        ->firstOrNew([
                            'product_id' => $product->id,
                            'business_date' => $businessDate,
                        ]);

                    // Determine price_unit: use submitted unit, or auto-detect from orders, or fall back to product unit
                    $priceUnit = ! empty($submittedUnit)
                        ? $submittedUnit
                        : (ProductUnit::normalizeUnit((string) ($this->detectPrimaryOrderedUnit((int) $product->id, $businessDate) ?? $product->unit ?: 'kg')));

                    if (! $approval->exists) {
                        $approval->purchase_price = (float) ($previousApproval?->purchase_price ?? ($product->vendor_price > 0 ? $product->vendor_price : $product->base_price));
                        $approval->price_unit = $priceUnit;
                        $approval->price_a = (float) ($previousApproval?->price_a ?? $product->base_price);
                        $approval->price_b = (float) ($previousApproval?->price_b ?? $product->base_price);
                        $approval->price_c = (float) ($previousApproval?->price_c ?? $product->base_price);
                    } else {
                        // Update price_unit on existing approval if submitted or auto-detected differs
                        if (! empty($submittedUnit) || $this->detectPrimaryOrderedUnit((int) $product->id, $businessDate)) {
                            $approval->price_unit = $priceUnit;
                        }
                    }

                    if ($newPrice !== null) {
                        if ($matrixCategory === 'a') {
                            $approval->price_a = $newPrice;
                            if ($approval->price_b === null || (float) $approval->price_b <= 0) {
                                $approval->price_b = (float) ($previousApproval?->price_b ?? $newPrice);
                            }
                            if ($approval->price_c === null || (float) $approval->price_c <= 0) {
                                $approval->price_c = (float) ($previousApproval?->price_c ?? $newPrice);
                            }
                        } elseif ($matrixCategory === 'b') {
                            $approval->price_b = $newPrice;
                        } else {
                            $approval->price_c = $newPrice;
                        }
                    }

                    $approval->status = 'approved';
                    $approval->approved_by = $userId;
                    $approval->approved_at = now();
                    $approval->save();

                    $updatedCells++;
                    $publishedDates[$businessDate] = true;
                    $this->publishDailyPriceApproval($product, $approval, $userId);
                }
            }
        });

        foreach (array_keys($publishedDates) as $dateStr) {
            $this->shopInvoiceService->generateForBusinessDate($dateStr, $userId);
            $this->shopInvoiceService->repriceAllForBusinessDate(
                $dateStr,
                $userId,
                "Purchaser updated matrix prices for {$dateStr}.",
            );
        }

        $message = "Updated {$updatedCells} matrix price cell(s) as final prices.";

        return redirect()
            ->route('purchasing.prices.matrix.index', [
                'date' => $validated['date'],
                'search' => $validated['search'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'week_start' => $validated['week_start'] ?? null,
                'matrix_category' => $matrixCategory,
            ])
            ->with('success', $message);
    }

    private function authorizeBoardAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin'), 403);
    }

    private function authorizePurchaserUpdate(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase'), 403);
    }

    private function previousApprovedApprovalFor(int $productId, string $businessDate): ?DailyPriceApproval
    {
        return DailyPriceApproval::query()
            ->where('product_id', $productId)
            ->whereDate('business_date', '<', Carbon::parse($businessDate)->toDateString())
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->orderByDesc('business_date')
            ->first();
    }

    /**
     * Detect the primary (most common) requested unit for a product on a given business date.
     * Returns the most frequent requested_unit from active shop orders.
     */
    private function detectPrimaryOrderedUnit(int $productId, string $businessDate): ?string
    {
        $units = \App\Models\ShopOrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', fn ($q) => $q->where('business_date', $businessDate)->where('state', 'approved'))
            ->pluck('requested_unit')
            ->filter()
            ->map(fn ($unit): string => ProductUnit::normalizeUnit((string) $unit))
            ->countBy()
            ->sortDesc()
            ->keys();

        return $units->first();
    }

    /**
     * @return array<int, array{unit:string,label:string}>
     */
    private function measurementUnitOptionsForProduct(Product $product): array
    {
        $units = $product->relationLoaded('orderUnits')
            ? $product->orderUnits
            : $product->orderUnits()->orderBy('sort_order')->orderBy('id')->get();

        $measurementUnits = $units
            ->filter(fn ($unit): bool => (float) $unit->conversion_to_base > 0)
            ->map(function ($unit): array {
                $normalizedUnit = ProductUnit::normalizeUnit((string) $unit->unit);

                return [
                    'unit' => $normalizedUnit,
                    'label' => strtoupper((string) ($unit->label ?: $normalizedUnit)),
                ];
            });

        $baseUnit = ProductUnit::normalizeUnit((string) ($product->unit ?: 'kg'));

        $merged = collect([[
            'unit' => $baseUnit,
            'label' => strtoupper($baseUnit),
        ]])
            ->merge($measurementUnits)
            ->unique(fn (array $row): string => $row['unit'])
            ->values();

        return $merged->isEmpty()
            ? [[
                'unit' => 'kg',
                'label' => 'KG',
            ]]
            : $merged->all();
    }

    private function updateActivePricesForGroup(Product $product, ?ShopPriceGroup $group, float $priceGradeA, int $userId): void
    {
        if (! $group) {
            return;
        }

        $grades = [
            'A' => 1.00,
            'B' => 0.90,
            'C' => 0.80,
        ];

        foreach ($grades as $gradeVal => $multiplier) {
            $calculatedPrice = round($priceGradeA * $multiplier, 2);

            $activePrice = DailyProductPrice::firstOrNew([
                'product_id' => $product->id,
                'shop_price_group_id' => $group->id,
                'grade' => $gradeVal,
            ]);

            $oldPrice = $activePrice->exists ? (float) $activePrice->selling_price : null;

            $activePrice->fill([
                'selling_price' => $calculatedPrice,
                'price_source' => 'manual',
                'margin_percent' => null,
                'manual_override' => true,
                'override_reason' => 'Purchaser updated matrix final price',
                'changed_by' => $userId,
            ]);
            $activePrice->save();

            if ($oldPrice === null || abs($oldPrice - $calculatedPrice) > 0.0001) {
                DailyProductPriceRevision::create([
                    'daily_product_price_id' => $activePrice->id,
                    'product_id' => $product->id,
                    'shop_price_group_id' => $group->id,
                    'grade' => $gradeVal,
                    'old_price' => $oldPrice,
                    'new_price' => $calculatedPrice,
                    'old_margin_percent' => null,
                    'new_margin_percent' => null,
                    'change_type' => 'manual',
                    'reason' => 'Purchaser updated matrix final price',
                    'changed_by' => $userId,
                    'changed_at' => now(),
                ]);
            }
        }
    }

    private function publishDailyPriceApproval(Product $product, DailyPriceApproval $approval, int $userId): void
    {
        $groupA = ShopPriceGroup::query()->where('name', 'A')->first();
        $groupB = ShopPriceGroup::query()->where('name', 'B')->first();
        $groupC = ShopPriceGroup::query()->where('name', 'C')->first();

        $this->updateActivePricesForGroup($product, $groupA, (float) $approval->price_a, $userId);
        $this->updateActivePricesForGroup($product, $groupB, (float) $approval->price_b, $userId);
        $this->updateActivePricesForGroup($product, $groupC, (float) $approval->price_c, $userId);
        $this->vendorPriceService->syncPrice($product->id, (float) $approval->purchase_price);
    }
}
