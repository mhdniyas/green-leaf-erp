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
                ];
            }

            $matrixProducts[] = [
                'sl_no' => $slNo++,
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'base_price' => (float) $product->base_price,
                'prices' => $dailyPrices,
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

        if ($rawMatrixPrices->isEmpty()) {
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

        $productIds = $rawMatrixPrices
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
            $products,
            $matrixCategory,
            $shouldPublish,
            $userId,
            $groupA,
            $groupB,
            $groupC,
            &$publishedDates,
            &$updatedCells
        ): void {
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

                    if (! $approval->exists) {
                        $approval->purchase_price = (float) $product->base_price;
                        $approval->price_unit = $product->unit ?: 'kg';
                        $approval->price_a = (float) $product->base_price;
                        $approval->price_b = (float) $product->base_price;
                        $approval->price_c = (float) $product->base_price;
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

    private function authorizeBoardAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin'), 403);
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
