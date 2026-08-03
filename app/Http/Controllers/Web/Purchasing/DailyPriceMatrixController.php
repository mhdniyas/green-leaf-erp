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
            ->with('category')
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
                    'unit' => $app?->price_unit ?? $product->unit,
                    'changed_a' => $changedA,
                    'changed_b' => $changedB,
                    'changed_c' => $changedC,
                    'status' => $app?->status ?? 'none',
                    'is_locked' => $app?->isLocked() ?? false,
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
                'base_price' => (float) $product->base_price,
                'prices' => $dailyPrices,
                'previous_day' => [
                    'date' => $previousDate,
                    'price_a' => $prevDayApp && $prevDayApp->price_a !== null ? (float) $prevDayApp->price_a : null,
                    'price_b' => $prevDayApp && $prevDayApp->price_b !== null ? (float) $prevDayApp->price_b : null,
                    'price_c' => $prevDayApp && $prevDayApp->price_c !== null ? (float) $prevDayApp->price_c : null,
                    'unit' => $prevDayApp?->price_unit ?? $product->unit,
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

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'date' => ['required', 'date'],
            'price_category' => ['required', 'string', 'in:a,b,c,A,B,C'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $isAdmin = (bool) $user?->hasRole('admin');
        $userId = (int) $user->id;

        $product = Product::query()->findOrFail((int) $validated['product_id']);
        $dateStr = Carbon::parse($validated['date'])->toDateString();
        $cat = strtolower($validated['price_category']);
        $newPrice = isset($validated['price']) && $validated['price'] !== '' && $validated['price'] !== null
            ? round((float) $validated['price'], 2)
            : null;

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', $dateStr)
            ->first();

        // Check if the price is already locked
        if ($approval && $approval->isLocked()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Price for {$product->name} on {$dateStr} is locked and cannot be modified.",
                ], 422);
            }
            return redirect()->back()->with('warning', "Price for {$product->name} on {$dateStr} is locked and cannot be modified.");
        }

        if (! $approval) {
            $approval = new DailyPriceApproval([
                'product_id' => $product->id,
                'business_date' => $dateStr,
                'purchase_price' => (float) $product->base_price,
                'price_unit' => $product->unit ?: 'kg',
                'price_a' => $newPrice ?? (float) $product->base_price,
                'price_b' => $newPrice ?? (float) $product->base_price,
                'price_c' => $newPrice ?? (float) $product->base_price,
                'status' => $isAdmin ? 'approved' : 'pending',
            ]);
        }

        if ($cat === 'a') {
            $approval->price_a = $newPrice;
            if ($approval->price_b === null || (float) $approval->price_b <= 0) {
                $approval->price_b = $newPrice;
            }
            if ($approval->price_c === null || (float) $approval->price_c <= 0) {
                $approval->price_c = $newPrice;
            }
        } elseif ($cat === 'b') {
            $approval->price_b = $newPrice;
        } elseif ($cat === 'c') {
            $approval->price_c = $newPrice;
        }

        if ($isAdmin) {
            $approval->status = 'approved';
            $approval->approved_by = $userId;
            $approval->approved_at = now();
            // Auto-lock the price when approved by admin
            $approval->locked_at = now();
            $approval->locked_by = $userId;
        } else {
            $approval->status = 'pending';
        }

        $approval->save();

        if ($isAdmin) {
            $groupA = ShopPriceGroup::query()->where('name', 'A')->first();
            $groupB = ShopPriceGroup::query()->where('name', 'B')->first();
            $groupC = ShopPriceGroup::query()->where('name', 'C')->first();

            $this->updateActivePricesForGroup($product, $groupA, (float) $approval->price_a, $userId);
            $this->updateActivePricesForGroup($product, $groupB, (float) $approval->price_b, $userId);
            $this->updateActivePricesForGroup($product, $groupC, (float) $approval->price_c, $userId);
            $this->vendorPriceService->syncPrice($product->id, (float) $approval->purchase_price);

            $this->shopInvoiceService->generateForBusinessDate($dateStr, $userId);
            $this->shopInvoiceService->repriceAllForBusinessDate(
                $dateStr,
                $userId,
                "Admin updated matrix cell price for {$product->name} on {$dateStr}."
            );
        }

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
            ]);
        }

        return redirect()->back()->with('success', "Price for {$product->name} on {$dateStr} saved.");
    }

    public function updateMatrix(Request $request): RedirectResponse
    {
        $this->authorizeBoardAccess();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'week_start' => ['nullable', 'date'],
            'matrix_category' => ['required', 'string', 'in:a,b,c,A,B,C'],
            'action' => ['required', 'string', 'in:update,approve_publish'],
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
        $isAdmin = (bool) $user?->hasRole('admin');
        $shouldPublish = $validated['action'] === 'approve_publish';
        if ($shouldPublish) {
            abort_unless($isAdmin, 403, 'Only admin can approve and publish matrix prices.');
        }

        $userId = (int) $user->id;
        $matrixCategory = strtolower((string) $validated['matrix_category']);
        $rawMatrixPrices = collect((array) ($validated['matrix_prices'] ?? []));
        $rawMatrixPriceUnits = collect((array) ($validated['matrix_price_units'] ?? []));
        
        // For approve/publish, process ALL visible products, not just submitted ones
        $allProductIds = collect((array) ($validated['all_product_ids'] ?? []))->filter()->unique();
        $allDates = collect((array) ($validated['all_dates'] ?? []))->filter()->unique();

        if ($rawMatrixPrices->isEmpty() && !$shouldPublish) {
            return redirect()
                ->route('purchasing.prices.matrix.index', [
                    'date' => $validated['date'],
                    'search' => $validated['search'] ?? null,
                    'category_id' => $validated['category_id'] ?? null,
                    'week_start' => $validated['week_start'] ?? null,
                    'matrix_category' => $matrixCategory,
                ])
                ->with('warning', 'No matrix prices were submitted.');
        }

        // When publishing, use all visible products; otherwise use only submitted ones
        $productIds = $shouldPublish && $allProductIds->isNotEmpty()
            ? $allProductIds
            : $rawMatrixPrices
                ->keys()
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $groupA = $shouldPublish ? ShopPriceGroup::query()->where('name', 'A')->first() : null;
        $groupB = $shouldPublish ? ShopPriceGroup::query()->where('name', 'B')->first() : null;
        $groupC = $shouldPublish ? ShopPriceGroup::query()->where('name', 'C')->first() : null;

        $publishedDates = [];
        $updatedCells = 0;

        DB::transaction(function () use (
            $rawMatrixPrices,
            $rawMatrixPriceUnits,
            $products,
            $matrixCategory,
            $shouldPublish,
            $userId,
            $groupA,
            $groupB,
            $groupC,
            $allDates,
            &$publishedDates,
            &$updatedCells
        ): void {
            // When publishing, process ALL visible product-date combinations
            if ($shouldPublish && $allDates->isNotEmpty()) {
                foreach ($products as $product) {
                    foreach ($allDates as $dateStr) {
                        $businessDate = Carbon::parse((string) $dateStr)->toDateString();
                        
                        // Get existing approval or skip if none exists
                        $approval = DailyPriceApproval::query()
                            ->where('product_id', $product->id)
                            ->where('business_date', $businessDate)
                            ->first();
                        
                        // Skip if locked
                        if ($approval && $approval->isLocked()) {
                            continue;
                        }
                        
                        // Skip if no approval exists (can't publish nothing)
                        if (!$approval) {
                            continue;
                        }
                        
                        // Check if there's a new price submitted for this product-date
                        $submittedPrice = $rawMatrixPrices->get((int) $product->id)?->{$dateStr} ?? null;
                        
                        if ($submittedPrice !== null && $submittedPrice !== '') {
                            $newPrice = round((float) $submittedPrice, 2);
                            
                            if ($matrixCategory === 'a') {
                                $approval->price_a = $newPrice;
                                if ($approval->price_b === null || (float) $approval->price_b <= 0) {
                                    $approval->price_b = $newPrice;
                                }
                                if ($approval->price_c === null || (float) $approval->price_c <= 0) {
                                    $approval->price_c = $newPrice;
                                }
                            } elseif ($matrixCategory === 'b') {
                                $approval->price_b = $newPrice;
                            } else {
                                $approval->price_c = $newPrice;
                            }
                        }
                        
                        // Approve and lock the existing approval
                        $approval->status = 'approved';
                        $approval->approved_by = $userId;
                        $approval->approved_at = now();
                        $approval->locked_at = now();
                        $approval->locked_by = $userId;
                        $approval->save();
                        
                        $updatedCells++;
                        $publishedDates[$businessDate] = true;
                        
                        // Publish to active prices
                        $this->updateActivePricesForGroup($product, $groupA, (float) $approval->price_a, $userId);
                        $this->updateActivePricesForGroup($product, $groupB, (float) $approval->price_b, $userId);
                        $this->updateActivePricesForGroup($product, $groupC, (float) $approval->price_c, $userId);
                        $this->vendorPriceService->syncPrice($product->id, (float) $approval->purchase_price);
                    }
                }
            } else {
                // Regular update flow: only process submitted prices
                foreach ($rawMatrixPrices as $productId => $datePrices) {
                $product = $products->get((int) $productId);

                if (! $product || ! is_array($datePrices)) {
                    continue;
                }

                foreach ($datePrices as $dateStr => $priceValue) {
                    if ($priceValue === null || $priceValue === '') {
                        continue;
                    }

                    $businessDate = Carbon::parse((string) $dateStr)->toDateString();
                    $newPrice = round((float) $priceValue, 2);

                    $approval = DailyPriceApproval::query()
                        ->firstOrNew([
                            'product_id' => $product->id,
                            'business_date' => $businessDate,
                        ]);

                    // Check if the price is already locked - if so, skip this update
                    if ($approval->exists && $approval->isLocked()) {
                        continue;
                    }

                    // Determine price_unit: use submitted unit, or auto-detect from orders, or fall back to product unit
                    $submittedUnit = (string) ($rawMatrixPriceUnits->get((int) $productId)?->{$dateStr} ?? '');
                    $priceUnit = ! empty($submittedUnit)
                        ? $submittedUnit
                        : ($this->detectPrimaryOrderedUnit((int) $product->id, $businessDate) ?? $product->unit ?: 'kg');

                    if (! $approval->exists) {
                        $approval->purchase_price = (float) $product->base_price;
                        $approval->price_unit = $priceUnit;
                        $approval->price_a = (float) $product->base_price;
                        $approval->price_b = (float) $product->base_price;
                        $approval->price_c = (float) $product->base_price;
                    } else {
                        // Update price_unit on existing approval if submitted or auto-detected differs
                        if (! empty($submittedUnit) || $this->detectPrimaryOrderedUnit((int) $product->id, $businessDate)) {
                            $approval->price_unit = $priceUnit;
                        }
                    }

                    if ($matrixCategory === 'a') {
                        $approval->price_a = $newPrice;
                        if ($approval->price_b === null || (float) $approval->price_b <= 0) {
                            $approval->price_b = $newPrice;
                        }
                        if ($approval->price_c === null || (float) $approval->price_c <= 0) {
                            $approval->price_c = $newPrice;
                        }
                    } elseif ($matrixCategory === 'b') {
                        $approval->price_b = $newPrice;
                    } else {
                        $approval->price_c = $newPrice;
                    }

                    $approval->status = $shouldPublish ? 'approved' : 'pending';
                    $approval->approved_by = $shouldPublish ? $userId : null;
                    $approval->approved_at = $shouldPublish ? now() : null;
                    // Auto-lock when publishing
                    if ($shouldPublish) {
                        $approval->locked_at = now();
                        $approval->locked_by = $userId;
                    }
                    $approval->save();

                    $updatedCells++;

                    if (! $shouldPublish) {
                        continue;
                    }

                    $publishedDates[$businessDate] = true;
                    $this->updateActivePricesForGroup($product, $groupA, (float) $approval->price_a, $userId);
                    $this->updateActivePricesForGroup($product, $groupB, (float) $approval->price_b, $userId);
                    $this->updateActivePricesForGroup($product, $groupC, (float) $approval->price_c, $userId);
                    $this->vendorPriceService->syncPrice($product->id, (float) $approval->purchase_price);
                }
            }
            } // End of else block for regular update flow
        });

        if ($shouldPublish) {
            foreach (array_keys($publishedDates) as $dateStr) {
                $this->shopInvoiceService->generateForBusinessDate($dateStr, $userId);
                $this->shopInvoiceService->repriceAllForBusinessDate(
                    $dateStr,
                    $userId,
                    "Admin published matrix prices for {$dateStr}.",
                );
            }
        }

        $message = $shouldPublish
            ? "Updated {$updatedCells} matrix price cell(s) and published them."
            : "Updated {$updatedCells} matrix price cell(s) and sent for admin approval.";

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

    public function importFromJson(Request $request): mixed
    {
        $this->authorizeBoardAccess();
        
        // Only admins can import prices
        abort_unless(auth()->user()?->hasRole('admin'), 403, 'Only admins can import prices.');

        $validated = $request->validate([
            'json_file' => ['required', 'file', 'mimes:json', 'max:10240'], // Max 10MB
            'unlock_locked' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $userId = (int) $user->id;
        $unlockLocked = (bool) ($validated['unlock_locked'] ?? false);

        // Read uploaded JSON file
        $uploadedFile = $request->file('json_file');
        
        if (!$uploadedFile || !$uploadedFile->isValid()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file upload.',
                ], 400);
            }
            return redirect()->back()->with('error', 'Invalid file upload.');
        }

        $jsonContent = file_get_contents($uploadedFile->getRealPath());
        $data = json_decode($jsonContent, true);

        if (!$data || !isset($data['prices'])) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON format. Expected "prices" array.',
                ], 400);
            }
            return redirect()->back()->with('error', 'Invalid JSON format.');
        }

        $updated = 0;
        $skipped = 0;
        $errors = [];
        $unlocked = 0;

        DB::transaction(function () use ($data, $userId, $unlockLocked, &$updated, &$skipped, &$errors, &$unlocked): void {
            foreach ($data['prices'] as $priceData) {
                $productCode = $priceData['product_code'] ?? null;
                $productName = $priceData['product_name'] ?? null;
                $dates = $priceData['dates'] ?? [];

                if ((!$productCode && !$productName) || empty($dates)) {
                    continue;
                }

                // Find product by BOTH code (SKU) AND name for safety
                $query = Product::query();
                
                if ($productCode && $productName) {
                    // Match both SKU and name - safest option
                    $query->where('sku', $productCode)->where('name', $productName);
                } elseif ($productCode) {
                    // Match by SKU only
                    $query->where('sku', $productCode);
                } else {
                    // Match by name only (least safe)
                    $query->where('name', $productName);
                }

                $product = $query->first();

                if (!$product) {
                    $identifier = $productCode ? "Code: {$productCode}, Name: {$productName}" : "Name: {$productName}";
                    $errors[] = "Product not found or mismatch: {$identifier}";
                    continue;
                }

                // Double-check: if both provided, verify they match
                if ($productCode && $productName) {
                    if ($product->sku !== $productCode || $product->name !== $productName) {
                        $errors[] = "Product mismatch: Expected SKU={$productCode} Name={$productName}, Found SKU={$product->sku} Name={$product->name}";
                        continue;
                    }
                }

                foreach ($dates as $dateStr => $price) {
                    $businessDate = Carbon::parse($dateStr)->toDateString();

                    // Find or create approval
                    $approval = DailyPriceApproval::query()
                        ->where('product_id', $product->id)
                        ->whereDate('business_date', $businessDate)
                        ->first();

                    if (!$approval) {
                        // Create new approval
                        $approval = new DailyPriceApproval([
                            'product_id' => $product->id,
                            'business_date' => $businessDate,
                            'price_a' => $price,
                            'price_b' => $price,
                            'price_c' => $price,
                            'status' => 'approved',
                            'approved_by' => $userId,
                            'approved_at' => now(),
                        ]);
                        $approval->save();
                        $updated++;
                    } else {
                        // Check if locked
                        if ($approval->isLocked()) {
                            if ($unlockLocked) {
                                // Unlock it
                                $approval->locked_at = null;
                                $approval->locked_by = null;
                                $unlocked++;
                            } else {
                                // Skip locked prices
                                $skipped++;
                                continue;
                            }
                        }

                        // Update existing approval
                        $approval->price_a = $price;
                        $approval->price_b = $price;
                        $approval->price_c = $price;
                        $approval->status = 'approved';
                        $approval->approved_by = $userId;
                        $approval->approved_at = now();
                        $approval->save();
                        $updated++;
                    }
                }
            }
        });

        $message = "Import completed: {$updated} prices updated";
        if ($skipped > 0) {
            $message .= ", {$skipped} locked prices skipped";
        }
        if ($unlocked > 0) {
            $message .= ", {$unlocked} prices unlocked";
        }
        if (!empty($errors)) {
            $message .= ". Errors: " . implode(', ', array_slice($errors, 0, 5));
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'updated' => $updated,
                'skipped' => $skipped,
                'unlocked' => $unlocked,
                'errors' => $errors,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function exportCsv(Request $request)
    {
        $this->authorizeBoardAccess();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'week_start' => ['nullable', 'date'],
            'matrix_category' => ['required', 'string', 'in:a,b,c,A,B,C'],
        ]);

        $selectedDate = Carbon::parse((string) $validated['date']);
        $search = (string) ($validated['search'] ?? '');
        $categoryId = (int) ($validated['category_id'] ?? 0) ?: null;
        $matrixCategory = strtolower((string) $validated['matrix_category']);

        $weekStart = $request->filled('week_start')
            ? Carbon::parse((string) $request->input('week_start'))->startOfWeek(Carbon::MONDAY)
            : $selectedDate->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        // Build date array for the week
        $dates = [];
        $currentDate = $weekStart->copy();
        while ($currentDate->lte($weekEnd)) {
            $dates[] = $currentDate->toDateString();
            $currentDate->addDay();
        }

        // Get products (same query as index method)
        $user = $request->user();
        $productQuery = Product::query()
            ->active()
            ->with('category')
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

        $filename = 'daily-prices-' . $weekStart->format('Y-m-d') . '-to-' . $weekEnd->format('Y-m-d') . '-category-' . strtoupper($matrixCategory) . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($products, $dates, $matrixCategory): void {
            $handle = fopen('php://output', 'w');

            // Header row
            $headerRow = ['Product Name', 'Product Code', 'Unit'];
            foreach ($dates as $dateStr) {
                $headerRow[] = \Illuminate\Support\Carbon::parse($dateStr)->format('d-M-Y');
            }
            fputcsv($handle, $headerRow);

            // Get approvals
            $productIds = $products->pluck('id');
            $approvals = \App\Models\DailyPriceApproval::query()
                ->whereIn('product_id', $productIds)
                ->whereIn('business_date', $dates)
                ->get()
                ->groupBy('product_id')
                ->map(fn ($items) => $items->keyBy(fn ($app) => $app->business_date->toDateString()));

            // Data rows
            foreach ($products as $product) {
                $productApprovals = $approvals->get($product->id) ?? collect();
                $row = [
                    $product->name,
                    $product->sku ?: '',
                    strtoupper($product->unit ?: 'KG'),
                ];
                foreach ($dates as $dateStr) {
                    $approval = $productApprovals->get($dateStr);
                    $price = match ($matrixCategory) {
                        'a' => $approval?->price_a,
                        'b' => $approval?->price_b,
                        default => $approval?->price_c,
                    };
                    $row[] = $price !== null ? number_format((float) $price, 2, '.', '') : '';
                }
                fputcsv($handle, $row);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function authorizeBoardAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin'), 403);
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
            ->countBy()
            ->sort()
            ->keys();

        return $units->first();
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
                'override_reason' => 'Admin updated matrix price cell',
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
                    'reason' => 'Admin updated matrix price cell',
                    'changed_by' => $userId,
                    'changed_at' => now(),
                ]);
            }
        }
    }
}
