<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyPricePublication;
use App\Models\DailyProductPrice;
use App\Models\DailyProductPriceRevision;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ShopPriceGroup;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\VendorPriceService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DailyPriceBoardController extends Controller
{
    public function __construct(
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly VendorPriceService $vendorPriceService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeBoardAccess();

        $purchaseDate = $request->input('date', $this->businessDayService->operationalDate()->toDateString());
        $targetBusinessDate = Carbon::parse($purchaseDate)->toDateString();
        $search = trim((string) $request->input('search', ''));
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $perPage = max(10, min(100, (int) $request->input('per_page', 20)));

        $productQuery = Product::query()
            ->active()
            ->with('category')
            ->ordered();

        if ($categoryId) {
            $productQuery->where('category_id', $categoryId);
        }

        if ($search !== '') {
            $productQuery->where(function ($query) use ($search): void {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $products = $productQuery->paginate($perPage)->withQueryString();
        $pageProductIds = $products->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $currentApprovals = DailyPriceApproval::query()
            ->whereDate('business_date', $targetBusinessDate)
            ->whereIn('product_id', $pageProductIds)
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $rows): DailyPriceApproval => $rows->first());

        $previousApprovals = DailyPriceApproval::query()
            ->with('product.category')
            ->whereDate('business_date', '<', $targetBusinessDate)
            ->whereIn('product_id', $pageProductIds)
            ->where('status', 'approved')
            ->orderByDesc('business_date')
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $rows): DailyPriceApproval => $rows->first());

        $products->setCollection(
            $products->getCollection()->map(function (Product $product) use ($currentApprovals, $previousApprovals): array {
                $currentApproval = $currentApprovals->get($product->id);
                $previousApproval = $previousApprovals->get($product->id);

                $purchasePrice = (float) ($currentApproval?->purchase_price
                    ?? $previousApproval?->purchase_price
                    ?? ($product->vendor_price > 0 ? $product->vendor_price : $product->base_price));

                $previousPurchasePrice = $previousApproval?->purchase_price !== null
                    ? (float) $previousApproval->purchase_price
                    : null;

                $sellingPriceA = (float) ($currentApproval?->price_a ?? $previousApproval?->price_a ?? 0);
                $sellingPriceB = (float) ($currentApproval?->price_b ?? $previousApproval?->price_b ?? 0);
                $sellingPriceC = (float) ($currentApproval?->price_c ?? $previousApproval?->price_c ?? 0);

                $diff = $previousPurchasePrice !== null ? round($purchasePrice - $previousPurchasePrice, 4) : null;

                return [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit ?: 'kg',
                    'category_name' => $product->category?->name ?? 'Uncategorized',
                    'purchase_price' => $purchasePrice,
                    'previous_purchase_price' => $previousPurchasePrice,
                    'purchase_diff' => $diff,
                    'selling_price_a' => $sellingPriceA,
                    'selling_price_b' => $sellingPriceB,
                    'selling_price_c' => $sellingPriceC,
                    'approval_status' => $currentApproval?->status ?? $previousApproval?->status ?? 'missing',
                    'approved_at' => $currentApproval?->approved_at ?? $previousApproval?->approved_at,
                    'price_unit' => $currentApproval?->price_unit ?? $previousApproval?->price_unit ?? $product->unit,
                    'is_updated_today' => $currentApproval !== null,
                    'source_label' => $currentApproval ? 'Today' : ($previousApproval ? 'Carried forward' : 'Vendor fallback'),
                ];
            })->values()
        );

        $isPublished = DailyPricePublication::isPublishedForDate($targetBusinessDate);

        return view('purchase-manager.prices.index', [
            'products' => $products,
            'search' => $search,
            'categoryId' => $categoryId,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'purchaseDate' => $purchaseDate,
            'targetBusinessDate' => $targetBusinessDate,
            'previousDate' => Carbon::parse($purchaseDate)->subDay()->toDateString(),
            'perPage' => $perPage,
            'isPublished' => $isPublished,
        ]);
    }

    /**
     * Batch or single update daily prices (purchase price, Selling Price A, B, C).
     * Includes user tracking (updated_by & approved_by) and auto-fallbacks for B and C.
     */
    public function updatePrices(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeBoardAccess();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'products' => ['nullable', 'array'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.price_a' => ['nullable', 'numeric', 'min:0'],
            'products.*.price_b' => ['nullable', 'numeric', 'min:0'],
            'products.*.price_c' => ['nullable', 'numeric', 'min:0'],

            // Support single item submit as fallback
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'price_a' => ['nullable', 'numeric', 'min:0'],
            'price_b' => ['nullable', 'numeric', 'min:0'],
            'price_c' => ['nullable', 'numeric', 'min:0'],
        ]);

        $dateStr = Carbon::parse($validated['date'])->toDateString();
        $userId = (int) $request->user()->id;

        $itemsToUpdate = [];
        if (! empty($validated['products'])) {
            $itemsToUpdate = $validated['products'];
        } elseif (! empty($validated['product_id'])) {
            $itemsToUpdate[] = [
                'product_id' => $validated['product_id'],
                'purchase_price' => $validated['purchase_price'] ?? null,
                'price_a' => $validated['price_a'] ?? null,
                'price_b' => $validated['price_b'] ?? null,
                'price_c' => $validated['price_c'] ?? null,
            ];
        }

        if (empty($itemsToUpdate)) {
            return redirect()->back()->with('warning', 'No prices submitted.');
        }

        $updatedCount = 0;

        DB::transaction(function () use ($itemsToUpdate, $dateStr, $userId, &$updatedCount): void {
            $groupA = ShopPriceGroup::query()->where('name', 'A')->first();
            $groupB = ShopPriceGroup::query()->where('name', 'B')->first();
            $groupC = ShopPriceGroup::query()->where('name', 'C')->first();

            foreach ($itemsToUpdate as $item) {
                $productId = (int) $item['product_id'];
                $product = Product::find($productId);
                if (! $product) {
                    continue;
                }

                $hasA = isset($item['price_a']) && $item['price_a'] !== '' && $item['price_a'] !== null;
                $hasB = isset($item['price_b']) && $item['price_b'] !== '' && $item['price_b'] !== null;
                $hasC = isset($item['price_c']) && $item['price_c'] !== '' && $item['price_c'] !== null;
                $hasPurchase = isset($item['purchase_price']) && $item['purchase_price'] !== '' && $item['purchase_price'] !== null;

                if (! $hasA && ! $hasB && ! $hasC && ! $hasPurchase) {
                    continue;
                }

                $priceA = $hasA ? round((float) $item['price_a'], 2) : null;
                $priceB = $hasB ? round((float) $item['price_b'], 2) : null;
                $priceC = $hasC ? round((float) $item['price_c'], 2) : null;
                $purchasePrice = $hasPurchase ? round((float) $item['purchase_price'], 4) : null;

                $approval = DailyPriceApproval::query()
                    ->where('product_id', $productId)
                    ->whereDate('business_date', $dateStr)
                    ->first();

                $previousApproval = DailyPriceApproval::query()
                    ->where('product_id', $productId)
                    ->whereDate('business_date', '<', $dateStr)
                    ->where('status', 'approved')
                    ->orderByDesc('business_date')
                    ->first();

                if (! $approval) {
                    $effectiveA = $priceA ?? (float) ($previousApproval?->price_a ?? $product->base_price);
                    $approval = new DailyPriceApproval([
                        'product_id' => $productId,
                        'business_date' => $dateStr,
                        'purchase_price' => $purchasePrice ?? (float) ($previousApproval?->purchase_price ?? ($product->vendor_price > 0 ? $product->vendor_price : $product->base_price)),
                        'price_unit' => ProductUnit::normalizeUnit((string) ($product->unit ?: 'kg')),
                        'price_a' => $effectiveA,
                        'price_b' => $priceB ?? ($priceA ?? (float) ($previousApproval?->price_b ?? $effectiveA)),
                        'price_c' => $priceC ?? ($priceA ?? (float) ($previousApproval?->price_c ?? $effectiveA)),
                        'status' => 'approved',
                    ]);
                } else {
                    if ($purchasePrice !== null) {
                        $approval->purchase_price = $purchasePrice;
                    }

                    if ($priceA !== null) {
                        $approval->price_a = $priceA;
                    }

                    // Handle B: if explicitly provided, use it. If A was provided and B is missing/invalid, default to A.
                    if ($priceB !== null) {
                        $approval->price_b = $priceB;
                    } elseif ($priceA !== null && ($approval->price_b === null || (float) $approval->price_b <= 0)) {
                        $approval->price_b = $priceA;
                    }

                    // Handle C: if explicitly provided, use it. If A was provided and C is missing/invalid, default to A.
                    if ($priceC !== null) {
                        $approval->price_c = $priceC;
                    } elseif ($priceA !== null && ($approval->price_c === null || (float) $approval->price_c <= 0)) {
                        $approval->price_c = $priceA;
                    }
                }

                $approval->status = 'approved';
                $approval->approved_by = $userId;
                $approval->approved_at = now();
                $approval->updated_by = $userId;
                $approval->save();

                // Publish/Sync to group active prices
                if ($groupA) {
                    $this->updateActivePricesForGroup($product, $groupA, (float) $approval->price_a, $userId);
                }
                if ($groupB) {
                    $this->updateActivePricesForGroup($product, $groupB, (float) $approval->price_b, $userId);
                }
                if ($groupC) {
                    $this->updateActivePricesForGroup($product, $groupC, (float) $approval->price_c, $userId);
                }

                if ((float) $approval->purchase_price > 0) {
                    $this->vendorPriceService->syncPrice($product->id, (float) $approval->purchase_price);
                }

                $updatedCount++;
            }
        });

        if ($updatedCount > 0) {
            try {
                $this->shopInvoiceService->generateForBusinessDate($dateStr, $userId);
                $this->shopInvoiceService->repriceAllForBusinessDate(
                    $dateStr,
                    $userId,
                    "Updated {$updatedCount} daily prices for business date {$dateStr}."
                );
            } catch (\Throwable $e) {
                Log::warning("Reprice warning after price update on {$dateStr}: ".$e->getMessage());
            }
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} daily price(s) for {$dateStr}.",
            ]);
        }

        return redirect()->back()->with('success', "Successfully updated {$updatedCount} daily price(s) for {$dateStr}.");
    }

    public function togglePublish(Request $request)
    {
        $this->authorizeBoardAccess();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'is_published' => ['required', 'boolean'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $isPublished = (bool) $validated['is_published'];

        $publication = DailyPricePublication::setPublishStatus($date, $isPublished, $request->user());

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_published' => $publication->is_published,
                'message' => $isPublished
                    ? "Daily prices for {$date} published successfully."
                    : "Daily prices for {$date} unpublished (set to draft).",
            ]);
        }

        $formattedDate = Carbon::parse($date)->format('d M Y');
        $message = $isPublished
            ? "Daily prices for {$formattedDate} are now published and visible to shop incharges."
            : "Daily prices for {$formattedDate} are set to draft (hidden from shop incharges).";

        return redirect()->back()->with('success', $message);
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
                'override_reason' => 'Purchaser updated daily price board',
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
                    'reason' => 'Purchaser updated daily price board',
                    'changed_by' => $userId,
                    'changed_at' => now(),
                ]);
            }
        }
    }

    private function authorizeBoardAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin'), 403);
    }
}
