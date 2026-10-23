<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Enums\Inventory\ProductGrade;
use App\Exports\DailyPriceMatrixExport;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyPricePublication;
use App\Models\DailyProductPrice;
use App\Models\DailyProductPriceRevision;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\VendorPriceService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DailyPriceMatrixController extends Controller
{
    private const MISSING_PRICE_FALLBACK = 9999.00;

    public function __construct(
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly VendorPriceService $vendorPriceService,
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeBoardAccess();

        return view('purchase-manager.prices.matrix', $this->buildMatrixViewData($request));
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorizeBoardAccess();

        $data = $this->buildMatrixViewData($request, forExport: true);
        $scope = $this->exportScope($request);
        $scopeLabel = str_replace('_', '-', $scope);
        $filename = "selling-price-matrix-{$scopeLabel}-{$data['targetBusinessDate']}.xlsx";
        $data['exportRows'] = $this->matrixExportRows($data, $scope);

        return Excel::download(new DailyPriceMatrixExport($data, $scope), $filename);
    }

    public function exportPdf(Request $request): View
    {
        $this->authorizeBoardAccess();

        $data = $this->buildMatrixViewData($request, forExport: true);
        $scope = $this->exportScope($request);

        return view('purchase-manager.prices.matrix-pdf', [
            ...$data,
            'exportScope' => $scope,
            'exportRows' => $this->matrixExportRows($data, $scope),
            'generatedBy' => $request->user()?->name ?? 'System',
            'generatedAt' => now()->format('d M Y, h:i A'),
            'companyName' => 'Green Leaf Distribution',
        ]);
    }

    public function exportWhatsApp(Request $request): RedirectResponse
    {
        $this->authorizeBoardAccess();

        $data = $this->buildMatrixViewData($request, forExport: true);
        $scope = $this->exportScope($request);

        return redirect()->away('https://api.whatsapp.com/send?text='.rawurlencode($this->buildMatrixShareText($data, $scope)));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMatrixViewData(Request $request, bool $forExport = false): array
    {
        $purchaseDate = $request->input('date', $this->businessDayService->operationalDate()->toDateString());
        $targetBusinessDate = Carbon::parse($purchaseDate)->toDateString();
        $selectedDate = Carbon::parse($purchaseDate);
        $search = trim((string) $request->input('search', ''));

        $categoryIds = [];
        if ($request->has('category_ids')) {
            $rawCatIds = $request->input('category_ids');
            if (is_array($rawCatIds)) {
                $categoryIds = array_map('intval', array_filter($rawCatIds));
            } elseif (is_string($rawCatIds) && strlen(trim($rawCatIds)) > 0) {
                $categoryIds = array_map('intval', array_filter(explode(',', $rawCatIds)));
            }
        } elseif ($request->filled('category_id')) {
            $rawCatId = $request->input('category_id');
            if (is_array($rawCatId)) {
                $categoryIds = array_map('intval', array_filter($rawCatId));
            } elseif (is_string($rawCatId) && str_contains($rawCatId, ',')) {
                $categoryIds = array_map('intval', array_filter(explode(',', $rawCatId)));
            } else {
                $categoryIds = [(int) $rawCatId];
            }
        }
        $categoryIds = array_values(array_unique(array_filter($categoryIds)));
        $categoryId = $categoryIds[0] ?? null;

        $matrixCategory = strtolower((string) $request->input('matrix_category', 'a'));
        if (! in_array($matrixCategory, ['a', 'b', 'c'], true)) {
            $matrixCategory = 'a';
        }

        $qualityGrade = strtoupper((string) ($request->input('quality_grade', $request->input('grade', 'A'))));
        if (! in_array($qualityGrade, ['A', 'B', 'C'], true)) {
            $qualityGrade = 'A';
        }

        // Window: previous 2 days and current day (3 days total)
        $matrixStartDate = $selectedDate->copy()->subDays(2);
        $matrixEndDate = $selectedDate->copy();

        $matrixDates = [];
        $currentDate = $matrixStartDate->copy();
        while ($currentDate->lte($matrixEndDate)) {
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

        if (! empty($categoryIds)) {
            $productQuery->whereIn('category_id', $categoryIds);
        }

        if ($search !== '') {
            $productQuery->where(function ($q) use ($search): void {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        if ($forExport) {
            $products = $productQuery->get(['id', 'category_id', 'name', 'sku', 'unit', 'base_price']);
            $paginatedProducts = null;
            $productIds = $products->pluck('id')->values();
            $slNo = 1;
        } else {
            $perPage = 40;
            $paginatedProducts = $productQuery->paginate($perPage, ['id', 'category_id', 'name', 'sku', 'unit', 'base_price'])->withQueryString();
            $products = $paginatedProducts->items();
            $productIds = collect($products)->pluck('id')->values();
            $slNo = $paginatedProducts->firstItem() ?? 1;
        }

        $monthApprovals = DailyPriceApproval::query()
            ->whereIn('product_id', $productIds)
            ->where('grade', $qualityGrade)
            ->whereBetween('business_date', [$matrixStartDate->toDateString(), $matrixEndDate->toDateString()])
            ->get()
            ->groupBy('product_id');

        $matrixProducts = [];
        $slNo = 1;

        // Baseline comparison: day before the 3-day matrix window
        $previousDate = $matrixStartDate->copy()->subDay()->toDateString();
        $previousDayApprovals = DailyPriceApproval::query()
            ->whereIn('product_id', $productIds)
            ->where('grade', $qualityGrade)
            ->where('business_date', $previousDate)
            ->get()
            ->keyBy('product_id');

        foreach ($products as $product) {
            $pId = (int) $product->id;
            $productMonthApprovals = ($monthApprovals->get($pId) ?? collect())
                ->keyBy(fn (DailyPriceApproval $item): string => $item->business_date->toDateString());
            $unitOptions = $this->measurementUnitOptionsForProduct($product);

            /** @var DailyPriceApproval|null $prevDayApp */
            $prevDayApp = $previousDayApprovals->get($pId);

            $dailyPrices = [];
            $prevPriceA = $prevDayApp && $prevDayApp->price_a !== null ? (float) $prevDayApp->price_a : null;
            $prevPriceB = $prevDayApp && $prevDayApp->price_b !== null ? (float) $prevDayApp->price_b : null;
            $prevPriceC = $prevDayApp && $prevDayApp->price_c !== null ? (float) $prevDayApp->price_c : null;

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

            $latestApprovedDate = null;
            $latestApprovedPrice = null;
            foreach ($matrixDates as $dateStr => $dateInfo) {
                $cellData = $dailyPrices[$dateStr] ?? null;
                if (! $cellData || ($cellData['status'] ?? 'none') !== 'approved') {
                    continue;
                }
                $candidatePrice = match ($matrixCategory) {
                    'a' => $cellData['price_a'] ?? null,
                    'b' => $cellData['price_b'] ?? null,
                    'c' => $cellData['price_c'] ?? null,
                    default => null,
                };
                if ($candidatePrice === null) {
                    continue;
                }
                if ($latestApprovedDate === null || $dateStr > $latestApprovedDate) {
                    $latestApprovedDate = $dateStr;
                    $latestApprovedPrice = (float) $candidatePrice;
                }
            }

            $rowUnitOptions = collect($unitOptions ?: [
                ['unit' => strtolower((string) ($product->unit ?? 'kg')), 'label' => strtoupper((string) ($product->unit ?? 'kg'))],
            ]);
            $unitsUsedInWeek = collect($dailyPrices)
                ->map(fn ($priceRow) => strtolower((string) ($priceRow['unit'] ?? '')))
                ->filter()
                ->map(fn ($unit) => [
                    'unit' => $unit,
                    'label' => strtoupper(str_replace('_', ' ', $unit)),
                ]);
            $rowUnitOptions = $rowUnitOptions
                ->merge($unitsUsedInWeek)
                ->unique(fn ($opt) => strtolower((string) ($opt['unit'] ?? '')))
                ->values()
                ->all();

            $matrixProducts[] = [
                'sl_no' => $slNo++,
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'unit_options' => $unitOptions,
                'row_unit_options' => $rowUnitOptions,
                'latest_approved_date' => $latestApprovedDate,
                'latest_approved_price' => $latestApprovedPrice,
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

        return [
            'purchaseDate' => $purchaseDate,
            'targetBusinessDate' => $targetBusinessDate,
            'search' => $search,
            'categoryId' => $categoryId,
            'categoryIds' => $categoryIds,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'matrixDates' => $matrixDates,
            'matrixProducts' => $matrixProducts,
            'paginatedProducts' => $paginatedProducts,
            'matrixCategory' => $matrixCategory,
            'qualityGrade' => $qualityGrade,
            'weekStartDate' => $matrixStartDate->toDateString(),
            'weekEndDate' => $matrixEndDate->toDateString(),
            'previousWeekStartDate' => $selectedDate->copy()->subDays(3)->toDateString(),
            'nextWeekStartDate' => $selectedDate->copy()->addDays(3)->toDateString(),
            'matrixStartDate' => $matrixStartDate->toDateString(),
            'matrixEndDate' => $matrixEndDate->toDateString(),
            'previousRangeDate' => $selectedDate->copy()->subDays(3)->toDateString(),
            'nextRangeDate' => $selectedDate->copy()->addDays(3)->toDateString(),
            'previousDate' => $previousDate,
            'isPublished' => DailyPricePublication::isPublishedForDate($targetBusinessDate),
        ];
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
            'product_grade' => ['nullable', 'string', 'in:a,b,c,A,B,C'],
            'grade' => ['nullable', 'string', 'in:a,b,c,A,B,C'],
        ]);

        $user = $request->user();
        $userId = (int) $user->id;

        $product = Product::query()->findOrFail((int) $validated['product_id']);
        $dateStr = Carbon::parse($validated['date'])->toDateString();
        $cat = strtolower($validated['price_category']);
        $submittedUnit = ProductUnit::normalizeUnit((string) ($validated['price_unit'] ?? ''));
        $qualityGrade = strtoupper((string) ($validated['product_grade'] ?? $validated['grade'] ?? 'A'));
        $gradeEnum = ProductGrade::tryResolve($qualityGrade) ?? ProductGrade::GradeA;
        $gradeStr = $gradeEnum->value;
        $newPrice = isset($validated['price']) && $validated['price'] !== '' && $validated['price'] !== null
            ? round((float) $validated['price'], 2)
            : null;

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->where('grade', $gradeStr)
            ->whereDate('business_date', $dateStr)
            ->first();

        $previousApproval = $this->previousApprovedApprovalFor($product->id, $dateStr, $gradeEnum);

        if (! $approval) {
            $defaultPurchase = $gradeStr === 'A'
                ? (float) ($previousApproval?->purchase_price ?? ($product->vendor_price > 0 ? $product->vendor_price : $product->base_price))
                : (float) ($previousApproval?->purchase_price ?? 0);

            $approval = new DailyPriceApproval([
                'product_id' => $product->id,
                'business_date' => $dateStr,
                'grade' => $gradeStr,
                'purchase_price' => $defaultPurchase,
                'price_unit' => $submittedUnit !== ''
                    ? $submittedUnit
                    : ProductUnit::normalizeUnit((string) ($this->detectPrimaryOrderedUnit((int) $product->id, $dateStr) ?? $product->unit ?: 'kg')),
                'price_a' => $newPrice ?? (float) ($previousApproval?->price_a ?? ($gradeStr === 'A' ? $product->base_price : 0)),
                'price_b' => $newPrice ?? (float) ($previousApproval?->price_b ?? ($gradeStr === 'A' ? $product->base_price : 0)),
                'price_c' => $newPrice ?? (float) ($previousApproval?->price_c ?? ($gradeStr === 'A' ? $product->base_price : 0)),
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
                'message' => "{$product->name} (Grade {$gradeStr}) price for {$dateStr} saved.",
                'product_id' => $product->id,
                'date' => $dateStr,
                'price_category' => $cat,
                'product_grade' => $gradeStr,
                'price_a' => $approval->price_a,
                'price_b' => $approval->price_b,
                'price_c' => $approval->price_c,
                'price_unit' => $approval->price_unit,
            ]);
        }

        return redirect()->back()->with('success', "Price for {$product->name} (Grade {$gradeStr}) on {$dateStr} saved.");
    }

    public function fillForward(Request $request): RedirectResponse
    {
        $this->authorizeBoardAccess();
        $this->authorizePurchaserUpdate();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable'],
            'category_ids' => ['nullable'],
            'week_start' => ['required', 'date'],
            'matrix_category' => ['required', 'string', 'in:a,b,c,A,B,C'],
            'quality_grade' => ['nullable', 'string', 'in:a,b,c,A,B,C'],
            'all_product_ids' => ['required', 'array', 'min:1'],
            'all_product_ids.*' => ['integer', 'exists:products,id'],
            'all_dates' => ['required', 'array', 'min:1'],
            'all_dates.*' => ['date'],
        ]);

        $userId = (int) $request->user()->id;
        $matrixCategory = strtolower((string) $validated['matrix_category']);
        $qualityGrade = strtoupper((string) ($validated['quality_grade'] ?? $request->input('quality_grade', 'A')));
        $gradeEnum = ProductGrade::tryResolve($qualityGrade) ?? ProductGrade::GradeA;
        $productIds = collect($validated['all_product_ids'])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $dateStrings = collect($validated['all_dates'])
            ->map(fn ($date): string => Carbon::parse((string) $date)->toDateString())
            ->filter(fn (string $date): bool => $date <= Carbon::parse((string) $validated['date'])->toDateString())
            ->unique()
            ->sort()
            ->values();

        if ($productIds->isEmpty() || $dateStrings->isEmpty()) {
            return redirect()
                ->route('purchasing.prices.matrix.index', $this->buildRedirectParams($request, $validated))
                ->with('warning', 'No visible products or dates were available to fill.');
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $firstDate = $dateStrings->first();
        $lastDate = $dateStrings->last();
        $existingApprovals = DailyPriceApproval::query()
            ->whereIn('product_id', $productIds)
            ->where('grade', $gradeEnum->value)
            ->whereBetween('business_date', [$firstDate, $lastDate])
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->keyBy(fn (DailyPriceApproval $approval): string => $approval->business_date->toDateString()));

        $filledRows = 0;
        $filledCells = 0;
        $publishedDates = [];

        DB::transaction(function () use (
            $productIds,
            $dateStrings,
            $products,
            $existingApprovals,
            $userId,
            $gradeEnum,
            &$filledRows,
            &$filledCells,
            &$publishedDates
        ): void {
            foreach ($productIds as $productId) {
                $product = $products->get((int) $productId);

                if (! $product instanceof Product) {
                    continue;
                }

                $carryApproval = $this->previousApprovedApprovalFor((int) $productId, (string) $dateStrings->first(), $gradeEnum);
                $productApprovals = $existingApprovals->get((int) $productId) ?? collect();

                foreach ($dateStrings as $dateStr) {
                    $approval = $productApprovals->get($dateStr);

                    if ($approval instanceof DailyPriceApproval && $this->hasUsableSellingPrice($approval)) {
                        $filledExistingCells = $this->fillMissingApprovalPrices($approval, $carryApproval, $product, $userId);
                        if ($filledExistingCells > 0) {
                            $filledRows++;
                            $filledCells += $filledExistingCells;
                            $publishedDates[$dateStr] = true;
                            $this->publishDailyPriceApproval($product, $approval, $userId);
                        }
                        $carryApproval = $approval;

                        continue;
                    }

                    if (! $carryApproval instanceof DailyPriceApproval || ! $this->hasUsableSellingPrice($carryApproval)) {
                        continue;
                    }

                    if (! $approval instanceof DailyPriceApproval) {
                        $approval = DailyPriceApproval::query()->firstOrNew([
                            'product_id' => (int) $productId,
                            'business_date' => $dateStr,
                            'grade' => $gradeEnum->value,
                        ]);
                    }

                    $before = [
                        'price_a' => (float) $approval->price_a,
                        'price_b' => (float) $approval->price_b,
                        'price_c' => (float) $approval->price_c,
                    ];

                    $approval->purchase_price = $this->carriedPurchasePriceFor($carryApproval, $product);
                    $approval->price_unit = ProductUnit::normalizeUnit((string) ($carryApproval?->price_unit ?: $product->unit ?: 'kg'));
                    $approval->price_a = $this->carriedSellingPriceFor($carryApproval, 'price_a');
                    $approval->price_b = $this->carriedSellingPriceFor($carryApproval, 'price_b');
                    $approval->price_c = $this->carriedSellingPriceFor($carryApproval, 'price_c');
                    $approval->status = 'approved';
                    $approval->approved_by = $userId;
                    $approval->approved_at = now();
                    $approval->updated_by = $userId;
                    $approval->save();

                    $filledRows++;
                    $filledCells += collect(['price_a', 'price_b', 'price_c'])
                        ->filter(fn (string $field): bool => (float) $before[$field] <= 0 && (float) $approval->{$field} > 0)
                        ->count();
                    $publishedDates[$dateStr] = true;
                    $carryApproval = $approval;

                    $this->publishDailyPriceApproval($product, $approval, $userId);
                }
            }
        });

        foreach (array_keys($publishedDates) as $dateStr) {
            $this->shopInvoiceService->generateForBusinessDate($dateStr, $userId);
            $this->shopInvoiceService->repriceAllForBusinessDate(
                $dateStr,
                $userId,
                "Purchaser filled missing matrix prices for {$dateStr}.",
            );
        }

        $message = $filledRows > 0
            ? "Filled {$filledRows} missing matrix row(s), {$filledCells} price value(s), from last approved prices."
            : 'No missing matrix prices needed filling.';

        return redirect()
            ->route('purchasing.prices.matrix.index', $this->buildRedirectParams($request, $validated))
            ->with($filledRows > 0 ? 'success' : 'warning', $message);
    }

    public function removeFuturePrices(Request $request): RedirectResponse
    {
        $this->authorizeBoardAccess();
        $this->authorizePurchaserUpdate();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'week_start' => ['required', 'date'],
            'matrix_category' => ['required', 'string', 'in:a,b,c,A,B,C'],
            'quality_grade' => ['nullable', 'string', 'in:a,b,c,A,B,C'],
            'all_product_ids' => ['required', 'array', 'min:1'],
            'all_product_ids.*' => ['integer', 'exists:products,id'],
            'all_dates' => ['required', 'array', 'min:1'],
            'all_dates.*' => ['date'],
        ]);

        $matrixCategory = strtolower((string) $validated['matrix_category']);
        $qualityGrade = strtoupper((string) ($validated['quality_grade'] ?? $request->input('quality_grade', 'A')));
        $gradeEnum = ProductGrade::tryResolve($qualityGrade) ?? ProductGrade::GradeA;
        $selectedDate = Carbon::parse((string) $validated['date'])->toDateString();
        $productIds = collect($validated['all_product_ids'])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $futureDates = collect($validated['all_dates'])
            ->map(fn ($date): string => Carbon::parse((string) $date)->toDateString())
            ->filter(fn (string $date): bool => $date > $selectedDate)
            ->unique()
            ->values();

        $deletedRows = 0;

        if ($productIds->isNotEmpty() && $futureDates->isNotEmpty()) {
            $deletedRows = DailyPriceApproval::query()
                ->whereIn('product_id', $productIds)
                ->where('grade', $gradeEnum->value)
                ->whereDate('business_date', '>', $selectedDate)
                ->whereDate('business_date', '<=', (string) $futureDates->max())
                ->delete();
        }

        $message = $deletedRows > 0
            ? "Removed {$deletedRows} future matrix price row(s) after {$selectedDate}."
            : 'No future matrix prices needed removing.';

        return redirect()
            ->route('purchasing.prices.matrix.index', $this->buildRedirectParams($request, $validated))
            ->with($deletedRows > 0 ? 'success' : 'warning', $message);
    }

    private function hasUsableSellingPrice(DailyPriceApproval $approval): bool
    {
        return (float) $approval->price_a > 0
            || (float) $approval->price_b > 0
            || (float) $approval->price_c > 0;
    }

    private function fillMissingApprovalPrices(DailyPriceApproval $approval, ?DailyPriceApproval $carryApproval, Product $product, int $userId): int
    {
        $filledCells = 0;
        $fields = ['price_a', 'price_b', 'price_c'];

        foreach ($fields as $field) {
            if ((float) $approval->{$field} > 0) {
                continue;
            }

            $approval->{$field} = $this->carriedSellingPriceFor($carryApproval, $field);
            $filledCells++;
        }

        if ((float) $approval->purchase_price <= 0) {
            $approval->purchase_price = $this->carriedPurchasePriceFor($carryApproval, $product);
        }

        if (! filled($approval->price_unit)) {
            $approval->price_unit = ProductUnit::normalizeUnit((string) ($carryApproval?->price_unit ?: $product->unit ?: 'kg'));
        }

        if ($filledCells > 0) {
            $approval->status = 'approved';
            $approval->approved_by = $approval->approved_by ?: $userId;
            $approval->approved_at = $approval->approved_at ?: now();
            $approval->updated_by = $userId;
            $approval->save();
        }

        return $filledCells;
    }

    private function carriedSellingPriceFor(?DailyPriceApproval $approval, string $field): float
    {
        $price = (float) ($approval?->{$field} ?? 0);

        return $price > 0 ? round($price, 2) : self::MISSING_PRICE_FALLBACK;
    }

    private function carriedPurchasePriceFor(?DailyPriceApproval $approval, Product $product): float
    {
        $carriedPrice = (float) ($approval?->purchase_price ?? 0);

        if ($carriedPrice > 0) {
            return round($carriedPrice, 4);
        }

        $productPrice = (float) ($product->vendor_price > 0 ? $product->vendor_price : $product->base_price);

        return $productPrice > 0 ? round($productPrice, 4) : self::MISSING_PRICE_FALLBACK;
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
            'quality_grade' => ['nullable', 'string', 'in:a,b,c,A,B,C'],
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
        $qualityGrade = strtoupper((string) ($validated['quality_grade'] ?? $request->input('quality_grade', 'A')));
        $gradeEnum = ProductGrade::tryResolve($qualityGrade) ?? ProductGrade::GradeA;
        $rawMatrixPrices = collect((array) ($validated['matrix_prices'] ?? []));
        $rawMatrixPriceUnits = collect((array) ($validated['matrix_price_units'] ?? []));

        if ($rawMatrixPrices->isEmpty() && $rawMatrixPriceUnits->isEmpty()) {
            return redirect()
                ->route('purchasing.prices.matrix.index', $this->buildRedirectParams($request, $validated))
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
            $gradeEnum,
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
                    $previousApproval = $this->previousApprovedApprovalFor((int) $product->id, $businessDate, $gradeEnum);

                    $approval = DailyPriceApproval::query()
                        ->firstOrNew([
                            'product_id' => $product->id,
                            'business_date' => $businessDate,
                            'grade' => $gradeEnum->value,
                        ]);

                    // Determine price_unit: use submitted unit, or auto-detect from orders, or fall back to product unit
                    $priceUnit = ! empty($submittedUnit)
                        ? $submittedUnit
                        : (ProductUnit::normalizeUnit((string) ($this->detectPrimaryOrderedUnit((int) $product->id, $businessDate) ?? $product->unit ?: 'kg')));

                    if (! $approval->exists) {
                        $defaultPurchase = $gradeEnum->value === 'A'
                            ? (float) ($previousApproval?->purchase_price ?? ($product->vendor_price > 0 ? $product->vendor_price : $product->base_price))
                            : (float) ($previousApproval?->purchase_price ?? 0);

                        $approval->purchase_price = $defaultPurchase;
                        $approval->price_unit = $priceUnit;
                        $approval->price_a = (float) ($previousApproval?->price_a ?? ($gradeEnum->value === 'A' ? $product->base_price : 0));
                        $approval->price_b = (float) ($previousApproval?->price_b ?? ($gradeEnum->value === 'A' ? $product->base_price : 0));
                        $approval->price_c = (float) ($previousApproval?->price_c ?? ($gradeEnum->value === 'A' ? $product->base_price : 0));
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
            ->route('purchasing.prices.matrix.index', $this->buildRedirectParams($request, $validated))
            ->with('success', $message);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildRedirectParams(Request $request, array $validated): array
    {
        $params = [
            'date' => $validated['date'] ?? null,
            'search' => $validated['search'] ?? null,
            'week_start' => $validated['week_start'] ?? null,
            'matrix_category' => strtolower((string) ($validated['matrix_category'] ?? 'a')),
            'quality_grade' => strtoupper((string) ($validated['quality_grade'] ?? $request->input('quality_grade', 'A'))),
        ];

        if ($request->has('category_ids')) {
            $params['category_ids'] = (array) $request->input('category_ids');
        } elseif ($request->filled('category_id')) {
            $params['category_id'] = $request->input('category_id');
        }

        return array_filter($params, fn ($val) => $val !== null && $val !== '' && $val !== []);
    }

    private function authorizeBoardAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin'), 403);
    }

    private function authorizePurchaserUpdate(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin'), 403);
    }

    private function previousApprovedApprovalFor(int $productId, string $businessDate, ProductGrade $grade = ProductGrade::GradeA): ?DailyPriceApproval
    {
        return DailyPriceApproval::query()
            ->where('product_id', $productId)
            ->where('grade', $grade->value)
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
        $units = ShopOrderItem::query()
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

    private function updateActivePricesForGroup(Product $product, ?ShopPriceGroup $group, float $price, string $gradeVal, int $userId): void
    {
        if (! $group) {
            return;
        }

        $calculatedPrice = round($price, 2);

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

    private function publishDailyPriceApproval(Product $product, DailyPriceApproval $approval, int $userId): void
    {
        $groupA = ShopPriceGroup::query()->where('name', 'A')->first();
        $groupB = ShopPriceGroup::query()->where('name', 'B')->first();
        $groupC = ShopPriceGroup::query()->where('name', 'C')->first();

        $gradeVal = $approval->grade instanceof ProductGrade ? $approval->grade->value : (string) ($approval->grade ?? 'A');

        $this->updateActivePricesForGroup($product, $groupA, (float) $approval->price_a, $gradeVal, $userId);
        $this->updateActivePricesForGroup($product, $groupB, (float) $approval->price_b, $gradeVal, $userId);
        $this->updateActivePricesForGroup($product, $groupC, (float) $approval->price_c, $gradeVal, $userId);

        if ($gradeVal === 'A') {
            $this->vendorPriceService->syncPrice($product->id, (float) $approval->purchase_price);
        }
    }

    private function exportScope(Request $request): string
    {
        $scope = strtolower((string) $request->input('scope', 'week'));

        return in_array($scope, ['today', 'week', 'today_changed'], true) ? $scope : 'week';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function matrixExportRows(array $data, string $scope): array
    {
        $matrixCategory = (string) $data['matrixCategory'];
        $targetDate = (string) $data['targetBusinessDate'];
        $dates = match ($scope) {
            'today', 'today_changed' => [$targetDate => $data['matrixDates'][$targetDate] ?? ['label' => Carbon::parse($targetDate)->format('d-M')]],
            default => (array) $data['matrixDates'],
        };

        $rows = [];

        foreach ((array) $data['matrixProducts'] as $product) {
            $prices = [];
            $hasChanged = false;

            foreach ($dates as $dateStr => $dateInfo) {
                $cell = $product['prices'][$dateStr] ?? [];
                $price = match ($matrixCategory) {
                    'a' => $cell['price_a'] ?? null,
                    'b' => $cell['price_b'] ?? null,
                    'c' => $cell['price_c'] ?? null,
                    default => null,
                };
                $changed = match ($matrixCategory) {
                    'a' => (bool) ($cell['changed_a'] ?? false),
                    'b' => (bool) ($cell['changed_b'] ?? false),
                    'c' => (bool) ($cell['changed_c'] ?? false),
                    default => false,
                };

                $prices[$dateStr] = [
                    'label' => $dateInfo['label'] ?? Carbon::parse($dateStr)->format('d-M'),
                    'price' => $price,
                    'unit' => ProductUnit::normalizeUnit((string) ($cell['unit'] ?? $product['unit'] ?? 'kg')),
                    'changed' => $changed,
                ];
                $hasChanged = $hasChanged || $changed;
            }

            if ($scope === 'today_changed' && ! $hasChanged) {
                continue;
            }

            $rows[] = [
                'sl_no' => $product['sl_no'],
                'sku' => $product['sku'] ?: '',
                'name' => $product['name'],
                'unit' => ProductUnit::normalizeUnit((string) ($product['unit'] ?? 'kg')),
                'prices' => $prices,
                'has_changed' => $hasChanged,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildMatrixShareText(array $data, string $scope): string
    {
        $rows = $this->matrixExportRows($data, $scope);
        $category = strtoupper((string) $data['matrixCategory']);
        $title = match ($scope) {
            'today' => 'Today Selling Price Matrix',
            'today_changed' => 'Today Changed Selling Prices',
            default => 'Weekly Selling Price Matrix',
        };

        $lines = [
            '*'.$title.'*',
            'Price '.$category,
            Carbon::parse((string) $data['targetBusinessDate'])->format('d M Y'),
        ];

        if ($scope === 'week') {
            $lines[] = Carbon::parse((string) $data['weekStartDate'])->format('d M').' - '.Carbon::parse((string) $data['weekEndDate'])->format('d M Y');
        }

        $lines[] = '---';

        foreach ($rows as $row) {
            $parts = [];

            foreach ($row['prices'] as $priceInfo) {
                $price = $priceInfo['price'] !== null ? number_format((float) $priceInfo['price'], 2, '.', '') : '-';
                $changedMark = $priceInfo['changed'] ? ' *changed*' : '';
                $parts[] = $priceInfo['label'].': '.$price.' '.strtoupper((string) $priceInfo['unit']).$changedMark;
            }

            $lines[] = trim(($row['sku'] !== '' ? $row['sku'].' - ' : '').$row['name']);
            $lines[] = implode(' | ', $parts);
            $lines[] = '';
        }

        if (empty($rows)) {
            $lines[] = 'No changed prices found.';
        }

        return trim(implode("\n", $lines));
    }
}
